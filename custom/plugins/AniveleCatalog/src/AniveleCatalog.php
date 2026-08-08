<?php declare(strict_types=1);

namespace Anivele\Catalog;

use Shopware\Core\Framework\Plugin;

/**
 * Cấu trúc catalog thời trang.
 *
 * Migration chuẩn hoá thuộc tính (tách Hoạ tiết khỏi Màu sắc, dịch vi-VN).
 * Việc sinh biến thể chạy bằng lệnh `anivele:catalog:variants` vì đó là thao tác
 * trên dữ liệu sản phẩm, không phải thay đổi cấu trúc — chạy khi nào cần.
 */
class AniveleCatalog extends Plugin
{
}
