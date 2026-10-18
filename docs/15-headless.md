# Headless — frontend Nuxt trên Shopware Store API

Quyết định và lý do: [decisions/ADR-005-headless-monorepo.md](decisions/ADR-005-headless-monorepo.md).

## 1. Chạy local

Yêu cầu: Node `>=22.22.3` (repo có `.nvmrc`), pnpm qua corepack, backend chạy ở `http://shopware-fashion-brand.test`.

```bash
nvm use                       # Node 22.23.1
corepack enable pnpm
pnpm install                  # ở gốc repo
cp frontend/.env.template frontend/.env   # rồi điền access key (mục 2)
pnpm dev                      # http://localhost:3000
```

Lệnh Shopware vẫn chạy trong `backend/`:

```bash
cd backend && bin/console cache:clear && bin/console cache:clear:http
```

## 2. Access key

Frontend gọi Store API bằng access key của channel `Headless` (type API — channel duy nhất, ADR-005 mục 3):

```sql
SELECT sc.access_key FROM sales_channel sc
 WHERE sc.type_id = UNHEX('f183ee5650cf4bdb8a774337575067a6');   -- Defaults::SALES_CHANNEL_TYPE_API
```

Hoặc Administration → Sales Channels → Headless → API access. Điền vào `NUXT_PUBLIC_SHOPWARE_ACCESS_TOKEN` và `OPENAPI_ACCESS_KEY` trong `frontend/.env` (file không vào git). Access key Store API là khoá công khai (trình duyệt gửi kèm mọi request) — không phải bí mật, nhưng vẫn không commit để mỗi môi trường dùng key của mình.

Domain frontend do migration `AcmeVietnam` tạo, URL lấy từ `ACME_FRONTEND_URL` trong `backend/.env.local` (local: `http://localhost:3000`). **Đặt biến này trước khi chạy `plugin:update AcmeVietnam`** trên môi trường mới; quên thì thêm domain sau trong Administration (loại "External storefront", vi-VN, VND). Mỗi (channel, ngôn ngữ) chỉ có **một** domain external storefront — ràng buộc của core.

Sau khi đổi dữ liệu catalog/SEO: `bin/console dal:refresh:index` (SEO URL headless có route `store-api.product.detail`, `store-api.category.detail`).

### Triển khai đợt 18/10/2026 lên môi trường khác

```bash
cd backend
# .env.local: ACME_FRONTEND_URL=https://<domain-frontend>
bin/console plugin:refresh
bin/console plugin:update AcmeVietnam
bin/console plugin:update AcmePayment
bin/console plugin:update AcmeDemoData
bin/console acme:demo:seed          # chỉ môi trường có dữ liệu demo
bin/console dal:refresh:index
bin/console cache:clear && bin/console cache:clear:http
```

## 3. Công nghệ

| Lớp | Chọn | Ghi chú |
|---|---|---|
| Framework | **Nuxt 4** (Vue 3.5, TypeScript strict) | Đi kèm template chính thức. SSR + ISR cho trang catalog/brand (SEO), CSR cho checkout/account (đã cấu hình sẵn trong `routeRules`). |
| Dữ liệu Shopware | `@shopware/api-client`, `@shopware/composables`, `@shopware/nuxt-module` | Không tự viết fetch tới Store API. Kiểu dữ liệu sinh bằng `pnpm generate-types` từ OpenAPI của backend — có cả route plugin tự viết. |
| Shopping Experiences | `@shopware/cms-base-layer` | Layout CMS dựng trong Admin (landing brand, bộ sưu tập, trang chủ) render được ngay; override block/element trong `frontend/app/components/cms/`. |
| CSS | **Tailwind** (cú pháp v3) — chạy trên engine UnoCSS `presetWind3` | Quy ước ở mục 6. Không cài `tailwindcss`: layer CMS của Shopware cần UnoCSS. Bảng màu nâu/be/kem của `AcmeTheme` thành theme trong `uno.config.ts`. |
| Đa ngôn ngữ | `@nuxtjs/i18n` | Đặt `vi-VN` mặc định, chiến lược `no_prefix` (chỉ một ngôn ngữ — URL giữ nguyên như storefront Twig). Bỏ `pl-PL`/`de-DE` của template. |
| Ảnh | `@nuxt/image` | Dùng thumbnail Shopware sinh sẵn (`srcset`), không resize lần hai. |
| Form | `@regle/core` | Có sẵn trong template; validate SĐT VN, địa chỉ 34 tỉnh/thành. |
| Lint/format | `oxlint`, `oxfmt` | Có sẵn. |
| Test | **Vitest** + `@nuxt/test-utils` (unit), **Playwright** (e2e + ảnh chụp) | Cần thêm. Playwright thay quy trình chụp Chrome headless ở [02-theme.md](02-theme.md); e2e tối thiểu: xem trang brand → thêm giỏ → đặt đơn COD. |
| Gói & monorepo | **pnpm workspace** | Khi tách theme brand dùng chung thành Nuxt layer, đặt ở `packages/*`. |
| Triển khai | Nitro `node-server` (Dockerfile có sẵn) sau Nginx, cùng VPS với backend lúc đầu | ISR cần cache dùng chung giữa các instance khi scale ngang. Vercel/Netlify cũng chạy được nhưng xa khách VN. |

**Không chọn:** Demo Store Template (deprecated, không cập nhật được), Next.js/React (không có renderer CMS chính thức), Astro (hỗ trợ cart/checkout mỏng). Không cần Pinia — composables Shopware đã giữ state dùng chung.

