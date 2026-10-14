<?php declare(strict_types=1);

namespace Acme\Brand;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Thương hiệu = `product_manufacturer` của core. Plugin chỉ bổ sung thứ core không có:
 *
 * - bảng `acme_brand` (1–1): mã brand UNIQUE, trạng thái, nổi bật, thứ tự, banner, layout
 * - custom field dịch được trên nhà sản xuất cho meta SEO (`BrandCustomFields`)
 * - trang storefront /brands, /brands/{slug}, SEO URL, sitemap
 *
 * Mọi thứ khác (tên, mô tả, logo, gán brand cho sản phẩm, bộ lọc, khuyến mãi, đơn hàng)
 * là của core — xem docs/decisions/ADR-003-mo-hinh-thuong-hieu.md.
 *
 * Gỡ plugin mà không giữ dữ liệu → xoá bảng mở rộng và định nghĩa custom field meta SEO.
 * Nhà sản xuất, sản phẩm, đơn hàng không bị ảnh hưởng.
 */
class AcmeBrand extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        // Bảng dịch cũ (trước 1.2.0) — có thể vẫn còn nếu chưa chạy migrate-destructive
        $connection->executeStatement('DROP TABLE IF EXISTS `acme_brand_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `acme_brand`');
        // Định nghĩa bộ custom field meta SEO (field + relation xoá theo FK). Giá trị đã nhập
        // vẫn nằm trong customFields của nhà sản xuất — vô hại, không xoá dữ liệu của core.
        $connection->executeStatement('DELETE FROM `custom_field_set` WHERE `name` = :name', ['name' => BrandCustomFields::SET_NAME]);
    }
}
