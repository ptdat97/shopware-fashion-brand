<?php declare(strict_types=1);

namespace Acme\Catalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * URL của frontend headless giống hệt storefront Twig (ADR-005) — chữ thường,
 * bộ sưu tập dưới `/collections/`:
 *
 *   Dam/                          → dam/
 *   Bo-suu-tap/Sale/              → collections/sale
 *   Bo-suu-tap/                   → collections/
 *   Set-ao-so-mi…/MAY-1005-01     → set-ao-so-mi…/may-1005-01
 *
 * Ba migration Phase 4 (CollectionSeoUrl, LowercaseSeoUrls, CollectionsRootUrl) cố ý chỉ
 * đổi template storefront (`is_headless = 0`) vì khi đó channel API "phục vụ ứng dụng bên
 * ngoài". Từ ADR-005 frontend headless chính là storefront, nên template headless
 * (`store-api.*`, `is_headless = 1`) nhận đúng bản cuối của ba migration đó — dùng lại
 * hằng số của chúng để hai bên không lệch nhau.
 *
 * Chỉ đổi khi template headless còn là bản mặc định của core (MIG-03).
 * URL cũ thành không-canonical; frontend chuyển hướng 301 sang URL canonical.
 * Sau migration: `bin/console dal:refresh:index`.
 *
 * @internal
 */
class Migration1792574752HeadlessSeoUrlTemplates extends MigrationStep
{
    public const PRODUCT_ROUTE = 'store-api.product.detail';

    public const CATEGORY_ROUTE = 'store-api.category.detail';

    public function getCreationTimestamp(): int
    {
        return 1792574752;
    }

    public function update(Connection $connection): void
    {
        $this->replaceHeadlessTemplate(
            $connection,
            self::PRODUCT_ROUTE,
            Migration1791972614LowercaseSeoUrls::CORE_PRODUCT_TEMPLATE,
            Migration1791972614LowercaseSeoUrls::PRODUCT_TEMPLATE
        );

        $this->replaceHeadlessTemplate(
            $connection,
            self::CATEGORY_ROUTE,
            Migration1791959515CollectionSeoUrl::CORE_TEMPLATE,
            Migration1791972801CollectionsRootUrl::TEMPLATE
        );
    }

    private function replaceHeadlessTemplate(Connection $connection, string $route, string $core, string $new): void
    {
        $connection->executeStatement(
            'UPDATE `seo_url_template`
                SET `template` = :new, `updated_at` = :now
              WHERE `route_name` = :route AND `sales_channel_id` IS NULL AND `is_headless` = 1
                AND `template` = :core',
            [
                'new' => $new,
                'core' => $core,
                'route' => $route,
                'now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
