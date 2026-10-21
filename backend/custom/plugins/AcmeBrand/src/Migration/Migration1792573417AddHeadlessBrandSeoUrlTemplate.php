<?php declare(strict_types=1);

namespace Acme\Brand\Migration;

use Acme\Brand\Seo\BrandPageSeoUrlRoute;
use Acme\Brand\Seo\BrandStoreApiUrlRoute;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Template SEO URL thương hiệu cho channel headless (`is_headless = 1`) — cùng mẫu
 * `brands/{{ manufacturer.translated.name|lower }}` với bản storefront. Core chỉ sinh
 * URL headless khi có template is_headless cho route đó (SeoUrlUpdater::update).
 *
 * Đã có (người vận hành tạo trong Administration → SEO) thì giữ nguyên (MIG-03).
 *
 * @internal
 */
class Migration1792573417AddHeadlessBrandSeoUrlTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792573417;
    }

    public function update(Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM seo_url_template WHERE route_name = :route AND sales_channel_id IS NULL AND is_headless = 1',
            ['route' => BrandStoreApiUrlRoute::ROUTE_NAME]
        );

        if ($exists) {
            return;
        }

        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => null,
            'route_name' => BrandStoreApiUrlRoute::ROUTE_NAME,
            'entity_name' => 'product_manufacturer',
            'template' => BrandPageSeoUrlRoute::DEFAULT_TEMPLATE,
            'is_valid' => 1,
            'is_headless' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
