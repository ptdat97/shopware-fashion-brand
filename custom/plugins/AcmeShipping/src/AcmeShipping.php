<?php declare(strict_types=1);

namespace Acme\Shipping;

use Acme\Shipping\Rule\InnerCityStateSync;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;

/**
 * Vận chuyển nội địa Việt Nam.
 *
 * Giai đoạn 1: bảng phí phẳng theo khu vực bằng Rule Builder + bảng giá của phương thức
 * vận chuyển có sẵn trong core — không có code chạy lúc khách đặt hàng. Giai đoạn 2
 * (GHN/GHTK) đặt ở đây.
 *
 * Độc lập (08-quy-tac-phat-trien.md, Quy tắc số 5):
 * - Tắt / gỡ plugin KHÔNG hoàn tác bảng phí: phí ship là cấu hình kinh doanh trên entity
 *   của core, đưa về 0 ₫ khi gỡ sẽ làm sai mọi đơn sau đó. Checkout vẫn chạy bình thường.
 * - Không phụ thuộc class của plugin nào. Rule "nội thành" dùng tỉnh/thành có trong DB
 *   (do AcmeVietnam nạp). Chưa có tỉnh thì rule không khớp → mọi địa chỉ tính phí tỉnh;
 *   cài AcmeVietnam rồi TẮT/BẬT lại plugin này để đồng bộ (activate() bên dưới).
 */
class AcmeShipping extends Plugin
{
    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        InnerCityStateSync::sync($this->container->get(Connection::class));
    }
}
