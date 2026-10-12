<?php declare(strict_types=1);

namespace Acme\Payment\Service;

/**
 * Sinh chuỗi payload VietQR theo chuẩn EMVCo / NAPAS.
 *
 * Chuỗi này là nội dung nhúng vào ảnh QR. App ngân hàng VN quét ra sẽ tự điền
 * ngân hàng thụ hưởng, số tài khoản, số tiền và nội dung chuyển khoản.
 *
 * Định dạng TLV (Tag-Length-Value): mỗi trường gồm 2 ký tự tag, 2 ký tự độ dài,
 * rồi tới giá trị. Trường cuối cùng luôn là CRC.
 *
 * Tham khảo: đặc tả "EMV QR Code Specification for Payment Systems (EMV QRCPS)"
 * và tài liệu VietQR của NAPAS.
 *
 * @internal
 */
final class VietQrPayloadBuilder
{
    /** Phiên bản định dạng payload — cố định "01" theo đặc tả */
    private const PAYLOAD_FORMAT = '01';

    /** 12 = QR động (có số tiền), dùng một lần. 11 = QR tĩnh, quét nhiều lần */
    private const POINT_OF_INITIATION_DYNAMIC = '12';

    /** Định danh của NAPAS trong hệ thống EMVCo */
    private const NAPAS_GUID = 'A000000727';

    /** Dịch vụ chuyển nhanh NAPAS247 tới số tài khoản */
    private const SERVICE_TRANSFER_TO_ACCOUNT = 'QRIBFTTA';

    private const CURRENCY_VND = '704';
    private const COUNTRY_VN = 'VN';

    /**
     * @param string $bankBin   Mã BIN 6 số của ngân hàng thụ hưởng (ví dụ Vietcombank = 970436)
     * @param string $accountNo Số tài khoản thụ hưởng
     * @param int    $amount    Số tiền VND, không có phần thập phân
     * @param string $reference Nội dung chuyển khoản — dùng mã đơn hàng để đối soát
     */
    public function build(string $bankBin, string $accountNo, int $amount, string $reference): string
    {
        $merchantAccount = $this->tlv('00', self::NAPAS_GUID)
            . $this->tlv('01', $this->tlv('00', $bankBin) . $this->tlv('01', $accountNo))
            . $this->tlv('02', self::SERVICE_TRANSFER_TO_ACCOUNT);

        $payload = $this->tlv('00', self::PAYLOAD_FORMAT)
            . $this->tlv('01', self::POINT_OF_INITIATION_DYNAMIC)
            . $this->tlv('38', $merchantAccount)
            . $this->tlv('53', self::CURRENCY_VND)
            . $this->tlv('54', (string) $amount)
            . $this->tlv('58', self::COUNTRY_VN)
            . $this->tlv('62', $this->tlv('08', $this->sanitizeReference($reference)));

        // CRC tính trên toàn bộ chuỗi đã gồm tag "6304" của chính trường CRC.
        return $payload . '63' . '04' . $this->crc16($payload . '6304');
    }

    /**
     * Nội dung chuyển khoản: app ngân hàng chỉ nhận chữ/số không dấu.
     * Giới hạn 25 ký tự để an toàn với mọi ngân hàng.
     */
    private function sanitizeReference(string $reference): string
    {
        $ascii = \iconv('UTF-8', 'ASCII//TRANSLIT', $reference);
        $ascii = $ascii === false ? $reference : $ascii;

        return substr((string) preg_replace('/[^A-Za-z0-9 ]/', '', $ascii), 0, 25);
    }

    private function tlv(string $tag, string $value): string
    {
        return $tag . str_pad((string) \strlen($value), 2, '0', \STR_PAD_LEFT) . $value;
    }

    /**
     * CRC-16/CCITT-FALSE: poly 0x1021, init 0xFFFF, không đảo bit, xorout 0x0000.
     * Đây là biến thể đặc tả EMVCo yêu cầu — dùng nhầm biến thể CRC khác
     * sẽ sinh mã QR mà app ngân hàng từ chối.
     */
    private function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $len = \strlen($data); $i < $len; ++$i) {
            $crc ^= \ord($data[$i]) << 8;

            for ($bit = 0; $bit < 8; ++$bit) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', \STR_PAD_LEFT));
    }
}
