<?php declare(strict_types=1);

namespace Anivele\Payment\Subscriber;

use Anivele\Payment\AnivelePayment;
use Anivele\Payment\Service\VietQrRenderer;
use Anivele\Payment\Struct\VietQrData;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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
    private const CONFIG_DOMAIN = 'AnivelePayment.config.';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly VietQrRenderer $renderer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'addVietQrToPage',
        ];
    }

    public function addVietQrToPage(CheckoutFinishPageLoadedEvent $event): void
    {
        $order = $event->getPage()->getOrder();

        $transaction = $order->getTransactions()?->first();
        $paymentMethod = $transaction?->getPaymentMethod();

        if ($paymentMethod?->getTechnicalName() !== AnivelePayment::VIETQR_TECHNICAL_NAME) {
            return;
        }

        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();

        $bankBin = (string) $this->systemConfigService->get(self::CONFIG_DOMAIN . 'bankBin', $salesChannelId);
        $accountNumber = (string) $this->systemConfigService->get(self::CONFIG_DOMAIN . 'accountNumber', $salesChannelId);
        $accountHolder = (string) $this->systemConfigService->get(self::CONFIG_DOMAIN . 'accountHolder', $salesChannelId);
        $prefix = (string) ($this->systemConfigService->get(self::CONFIG_DOMAIN . 'referencePrefix', $salesChannelId) ?: 'ANV');

        // Chưa cấu hình tài khoản → hiện hướng dẫn thay vì QR sai.
        if ($bankBin === '' || $accountNumber === '') {
            $event->getPage()->addExtension(VietQrData::EXTENSION_NAME, new VietQrData(
                qrDataUri: null,
                accountNumber: '',
                accountHolder: '',
                bankBin: '',
                reference: '',
                amount: 0,
                configured: false
            ));

            return;
        }

        $amount = (int) round($order->getAmountTotal());
        $reference = $prefix . $order->getOrderNumber();

        $event->getPage()->addExtension(VietQrData::EXTENSION_NAME, new VietQrData(
            qrDataUri: $this->renderer->renderDataUri($bankBin, $accountNumber, $amount, $reference),
            accountNumber: $accountNumber,
            accountHolder: $accountHolder,
            bankBin: $bankBin,
            reference: $reference,
            amount: $amount,
            configured: true
        ));
    }
}
