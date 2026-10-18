<?php declare(strict_types=1);

namespace Acme\DemoData;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lỗi do môi trường chưa sẵn sàng (thiếu thuế, thiếu ảnh…), không phải bug — trả HTTP 400
 * kèm mã lỗi ổn định; Administration hiển thị `errors[0].detail`.
 */
class DemoDataException extends HttpException
{
    final public const MEDIA_MISSING = 'ACME_DEMO_DATA__MEDIA_MISSING';

    final public const TAX_MISSING = 'ACME_DEMO_DATA__TAX_MISSING';

    final public const ROOT_CATEGORY_MISSING = 'ACME_DEMO_DATA__ROOT_CATEGORY_MISSING';

    public static function mediaMissing(string $directory): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_MISSING,
            'Không tìm thấy ảnh nào trong {{ directory }}',
            ['directory' => $directory],
        );
    }

    public static function taxMissing(float $rate): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TAX_MISSING,
            'Không tìm thấy thuế {{ rate }}%. Hãy cài plugin AcmeVietnam (Phase 0) trước.',
            ['rate' => $rate],
        );
    }

    public static function rootCategoryMissing(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ROOT_CATEGORY_MISSING,
            'Không tìm thấy danh mục gốc của shop.',
        );
    }
}
