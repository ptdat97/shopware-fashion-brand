<?php declare(strict_types=1);

namespace Acme\Brand\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Xoá template và SEO URL của route storefront Twig `frontend.acme.brand.page` — route đã
 * bỏ ở 2.0.0 (ADR-006). Không còn route nào phục vụ các URL này; để lại thì Administration
 * vẫn hiện template thừa và `seo_url` chứa đường dẫn tới controller không tồn tại.
 *
 * Chỉ xoá đúng route của plugin này. SEO URL headless (`store-api.acme.brand.detail`) giữ nguyên.
 *
 * @internal
 */
class Migration1792666664RemoveStorefrontBrandSeoUrls extends MigrationStep
{
    final public const STOREFRONT_ROUTE = 'frontend.acme.brand.page';

    public function getCreationTimestamp(): int
    {
        return 1792666664;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM `seo_url` WHERE `route_name` = :route', ['route' => self::STOREFRONT_ROUTE]);
        $connection->executeStatement('DELETE FROM `seo_url_template` WHERE `route_name` = :route', ['route' => self::STOREFRONT_ROUTE]);
    }
}
