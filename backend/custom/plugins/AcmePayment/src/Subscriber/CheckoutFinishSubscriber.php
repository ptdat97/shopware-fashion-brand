<?php declare(strict_types=1);

namespace Acme\Payment\Subscriber;

use Acme\Payment\AcmePayment;
use Acme\Payment\Service\BankTransferInfoProvider;
use Acme\Payment\Struct\VietQrData;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gắn mã QR VietQR vào trang cảm ơn khi khách chọn phương thức chuyển khoản QR.
 *
 * Sinh QR ở đây chứ không ở `pay()` vì `pay()` chỉ chạy một lần, còn khách
 * hoàn toàn có thể đóng trang rồi mở lại đơn hàng để quét sau.
 *
 * @internal
 */
class CheckoutFinishSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly BankTransferInfoProvider $provider)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'addVietQrToPage',
        ];
    }

    public function addVietQrToPage(CheckoutFinishPageLoadedEvent $event): void
    {
        $info = $this->provider->forOrder($event->getPage()->getOrder(), $event->getSalesChannelContext()->getSalesChannelId());

        // Template Twig chỉ có khối VietQR; "Chuyển khoản trước" chỉ có ở frontend headless.
        if ($info->paymentMethodTechnicalName !== AcmePayment::VIETQR_TECHNICAL_NAME) {
            return;
        }

        $event->getPage()->addExtension(VietQrData::EXTENSION_NAME, new VietQrData(
            qrDataUri: $info->qrDataUri,
            accountNumber: $info->accountNumber,
            accountHolder: $info->accountHolder,
            bankBin: $info->bankBin,
            reference: $info->reference,
            amount: (int) round($info->amount),
            configured: $info->configured
        ));
    }
}
