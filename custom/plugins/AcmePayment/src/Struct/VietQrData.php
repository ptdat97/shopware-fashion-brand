<?php declare(strict_types=1);

namespace Acme\Payment\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Dữ liệu VietQR truyền sang template trang cảm ơn.
 *
 * @internal
 */
class VietQrData extends Struct
{
    public const EXTENSION_NAME = 'acmeVietQr';

    public function __construct(
        public readonly ?string $qrDataUri,
        public readonly string $accountNumber,
        public readonly string $accountHolder,
        public readonly string $bankBin,
        public readonly string $reference,
        public readonly int $amount,
        public readonly bool $configured
    ) {
    }
}
