<?php declare(strict_types=1);

namespace Acme\Theme;

use Shopware\Core\Framework\Plugin;
use Shopware\Storefront\Framework\ThemeInterface;

/**
 * Giao diện dùng chung cho mọi thương hiệu: bảng màu nâu/be/kem, font, swatch tròn,
 * thẻ sản phẩm đổi ảnh khi hover, bảng size trong buy box. Mobile-first.
 *
 * Bề mặt công khai:
 * - Theme `AcmeTheme` và các trường cấu hình trong `theme.json` (sửa ở Administration → Themes)
 * - Block Twig ghi đè: `layout_header_logo`, thẻ sản phẩm, `element_buy_box_inner`
 * - Đọc (không sở hữu) custom field danh mục `acme_size_chart_table`, `acme_size_chart_note`
 *   của `AcmeCatalog` — không có thì không hiện nút bảng size (ARCH-03)
 *
 * Tắt / gỡ: phải đổi sales channel sang theme khác TRƯỚC (core chặn tắt theme đang gán,
 * LIFE-06). Sau đó storefront dùng theme Storefront mặc định; mua hàng không ảnh hưởng.
 * Wishlist đã bật vẫn giữ — là cấu hình core, không hoàn tác (LIFE-02).
 *
 * Namespace đổi từ `AcmeTheme\` sang `Acme\Theme\` ngày 14/10/2026 — cách chuyển một
 * bản cài cũ: decisions/ADR-004-namespace-acme-theme.md.
 */
class AcmeTheme extends Plugin implements ThemeInterface
{
}