> ⚠️ Template khai báo các gói `@shopware/*` theo tag `canary`. `pnpm-lock.yaml` đang khoá version cụ thể; trước go-live đổi sang version cố định trong `frontend/package.json`.

## 4. Việc cần chuyển từ storefront Twig

| # | Chức năng | Hiện ở | Việc headless |
|---|---|---|---|
| H1 | i18n vi-VN, VND, theme nâu/be/kem, logo ANIVELE | `AcmeTheme`, template | ✅ i18n: `vi-VN` là locale duy nhất (bỏ en-GB/pl-PL/de-DE), dịch cả khoá của `cms-base-layer`, nhãn bộ lọc qua override `SwFilterDropdown`. ⬜ theme nâu/be/kem, logo; chip bộ lọc đang đặt chữ tiếng Anh cứng trong `SwFilterChips`. |
| H2 | Trang `/brands`, `/brands/{slug}`, brand tắt → 404 | `AcmeBrand` Storefront controller | Route Store API trong `AcmeBrand` (danh sách brand, brand theo SEO path); component `FrontendBrandPage` trong `app/components/global/` để resolver `[...all].vue` nhận route brand. |
| H3 | Link brand + logo brand ở trang sản phẩm | `AcmeBrand` Twig | Đọc `product.manufacturer` + extension `acmeBrand` từ Store API. |
| H4 | Bộ sưu tập `/collections/*`, `/collections/sale` | `AcmeCatalog` (SEO navigation) | Không cần code mới nếu SEO URL resolve ra category — kiểm chứng. |
| H5 | Card sản phẩm, swatch Màu × Size, buy box | `AcmeTheme` Twig | Override component `product/` của template. |
| H6 | Trang cảm ơn: hướng dẫn chuyển khoản / VietQR | `AcmePayment` Twig | Đọc thông tin từ đơn hàng / Store API; VietQR đang tạm gác. COD chạy qua flow chuẩn. |
| H7 | Canonical, meta, sitemap, robots | Core + `AcmeBrand` | `useCmsHead` + head của trang brand; sitemap/robots phải phục vụ từ domain frontend. |
| H8 | Phí ship theo khu vực, VAT, khuyến mãi brand | `AcmeShipping`, Rule Builder | Không đổi — tính trong cart backend, frontend chỉ hiển thị. |

Xong H1–H8 + e2e COD → đánh giá gỡ storefront Twig (ADR riêng).

## 5. Ghi chú khi tuỳ biến frontend

- **Override component của layer:** đặt file cùng tên vào `frontend/app/components/cms/` (global, priority 2). Nuxt in cảnh báo `NUXT_B3011 Two component files resolving to the same name` — đó là hành vi bình thường của layer override; kiểm tra `.nuxt/components.d.ts` trỏ về file trong `app/`.
- **Bản dịch:** `frontend/i18n/vi-VN/*.json`. Component của `cms-base-layer` đọc chung messages này qua `useCmsTranslations` (mặc định tiếng Anh nếu thiếu khoá). Khi nâng cấp template, so khoá với `i18n/en-GB` của upstream.
- **Lint/format:** `.oxlintrc.json`, `.oxfmtrc.json` lấy từ gốc repo `shopware/frontends` (template dựa vào chúng nhưng không mang theo). `@nuxt/kit`, `@types/three`, `@types/js-cookie` khai báo thêm vì cùng lý do.

## 6. CSS: viết Tailwind, engine UnoCSS

Quyết định 18/10/2026 (ADR-005 mục 6): **frontend viết class Tailwind**, nhưng không cài gói `tailwindcss`. Engine là **UnoCSS + `presetWind3`** — preset tương thích cú pháp Tailwind v3 — vì 121 component của `@shopware/cms-base-layer` và `@shopware/unocss-design-tokens-layer` dựa vào UnoCSS (~290 class design token, icon `i-carbon-*`, sinh class lúc chạy cho HTML soạn trong CMS). Thay bằng Tailwind thật phải viết lại phần này và kiểm lại mỗi lần nâng cấp template.

| Được | Không |
|---|---|
| Class Tailwind v3 trong template: `flex gap-4 px-4 md:grid-cols-3 hover:bg-…` | Cài `tailwindcss`, `@tailwindcss/vite`, `@nuxtjs/tailwindcss` |
| Màu/font thương hiệu khai báo trong `theme` của `frontend/uno.config.ts`, dùng như Tailwind: `bg-brand-primary`, `text-surface-on-surface` | Mã màu rời rạc `bg-[#8b5e3c]` trong component — thêm vào theme |
| Icon: `i-carbon-*` (preset icons, có sẵn) | Chế độ attributify (`<div flex p-4>`) dù preset đang bật — không phải cú pháp Tailwind, dev mới không đọc được |
| Cú pháp riêng của Tailwind v4 cần kiểm trước khi dùng (`@theme`, `size-*` v.v.) | Thư viện UI kéo theo Tailwind riêng (DaisyUI, Flowbite…) |

Đọc thêm: tài liệu Tailwind v3 dùng được cho hầu hết class; sai khác giữa `presetWind3` và Tailwind: <https://unocss.dev/presets/wind3>. Gợi ý editor: extension **UnoCSS** (VS Code) để có autocomplete — extension Tailwind IntelliSense không nhận cấu hình này.
