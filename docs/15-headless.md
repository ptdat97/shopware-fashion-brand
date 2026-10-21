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
| CSS | **Tailwind CSS v4** (`@tailwindcss/vite`) | Thay UnoCSS của template (18/10). Cấu hình trong `app/assets/css/tailwind.css`, quy ước ở mục 6. Bảng màu nâu/be/kem của `AcmeTheme` thành biến `@theme`. |
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
| H1 | i18n vi-VN, VND, theme nâu/be/kem, logo ANIVELE | `AcmeTheme`, template | ✅ (21/10) `vi-VN` là locale duy nhất, dịch cả khoá của `cms-base-layer`. Bảng màu AcmeTheme vào token `@theme` (đen mực cho nút/icon, nâu hover/focus, kem/be nền phụ và viền, đỏ trầm giá KM). Font tự host Inter + Cormorant Garamond (`font-serif` — tên sản phẩm, tiêu đề). Logo chữ `LayoutLogo`. Nhãn bộ lọc ngang/thanh bên và chip bộ lọc tiếng Việt, giá chip theo VND. |
| H2 | Trang `/brands`, `/brands/{slug}`, brand tắt → 404 | `AcmeBrand` Storefront controller | Route Store API trong `AcmeBrand` (danh sách brand, brand theo SEO path); component `FrontendBrandPage` trong `app/components/global/` để resolver `[...all].vue` nhận route brand. |
| H3 | Link brand + logo brand ở trang sản phẩm | `AcmeBrand` Twig | Đọc `product.manufacturer` + extension `acmeBrand` từ Store API. |
| H4 | Bộ sưu tập `/collections/*`, `/collections/sale` | `AcmeCatalog` (SEO navigation) | Không cần code mới nếu SEO URL resolve ra category — kiểm chứng. |
| H5 | Card sản phẩm, swatch Màu × Size, buy box | `AcmeTheme` Twig | Override component `product/` của template. |
| H6 | Trang cảm ơn: hướng dẫn chuyển khoản / VietQR | `AcmePayment` Twig | Đọc thông tin từ đơn hàng / Store API; VietQR đang tạm gác. COD chạy qua flow chuẩn. |
| H7 | Canonical, meta, sitemap, robots | Core + `AcmeBrand` | `useCmsHead` + head của trang brand; sitemap/robots phải phục vụ từ domain frontend. |
| H8 | Phí ship theo khu vực, VAT, khuyến mãi brand | `AcmeShipping`, Rule Builder | Không đổi — tính trong cart backend, frontend chỉ hiển thị. |

Xong H1–H8 + e2e COD → đánh giá gỡ storefront Twig (ADR riêng).

**Phát hiện ngoài danh sách (21/10):** tìm kiếm không dấu (`dam` → 0 kết quả) — ✅ đã sửa cùng ngày trong `AcmeVietnam` 1.3.0, xem [04-thi-truong-vn.md](04-thi-truong-vn.md) mục 7.

## 5. Ghi chú khi tuỳ biến frontend

- **Override component của layer:** đặt file cùng tên vào `frontend/app/components/cms/` (global, priority 2). Nuxt in cảnh báo `NUXT_B3011 Two component files resolving to the same name` — đó là hành vi bình thường của layer override; kiểm tra `.nuxt/components.d.ts` trỏ về file trong `app/`. Override cần import component con của layer thì dùng alias **`#cms-base-layer`** (khai báo trong `nuxt.config.ts`) — package chỉ export `.` và `./package.json`, không import sâu được bằng tên package.
- **Override hiện có** (khi nâng cấp layer, so lại với bản gốc cùng tên):

  | File | Lý do |
  |---|---|
  | `SwFilterDropdown.vue` | Nhãn bộ lọc dạng ngang là mã aggregation thô |
  | `SwProductListingFilter.vue` | Như trên, cho bộ lọc dạng thanh bên — chặn ở đây thay vì override 4 component con |
  | `SwFilterChips.vue` | Chuỗi "Price:", "Rating:", "Free Shipping" cứng; giá hiện số thô |

  Logic dịch nhãn dùng chung: `app/composables/useFilterLabel.ts`.
- **Bản dịch:** `frontend/i18n/vi-VN/*.json`. Component của `cms-base-layer` đọc chung messages này qua `useCmsTranslations` (mặc định tiếng Anh nếu thiếu khoá). Khi nâng cấp template, so khoá với `i18n/en-GB` của upstream.
- **Lint/format:** `.oxlintrc.json`, `.oxfmtrc.json` lấy từ gốc repo `shopware/frontends` (template dựa vào chúng nhưng không mang theo). `@nuxt/kit`, `@types/three`, `@types/js-cookie` khai báo thêm vì cùng lý do.

