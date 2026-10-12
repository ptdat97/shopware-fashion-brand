<?php declare(strict_types=1);

namespace Acme\Payment\Service;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Sinh ảnh QR dạng SVG từ chuỗi payload VietQR.
 *
 * Dùng SVG thay vì PNG: nét sắc ở mọi độ phân giải màn hình, nhúng thẳng vào
 * HTML nên không tốn thêm request, và không phụ thuộc extension GD.
 *
 * @internal
 */
final class VietQrRenderer
{
    public function __construct(private readonly VietQrPayloadBuilder $payloadBuilder)
    {
    }

    /**
     * @return string Data URI nhúng trực tiếp vào thuộc tính src của thẻ img
     */
    public function renderDataUri(string $bankBin, string $accountNo, int $amount, string $reference): string
    {
        $payload = $this->payloadBuilder->build($bankBin, $accountNo, $amount, $reference);

        $qrCode = new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            // Mức H chịu được ~30% diện tích bị che/mờ — quan trọng vì khách
            // thường quét qua màn hình điện thoại có vân tay hoặc chói sáng.
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 280,
            margin: 16
        );

        $result = (new SvgWriter())->write($qrCode);

        return $result->getDataUri();
    }
}
