<?php declare(strict_types=1);

namespace Acme\Vietnam\Tests\Unit\Checkout;

use Acme\Vietnam\Checkout\Order\GuestPhoneOrderRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Luồng xác minh đầy đủ (đặt đơn → mở link email → nhập email + SĐT) kiểm bằng Playwright:
 * frontend/tests/e2e/checkout.spec.ts.
 *
 * @internal
 */
#[CoversClass(GuestPhoneOrderRoute::class)]
class GuestPhoneOrderRouteTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function phones(): iterable
    {
        yield 'đã chuẩn' => ['0987654321', '0987654321'];
        yield 'có khoảng trắng, gạch' => ['0987 654-321', '0987654321'];
        yield 'mã quốc gia +84' => ['+84 987 654 321', '0987654321'];
        yield 'mã quốc gia không dấu +' => ['84987654321', '0987654321'];
        yield 'số bắt đầu 84 nhưng ngắn (không phải mã quốc gia)' => ['8498765', '8498765'];
        yield 'rỗng' => ['', ''];
    }

    #[DataProvider('phones')]
    public function testNormalizePhone(string $input, string $expected): void
    {
        static::assertSame($expected, GuestPhoneOrderRoute::normalizePhone($input));
    }
}
