<?php declare(strict_types=1);

namespace Acme\Payment\Service;

/**
 * Tên ngân hàng theo mã BIN NAPAS — để khách thấy "Vietcombank" thay vì "970436".
 *
 * Phải khớp danh sách lựa chọn `bankBin` trong Resources/config/config.xml
 * (BankDirectoryTest kiểm tra hai nơi không lệch nhau).
 *
 * @internal
 */
final class BankDirectory
{
    public const NAMES = [
        '970436' => 'Vietcombank',
        '970407' => 'Techcombank',
        '970422' => 'MB Bank',
        '970415' => 'VietinBank',
        '970418' => 'BIDV',
        '970416' => 'ACB',
        '970432' => 'VPBank',
        '970405' => 'Agribank',
        '970403' => 'Sacombank',
        '970423' => 'TPBank',
    ];

    public static function name(string $bin): string
    {
        return self::NAMES[$bin] ?? $bin;
    }
}
