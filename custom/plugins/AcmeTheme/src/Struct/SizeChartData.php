<?php declare(strict_types=1);

namespace AcmeTheme\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Dữ liệu bảng size truyền sang template trang sản phẩm.
 *
 * @internal
 */
class SizeChartData extends Struct
{
    public const EXTENSION_NAME = 'acmeSizeChart';

    public function __construct(
        public readonly string $table,
        public readonly ?string $note,
        public readonly string $categoryName
    ) {
    }
}
