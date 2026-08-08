<?php declare(strict_types=1);

namespace Anivele\Catalog\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Chuẩn hoá thuộc tính trước khi sinh biến thể.
 *
 * Phải chạy TRƯỚC khi sinh variant: sửa cấu trúc thuộc tính sau khi đã có
 * hàng trăm SKU rất tốn kém (xem docs/10-phase2-catalog.md mục 7).
 *
 * Hai việc:
 * 1. Tách "Hoa nhiều màu" khỏi nhóm Màu sắc sang nhóm Hoạ tiết riêng.
 *    Hoạ tiết không phải màu — để chung làm hỏng bộ lọc: khách lọc "màu tím"
 *    sẽ ra váy hoa.
 * 2. Bổ sung bản dịch vi-VN. Thuộc tính hiện chỉ có bản dịch en-US nên
 *    storefront vi-VN phải fallback thay vì đọc thẳng.
 *
 * @internal
 */
class Migration1786159617PropertyGroupsForFashion extends MigrationStep
{
    private const PATTERN_OPTION = 'Hoa nhiều màu';

    public function getCreationTimestamp(): int
    {
        return 1786159617;
    }

    public function update(Connection $connection): void
    {
        $vietnameseId = $this->languageIdByLocale($connection, 'vi-VN');

        $this->translateExistingGroups($connection, $vietnameseId);
        $this->movePatternOutOfColour($connection);
        $this->ensureEveryProductHasBaseColour($connection);
    }

