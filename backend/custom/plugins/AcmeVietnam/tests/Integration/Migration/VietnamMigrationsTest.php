<?php declare(strict_types=1);

namespace Acme\Vietnam\Tests\Integration\Migration;

use Acme\Vietnam\Migration\Migration1786157044VietnamMarketBaseline;
use Acme\Vietnam\Migration\Migration1786157045VietnameseStorefront;
use Acme\Vietnam\Migration\Migration1791973286VietnameseCoreLabels;
use Acme\Vietnam\Migration\Migration1792336734VietnameseDeliveryTimes;
use Acme\Vietnam\Migration\Migration1792572392VietnameseSearchConfig;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Migration chạy trong transaction của IntegrationTestBehaviour, rollback sau mỗi test —
 * DB test dùng chung với plugin khác vẫn giữ USD / mọi quốc gia.
 *
 * @internal
 */
class VietnamMigrationsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testBaselineTurnsTheShopIntoAVietnameseShop(): void
    {
        (new Migration1786157044VietnamMarketBaseline())->update($this->connection);

        $currency = $this->connection->fetchAssociative(
            'SELECT iso_code, factor, JSON_EXTRACT(item_rounding, "$.decimals") AS decimals FROM currency WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::CURRENCY)]
        );
        static::assertSame(['iso_code' => 'VND', 'factor' => 1.0, 'decimals' => '0'], [
            'iso_code' => $currency['iso_code'] ?? null,
            'factor' => (float) ($currency['factor'] ?? 0),
            'decimals' => (string) ($currency['decimals'] ?? ''),
        ], 'Đổi tại chỗ tiền tệ mặc định, không làm tròn lẻ');

        static::assertSame(['VN'], $this->connection->fetchFirstColumn('SELECT iso FROM country WHERE active = 1'));
        static::assertSame(34, $this->provinceCount());
        static::assertEqualsCanonicalizing(['0.000', '8.000', '10.000'], $this->connection->fetchFirstColumn('SELECT tax_rate FROM tax'));
    }

    public function testBaselineRerunIsHarmless(): void
    {
        $migration = new Migration1786157044VietnamMarketBaseline();
        $migration->update($this->connection);
        $before = $this->snapshot();

        $migration->update($this->connection);

        static::assertSame($before, $this->snapshot(), 'Chạy lại không sinh thêm dòng');
    }

    public function testStorefrontStepIsSkippedWithoutVietnameseLanguage(): void
    {
        $before = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(language_id)) FROM sales_channel ORDER BY id');

        (new Migration1786157045VietnameseStorefront())->update($this->connection);
        (new Migration1791973286VietnameseCoreLabels())->update($this->connection);

        static::assertSame($before, $this->connection->fetchFirstColumn('SELECT LOWER(HEX(language_id)) FROM sales_channel ORDER BY id'));
    }

    public function testStorefrontSwitchesToVietnameseOnceTheLanguageExists(): void
    {
        $vietnameseId = $this->createVietnameseLanguage();
        $migration = new Migration1786157045VietnameseStorefront();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $storefront = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        static::assertSame([Uuid::fromBytesToHex($vietnameseId)], $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(language_id)) FROM sales_channel WHERE type_id = :type',
            ['type' => $storefront]
        ));
        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sales_channel_domain d JOIN sales_channel sc ON sc.id = d.sales_channel_id
              WHERE sc.type_id = :type AND d.language_id <> :lang',
            ['type' => $storefront, 'lang' => $vietnameseId]
        ), 'Domain storefront cũng sang vi-VN');
        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sales_channel WHERE type_id <> :type AND language_id = :lang',
            ['type' => $storefront, 'lang' => $vietnameseId]
        ), 'Channel Headless giữ ngôn ngữ cũ');
    }

    public function testSortingLabelEditedByMerchantIsKept(): void
    {
        $vietnameseId = $this->createVietnameseLanguage();
        $sortingId = $this->connection->fetchOne('SELECT id FROM product_sorting WHERE url_key = "price-asc"');
        $this->connection->insert('product_sorting_translation', [
            'product_sorting_id' => $sortingId,
            'language_id' => $vietnameseId,
            'label' => 'Rẻ trước',
            'created_at' => '2026-10-14 00:00:00.000',
        ]);

        (new Migration1791973286VietnameseCoreLabels())->update($this->connection);

        $labels = $this->connection->fetchAllKeyValue(
            'SELECT s.url_key, t.label FROM product_sorting_translation t JOIN product_sorting s ON s.id = t.product_sorting_id
              WHERE t.language_id = :lang AND s.url_key IN ("price-asc", "price-desc")',
            ['lang' => $vietnameseId]
        );
        static::assertEquals(['price-asc' => 'Rẻ trước', 'price-desc' => 'Giá giảm dần'], $labels, 'Nhãn đã sửa giữ nguyên, nhãn trống được điền');
    }

    public function testDeliveryTimesTranslatedWithoutOverwritingMerchantText(): void
    {
        $vietnameseId = $this->createVietnameseLanguage();
        $threeToFour = $this->connection->fetchOne('SELECT id FROM delivery_time WHERE min = 3 AND max = 4 AND unit = "week"');
        static::assertNotFalse($threeToFour, 'Bản cài core luôn có "3-4 weeks"');
        $this->connection->insert('delivery_time_translation', [
            'delivery_time_id' => $threeToFour,
            'language_id' => $vietnameseId,
            'name' => 'Đặt may 3-4 tuần',
            'created_at' => '2026-10-18 00:00:00.000',
        ]);

        $migration = new Migration1792336734VietnameseDeliveryTimes();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $names = $this->connection->fetchAllKeyValue(
            'SELECT CONCAT(d.min, "-", d.max, " ", d.unit), t.name FROM delivery_time_translation t
               JOIN delivery_time d ON d.id = t.delivery_time_id WHERE t.language_id = :lang',
            ['lang' => $vietnameseId]
        );
        static::assertSame('1-3 ngày', $names['1-3 day'] ?? null);
        static::assertSame('Đặt may 3-4 tuần', $names['3-4 week'] ?? null, 'Chữ người vận hành đã sửa giữ nguyên');
    }

    public function testSearchConfigCopiesSystemFieldsWithVietnameseStopwords(): void
    {
        $vietnameseId = $this->createVietnameseLanguage();
        $migration = new Migration1792572392VietnameseSearchConfig();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $configs = $this->connection->fetchAllAssociative(
            'SELECT id, excluded_terms FROM product_search_config WHERE language_id = :lang',
            ['lang' => $vietnameseId]
        );
        static::assertCount(1, $configs, 'Chạy lại không tạo cấu hình thứ hai');
        static::assertSame(
            Migration1792572392VietnameseSearchConfig::EXCLUDED_TERMS,
            json_decode((string) $configs[0]['excluded_terms'], true),
            'Hư từ tiếng Việt thay cho stopword tiếng Anh'
        );

        $fieldsOf = fn (string $configId): array => $this->connection->fetchAllKeyValue(
            'SELECT field, CONCAT(searchable, "/", ranking) FROM product_search_config_field WHERE product_search_config_id = :id ORDER BY field',
            ['id' => $configId]
        );
        $systemConfigId = (string) $this->connection->fetchOne(
            'SELECT id FROM product_search_config WHERE language_id = :lang',
            ['lang' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        static::assertSame($fieldsOf($systemConfigId), $fieldsOf((string) $configs[0]['id']), 'Cùng trường tìm kiếm và ranking');
    }

    public function testSearchConfigCreatedByMerchantIsKept(): void
    {
        $vietnameseId = $this->createVietnameseLanguage();
        $this->connection->insert('product_search_config', [
            'id' => Uuid::randomBytes(),
            'language_id' => $vietnameseId,
            'and_logic' => 1,
            'min_search_length' => 3,
            'excluded_terms' => '["shop"]',
            'created_at' => '2026-10-21 00:00:00.000',
        ]);

        (new Migration1792572392VietnameseSearchConfig())->update($this->connection);

        static::assertSame(['and_logic' => 1, 'excluded_terms' => '["shop"]'], array_map(
            static fn ($v) => is_numeric($v) ? (int) $v : $v,
            (array) $this->connection->fetchAssociative(
                'SELECT and_logic, excluded_terms FROM product_search_config WHERE language_id = :lang',
                ['lang' => $vietnameseId]
            )
        ));
    }

    private function provinceCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM country_state s JOIN country c ON c.id = s.country_id WHERE c.iso = "VN"'
        );
    }

    /**
     * @return array<string, int>
     */
    private function snapshot(): array
    {
        return [
            'provinces' => $this->provinceCount(),
            'province_translations' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM country_state_translation'),
            'taxes' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tax'),
            'currency_translations' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM currency_translation'),
            'system_config' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM system_config'),
            'active_countries' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM country WHERE active = 1'),
        ];
    }

    private function createVietnameseLanguage(): string
    {
        $localeId = $this->connection->fetchOne('SELECT id FROM locale WHERE code = "vi-VN"');
        static::assertNotFalse($localeId, 'Bảng locale của core luôn có vi-VN');

        $id = Uuid::randomBytes();
        $this->connection->insert('language', [
            'id' => $id,
            'name' => 'Tiếng Việt',
            'locale_id' => $localeId,
            'translation_code_id' => $localeId,
            'created_at' => '2026-10-14 00:00:00.000',
        ]);

        return $id;
    }
}
