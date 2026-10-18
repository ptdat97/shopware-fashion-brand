# ADR-005: Chuyển sang headless — monorepo `backend/` + `frontend/`

**Trạng thái:** ✅ Đã duyệt — 18/10/2026 · cập nhật cùng ngày: channel `anivele` đã xoá, `Headless` là channel duy nhất (mục 3–4)
**Quyết định:** Shopware chỉ làm **backend thương mại** (Administration, Store API, Admin API, plugin nghiệp vụ). Giao diện khách hàng là ứng dụng **Nuxt 4** dựng từ [Vue Starter Template](https://developer.shopware.com/frontends/introduction/templates/vue-starter-template.html) của Shopware Frontends. Hai phần nằm chung một repo:

```
shopware-fashion-brand/
├── backend/            Shopware 6.7 (Composer) — trước đây là gốc repo
├── frontend/           Nuxt 4 + Shopware Frontends (pnpm)
├── docs/               tài liệu chung
├── package.json        script gốc: pnpm dev | build | lint | typecheck
├── pnpm-workspace.yaml workspace JS (chỉ frontend/, sau này thêm packages/*)
└── CLAUDE.md
```

## Bối cảnh

- Storefront Twig (`anivele`) đã chạy tốt cho Phase 0–MB5, nhưng mọi tuỳ biến giao diện phải đi qua theme/Twig override của plugin, khó làm landing page brand phong phú và tối ưu Core Web Vitals trên mobile.
- Shopware Frontends cung cấp chính thức API client, composables, renderer cho Shopping Experiences (CMS) — dùng lại được toàn bộ layout CMS, product stream, SEO URL đã cấu hình trong Admin.
- Mô hình đa thương hiệu (ADR-003) **không đổi**: brand vẫn là `product_manufacturer` + `acme_brand`; một catalog, một checkout, một sales channel.

## Quyết định chi tiết

1. **Monorepo, không tách repo.** Thay đổi Store API (plugin) và UI dùng nó đi chung một commit/PR. `backend/` giữ nguyên cấu trúc Shopware chuẩn — mọi lệnh PHP chạy trong `backend/`.
2. **Template:** Vue Starter Template (Nuxt 4, UnoCSS, i18n, TypeScript). Không dùng Demo Store (đã deprecated), không dùng Blank (phải tự viết toàn bộ UI cart/checkout/account).
3. **Sales channel: `Headless` (type API) là channel duy nhất.** Chủ dự án đã xoá channel Storefront `anivele` ngày 18/10/2026 (kèm đơn/khách test của MB5). Channel `Headless` được đưa về thị trường VN bằng migration, không sửa tay:
   - `AcmeVietnam` `Migration1792336157VietnameseHeadlessChannel` — vi-VN, gán tiền tệ VND, domain **external storefront** (`is_external_storefront = 1`) lấy từ biến môi trường `ACME_FRONTEND_URL`. Core chỉ sinh SEO URL (`store-api.*`) và sitemap cho channel API qua loại domain này.
   - `AcmeVietnam` `Migration1792336734VietnameseDeliveryTimes` — tên thời gian giao hàng tiếng Việt.
   - `AcmePayment` `Migration1792336158HeadlessPaymentMethods` — COD (mặc định) + chuyển khoản; gỡ ghi nợ/hoá đơn mặc định của core. VietQR vẫn tạm gác.
   - `AcmeDemoData` 1.2.0 — gán sản phẩm cho mọi channel Storefront + API, gán thời gian giao của `shipping_standard`.

   Không tạo channel theo brand (vẫn là quy tắc cứng số 5).
4. **Storefront Twig không còn phục vụ channel nào**, nhưng chưa gỡ `shopware/storefront` — `AcmeTheme`, `AcmeBrand` (controller `/brands`), `AcmePayment` (trang finish) còn phụ thuộc về mặt code. Gỡ là một ADR riêng sau khi xong H1–H8.
5. **Plugin mở Store API, frontend vẽ UI.** Nghiệp vụ mới (trang brand, bảng size, …) viết thành route Store API trong plugin của nghiệp vụ đó; không đặt logic nghiệp vụ trong Nuxt.

## Hệ quả với bảy quy tắc cứng

| Quy tắc | Áp dụng trong headless |
|---|---|
| 1. Không sửa `vendor/` | Thành `backend/vendor/` **và** `frontend/node_modules/`. Tuỳ biến UI qua component override / Nuxt layer, không patch package `@shopware/*`. |
| 3. Không bịa API | Mở rộng sang Store API: endpoint và kiểu dữ liệu lấy từ `pnpm generate-types` (OpenAPI của chính backend), không đoán từ tutorial. |
| 5, 6 | Không đổi. Frontend ưu tiên dữ liệu native (CMS, product stream, SEO URL) trước khi xin endpoint mới. |
| 7. Plugin độc lập | "Markup plugin nào thì CSS plugin đó" chỉ còn áp dụng cho Twig. Với frontend: mỗi tính năng gọi endpoint của **một** plugin; endpoint trả 404 (plugin tắt) → tính năng ẩn đi, xem hàng/giỏ/COD vẫn chạy. |

## Phương án đã loại

| Phương án | Lý do loại |
|---|---|
| Giữ storefront Twig | Mục tiêu dự án đã đổi sang headless. |
| Next.js / React | Không có composables và renderer CMS chính thức — phải tự viết lại toàn bộ phần Shopping Experiences, cart, checkout. |
| Tách hai repo | Thay đổi API và UI lệch phiên bản; dự án một nhóm nhỏ không cần ranh giới đó. |

## Việc đã làm (18/10/2026)

- `git mv` toàn bộ Shopware vào `backend/` (lịch sử file giữ nguyên); `vendor/`, `.env.local`, `install.lock` chuyển theo.
- `.gitignore` tách làm ba: gốc, `backend/`, `frontend/`.
- Herd: symlink `shopware-fashion-brand` → `backend/` (domain `shopware-fashion-brand.test` không đổi).
- `COMPOSER_HOME` trong `backend/.env.local` đổi sang đường dẫn mới; xoá `backend/var/cache` (container cache chứa đường dẫn tuyệt đối cũ).
- `frontend/` dựng bằng `npx tiged shopware/frontends/templates/vue-starter-template frontend`, endpoint trỏ backend local, `pnpm build` đạt.

## Liên quan

[15-headless.md](../15-headless.md) · [ADR-003](ADR-003-mo-hinh-thuong-hieu.md) · [12-da-thuong-hieu.md](../12-da-thuong-hieu.md)
