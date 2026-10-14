<?php declare(strict_types=1);

namespace Acme\Catalog;

use Shopware\Core\Framework\Plugin;

/**
 * Cấu trúc catalog thời trang.
 *
 * Migration chuẩn hoá thuộc tính (tách Hoạ tiết khỏi Màu sắc, dịch vi-VN), đặt template
 * SEO cho danh mục / bộ sưu tập (`/collections/{tên}`, chữ thường), custom field bảng size
 * theo danh mục, canonical cho biến thể. Việc sinh biến thể chạy bằng lệnh
 * `acme:catalog:variants` vì đó là thao tác trên dữ liệu sản phẩm, không phải thay đổi
 * cấu trúc — chạy khi nào cần.
 *
 * Bề mặt công khai:
 * - Lệnh `acme:catalog:variants [--sku=] [--dry-run]` và service `VariantGenerator`
 * - Custom field danh mục: `acme_collection` (Là bộ sưu tập), `acme_size_chart_table`,
 *   `acme_size_chart_note`
 * - Custom field sản phẩm: `acme_model_size` (Người mẫu mặc size)
 * - Nhóm thuộc tính native "Chất liệu" (cùng "Màu sắc", "Kích cỡ" là dữ liệu catalog)
 *
 * Tắt / gỡ: template SEO, thuộc tính, biến thể và canonical đã ghi là dữ liệu core nên
 * giữ nguyên (LIFE-02) — URL không đổi, không 404. Chỉ mất lệnh sinh biến thể. Custom
 * field vẫn còn vì định nghĩa nằm trong DB; ô nhập vẫn hiện trong Administration.
 */
class AcmeCatalog extends Plugin
{
}
