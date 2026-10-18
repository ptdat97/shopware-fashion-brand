<?php declare(strict_types=1);

namespace Acme\Vietnam\Tests\Integration\Migration;

use Acme\Vietnam\Migration\Migration1792336157VietnameseHeadlessChannel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Channel type API (frontend headless — ADR-005) sang vi-VN và có domain external storefront.
 *
 * @internal
 */
class HeadlessChannelMigrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FRONTEND_URL = 'http://frontend.acme.test';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $_SERVER[Migration1792336157VietnameseHeadlessChannel::FRONTEND_URL_ENV] = self::FRONTEND_URL . '/';
    }

    protected function tearDown(): void
    {
        unset($_SERVER[Migration1792336157VietnameseHeadlessChannel::FRONTEND_URL_ENV]);
    }

    public function testSkippedWithoutVietnameseLanguage(): void
    {
        $before = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(language_id)) FROM sales_channel ORDER BY id');

        (new Migration1792336157VietnameseHeadlessChannel())->update($this->connection);

        static::assertSame($before, $this->connection->fetchFirstColumn('SELECT LOWER(HEX(language_id)) FROM sales_channel ORDER BY id'));
        static::assertFalse($this->connection->fetchOne('SELECT 1 FROM sales_channel_domain WHERE url = :url', ['url' => self::FRONTEND_URL]));
    }

    public function testHeadlessChannelBecomesVietnameseWithExternalDomain(): void
    {
        $vietnameseId = $this->createVietnamese();
        $headlessId = $this->createHeadlessChannel();
        $migration = new Migration1792336157VietnameseHeadlessChannel();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame($vietnameseId, $this->connection->fetchOne(
            'SELECT language_id FROM sales_channel WHERE id = :id',
            ['id' => $headlessId]
        ));
        static::assertNotFalse($this->connection->fetchOne(
            'SELECT 1 FROM sales_channel_currency x JOIN sales_channel sc ON sc.id = x.sales_channel_id
              WHERE sc.id = :id AND x.currency_id = sc.currency_id',
            ['id' => $headlessId]
        ), 'Tiền tệ mặc định được gán cho channel');

        $domains = $this->connection->fetchAllAssociative(
            'SELECT url, language_id, is_external_storefront FROM sales_channel_domain
              WHERE sales_channel_id = :id AND is_external_storefront = 1',
            ['id' => $headlessId]
        );
        static::assertCount(1, $domains, 'Chạy lại không tạo domain thứ hai');
        static::assertSame(self::FRONTEND_URL, $domains[0]['url'], 'Bỏ dấu / cuối');
        static::assertSame($vietnameseId, $domains[0]['language_id']);

        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sales_channel WHERE type_id = :type AND language_id = :lang',
            ['type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT), 'lang' => $vietnameseId]
        ), 'Channel Storefront không thuộc phạm vi migration này');
    }

    public function testLanguageChosenByMerchantIsKept(): void
    {
        $this->createVietnamese();
        $germanId = $this->connection->fetchOne(
            'SELECT l.id FROM language l JOIN locale loc ON loc.id = l.locale_id WHERE loc.code = "de-DE"'
        );
        static::assertNotFalse($germanId, 'Bản cài core luôn có de-DE');
        $headlessId = $this->createHeadlessChannel($germanId);

        (new Migration1792336157VietnameseHeadlessChannel())->update($this->connection);

        static::assertSame($germanId, $this->connection->fetchOne(
            'SELECT language_id FROM sales_channel WHERE id = :id',
            ['id' => $headlessId]
        ));
    }

    public function testExistingExternalDomainIsKept(): void
    {
        $vietnameseId = $this->createVietnamese();
        $headlessId = $this->createHeadlessChannel();
        $this->connection->insert('sales_channel_domain', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => $headlessId,
            'language_id' => $vietnameseId,
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'snippet_set_id' => $this->connection->fetchOne('SELECT id FROM snippet_set WHERE iso = "vi-VN"'),
            'url' => 'https://shop.acme.test',
            'is_external_storefront' => 1,
            'created_at' => '2026-10-18 00:00:00.000',
        ]);

        (new Migration1792336157VietnameseHeadlessChannel())->update($this->connection);

        static::assertSame(['https://shop.acme.test'], $this->connection->fetchFirstColumn(
            'SELECT url FROM sales_channel_domain WHERE sales_channel_id = :id AND is_external_storefront = 1',
            ['id' => $headlessId]
        ));
    }

    /**
     * Channel API cũ nhất — channel migration gắn domain. Đưa về trạng thái bản cài
     * (ngôn ngữ hệ thống, chưa có domain external) vì DB test dùng chung với plugin khác;
     * transaction của IntegrationTestBehaviour rollback sau mỗi test.
     */
    private function createHeadlessChannel(?string $languageId = null): string
    {
        $languageId ??= Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $id = $this->connection->fetchOne(
            'SELECT id FROM sales_channel WHERE type_id = :type ORDER BY created_at, id LIMIT 1',
            ['type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)]
        );
        static::assertNotFalse($id, 'Bản cài core luôn có channel Headless');

        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_language (sales_channel_id, language_id) VALUES (:id, :lang)',
            ['id' => $id, 'lang' => $languageId]
        );
        $this->connection->update('sales_channel', ['language_id' => $languageId], ['id' => $id]);
        $this->connection->executeStatement('DELETE FROM sales_channel_currency WHERE sales_channel_id = :id', ['id' => $id]);
        $this->connection->executeStatement(
            'DELETE FROM sales_channel_domain WHERE sales_channel_id = :id AND is_external_storefront = 1',
            ['id' => $id]
        );

        return $id;
    }

    private function createVietnamese(): string
    {
        $localeId = $this->connection->fetchOne('SELECT id FROM locale WHERE code = "vi-VN"');
        static::assertNotFalse($localeId, 'Bảng locale của core luôn có vi-VN');

        $id = $this->connection->fetchOne('SELECT id FROM language WHERE locale_id = :locale', ['locale' => $localeId]);
        if ($id === false) {
            $id = Uuid::randomBytes();
            $this->connection->insert('language', [
                'id' => $id,
                'name' => 'Tiếng Việt',
                'locale_id' => $localeId,
                'translation_code_id' => $localeId,
                'created_at' => '2026-10-18 00:00:00.000',
            ]);
        }

        if ($this->connection->fetchOne('SELECT 1 FROM snippet_set WHERE iso = "vi-VN"') === false) {
            $this->connection->insert('snippet_set', [
                'id' => Uuid::randomBytes(),
                'name' => 'BASE vi-VN',
                'iso' => 'vi-VN',
                'base_file' => 'messages.vi-VN',
                'created_at' => '2026-10-18 00:00:00.000',
            ]);
        }

        return $id;
    }
}
