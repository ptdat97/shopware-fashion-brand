<?php declare(strict_types=1);

namespace Acme\Catalog\Tests\Integration\Migration;

use Acme\Catalog\Migration\Migration1791972801CollectionsRootUrl as RootUrl;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1791972801CollectionsRootUrlTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testCollectionsParentMovesUnderTheSamePrefixAsItsChildren(): void
    {
        $this->setTemplate(RootUrl::PREVIOUS_TEMPLATE);
        (new RootUrl())->update($this->connection);

        $salesChannelId = (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id = :type LIMIT 1',
            ['type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
        );
        $rootId = (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($salesChannelId)]
        );

        $parentId = Uuid::randomHex();
        $saleId = Uuid::randomHex();
        $plainId = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([
            ['id' => $parentId, 'name' => 'Bộ sưu tập', 'parentId' => $rootId, 'customFields' => ['acme_collection_root' => true]],
            ['id' => $saleId, 'name' => 'Sale', 'parentId' => $parentId, 'customFields' => ['acme_collection' => true]],
            ['id' => $plainId, 'name' => 'Chân váy', 'parentId' => $rootId],
        ], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update(RootUrl::ROUTE_NAME, [$parentId, $saleId, $plainId]);

        static::assertSame('collections/', $this->canonicalPath($parentId, $salesChannelId));
        static::assertSame('collections/sale', $this->canonicalPath($saleId, $salesChannelId));
        static::assertSame('chan-vay/', $this->canonicalPath($plainId, $salesChannelId));
    }

    public function testTemplateCustomizedByMerchantIsLeftAlone(): void
    {
        $custom = 'danh-muc/{{ category.translated.name }}';
        $this->setTemplate($custom);

        (new RootUrl())->update($this->connection);

        static::assertSame($custom, (string) $this->connection->fetchOne(
            'SELECT template FROM seo_url_template WHERE route_name = :r AND sales_channel_id IS NULL AND is_headless = 0',
            ['r' => RootUrl::ROUTE_NAME]
        ));
    }

    public function testRunningTwiceAddsTheFieldOnce(): void
    {
        (new RootUrl())->update($this->connection);
        (new RootUrl())->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM custom_field WHERE name = :name',
            ['name' => RootUrl::FIELD_NAME]
        ));
    }

    private function canonicalPath(string $categoryId, string $salesChannelId): ?string
    {
        $path = $this->connection->fetchOne(
            'SELECT seo_path_info FROM seo_url
              WHERE route_name = :route AND foreign_key = :id AND sales_channel_id = :sc
                AND is_canonical = 1 AND is_deleted = 0 LIMIT 1',
            ['route' => RootUrl::ROUTE_NAME, 'id' => Uuid::fromHexToBytes($categoryId), 'sc' => Uuid::fromHexToBytes($salesChannelId)]
        );

        return $path === false ? null : (string) $path;
    }

    private function setTemplate(string $template): void
    {
        $this->connection->executeStatement(
            'UPDATE seo_url_template SET template = :t WHERE route_name = :r AND sales_channel_id IS NULL AND is_headless = 0',
            ['t' => $template, 'r' => RootUrl::ROUTE_NAME]
        );
    }
}
