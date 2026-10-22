# Khảo sát dự án nguồn mở để đẩy nhanh phát triển

> Khảo sát 22/10/2026. Phiên bản và ngày phát hành lấy thẳng từ npm / Packagist cùng ngày, không dựa trên bài viết. Đây là **đề xuất**, chưa cài gì. Mỗi mục được chọn sẽ làm thành một commit riêng, có kiểm chứng.

## 1. Tiêu chí

Một dự án chỉ được đề xuất khi đạt đủ cả bốn điều:

1. **Giấy phép mở** (MIT / Apache), **còn duy trì**: có bản phát hành trong 12 tháng gần đây.
2. **Tương thích** Shopware 6.7, Nuxt 4, Tailwind v4.
3. **Không cần `shopware/storefront`.** ADR-006 đã gỡ mọi phụ thuộc vào gói này; gói còn cài chỉ vì language pack.
4. **Không phá 8 quy tắc cứng.** Đặc biệt quy tắc 6: không làm lại thứ core đã có. Quy tắc 8: frontend không tự sinh dữ liệu mà backend đã cung cấp.

Đã có sẵn, không cần thêm: `@nuxt/image`, `@vueuse/nuxt`, `@regle` (kiểm form), `@nuxt/a11y`, JSON-LD sản phẩm (`useProductJsonLD`), sitemap và robots do backend sinh (H7, L4).

## 2. Đề xuất

### Ưu tiên 1: làm ngay, lợi ích lớn nhất

| Dự án | Bản / ngày | Giải quyết việc gì đang làm tay |
|---|---|---|
| **Playwright** (`@playwright/test`) | 1.64.1 · 14/10/2026 · Apache-2.0 | Mọi kiểm chứng H1–H8 và ADR-006 đang chạy bằng khoảng 18 script CDP tự viết trong thư mục tạm. Kết quả không lưu vào repo, không chạy lại được. Chuyển thành bộ test e2e trong `frontend/tests/e2e`: đặt đơn COD, chuyển khoản, mã khuyến mãi, trang brand, ảnh rê chuột, khổ mobile 390px, chụp ảnh so sánh. Shopware 6.7 đã lấy Playwright làm chuẩn e2e (thay Cypress), template Nuxt của Shopware Frontends có sẵn thư mục `testing/playwright` |
| **Mailpit** (SMTP giả lập cho local) + **`frosh/mail-platform-archive`** | 3.7.0 · 19/10/2026 · core `~6.6 \|\| ~6.7`, không cần storefront | Hai lỗi email ở ADR-006 (link trỏ domain giữ chỗ, email tiếng Anh) chỉ phát hiện được nhờ đọc bảng `log_entry`. Mailpit cho xem email thật trên local. Mail Archive lưu email đã gửi ngay trong Administration: chăm sóc khách hàng tra cứu và gửi lại được trên production |
| **`frosh/tools`** | 3.15.3 · 21/10/2026 · 6.7 | Xem trong Admin: hàng đợi message, scheduled task (sitemap cần chạy mỗi giờ — 15-headless mục 7), log, cache, kiểm tra sức khoẻ hệ thống. Hiện việc này phải làm bằng `bin/console` và SQL |
| **`shopware-cli`** + **`shopware/deployment-helper`** | deployment-helper 0.1.10 · 28/09/2026 | Chuẩn build và deploy chính thức của Shopware. `shopware-cli project ci` build artifact; deployment-helper chạy migration, `plugin:update` theo đúng thứ tự, cài mới hoặc cập nhật tự động. Thay các bước "Triển khai: plugin:update …" đang ghi tay trong từng commit (REL-02). Cần trước go-live |

### Ưu tiên 2: dùng khi tới việc tương ứng

