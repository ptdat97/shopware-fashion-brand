<?php declare(strict_types=1);

namespace Acme\Payment\Tests\Unit\Service;

use Acme\Payment\AcmePayment;
use Acme\Payment\Service\BankTransferInfoProvider;
use Acme\Payment\Service\VietQrPayloadBuilder;
use Acme\Payment\Service\VietQrRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(BankTransferInfoProvider::class)]
class BankTransferInfoProviderTest extends TestCase
{
    private const CHANNEL = 'channel';

    private const ACCOUNT = [
        'AcmePayment.config.bankBin' => '970436',
        'AcmePayment.config.accountNumber' => ' 0123456789 ',
        'AcmePayment.config.accountHolder' => 'CONG TY ACME',
        'AcmePayment.config.referencePrefix' => 'ANV',
        'AcmePayment.config.transferInstruction' => 'Ghi đúng nội dung.',
    ];

    public function testPrepaymentOrderGetsAccountAmountAndReference(): void
    {
        $info = $this->provider(self::ACCOUNT)->forOrder($this->order(['payment_prepayment']), self::CHANNEL);

        static::assertTrue($info->applicable);
        static::assertTrue($info->configured);
        static::assertSame('Vietcombank', $info->bankName);
        static::assertSame('0123456789', $info->accountNumber, 'Bỏ khoảng trắng thừa người vận hành gõ nhầm');
        static::assertSame('CONG TY ACME', $info->accountHolder);
        static::assertSame(825000.0, $info->amount);
        static::assertSame('ANV10042', $info->reference);
        static::assertSame('Ghi đúng nội dung.', $info->instruction);
        static::assertNull($info->qrDataUri, 'Chuyển khoản thường không có QR');
    }

    public function testVietQrOrderAlsoGetsQrCode(): void
    {
        $info = $this->provider(self::ACCOUNT)->forOrder($this->order([AcmePayment::VIETQR_TECHNICAL_NAME]), self::CHANNEL);

        static::assertStringStartsWith('data:image/svg+xml', (string) $info->qrDataUri);
    }

    public function testCashOnDeliveryIsNotApplicable(): void
    {
        $info = $this->provider(self::ACCOUNT)->forOrder($this->order(['payment_cashpayment']), self::CHANNEL);

        static::assertFalse($info->applicable);
        static::assertSame('', $info->accountNumber);
    }

    public function testLatestTransactionDecides(): void
    {
        // Đặt bằng chuyển khoản rồi đổi sang COD sau khi đặt → không còn là chuyển khoản
        $info = $this->provider(self::ACCOUNT)->forOrder($this->order(['payment_prepayment', 'payment_cashpayment']), self::CHANNEL);

        static::assertFalse($info->applicable);
    }

    public function testMissingAccountShowsOnlyInstruction(): void
    {
        $config = self::ACCOUNT;
        unset($config['AcmePayment.config.accountNumber']);

        $info = $this->provider($config)->forOrder($this->order(['payment_prepayment']), self::CHANNEL);

        static::assertTrue($info->applicable);
        static::assertFalse($info->configured);
        static::assertSame('', $info->accountNumber);
        static::assertSame('', $info->reference);
        static::assertSame('Ghi đúng nội dung.', $info->instruction);
    }

    /**
     * @param array<string, string> $config
     */
    private function provider(array $config): BankTransferInfoProvider
    {
        return new BankTransferInfoProvider(
            new StaticSystemConfigService([self::CHANNEL => $config]),
            new VietQrRenderer(new VietQrPayloadBuilder())
        );
    }

    /**
     * @param list<string> $paymentMethods phương thức theo thứ tự giao dịch được tạo
     */
    private function order(array $paymentMethods): OrderEntity
    {
        $created = [];
        foreach ($paymentMethods as $i => $technicalName) {
            $method = new PaymentMethodEntity();
            $method->setId(Uuid::randomHex());
            $method->setTechnicalName($technicalName);

            $transaction = new OrderTransactionEntity();
            $transaction->setId(Uuid::randomHex());
            $transaction->setPaymentMethod($method);
            $transaction->setCreatedAt(new \DateTimeImmutable('2026-10-21 10:00:00 +' . $i . ' minutes'));
            $created[] = $transaction;
        }
        // Thứ tự trong collection không phải thứ tự thời gian
        $transactions = new OrderTransactionCollection(array_reverse($created));

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber('10042');
        $order->setAmountTotal(825000.0);
        $order->setTransactions($transactions);

        return $order;
    }
}