## 6. CSS: Tailwind CSS v4

Quyết định 18/10/2026 (ADR-005 mục 6): **Tailwind CSS v4 thật**, qua `@tailwindcss/vite`. UnoCSS và `@shopware/unocss-design-tokens-layer` đã gỡ — README của `cms-base-layer` cho phép: *"keep only `@shopware/cms-base-layer` and provide your own UnoCSS or Tailwind setup"*.

Toàn bộ cấu hình nằm trong **`frontend/app/assets/css/tailwind.css`** (Tailwind v4 cấu hình bằng CSS, không có `tailwind.config.js`):

| Phần | Vì sao |
|---|---|
| `@source "../../../node_modules/@shopware/cms-base-layer/app"` | Component của layer nằm trong `node_modules` — Tailwind không tự quét |
| `@source inline("…")` | Class do CMS trả về, không có trong mã nguồn (trước là `safelist` của UnoCSS) |
| `@theme { --color-brand-primary … }` | Token màu — **tên** của Shopware (layer dùng), **giá trị** theo bảng màu thương hiệu AcmeTheme. Nền trang trắng: layer tô trắng nhiều khối, nền kem gây vệt lệch màu |
| `@import "./fonts.css"`, `--font-sans`, `--font-serif` | Inter + Cormorant Garamond tự host ở `public/fonts/` (latin + vietnamese, OFL). Layer dùng `font-serif` cho tên sản phẩm/tiêu đề |
| `--radius-sm`, `--shadow-sm`, `--blur-sm`, `--default-ring-width: 3px` | Layer viết theo thang Tailwind v3; v4 đổi nghĩa các tên này |
| `@layer base` | Hành vi v3: viền mặc định gray-200 (v4 là currentColor), placeholder gray-400, con trỏ tay trên nút |
| `@utility text-red / c-gray-500 / text-3` | Class riêng của UnoCSS mà layer còn dùng (giá khuyến mãi, spinner, hộp đánh giá) |
| `@plugin "../../../tailwind/carbon-icons.mjs"` | Sinh class icon `i-carbon-*` mà layer và template dùng (Iconify Carbon, mask + currentColor) |

Kiểm chứng khi chuyển: chụp 5 trang × desktop/mobile trước và sau, so điểm ảnh — lệch ≈ 0,08% (chỉ nút devtools của Nuxt), đối chứng hai trang khác nhau lệch 45%.

| Được | Không |
|---|---|
| Class Tailwind v4 chuẩn: `flex gap-4 px-4 md:grid-cols-3 bg-black/50 text-[2.5rem]` | Cú pháp riêng UnoCSS: `color-*`, `c-*`, `p4`, `p-x-2`, `text-10`, attributify `<div flex>`, nhóm biến thể `hover:(…)` |
| Màu/font mới: thêm biến vào `@theme` (`--color-…`, `--font-…`) rồi dùng `bg-…`, `font-…` | Mã màu rời rạc `bg-[#8b5e3c]` lặp lại nhiều chỗ — thêm vào `@theme` |
| Icon: `i-carbon-<tên>` | Cài thêm `@nuxtjs/tailwindcss` (module cho Tailwind v3) hay file `tailwind.config.js` |
| Dùng `text-red`/`c-gray-500`/`text-3` **chỉ** để giữ layer hiển thị đúng | Dùng các class tương thích đó trong mã của mình — viết `text-red-400`, `text-gray-500`, `text-xs` |

**Đã biết, chấp nhận:** `focus:ring-opacity-20` của layer (ô "Mỗi trang") không còn tác dụng ở v4 → viền focus ô đó đậm hơn trước. Trang lỗi 404/500 mặc định của Nuxt vẫn mang CSS UnoCSS dựng sẵn của Nuxt — không phải mã của mình.

**Khi nâng cấp `cms-base-layer`:** class kiểu UnoCSS mới trong layer sẽ không có CSS — lỗi im lặng, không báo khi build. Chụp lại các trang chính trước/sau nâng cấp và so điểm ảnh (`magick compare -metric RMSE`), đọc changelog của layer; class lạ thì thêm `@utility` tương thích vào `tailwind.css`.

Editor: extension **Tailwind CSS IntelliSense** (VS Code) nhận cấu hình v4 trong file CSS.