| Dự án | Bản / ngày | Dùng khi |
|---|---|---|
| **`frosh/frosh-platform-template-mail`** | 4.0.0 · 01/2026 · 6.7, không cần storefront | **Mẫu email tiếng Việt** (việc còn treo ở ADR-006): mẫu email là file Twig trong plugin, có lịch sử git và review được, thay vì chỉ nằm trong DB. Hợp với quy tắc "cấu hình phải tái lập được" |
| **Reka UI** | 2.11.0 · 05/10/2026 · MIT | Component **mới** cần trợ năng: drawer bộ lọc mobile, accordion, combobox chọn phường/xã. Không kèm giao diện, nên tự định kiểu bằng token màu sẵn có, không sinh ra hai hệ thống thiết kế song song. Là nền của Nuxt UI và shadcn-vue |
| Nuxt UI | 4.12.0 · 15/10/2026 · MIT (từ v4 gộp cả bản Pro, miễn phí) | Chỉ khi cần nhiều màn hình dạng ứng dụng (tài khoản, dashboard). Có hệ token riêng, phải ánh xạ sang màu thương hiệu, trùng vai trò với `cms-base-layer`. Không đề xuất cho storefront |
| **`nuxt-schema-org`** (chỉ module này, **không** dùng trọn `@nuxtjs/seo`) | 7.0.1 · 13/10/2026 · MIT | Mở rộng JSON-LD: Breadcrumb, Organization, ItemList cho danh mục/brand. Thay `useProductJsonLD` tự viết. **Không** dùng phần sitemap/robots của `@nuxtjs/seo`: backend đã sinh, dùng thêm là vi phạm quy tắc 8 |
| **`@nuxt/scripts`** | 1.3.12 · 03/10/2026 · MIT | Phase 4 marketing: GA4, GTM, Meta Pixel tải không chặn trang, có sẵn chế độ chờ đồng ý cookie |
| **`@nuxt/test-utils`** + Vitest | 4.3.4 · 16/10/2026 · MIT | Unit test cho composable (`useShopSettings`, `useFilterLabel`, `useBrandPath`…). Frontend hiện chưa có unit test nào |
| **`frosh/shopware-rector`** | 0.6.0 · 13/08/2026 | Khi nâng lên Shopware 6.8: tự sửa code plugin theo deprecation |
| **Dữ liệu hành chính VN**: `thanglequoc/vietnamese-provinces-database` (MIT, v4.0.0 · 06/2026, có SQL MySQL) hoặc `quangtam/vietnam-address-database` (MIT, JSON + PHP) | — | Khi cần chọn **phường/xã**: ô "Quận/huyện" hiện là chữ tự do. Đã có 34 tỉnh/thành theo Nghị quyết 202/2025 (`AcmeVietnam`). Core không có cấp phường/xã, nên phải thiết kế (custom field địa chỉ + route Store API) **trước** khi nạp dữ liệu (quy tắc 6). Đối chiếu với danh mục của Tổng cục Thống kê trước khi dùng |

### Không dùng

| Dự án | Lý do |
|---|---|
| `frosh/platform-filter-search`, `frosh/platform-share-basket` | Cần `shopware/storefront` (đi ngược ADR-006) |
| Toàn bộ `@nuxtjs/seo` | Sitemap/robots trùng với backend (H7, L4) |
| `vee-validate` | Đã có `@regle` của template; bản mới nhất từ 06/2025 |
| Alokai / Storefront UI | Xem đánh giá ngày 22/10: Alokai thay cả frontend, phần kết nối Shopware dùng được cho production là gói Enterprise phải mua; Storefront UI tạo hệ token thứ hai |
| Plugin GHN / GHTK / VNPay / MoMo cho Shopware | **Không tìm thấy** dự án mở nào. Tự viết theo API chính thức: GHN/GHTK vào `AcmeShipping` giai đoạn 2, cổng thanh toán vào `AcmePayment` (handler 6.7, quy tắc 3) |

## 3. Lộ trình đề xuất

1. ✅ (22/10) **Playwright** — 11 test trong `frontend/tests/e2e`, `pnpm test:e2e` ([15-headless.md](15-headless.md) mục 9).
2. **Mailpit + Mail Archive + Template Mail**, rồi làm mẫu email tiếng Việt và kiểm bằng email thật.
3. **FroshTools**, sau đó **shopware-cli + deployment-helper** trong lúc chuẩn bị go-live ([06-golive.md](06-golive.md)).
4. Phần còn lại cài khi tới việc tương ứng.

## 4. Lưu ý khi cài

- Plugin bên thứ ba cài bằng `composer require` (đúng ngoại lệ của quy tắc 2). Sau khi cài, kiểm `composer why shopware/storefront` không có thêm gói nào phụ thuộc storefront.
- Backup trước khi cài (quy tắc 4). Thử trên local rồi staging. Ghi phiên bản đã kiểm vào [03-plugin-app.md](03-plugin-app.md).
- Gói npm: phiên bản ghi chính xác trong `pnpm-lock.yaml`. Chạy `pnpm lint && pnpm typecheck && pnpm build` trước khi commit.

## Nguồn

- npm registry, Packagist (truy vấn trực tiếp 22/10/2026)
- [Nuxt UI — getting started](https://ui.nuxt.com/docs/getting-started)
- [Shopware — Deployment Helper](https://developer.shopware.com/docs/guides/hosting/installation-updates/deployments/deployment-helper/) · [shopware-cli project ci](https://developer.shopware.com/docs/products/tools/cli/project-commands/build.html)
- [Shopware — Testing (Playwright)](https://developer.shopware.com/docs/guides/development/testing/) · [Playwright e2e cho template Nuxt](https://scayle.dev/en/storefront-guide/storefront-application/testing/end-to-end-testing)
- [FroshTools trên 6.7 (forum)](https://forum.shopware.com/t/frosh-tools-staendig-ohne-navigation-bei-6-7-0-0-rc-4/107256)
- [quangtam/vietnam-address-database](https://packagist.org/packages/quangtam/vietnam-address-database) · [thanglequoc/vietnamese-provinces-database](https://awesome.ecosyste.ms/projects/github.com%2Fthanglequoc%2Fvietnamese-provinces-database) · [sunshine-tech/VietnamProvinces](https://github.com/sunshine-tech/VietnamProvinces)
