<?php declare(strict_types=1);

namespace Acme\Payment\Subscriber;

use Acme\Payment\Service\BankTransferInfoProvider;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gắn thông tin chuyển khoản (biến `acmeBankTransfer`, struct BankTransferInfo) vào mọi email
 * có đơn hàng trả bằng "Chuyển khoản trước" / VietQR — email xác nhận đơn, nhắc thanh toán…
 * hiện được tài khoản nhận, số tiền, nội dung CK mà không chép lại quy tắc ghép nội dung CK
 * vào mẫu email.
 *
 * Mẫu email đọc biến theo kiểu "có thì dùng" (`acmeBankTransfer is defined`) — tắt plugin thì
 * email vẫn gửi, chỉ mất khối chuyển khoản (quy tắc 7).
 *
 * @internal
 */
class BankTransferMailSubscriber implements EventSubscriberInterface
{
    final public const TEMPLATE_VARIABLE = 'acmeBankTransfer';

    public function __construct(private readonly BankTransferInfoProvider $provider)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'addBankTransferInfo',
        ];
    }

    public function addBankTransferInfo(MailBeforeValidateEvent $event): void
    {
        $order = $event->getTemplateData()['order'] ?? null;
        if (!$order instanceof OrderEntity || $order->getTransactions() === null) {
            return;
        }

        $info = $this->provider->forOrder($order, $order->getSalesChannelId());
        if ($info->applicable) {
            $event->addTemplateData(self::TEMPLATE_VARIABLE, $info);
        }
    }
}
