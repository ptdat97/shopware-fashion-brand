<?php declare(strict_types=1);

namespace Acme\Payment\Tests\Unit\Subscriber;

use Acme\Payment\Service\BankTransferInfoProvider;
use Acme\Payment\Service\VietQrPayloadBuilder;
use Acme\Payment\Service\VietQrRenderer;
use Acme\Payment\Struct\BankTransferInfo;
use Acme\Payment\Subscriber\BankTransferMailSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(BankTransferMailSubscriber::class)]
class BankTransferMailSubscriberTest extends TestCase
{
    public function testBankTransferOrderMailGetsAccountDetails(): void
    {
        $event = $this->event(['order' => $this->order('payment_prepayment')]);

        $this->subscriber()->addBankTransferInfo($event);

        $info = $event->getTemplateData()[BankTransferMailSubscriber::TEMPLATE_VARIABLE] ?? null;
        static::assertInstanceOf(BankTransferInfo::class, $info);
        static::assertSame('ANV10042', $info->reference);
    }

    public function testCashOnDeliveryAndMailsWithoutOrderAreUntouched(): void
    {
        foreach ([['order' => $this->order('payment_cashpayment')], ['customer' => 'không có đơn']] as $data) {
            $event = $this->event($data);
            $this->subscriber()->addBankTransferInfo($event);
            static::assertArrayNotHasKey(BankTransferMailSubscriber::TEMPLATE_VARIABLE, $event->getTemplateData());
        }
    }

    private function subscriber(): BankTransferMailSubscriber
    {
        return new BankTransferMailSubscriber(new BankTransferInfoProvider(
            new StaticSystemConfigService(['channel' => [
                'AcmePayment.config.bankBin' => '970436',
                'AcmePayment.config.accountNumber' => '0123456789',
                'AcmePayment.config.referencePrefix' => 'ANV',
            ]]),
            new VietQrRenderer(new VietQrPayloadBuilder())
        ));
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function event(array $templateData): MailBeforeValidateEvent
    {
        return new MailBeforeValidateEvent([], Context::createDefaultContext(), $templateData);
    }

    private function order(string $paymentMethod): OrderEntity
    {
        $method = new PaymentMethodEntity();
        $method->setId(Uuid::randomHex());
        $method->setTechnicalName($paymentMethod);

        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setPaymentMethod($method);
        $transaction->setCreatedAt(new \DateTimeImmutable());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId('channel');
        $order->setOrderNumber('10042');
        $order->setAmountTotal(825000.0);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
    }
}
