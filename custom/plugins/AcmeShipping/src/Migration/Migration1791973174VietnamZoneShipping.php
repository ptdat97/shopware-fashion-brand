<?php declare(strict_types=1);

namespace Acme\Shipping\Migration;

use Acme\Shipping\Rule\InnerCityStateSync;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Bảng phí vận chuyển phẳng theo khu vực.
 *
 * Chuyển từ AcmePayment (Migration1786162034VietnameseShipping) sang plugin vận chuyển
 * riêng ngày 14/10/2026 — vận chuyển không phải nghiệp vụ thanh toán. Cùng kết quả,
 * nhưng an toàn khi chạy trên môi trường đã có bản cũ:
 *   - rule đã có (tìm theo tên) thì dùng lại, không tạo trùng;
 *   - bảng giá chỉ ghi khi phương thức CHƯA có giá gắn rule của plugin — không ghi đè
 *     giá người vận hành đã sửa trong Administration;
 *   - danh sách tỉnh nội thành đồng bộ lại với DB (InnerCityStateSync).
 *
 * Giai đoạn 1 theo docs/03-plugin-app.md: dùng Rule Builder có sẵn, chưa nối
 * API GHN/GHTK. Đủ để mở bán, không cần code tích hợp.
 *
 * Ba mức phí:
 *   - Nội thành HN/HCM:  25.000 ₫
 *   - Các tỉnh khác:     35.000 ₫
 *   - Miễn phí từ:      500.000 ₫
 *
 * @internal
 */
class Migration1791973174VietnamZoneShipping extends MigrationStep
{
    private const FEE_INNER_CITY = 25000;
    private const FEE_PROVINCE = 35000;
    private const FREE_SHIPPING_THRESHOLD = 500000;

    public function getCreationTimestamp(): int
    {
        return 1791973174;
    }

    public function update(Connection $connection): void
    {
        $this->translateShippingMethods($connection);

        $standardId = $this->findShippingMethod($connection, 'shipping_standard');
        if ($standardId === null) {
            return;
        }

        $innerCityRuleId = $this->ensureInnerCityRule($connection);
        $freeShippingRuleId = $this->ensureFreeShippingRule($connection);

        InnerCityStateSync::sync($connection);

        if (!$this->hasPricesForRules($connection, $standardId, [$innerCityRuleId, $freeShippingRuleId])) {
            $this->applyPrices($connection, $standardId, $innerCityRuleId, $freeShippingRuleId);
        }

        $this->attachToStorefront($connection, $standardId);
    }