    /**
     * Sau khi tách Hoạ tiết, sản phẩm nào chỉ có mỗi "Hoa nhiều màu" sẽ còn 0 màu
     * → không sinh được biến thể (cần cả hai trục Màu × Size).
     *
     * Gán màu nền cho những sản phẩm đó. Hàng hoạ tiết vẫn có màu nền thật —
     * đây cũng là cách shop thời trang thật phân loại: lọc theo màu nền,
     * lọc riêng theo hoạ tiết.
     */
    private function ensureEveryProductHasBaseColour(Connection $connection): void
    {
        $colourGroupId = $connection->fetchOne(
            'SELECT pg.`id` FROM `property_group` pg
             JOIN `property_group_translation` pgt ON pgt.`property_group_id` = pg.`id`
             WHERE pgt.`name` = :name LIMIT 1',
            ['name' => 'Màu sắc']
        );

        if ($colourGroupId === false) {
            return;
        }

        // "Kem" là tông nền sáng, hợp với ảnh thật của các mẫu hoạ tiết hiện có.
        $baseColourId = $connection->fetchOne(
            'SELECT pgo.`id` FROM `property_group_option` pgo
             JOIN `property_group_option_translation` t ON t.`property_group_option_id` = pgo.`id`
             WHERE pgo.`property_group_id` = :groupId AND t.`name` = :name LIMIT 1',
            ['groupId' => $colourGroupId, 'name' => 'Kem']
        );

        if ($baseColourId === false) {
            return;
        }

        $connection->executeStatement(
            'INSERT IGNORE INTO `product_property`
                (`product_id`, `product_version_id`, `property_group_option_id`)
             SELECT p.`id`, p.`version_id`, :colourId
             FROM `product` p
             WHERE p.`parent_id` IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM `product_property` pp
                   JOIN `property_group_option` o ON o.`id` = pp.`property_group_option_id`
                   WHERE pp.`product_id` = p.`id` AND o.`property_group_id` = :groupId
               )',
            ['colourId' => $baseColourId, 'groupId' => $colourGroupId]
        );
    }

    /**
     * Nhóm và giá trị hiện có tên tiếng Việt nhưng nằm ở bản dịch en-US.
     * Nhân bản sang vi-VN để storefront đọc thẳng, không phải fallback.
     *
     * ⚠️ `ON DUPLICATE KEY` cố ý GIỮ NGUYÊN bản dịch vi-VN đã có, không ghi đè.
     * Bẫy đã gặp: nhóm Hoạ tiết tạo với tên en-GB "Pattern", chạy lại migration
     * thì bản sao en-US ghi đè lên vi-VN → bộ lọc storefront hiện "Pattern"
     * thay vì "Hoạ tiết". Chỉ phát hiện được khi chụp ảnh màn hình storefront.
     */
    private function translateExistingGroups(Connection $connection, ?string $vietnameseId): void
    {
        if ($vietnameseId === null) {
            return;
        }

        $connection->executeStatement(
            'INSERT INTO `property_group_translation`
                (`property_group_id`, `language_id`, `name`, `description`, `position`, `created_at`)
             SELECT pgt.`property_group_id`, :vi, pgt.`name`, pgt.`description`, pgt.`position`, :now
             FROM `property_group_translation` pgt
             WHERE pgt.`language_id` = :source
             ON DUPLICATE KEY UPDATE `name` = `property_group_translation`.`name`',
            [
                'vi' => $vietnameseId,
                'source' => $this->languageIdByLocale($connection, 'en-US'),
                'now' => $this->now(),
            ]
        );

        $connection->executeStatement(
            'INSERT INTO `property_group_option_translation`
                (`property_group_option_id`, `language_id`, `name`, `position`, `created_at`)
             SELECT t.`property_group_option_id`, :vi, t.`name`, t.`position`, :now
             FROM `property_group_option_translation` t
             WHERE t.`language_id` = :source
             ON DUPLICATE KEY UPDATE `name` = `property_group_option_translation`.`name`',
            [
                'vi' => $vietnameseId,
                'source' => $this->languageIdByLocale($connection, 'en-US'),
                'now' => $this->now(),
            ]
        );
    }

    /**
     * "Hoa nhiều màu" là hoạ tiết, không phải màu. Chuyển sang nhóm riêng
     * để bộ lọc màu sắc không trả về nhầm.
     */
    private function movePatternOutOfColour(Connection $connection): void
    {
        $optionId = $connection->fetchOne(
            'SELECT `property_group_option_id` FROM `property_group_option_translation`
             WHERE `name` = :name LIMIT 1',
            ['name' => self::PATTERN_OPTION]
        );

        if ($optionId === false) {
            // Đã tách rồi hoặc dữ liệu demo khác — không phải lỗi.
            return;
        }

        $patternGroupId = $this->ensurePatternGroup($connection);

        $connection->executeStatement(
            'UPDATE `property_group_option`
             SET `property_group_id` = :groupId, `color_hex_code` = NULL, `updated_at` = :now
             WHERE `id` = :id',
            ['groupId' => $patternGroupId, 'now' => $this->now(), 'id' => $optionId]
        );
    }

    /**
     * @return string ID nhị phân của nhóm Hoạ tiết
     */
    private function ensurePatternGroup(Connection $connection): string
    {
        // Dò theo MỌI tên ngôn ngữ của nhóm, không chỉ tên tiếng Việt.
        // Nếu chỉ dò 'Hoạ tiết', lần chạy sau sẽ không nhận ra nhóm đã tạo bằng
        // tên 'Pattern' ở lần trước và sinh ra nhóm trùng.
        $existing = $connection->fetchOne(
            'SELECT pg.`id` FROM `property_group` pg
             JOIN `property_group_translation` pgt ON pgt.`property_group_id` = pg.`id`
             WHERE pgt.`name` IN (:names) LIMIT 1',
            ['names' => ['Hoạ tiết', 'Pattern', 'Muster']],
            ['names' => ArrayParameterType::STRING]
        );

        if ($existing !== false) {
            return $existing;
        }

        $groupId = Uuid::randomBytes();

        $connection->insert('property_group', [
            'id' => $groupId,
            'display_type' => 'text',
            'sorting_type' => 'alphanumeric',
            'filterable' => 1,
            'visible_on_product_detail_page' => 1,
            'created_at' => $this->now(),
        ]);

        $names = ['vi-VN' => 'Hoạ tiết', 'en-US' => 'Pattern', 'en-GB' => 'Pattern', 'de-DE' => 'Muster'];

        foreach ($names as $locale => $name) {
            $languageId = $this->languageIdByLocale($connection, $locale);
            if ($languageId === null) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO `property_group_translation`
                    (`property_group_id`, `language_id`, `name`, `created_at`)
                 VALUES (:groupId, :languageId, :name, :now)
                 ON DUPLICATE KEY UPDATE `name` = :name',
                ['groupId' => $groupId, 'languageId' => $languageId, 'name' => $name, 'now' => $this->now()]
            );
        }

        return $groupId;
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
