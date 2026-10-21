<?php declare(strict_types=1);

namespace Acme\Payment\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Thông tin chuyển khoản của một đơn — tài khoản nhận, số tiền, nội dung chuyển khoản,
 * hướng dẫn. Mọi giá trị do backend quyết (quy tắc cứng số 8), frontend chỉ hiển thị.
 *
 * - `applicable = false`: đơn không trả bằng chuyển khoản (COD…) — không hiện gì.
 * - `configured = false`: chưa nhập số tài khoản trong cấu hình plugin — không hiện
 *   tài khoản rỗng/sai, chỉ hiện hướng dẫn.
 * - `qrDataUri`: chỉ có với phương thức VietQR.
 */
class BankTransferInfo extends Struct
{
    public function __construct(
        public readonly bool $applicable,
        public readonly bool $configured = false,
        public readonly ?string $paymentMethodTechnicalName = null,
        public readonly string $bankBin = '',
        public readonly string $bankName = '',
        public readonly string $accountNumber = '',
        public readonly string $accountHolder = '',
        public readonly float $amount = 0.0,
        public readonly string $reference = '',
        public readonly string $instruction = '',
        public readonly ?string $qrDataUri = null,
    ) {
    }

    public static function notApplicable(): self
    {
        return new self(applicable: false);
    }

    public function getApiAlias(): string
    {
        return 'acme_payment_bank_transfer';
    }
}
