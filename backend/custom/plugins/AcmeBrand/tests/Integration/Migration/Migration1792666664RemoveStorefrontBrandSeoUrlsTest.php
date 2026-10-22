<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Integration\Migration;

use Acme\Brand\Migration\Migration1792666664RemoveStorefrontBrandSeoUrls as Migration;
use Acme\Brand\Seo\BrandStoreApiUrlRoute;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1792666664RemoveStorefrontBrandSeoUrlsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRemovesOnlyStorefrontBrandRoute(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        foreach ([Migration::STOREFRONT_ROUTE, BrandStoreApiUrlRoute::ROUTE_NAME] as $route) {
            $connection->insert('seo_url', [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => $route,
                'path_info' => '/x/' . Uuid::randomHex(),
                'seo_path_info' => 'brands/' . Uuid::randomHex(),
                'is_canonical' => 1,
                'created_at' => '2026-10-22 00:00:00',
            ]);
        }

        $migration = new Migration();
        $migration->update($connection);
        $migration->update($connection);

        $count = static fn (string $table, string $route): int => (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE route_name = :route',
            ['route' => $route]
        );

        static::assertSame(0, $count('seo_url', Migration::STOREFRONT_ROUTE));
        static::assertSame(0, $count('seo_url_template', Migration::STOREFRONT_ROUTE));
        static::assertGreaterThan(0, $count('seo_url', BrandStoreApiUrlRoute::ROUTE_NAME), 'URL headless giữ nguyên');
    }
}
