<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Nền tảng thị trường Việt Nam — tái lập Phase 0 một cách nhất quán trên mọi môi trường.
 *
 * Thay thế loạt SQL thủ công trước đây (xem docs/runbook/04-phase0-da-thuc-hien.md).
 * Mọi bước đều idempotent: chạy lại không sinh dữ liệu trùng.
 *
 * @internal
 */
class Migration1786157044VietnamMarketBaseline extends MigrationStep
{
    /**
     * 34 tỉnh/thành phố theo cơ cấu hành chính sau sáp nhập 2025.
     *
     * ⚠️ Danh mục đơn vị hành chính VN thay đổi theo các đợt sáp nhập.
     * Kiểm tra lại với nguồn chính thức trước khi go-live.
     *
     * @var array<string, string> mã ngắn => tên tiếng Việt
     */
    private const PROVINCES = [
        'HN' => 'Hà Nội',
        'HCM' => 'Hồ Chí Minh',
        'HP' => 'Hải Phòng',
        'DN' => 'Đà Nẵng',
        'CT' => 'Cần Thơ',
        'HUE' => 'Huế',
        'LC' => 'Lai Châu',
        'DB' => 'Điện Biên',
        'SL' => 'Sơn La',
        'LS' => 'Lạng Sơn',
        'QN' => 'Quảng Ninh',
        'TQ' => 'Tuyên Quang',
        'LCA' => 'Lào Cai',
        'TN' => 'Thái Nguyên',
        'PT' => 'Phú Thọ',
        'BN' => 'Bắc Ninh',
        'HY' => 'Hưng Yên',
        'NB' => 'Ninh Bình',
        'CB' => 'Cao Bằng',
        'TH' => 'Thanh Hóa',
        'NA' => 'Nghệ An',
        'HT' => 'Hà Tĩnh',
        'QT' => 'Quảng Trị',
        'QNG' => 'Quảng Ngãi',
        'GL' => 'Gia Lai',
        'KH' => 'Khánh Hòa',
        'LD' => 'Lâm Đồng',
        'DL' => 'Đắk Lắk',
        'DNA' => 'Đồng Nai',
        'TNI' => 'Tây Ninh',
        'VL' => 'Vĩnh Long',
        'DT' => 'Đồng Tháp',
        'CM' => 'Cà Mau',
        'AG' => 'An Giang',
    ];

    public function getCreationTimestamp(): int
    {
        return 1786157044;
    }

    public function update(Connection $connection): void
    {
        $this->convertDefaultCurrencyToVnd($connection);
        $this->applyVietnameseTaxRates($connection);
        $vietnamId = $this->activateVietnamOnly($connection);
        $this->translateVietnam($connection, $vietnamId);
        $this->applyVietnameseAddressRules($connection, $vietnamId);
        $this->seedProvinces($connection, $vietnamId);
        $this->configureAddressFieldOrder($connection);
    }

    /**
     * Chuyển đổi tại chỗ chính row Defaults::CURRENCY thay vì tạo currency mới.
     *
     * Defaults::CURRENCY là hằng số hardcode trong core, dùng khắp logic giá
     * (PriceCollection, PriceFieldSerializer, PriceFieldAccessorBuilder...).
     * Tạo currency VND mới rồi đặt làm mặc định sẽ khiến hệ thống vẫn coi
     * USD là đồng tiền gốc → giá bị quy đổi sai.
     */
    private function convertDefaultCurrencyToVnd(Connection $connection): void
    {
        $rounding = json_encode(['decimals' => 0, 'interval' => 1, 'roundForNet' => true], \JSON_THROW_ON_ERROR);

        $connection->executeStatement(
            'UPDATE `currency` SET
                `iso_code`       = :iso,
                `factor`         = 1,
                `symbol`         = :symbol,
                `position`       = 2,
                `item_rounding`  = :rounding,
                `total_rounding` = :rounding,
                `updated_at`     = :now
             WHERE `id` = :id',
            [
                'iso' => 'VND',
                'symbol' => '₫',
                'rounding' => $rounding,
                'now' => $this->now(),
                'id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            ]
        );

        $names = [
            'vi-VN' => ['Đồng Việt Nam', 'VND'],
            'en-US' => ['Vietnamese Dong', 'VND'],
            'en-GB' => ['Vietnamese Dong', 'VND'],
            'de-DE' => ['Vietnamesischer Dong', 'VND'],
        ];

        foreach ($names as $locale => [$name, $shortName]) {
            $languageId = $this->languageIdByLocale($connection, $locale);
            if ($languageId === null) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO `currency_translation`
                    (`currency_id`, `language_id`, `name`, `short_name`, `created_at`)
                 VALUES (:currencyId, :languageId, :name, :shortName, :now)
                 ON DUPLICATE KEY UPDATE
                    `name` = :name, `short_name` = :shortName, `updated_at` = :now',
                [
                    'currencyId' => Uuid::fromHexToBytes(Defaults::CURRENCY),
                    'languageId' => $languageId,
                    'name' => $name,
                    'shortName' => $shortName,
                    'now' => $this->now(),
                ]
            );
        }
    }

