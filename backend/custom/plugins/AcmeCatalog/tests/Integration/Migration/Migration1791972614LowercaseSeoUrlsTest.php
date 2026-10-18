<?php declare(strict_types=1);

namespace Acme\Catalog\Tests\Integration\Migration;

use Acme\Catalog\Migration\Migration1791972614LowercaseSeoUrls as Lowercase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class Migration1791972614LowercaseSeoUrlsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testTemplatesSetByCoreAndPluginBecomeLowercase(): void
    {
        $this->setTemplate(Lowercase::PRODUCT_ROUTE, Lowercase::CORE_PRODUCT_TEMPLATE);
        $this->setTemplate(Lowercase::NAVIGATION_ROUTE, Lowercase::COLLECTION_NAVIGATION_TEMPLATE);

        (new Lowercase())->update($this->connection);

        static::assertSame(Lowercase::PRODUCT_TEMPLATE, $this->template(Lowercase::PRODUCT_ROUTE));
        static::assertSame(Lowercase::NAVIGATION_TEMPLATE, $this->template(Lowercase::NAVIGATION_ROUTE));
    }

    public function testTemplateCustomizedByMerchantIsLeftAlone(): void
    {
        $custom = 'san-pham/{{ product.productNumber }}';
        $this->setTemplate(Lowercase::PRODUCT_ROUTE, $custom);

        (new Lowercase())->update($this->connection);

        static::assertSame($custom, $this->template(Lowercase::PRODUCT_ROUTE));
    }

    public function testGeneratedUrlsAreLowercaseWithoutDiacritics(): void
    {
        $this->setTemplate(Lowercase::PRODUCT_ROUTE, Lowercase::CORE_PRODUCT_TEMPLATE);
        $this->setTemplate(Lowercase::NAVIGATION_ROUTE, Lowercase::COLLECTION_NAVIGATION_TEMPLATE);
        (new Lowercase())->update($this->connection);

        $ids = new IdsCollection();
        $salesChannelId = (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id = :type LIMIT 1',
            ['type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
        );
        $rootId = (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($salesChannelId)]
        );

        static::getContainer()->get('category.repository')->create([
            ['id' => $ids->get('category'), 'name' => 'Đầm Dạ Hội', 'parentId' => $rootId],
        ], Context::createDefaultContext());
        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($ids, 'MAY-1002-01'))
                ->name('Áo Polo Dệt Kim')
                ->price(100)
                ->visibility($salesChannelId)
                ->add('categories', [['id' => $ids->get('category')]])
                ->build(),
        ], Context::createDefaultContext());

        $updater = static::getContainer()->get(SeoUrlUpdater::class);
        $updater->update(Lowercase::NAVIGATION_ROUTE, [$ids->get('category')]);
        $updater->update(Lowercase::PRODUCT_ROUTE, [$ids->get('MAY-1002-01')]);

        static::assertSame('dam-da-hoi/', $this->canonicalPath(Lowercase::NAVIGATION_ROUTE, $ids->get('category'), $salesChannelId));
        static::assertSame('ao-polo-det-kim/may-1002-01', $this->canonicalPath(Lowercase::PRODUCT_ROUTE, $ids->get('MAY-1002-01'), $salesChannelId));
    }

    private function canonicalPath(string $route, string $id, string $salesChannelId): ?string
    {
        $path = $this->connection->fetchOne(
            'SELECT seo_path_info FROM seo_url
              WHERE route_name = :route AND foreign_key = :id AND sales_channel_id = :sc
                AND is_canonical = 1 AND is_deleted = 0 LIMIT 1',
            ['route' => $route, 'id' => Uuid::fromHexToBytes($id), 'sc' => Uuid::fromHexToBytes($salesChannelId)]
        );

        return $path === false ? null : (string) $path;
    }

    private function setTemplate(string $route, string $template): void
    {
        $this->connection->executeStatement(
            'UPDATE seo_url_template SET template = :t WHERE route_name = :r AND sales_channel_id IS NULL AND is_headless = 0',
            ['t' => $template, 'r' => $route]
        );
    }

    private function template(string $route): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT template FROM seo_url_template WHERE route_name = :r AND sales_channel_id IS NULL AND is_headless = 0',
            ['r' => $route]
        );
    }
}
