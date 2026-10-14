<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Unit\Validation;

use Acme\Brand\BrandException;
use Acme\Brand\Validation\BrandUniqueExceptionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BrandUniqueExceptionHandler::class)]
class BrandUniqueExceptionHandlerTest extends TestCase
{
    private BrandUniqueExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new BrandUniqueExceptionHandler();
    }

    #[DataProvider('duplicateCodeMessages')]
    public function testDuplicateCodeBecomesBrandException(string $message): void
    {
        $previous = new \RuntimeException($message);

        $matched = $this->handler->matchException($previous);

        static::assertEquals(BrandException::duplicateCode('NKE', $previous), $matched);
    }

    public static function duplicateCodeMessages(): \Generator
    {
        yield 'MySQL 8 tiền tố tên bảng trước tên khoá' => [
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'NKE\' for key \'acme_brand.uniq.acme_brand.code\'',
        ];
        yield 'MySQL 5.7 / MariaDB chỉ ghi tên khoá' => [
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'NKE\' for key \'uniq.acme_brand.code\'',
        ];
    }

    public function testSecondBrandForSameManufacturerBecomesBrandException(): void
    {
        $previous = new \RuntimeException(
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'\x01\x02-\x03\x04\' for key \'acme_brand.uniq.acme_brand.manufacturer\''
        );

        static::assertEquals(BrandException::duplicateManufacturer($previous), $this->handler->matchException($previous));
    }

    public function testDuplicateOnOtherTableIsLeftToOtherHandlers(): void
    {
        $exception = new \RuntimeException(
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'SW1\' for key \'product.uniq.product.product_number__version_id\''
        );

        static::assertNull($this->handler->matchException($exception));
    }
}
