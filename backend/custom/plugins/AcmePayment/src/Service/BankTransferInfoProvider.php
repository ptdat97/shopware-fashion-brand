<?php declare(strict_types=1);

namespace Acme\Payment\Service;

use Acme\Payment\AcmePayment;
use Acme\Payment\Struct\BankTransferInfo;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Dựng thông tin chuyển khoản cho một đơn từ cấu hình plugin (theo sales channel).
 * Dùng cho Store API (frontend headless) và dữ liệu email (BankTransferMailSubscriber).
 *
 * Áp dụng cho "Chuyển khoản trước" của core và VietQR — cùng handler PrePayment, cùng
 * tài khoản nhận; VietQR có thêm mã QR.
 *
 * @internal
 */
final class BankTransferInfoProvider
{
    public const CONFIG_DOMAIN = 'AcmePayment.config.';

    public const PREPAYMENT_TECHNICAL_NAME = 'payment_prepayment';

    private const BANK_TRANSFER_METHODS = [self::PREPAYMENT_TECHNICAL_NAME, AcmePayment::VIETQR_TECHNICAL_NAME];

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly VietQrRenderer $renderer,
    ) {
    }

    /**
     * Đơn phải có sẵn association `transactions.paymentMethod`.
     */
    public function forOrder(OrderEntity $order, string $salesChannelId): BankTransferInfo
    {
        $technicalName = $this->latestTransaction($order)?->getPaymentMethod()?->getTechnicalName();

        if (!\in_array($technicalName, self::BANK_TRANSFER_METHODS, true)) {
            return BankTransferInfo::notApplicable();
        }

        $bankBin = $this->config('bankBin', $salesChannelId);
        $accountNumber = $this->config('accountNumber', $salesChannelId);
        $instruction = $this->config('transferInstruction', $salesChannelId);

        // Chưa cấu hình tài khoản → không đưa số tài khoản rỗng / QR sai cho khách.
        if ($bankBin === '' || $accountNumber === '') {
            return new BankTransferInfo(
                applicable: true,
                configured: false,
                paymentMethodTechnicalName: $technicalName,
                instruction: $instruction,
            );
        }

        $amount = $order->getAmountTotal();
        $reference = ($this->config('referencePrefix', $salesChannelId) ?: 'ANV') . $order->getOrderNumber();

        return new BankTransferInfo(
            applicable: true,
            configured: true,
            paymentMethodTechnicalName: $technicalName,
            bankBin: $bankBin,
            bankName: BankDirectory::name($bankBin),
            accountNumber: $accountNumber,
            accountHolder: $this->config('accountHolder', $salesChannelId),
            amount: $amount,
            reference: $reference,
            instruction: $instruction,
            qrDataUri: $technicalName === AcmePayment::VIETQR_TECHNICAL_NAME
                ? $this->renderer->renderDataUri($bankBin, $accountNumber, (int) round($amount), $reference)
                : null,
        );
    }

    /**
     * Khách có thể đổi phương thức sau khi đặt (afterOrderEnabled) — lấy giao dịch mới nhất.
     */
    private function latestTransaction(OrderEntity $order): ?OrderTransactionEntity
    {
        $transactions = $order->getTransactions();
        if ($transactions === null || $transactions->count() === 0) {
            return null;
        }

        $transactions->sort(static fn (OrderTransactionEntity $a, OrderTransactionEntity $b): int => $a->getCreatedAt() <=> $b->getCreatedAt());

        return $transactions->last();
    }

    private function config(string $key, string $salesChannelId): string
    {
        return trim($this->systemConfigService->getString(self::CONFIG_DOMAIN . $key, $salesChannelId));
    }
}
