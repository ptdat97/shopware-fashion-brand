<?php declare(strict_types=1);

namespace Acme\Payment\Payment;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * VietQR — chuyển khoản bằng mã QR chuẩn NAPAS.
 *
 * Không có luồng redirect: khách quét QR bằng app ngân hàng ngay trên trang
 * cảm ơn, chuyển khoản, rồi shop đối soát thủ công trước khi giao hàng.
 * Vì vậy `pay()` trả về null giống COD/chuyển khoản của core
 * (xem vendor/shopware/core/.../PaymentHandler/DefaultPayment.php).
 *
 * ⚠️ Đơn hàng KHÔNG tự chuyển sang "đã thanh toán". Nhân viên phải kiểm tra
 * sao kê ngân hàng rồi đổi trạng thái trong Administration. Tự động đối soát
 * cần webhook ngân hàng — chưa làm ở giai đoạn này.
 *
 * Mã QR được sinh ở tầng hiển thị (subscriber gắn vào trang cảm ơn), không
 * sinh ở đây, vì `pay()` chỉ chạy một lần còn khách có thể mở lại trang đơn.
 */
class VietQrPaymentHandler extends AbstractPaymentHandler
{
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        // Không hỗ trợ thanh toán định kỳ lẫn hoàn tiền tự động:
        // chuyển khoản thủ công thì hoàn tiền cũng làm thủ công.
        return false;
    }

    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): ?RedirectResponse {
        // Trả null = không redirect, Shopware đưa khách sang trang cảm ơn.
        // Trạng thái giao dịch giữ nguyên "open" cho tới khi đối soát xong.
        return null;
    }
}
