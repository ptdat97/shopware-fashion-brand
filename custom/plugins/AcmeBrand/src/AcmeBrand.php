<?php declare(strict_types=1);

namespace Acme\Brand;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Thương hiệu = `product_manufacturer` của core + phần mở rộng trong bảng `acme_brand`.
 *
 * Tên, mô tả, logo vẫn nằm ở manufacturer; plugin chỉ lưu thứ core không có
 * (trạng thái, mã brand, banner, layout landing, SEO meta). Xem
 * docs/decisions/ADR-003-mo-hinh-thuong-hieu.md.
 *
 * Gỡ plugin mà không giữ dữ liệu → xoá hai bảng mở rộng. Manufacturer, sản phẩm
 * và đơn hàng không bị ảnh hưởng.
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
        $connection->executeStatement('DROP TABLE IF EXISTS `acme_brand_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `acme_brand`');
    }
}
