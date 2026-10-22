<?php declare(strict_types=1);

namespace Acme\Headless;

use Shopware\Core\Framework\Plugin;

/**
 * Cấu hình và dữ liệu hiển thị cho frontend headless (ADR-005) mà core chưa đưa ra Store API.
 *
 * Storefront Twig đọc logo từ cấu hình theme và sinh robots.txt bằng RobotsPageLoader —
 * cả hai thuộc gói `shopware/storefront`, frontend Nuxt không với tới. Plugin này đưa
 * chúng vào `/store-api/shop-settings` (route core) dưới `extensions.acmeHeadless`, để
 * frontend có một nguồn cấu hình duy nhất (quy tắc cứng số 8).
 *
 * Bề mặt công khai:
 * - Cấu hình `AcmeHeadless.config.logo` (id media — file nằm trong Media của Shopware)
 * - `extensions.acmeHeadless` trên `/store-api/shop-settings`: `logo` (media), `robotsRules`,
 *   `robotsDisableDefaults` (cấu hình core `core.basicInformation.*`), `staging` (`core.staging`)
 * - Migration: quy tắc robots.txt riêng cho channel type API (đường dẫn của frontend)
 * - Migration: link trong mẫu email của core trỏ thẳng domain frontend, không qua route
 *   storefront (1.1.0, ADR-006 bước 1)
 * - Listing nạp thêm `media.media` — ảnh thứ hai khi rê chuột trên thẻ sản phẩm (1.2.0)
 * - Migration: bật wishlist nếu người vận hành chưa đặt (1.2.0)
 *
 * Tắt / gỡ (08-quy-tac-phat-trien.md, Quy tắc số 5): `extensions.acmeHeadless` biến mất,
 * frontend quay về logo chữ (tên shop), robots.txt chỉ còn quy tắc mặc định, thẻ sản phẩm
 * không đổi ảnh khi rê chuột. Xem hàng, giỏ hàng, COD không ảnh hưởng. Quy tắc robots, mẫu
 * email, wishlist đã ghi là cấu hình/dữ liệu core — giữ nguyên.
 */
class AcmeHeadless extends Plugin
{
}