    private function translateShippingMethods(Connection $connection): void
    {
        $vietnameseId = $this->languageIdByLocale($connection, 'vi-VN');
        if ($vietnameseId === null) {
            return;
        }

        $names = [
            'shipping_standard' => ['Giao hàng tiêu chuẩn', 'Giao trong 2–5 ngày làm việc tuỳ khu vực.'],
            'shipping_express' => ['Giao hàng nhanh', 'Giao trong 1–2 ngày làm việc tại nội thành.'],
        ];

        foreach ($names as $technicalName => [$name, $description]) {
            $id = $this->findShippingMethod($connection, $technicalName);
            if ($id === null) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO `shipping_method_translation`
                    (`shipping_method_id`, `language_id`, `name`, `description`, `created_at`)
                 VALUES (:id, :lang, :name, :description, :now)
                 ON DUPLICATE KEY UPDATE `name` = :name, `description` = :description, `updated_at` = :now',
                [
                    'id' => $id,
                    'lang' => $vietnameseId,
                    'name' => $name,
                    'description' => $description,
                    'now' => $this->now(),
                ]
            );
        }
    }

    /**
     * Rule "giao nội thành": địa chỉ giao thuộc Hà Nội hoặc Hồ Chí Minh.
     */
    private function ensureInnerCityRule(Connection $connection): string
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `rule` WHERE `name` = :name LIMIT 1',
            ['name' => InnerCityStateSync::RULE_NAME]
        );

        if ($existing !== false) {
            return $existing;
        }

        $ruleId = Uuid::randomBytes();

        $connection->insert('rule', [
            'id' => $ruleId,
            'name' => InnerCityStateSync::RULE_NAME,
            'priority' => 100,
            'created_at' => $this->now(),
        ]);

        // Danh sách tỉnh điền bởi InnerCityStateSync::sync() ngay sau đó
        $this->insertConditionTree(
            $connection,
            $ruleId,
            'customerShippingState',
            ['operator' => '=', 'stateIds' => []]
        );

        return $ruleId;
    }

    /**
     * Rule "miễn phí ship": giá trị hàng từ ngưỡng trở lên.
     */
    private function ensureFreeShippingRule(Connection $connection): string
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `rule` WHERE `name` = :name LIMIT 1',
            ['name' => 'Miễn phí vận chuyển từ 500.000 ₫']
        );

        if ($existing !== false) {
            return $existing;
        }

        $ruleId = Uuid::randomBytes();

        $connection->insert('rule', [
            'id' => $ruleId,
            'name' => 'Miễn phí vận chuyển từ 500.000 ₫',
            'priority' => 200,
            'created_at' => $this->now(),
        ]);

        $this->insertConditionTree(
            $connection,
            $ruleId,
            'cartGoodsPrice',
            ['operator' => '>=', 'amount' => self::FREE_SHIPPING_THRESHOLD]
        );

        return $ruleId;
    }

    /**
     * Rule của Shopware bắt buộc có cây container: orContainer → andContainer → điều kiện.
     *
     * Bẫy đã gặp: chèn thẳng rule_condition không có parent thì rule KHÔNG bao giờ
     * khớp — không báo lỗi, chỉ âm thầm không áp dụng. So sánh với rule sẵn có
     * của core ("Cart >= 0") mới phát hiện ra.
     *
     * @param array<string, mixed> $value
     */
    private function insertConditionTree(
        Connection $connection,
        string $ruleId,
        string $type,
        array $value
    ): void {
        $orId = Uuid::randomBytes();
        $andId = Uuid::randomBytes();

        $connection->insert('rule_condition', [
            'id' => $orId,
            'rule_id' => $ruleId,
            'type' => 'orContainer',
            'position' => 0,
            'created_at' => $this->now(),
        ]);

        $connection->insert('rule_condition', [
            'id' => $andId,
            'rule_id' => $ruleId,
            'parent_id' => $orId,
            'type' => 'andContainer',
            'position' => 0,
            'created_at' => $this->now(),
        ]);

        $connection->insert('rule_condition', [
            'id' => Uuid::randomBytes(),
            'rule_id' => $ruleId,
            'parent_id' => $andId,
            'type' => $type,
            'value' => json_encode($value, \JSON_THROW_ON_ERROR),
            'position' => 0,
            'created_at' => $this->now(),
        ]);
    }

    /**
     * Ba dòng giá, Shopware chọn dòng có rule khớp và ưu tiên rule priority cao hơn.
     */
    private function applyPrices(
        Connection $connection,
        string $shippingMethodId,
        string $innerCityRuleId,
        string $freeShippingRuleId
    ): void {
        // Xoá giá cũ (0 ₫ mặc định của bản cài) để tránh lẫn với bảng giá mới.
        $connection->executeStatement(
            'DELETE FROM `shipping_method_price` WHERE `shipping_method_id` = :id',
            ['id' => $shippingMethodId]
        );

        $prices = [
            [null, self::FEE_PROVINCE],              // mặc định: các tỉnh
            [$innerCityRuleId, self::FEE_INNER_CITY], // nội thành rẻ hơn
            [$freeShippingRuleId, 0],                 // đơn lớn: miễn phí
        ];

        foreach ($prices as [$ruleId, $gross]) {
            $connection->insert('shipping_method_price', [
                'id' => Uuid::randomBytes(),
                'shipping_method_id' => $shippingMethodId,
                'rule_id' => $ruleId,
                'calculation' => 1,
                'quantity_start' => 1,
                'currency_price' => json_encode([
                    'c' . Defaults::CURRENCY => [
                        'net' => $gross,
                        'gross' => $gross,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ], \JSON_THROW_ON_ERROR),
                'created_at' => $this->now(),
            ]);
        }
    }

    private function attachToStorefront(Connection $connection, string $shippingMethodId): void
    {
        $storefrontIds = $connection->fetchFirstColumn(
            'SELECT `id` FROM `sales_channel` WHERE `type_id` = :typeId',
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
        );

        foreach ($storefrontIds as $salesChannelId) {
            $connection->executeStatement(
                'INSERT IGNORE INTO `sales_channel_shipping_method`
                    (`sales_channel_id`, `shipping_method_id`) VALUES (:sc, :sm)',
                ['sc' => $salesChannelId, 'sm' => $shippingMethodId]
            );
        }

        $connection->executeStatement(
            'UPDATE `shipping_method` SET `active` = 1, `updated_at` = :now WHERE `id` = :id',
            ['now' => $this->now(), 'id' => $shippingMethodId]
        );
    }

    /**
     * @param list<string> $ruleIds
     */
    private function hasPricesForRules(Connection $connection, string $shippingMethodId, array $ruleIds): bool
    {
        foreach ($ruleIds as $ruleId) {
            $found = $connection->fetchOne(
                'SELECT 1 FROM `shipping_method_price` WHERE `shipping_method_id` = :sm AND `rule_id` = :rule LIMIT 1',
                ['sm' => $shippingMethodId, 'rule' => $ruleId]
            );
            if ($found !== false) {
                return true;
            }
        }

        return false;
    }

    private function findShippingMethod(Connection $connection, string $technicalName): ?string
    {
        $id = $connection->fetchOne(
            'SELECT `id` FROM `shipping_method` WHERE `technical_name` = :name LIMIT 1',
            ['name' => $technicalName]
        );

        return $id === false ? null : $id;
    }

    private function languageIdByLocale(Connection $connection, string $locale): ?string
    {
        $id = $connection->fetchOne(
            'SELECT l.`id` FROM `language` l
             JOIN `locale` loc ON loc.`id` = l.`locale_id`
             WHERE loc.`code` = :code LIMIT 1',
            ['code' => $locale]
        );

        return $id === false ? null : $id;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
