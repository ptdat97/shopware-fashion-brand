<?php declare(strict_types=1);

namespace Acme\Catalog\Tests\Integration\Migration;

use Acme\Catalog\Migration\Migration1791959515CollectionSeoUrl;
use Acme\Catalog\Migration\Migration1791972614LowercaseSeoUrls;
use Acme\Catalog\Migration\Migration1792574752HeadlessSeoUrlTemplates as Headless;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * URL headless trên channel type API có domain external storefront (cấu hình thật, ADR-005).
 *
 * @internal
 */
class Migration1792574752HeadlessSeoUrlTemplatesTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    public function testHeadlessUrlsMatchStorefrontConventions(): void
    {
        $this->setHeadlessTemplate(Headless::PRODUCT_ROUTE, Migration1791972614LowercaseSeoUrls::CORE_PRODUCT_TEMPLATE);
        $this->setHeadlessTemplate(Headless::CATEGORY_ROUTE, Migration1791959515CollectionSeoUrl::CORE_TEMPLATE);

        $migration = new Headless();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->createSalesChannel([
            'id' => $this->ids->get('channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [['url' => 'http://catalog-frontend.acme.test', 'isExternalStorefront' => true]],
        ]);
        $rootId = (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('channel'))]
        );

        $context = Context::createDefaultContext();
        static::getContainer()->get('category.repository')->create([
            ['id' => $this->ids->get('parent'), 'name' => 'Bộ sưu tập', 'parentId' => $rootId, 'customFields' => ['acme_collection_root' => true]],
            ['id' => $this->ids->get('sale'), 'name' => 'Sale', 'parentId' => $this->ids->get('parent'), 'customFields' => ['acme_collection' => true]],
            ['id' => $this->ids->get('plain'), 'name' => 'Chân váy', 'parentId' => $rootId],
        ], $context);
        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($this->ids, 'MAY-9001-01'))
                ->name('Đầm Thử Nghiệm')
                ->price(100)
                ->visibility($this->ids->get('channel'))
                ->build(),
        ], $context);

        $updater = static::getContainer()->get(SeoUrlUpdater::class);
        $updater->update(Headless::CATEGORY_ROUTE, [$this->ids->get('parent'), $this->ids->get('sale'), $this->ids->get('plain')]);
        $updater->update(Headless::PRODUCT_ROUTE, [$this->ids->get('MAY-9001-01')]);

        static::assertSame('collections/', $this->canonicalPath(Headless::CATEGORY_ROUTE, 'parent'));
        static::assertSame('collections/sale', $this->canonicalPath(Headless::CATEGORY_ROUTE, 'sale'));
        static::assertSame('chan-vay/', $this->canonicalPath(Headless::CATEGORY_ROUTE, 'plain'));
        static::assertSame('dam-thu-nghiem/may-9001-01', $this->canonicalPath(Headless::PRODUCT_ROUTE, 'MAY-9001-01'));
    }

    public function testTemplateCustomizedByMerchantIsLeftAlone(): void
    {
        $custom = 'san-pham/{{ product.productNumber }}';
        $this->setHeadlessTemplate(Headless::PRODUCT_ROUTE, $custom);

        (new Headless())->update($this->connection);

        static::assertSame($custom, $this->connection->fetchOne(
            'SELECT template FROM seo_url_template WHERE route_name = :r AND sales_channel_id IS NULL AND is_headless = 1',
            ['r' => Headless::PRODUCT_ROUTE]
        ));
    }

    public function testStorefrontTemplatesAreNotTouched(): void
    {
        $before = $this->connection->fetchAllKeyValue(
            'SELECT route_name, template FROM seo_url_template WHERE is_headless = 0 ORDER BY route_name'
        );

        (new Headless())->update($this->connection);

        static::assertSame($before, $this->connection->fetchAllKeyValue(
            'SELECT route_name, template FROM seo_url_template WHERE is_headless = 0 ORDER BY route_name'
        ));
    }

    private function setHeadlessTemplate(string $route, string $template): void
    {
        $this->connection->executeStatement(
            'UPDATE seo_url_template SET template = :t WHERE route_name = :r AND sales_channel_id IS NULL AND is_headless = 1',
            ['t' => $template, 'r' => $route]
        );
    }

    private function canonicalPath(string $route, string $key): ?string
    {
        $path = $this->connection->fetchOne(
            'SELECT seo_path_info FROM seo_url
              WHERE route_name = :route AND foreign_key = :id AND sales_channel_id = :channel
                AND is_canonical = 1 AND is_deleted = 0',
            [
                'route' => $route,
                'id' => Uuid::fromHexToBytes($this->ids->get($key)),
                'channel' => Uuid::fromHexToBytes($this->ids->get('channel')),
            ]
        );

        return $path === false ? null : (string) $path;
    }
}
