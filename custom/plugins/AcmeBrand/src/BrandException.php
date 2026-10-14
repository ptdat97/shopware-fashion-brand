<?php declare(strict_types=1);

namespace Acme\Brand;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class BrandException extends HttpException
{
    final public const DUPLICATE_CODE = 'ACME_BRAND__DUPLICATE_CODE';

    final public const DUPLICATE_MANUFACTURER = 'ACME_BRAND__DUPLICATE_MANUFACTURER';

    public static function duplicateCode(string $code, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DUPLICATE_CODE,
            'Brand code "{{ code }}" is already used by another brand.',
            ['code' => $code],
            $previous,
        );
    }

    public static function duplicateManufacturer(?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DUPLICATE_MANUFACTURER,
            'This manufacturer already has brand data.',
            [],
            $previous,
        );
    }
}
