<?php declare(strict_types=1);

namespace Acme\Catalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * URL sản phẩm và danh mục chữ thường — đồng nhất với /brands/…, /collections/…
 *
 *   Dam/                                   → dam/
 *   Ao-polo-det-kim-tay-ngan/MAY-1002-01   → ao-polo-det-kim-tay-ngan/may-1002-01
 *
 * Chỉ dùng bộ lọc `lower` trong template SEO của core — không code. Chỉ đổi template khi
 * nó vẫn đúng là bản core / plugin đã đặt; người vận hành đã tự sửa thì giữ nguyên.
 * Template của kênh headless (store-api.*) không đổi: phục vụ ứng dụng bên ngoài, không
 * phải storefront.
 *
 * URL cũ: core giữ bản ghi cũ dạng không-canonical và chuyển hướng 301 sang URL mới
 * (`core.seo.redirectToCanonicalUrl`). Sau migration: `bin/console dal:refresh:index`.
 *
 * @internal
 */
class Migration1791972614LowercaseSeoUrls extends MigrationStep
{
    public const PRODUCT_ROUTE = 'frontend.detail.page';

    public const NAVIGATION_ROUTE = 'frontend.navigation.page';

    public const CORE_PRODUCT_TEMPLATE = '{{ product.translated.name }}/{{ product.productNumber }}';

    public const PRODUCT_TEMPLATE = '{{ product.translated.name|lower }}/{{ product.productNumber|lower }}';

    /** Bản do Migration1791959515CollectionSeoUrl đặt */
    public const COLLECTION_NAVIGATION_TEMPLATE = '{% if category.translated.customFields.acme_collection|default(false) %}'
        . 'collections/{{ category.translated.name|lower }}'
        . '{% else %}'
        . '{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}'
        . '{% endif %}';

    public const NAVIGATION_TEMPLATE = '{% if category.translated.customFields.acme_collection|default(false) %}'
        . 'collections/{{ category.translated.name|lower }}'
        . '{% else %}'
        . '{% for part in category.seoBreadcrumb %}{{ part|lower }}/{% endfor %}'
        . '{% endif %}';

    public function getCreationTimestamp(): int
    {
        return 1791972614;
    }

    public function update(Connection $connection): void
    {
        $this->replaceTemplate($connection, self::PRODUCT_ROUTE, self::CORE_PRODUCT_TEMPLATE, self::PRODUCT_TEMPLATE);
        $this->replaceTemplate($connection, self::NAVIGATION_ROUTE, self::COLLECTION_NAVIGATION_TEMPLATE, self::NAVIGATION_TEMPLATE);
    }

    private function replaceTemplate(Connection $connection, string $route, string $expected, string $new): void
    {
        $connection->executeStatement(
            'UPDATE `seo_url_template`
                SET `template` = :new, `updated_at` = :now
              WHERE `route_name` = :route AND `sales_channel_id` IS NULL AND `is_headless` = 0
                AND `template` = :expected',
            [
                'new' => $new,
                'expected' => $expected,
                'route' => $route,
                'now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
