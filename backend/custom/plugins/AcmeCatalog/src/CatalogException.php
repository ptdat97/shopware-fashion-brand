<?php declare(strict_types=1);

namespace Acme\Catalog;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class CatalogException extends HttpException
{
    final public const VARIANT_GROUPS_MISSING = 'ACME_CATALOG__VARIANT_GROUPS_MISSING';

    public static function variantGroupsMissing(string $colourGroup, string $sizeGroup): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VARIANT_GROUPS_MISSING,
            'Không tìm thấy nhóm thuộc tính "{{ colour }}" và "{{ size }}".',
            ['colour' => $colourGroup, 'size' => $sizeGroup],
        );
    }
}
