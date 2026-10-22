<?php declare(strict_types=1);

namespace Acme\Brand\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Template SEO mặc định cho trang thương hiệu (`/brands/nike`).
 *
 * Bắt buộc phải có: thiếu template mặc định (sales_channel_id NULL) thì SeoUrlUpdater
 * của core ném lỗi "Default templates not configured". Có bản ghi này thì template còn
 * sửa được trong Administration → Settings → SEO.
 *
 * @internal
 */
class Migration1791953896AddBrandSeoUrlTemplate extends MigrationStep
{
    // Giá trị của BrandPageSeoUrlRoute (đã xoá ở 2.0.0 — ADR-006), giữ nguyên để migration
    // chạy y như cũ trên bản cài mới (MIG-04)
    private const ROUTE_NAME = 'frontend.acme.brand.page';

    private const DEFAULT_TEMPLATE = 'brands/{{ manufacturer.translated.name|lower }}';

    public function getCreationTimestamp(): int
    {
        return 1791953896;
    }

    public function update(Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM seo_url_template WHERE route_name = :route AND sales_channel_id IS NULL AND is_headless = 0',
            ['route' => self::ROUTE_NAME]
        );

        if ($exists) {
            return;
        }

        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => null,
            'route_name' => self::ROUTE_NAME,
            'entity_name' => 'product_manufacturer',
            'template' => self::DEFAULT_TEMPLATE,
            'is_valid' => 1,
            'is_headless' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
