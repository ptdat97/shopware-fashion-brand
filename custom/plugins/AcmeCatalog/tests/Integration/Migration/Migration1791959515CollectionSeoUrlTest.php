<?php declare(strict_types=1);

namespace Acme\Catalog\Tests\Integration\Migration;

use Acme\Catalog\Migration\Migration1791959515CollectionSeoUrl;
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
class Migration1791959515CollectionSeoUrlTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testCoreTemplateIsReplacedByCollectionTemplate(): void
    {
        $this->setNavigationTemplate(Migration1791959515CollectionSeoUrl::CORE_TEMPLATE);

        (new Migration1791959515CollectionSeoUrl())->update($this->connection);

        static::assertSame(Migration1791959515CollectionSeoUrl::COLLECTION_TEMPLATE, $this->navigationTemplate());
    }

    public function testTemplateCustomizedByMerchantIsLeftAlone(): void
    {
        $custom = 'shop/{{ category.translated.name }}';
        $this->setNavigationTemplate($custom);

        (new Migration1791959515CollectionSeoUrl())->update($this->connection);

        static::assertSame($custom, $this->navigationTemplate());
    }

    public function testRunningTwiceCreatesTheFlagFieldOnce(): void
    {
        $migration = new Migration1791959515CollectionSeoUrl();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM custom_field WHERE name = :name',
            ['name' => Migration1791959515CollectionSeoUrl::FIELD_NAME]
        ));
    }

    public function testFlaggedCategoryGetsCollectionsUrlOthersKeepTreePath(): void
    {
        $this->setNavigationTemplate(Migration1791959515CollectionSeoUrl::CORE_TEMPLATE);
        (new Migration1791959515CollectionSeoUrl())->update($this->connection);

        $salesChannelId = $this->storefrontSalesChannelId();
        $rootId = (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($salesChannelId)]
        );

        $parentId = Uuid::randomHex();
        $collectionId = Uuid::randomHex();
        $plainId = Uuid::randomHex();

        static::getContainer()->get('category.repository')->create([
            ['id' => $parentId, 'name' => 'Bộ sưu tập', 'parentId' => $rootId],
            ['id' => $collectionId, 'name' => 'Hàng mới về', 'parentId' => $parentId, 'customFields' => ['acme_collection' => true]],
            ['id' => $plainId, 'name' => 'Đầm', 'parentId' => $rootId],
        ], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update('frontend.navigation.page', [$collectionId, $plainId]);

        static::assertSame('collections/hang-moi-ve', $this->canonicalPath($collectionId, $salesChannelId));
        static::assertSame('Dam/', $this->canonicalPath($plainId, $salesChannelId));
    }

    private function canonicalPath(string $categoryId, string $salesChannelId): ?string
    {
        $path = $this->connection->fetchOne(
            'SELECT seo_path_info FROM seo_url
              WHERE route_name = "frontend.navigation.page" AND foreign_key = :id
                AND sales_channel_id = :sc AND is_canonical = 1 AND is_deleted = 0
              LIMIT 1',
            ['id' => Uuid::fromHexToBytes($categoryId), 'sc' => Uuid::fromHexToBytes($salesChannelId)]
        );

        return $path === false ? null : (string) $path;
    }

    private function storefrontSalesChannelId(): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id = :type LIMIT 1',
            ['type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
        );
    }

    private function setNavigationTemplate(string $template): void
    {
        $this->connection->executeStatement(
            'UPDATE seo_url_template SET template = :t
              WHERE route_name = "frontend.navigation.page" AND sales_channel_id IS NULL AND is_headless = 0',
            ['t' => $template]
        );
    }

    private function navigationTemplate(): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT template FROM seo_url_template
              WHERE route_name = "frontend.navigation.page" AND sales_channel_id IS NULL AND is_headless = 0'
        );
    }
}
