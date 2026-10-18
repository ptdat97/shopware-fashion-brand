<?php declare(strict_types=1);

namespace Acme\Payment\Tests\Unit\Service;

use Acme\Payment\Service\VietQrPayloadBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Chuỗi mong đợi sinh bằng một cài đặt độc lập (Python `binascii.crc_hqx(data, 0xFFFF)`
 * = CRC-16/CCITT-FALSE), không chép từ output của chính class — test không tự xác nhận mình.
 *
 * @internal
 */
class VietQrPayloadBuilderTest extends TestCase
{
    private const GOLDEN = '00020101021238570010A00000072701270006970436011300110012345670208QRIBFTTA'
        . '5303704540712500005802VN62120808ANV10057630434DB';

    public function testBuildsNapasPayloadWithIndependentlyVerifiedCrc(): void
    {
        $payload = (new VietQrPayloadBuilder())->build('970436', '0011001234567', 1250000, 'ANV10057');

        static::assertSame(self::GOLDEN, $payload);
    }

    public function testFieldsAreReadableAsTlv(): void
    {
        $fields = $this->parseTlv((new VietQrPayloadBuilder())->build('970407', '19031234567', 499000, 'ANV10001'));

        static::assertSame('01', $fields['00'], 'Phiên bản payload');
        static::assertSame('12', $fields['01'], 'QR động — dùng một lần, có số tiền');
        static::assertSame('704', $fields['53'], 'VND');
        static::assertSame('499000', $fields['54']);
        static::assertSame('VN', $fields['58']);

        $merchant = $this->parseTlv($fields['38']);
        static::assertSame('A000000727', $merchant['00'], 'GUID của NAPAS');
        static::assertSame(['00' => '970407', '01' => '19031234567'], $this->parseTlv($merchant['01']));
        static::assertSame('QRIBFTTA', $merchant['02']);

        static::assertSame(['08' => 'ANV10001'], $this->parseTlv($fields['62']));
        static::assertMatchesRegularExpression('/^[0-9A-F]{4}$/', $fields['63']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function references(): iterable
    {
        yield 'bỏ dấu tiếng Việt vì app ngân hàng chỉ nhận chữ không dấu' => ['Đơn ANV10001', 'Don ANV10001'];
        yield 'bỏ ký tự đặc biệt' => ['ANV-10001#', 'ANV10001'];
        yield 'cắt còn 25 ký tự để mọi ngân hàng đều nhận' => [str_repeat('A', 30), str_repeat('A', 25)];
    }

    #[DataProvider('references')]
    public function testReferenceIsSanitizedForBankingApps(string $input, string $expected): void
    {
        $fields = $this->parseTlv((new VietQrPayloadBuilder())->build('970436', '123', 1000, $input));

        static::assertSame($expected, $this->parseTlv($fields['62'])['08']);
    }

    /**
     * @return array<string, string>
     */
    private function parseTlv(string $data): array
    {
        $fields = [];
        for ($i = 0; $i < \strlen($data);) {
            $tag = substr($data, $i, 2);
            $length = (int) substr($data, $i + 2, 2);
            $fields[$tag] = substr($data, $i + 4, $length);
            $i += 4 + $length;
        }

        return $fields;
    }
}
