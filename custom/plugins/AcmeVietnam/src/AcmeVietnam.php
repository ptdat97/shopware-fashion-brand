<?php declare(strict_types=1);

namespace Acme\Vietnam;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Nền tảng thị trường Việt Nam: tiền tệ VND, thuế VAT, quốc gia VN và 34 tỉnh/thành,
 * quy tắc địa chỉ, ngôn ngữ vi-VN cho storefront, nhãn tiếng Việt cho dữ liệu core.
 *
 * Plugin không có code chạy lúc khách mua hàng — chỉ có migration ghi cấu hình lên entity
 * của core (LIFE-01). Bề mặt công khai là chính dữ liệu đó: mã ISO `VN`, mã tỉnh
 * `VN-HN`, `VN-HCM`… mà plugin khác (vd. `AcmeShipping`) đọc theo kiểu có thì dùng.
 *
 * Tắt / gỡ KHÔNG hoàn tác cấu hình (LIFE-02): đưa currency về USD khi shop đã có đơn
 * giá VND sẽ làm sai toàn bộ số tiền. Tắt plugin thì shop vẫn chạy y như lúc bật.
 */
class AcmeVietnam extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        // Cố ý không hoàn tác: xem chú thích ở đầu class.
        $uninstallContext->setKeepMigrations(false);
    }
}