    /**
     * Biểu thuế Đức (19% / 7%) → VAT Việt Nam (10% / 8% / 0%).
     *
     * Cập nhật theo tax_rate hiện có thay vì theo ID: ID của bản cài mới
     * được sinh ngẫu nhiên nên không thể hardcode.
     *
     * Giữ cả 10% và 8% để linh hoạt theo chính sách giảm thuế từng thời kỳ.
     */
    private function applyVietnameseTaxRates(Connection $connection): void
    {
        $mapping = [
            ['from' => '19.000', 'rate' => '10.000', 'name' => 'VAT 10%'],
            ['from' => '7.000', 'rate' => '8.000', 'name' => 'VAT 8%'],
            ['from' => '0.000', 'rate' => '0.000', 'name' => 'Không chịu thuế (0%)'],
        ];

        foreach ($mapping as $tax) {
            $id = $connection->fetchOne(
                'SELECT `id` FROM `tax` WHERE `tax_rate` = :rate LIMIT 1',
                ['rate' => $tax['rate']]
            ) ?: $connection->fetchOne(
                'SELECT `id` FROM `tax` WHERE `tax_rate` = :rate LIMIT 1',
                ['rate' => $tax['from']]
            );

            if ($id === false) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE `tax` SET `tax_rate` = :rate, `name` = :name, `updated_at` = :now WHERE `id` = :id',
                ['rate' => $tax['rate'], 'name' => $tax['name'], 'now' => $this->now(), 'id' => $id]
            );
        }
    }

    /**
     * Tắt toàn bộ quốc gia trừ Việt Nam.
     *
     * @return string ID nhị phân của Việt Nam
     */
    private function activateVietnamOnly(Connection $connection): string
    {
        $vietnamId = $connection->fetchOne("SELECT `id` FROM `country` WHERE `iso` = 'VN' LIMIT 1");

        if ($vietnamId === false) {
            throw new \RuntimeException('Không tìm thấy quốc gia VN trong bảng country.');
        }

        $connection->executeStatement(
            'UPDATE `country` SET `active` = 0, `updated_at` = :now WHERE `iso` <> :iso AND `active` = 1',
            ['now' => $this->now(), 'iso' => 'VN']
        );

        $connection->executeStatement(
            'UPDATE `country`
             SET `active` = 1, `shipping_available` = 1, `updated_at` = :now
             WHERE `id` = :id',
            ['now' => $this->now(), 'id' => $vietnamId]
        );

        // Bẫy đã gặp: sales channel vẫn trỏ country_id tới quốc gia vừa tắt → lỗi khi gọi API.
        // Phải kiểm tra MỌI channel, kể cả channel không dùng tới.
        $connection->executeStatement(
            'UPDATE `sales_channel` sc
             LEFT JOIN `country` c ON c.`id` = sc.`country_id`
             SET sc.`country_id` = :vn, sc.`updated_at` = :now
             WHERE c.`id` IS NULL OR c.`active` = 0',
            ['vn' => $vietnamId, 'now' => $this->now()]
        );

        // Dọn liên kết sales channel tới quốc gia đã tắt, đảm bảo VN luôn có mặt.
        $connection->executeStatement(
            'DELETE scc FROM `sales_channel_country` scc
             JOIN `country` c ON c.`id` = scc.`country_id`
             WHERE c.`active` = 0'
        );

        $connection->executeStatement(
            'INSERT IGNORE INTO `sales_channel_country` (`sales_channel_id`, `country_id`)
             SELECT sc.`id`, :vn FROM `sales_channel` sc',
            ['vn' => $vietnamId]
        );

        return $vietnamId;
    }

    /**
     * Bản cài mới không có bản dịch vi-VN nào cho quốc gia → storefront tiếng Việt
     * sẽ hiện "Viet Nam" tiếng Anh.
     */
    private function translateVietnam(Connection $connection, string $vietnamId): void
    {
        $names = [
            'vi-VN' => 'Việt Nam',
            'en-US' => 'Vietnam',
            'en-GB' => 'Vietnam',
            'de-DE' => 'Vietnam',
        ];

        foreach ($names as $locale => $name) {
            $languageId = $this->languageIdByLocale($connection, $locale);
            if ($languageId === null) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO `country_translation` (`country_id`, `language_id`, `name`, `created_at`)
                 VALUES (:countryId, :languageId, :name, :now)
                 ON DUPLICATE KEY UPDATE `name` = :name, `updated_at` = :now',
                [
                    'countryId' => $vietnamId,
                    'languageId' => $languageId,
                    'name' => $name,
                    'now' => $this->now(),
                ]
            );
        }
    }

    /**
     * Người Việt hầu như không dùng mã bưu chính, nhưng luôn cần Tỉnh/Thành phố.
     */
    private function applyVietnameseAddressRules(Connection $connection, string $vietnamId): void
    {
        $connection->executeStatement(
            'UPDATE `country` SET
                `postal_code_required`               = 0,
                `check_postal_code_pattern`          = 0,
                `check_advanced_postal_code_pattern` = 0,
                `display_state_in_registration`      = 1,
                `force_state_in_registration`        = 1,
                `updated_at`                         = :now
             WHERE `id` = :id',
            ['now' => $this->now(), 'id' => $vietnamId]
        );
    }

    /**
     * Bẫy quan trọng: bật force_state_in_registration mà country_state rỗng
     * → khách không thể hoàn tất đăng ký. Phải nạp tỉnh/thành ngay cùng bước.
     */
    private function seedProvinces(Connection $connection, string $vietnamId): void
    {
        $locales = ['vi-VN', 'en-US', 'en-GB', 'de-DE'];
        $languageIds = [];
        foreach ($locales as $locale) {
            $languageId = $this->languageIdByLocale($connection, $locale);
            if ($languageId !== null) {
                $languageIds[] = $languageId;
            }
        }

        $position = 1;
        foreach (self::PROVINCES as $shortCode => $name) {
            $code = 'VN-' . $shortCode;

            $stateId = $connection->fetchOne(
                'SELECT `id` FROM `country_state` WHERE `country_id` = :countryId AND `short_code` = :code LIMIT 1',
                ['countryId' => $vietnamId, 'code' => $code]
            );

            if ($stateId === false) {
                $stateId = Uuid::randomBytes();
                $connection->insert('country_state', [
                    'id' => $stateId,
                    'country_id' => $vietnamId,
                    'short_code' => $code,
                    'position' => $position,
                    'active' => 1,
                    'created_at' => $this->now(),
                ]);
            } else {
                $connection->executeStatement(
                    'UPDATE `country_state` SET `position` = :position, `active` = 1, `updated_at` = :now WHERE `id` = :id',
                    ['position' => $position, 'now' => $this->now(), 'id' => $stateId]
                );
            }

            // Tên tỉnh giữ nguyên tiếng Việt ở mọi ngôn ngữ — đây là địa danh, không dịch.
            foreach ($languageIds as $languageId) {
                $connection->executeStatement(
                    'INSERT INTO `country_state_translation`
                        (`country_state_id`, `language_id`, `name`, `created_at`)
                     VALUES (:stateId, :languageId, :name, :now)
                     ON DUPLICATE KEY UPDATE `name` = :name, `updated_at` = :now',
                    [
                        'stateId' => $stateId,
                        'languageId' => $languageId,
                        'name' => $name,
                        'now' => $this->now(),
                    ]
                );
            }

            ++$position;
        }
    }

    /**
     * Mã bưu chính là tuỳ chọn ở VN nên đưa xuống cuối form.
     */
    private function configureAddressFieldOrder(Connection $connection): void
    {
        $this->setSystemConfig($connection, 'core.loginRegistration.addressInputFieldArrangement', 'city-state-zip');
        $this->setSystemConfig($connection, 'core.basicInformation.phoneNumberFieldRequired', true);
        $this->setSystemConfig($connection, 'core.loginRegistration.showPhoneNumberField', true);
    }

    private function setSystemConfig(Connection $connection, string $key, mixed $value): void
    {
        $encoded = json_encode(['_value' => $value], \JSON_THROW_ON_ERROR);

        $existing = $connection->fetchOne(
            'SELECT `id` FROM `system_config`
             WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL LIMIT 1',
            ['key' => $key]
        );

        if ($existing === false) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => $key,
                'configuration_value' => $encoded,
                'sales_channel_id' => null,
                'created_at' => $this->now(),
            ]);

            return;
        }

        $connection->executeStatement(
            'UPDATE `system_config` SET `configuration_value` = :value, `updated_at` = :now WHERE `id` = :id',
            ['value' => $encoded, 'now' => $this->now(), 'id' => $existing]
        );
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
