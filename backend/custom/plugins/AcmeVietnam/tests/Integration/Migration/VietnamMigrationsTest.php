<?php declare(strict_types=1);

namespace Acme\Vietnam\Tests\Integration\Migration;

use Acme\Vietnam\Migration\Migration1786157044VietnamMarketBaseline;
use Acme\Vietnam\Migration\Migration1786157045VietnameseStorefront;
use Acme\Vietnam\Migration\Migration1791973286VietnameseCoreLabels;
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
