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
| H2 | Trang `/brands`, `/brands/{slug}`, brand tắt → 404 | `AcmeBrand` Storefront controller | ✅ (21/10) 3 route Store API + SEO URL headless trong `AcmeBrand` 1.3.0 ([12 mục 5.2.1](12-da-thuong-hieu.md)). Frontend: `pages/brands/index.vue`, `components/global/StoreApiAcmeBrandDetail.vue` (resolver nhận theo tên route), listing dùng lại component layer bằng cách provide `categoryListing`. Mục "Thương hiệu" trên menu. Kiểm: SSR, đổi sắp xếp phía client (Chrome thật), brand tắt → 404 nhưng sản phẩm vẫn bán. |
| H3 | Link brand + logo brand ở trang sản phẩm | `AcmeBrand` Twig | ✅ (21/10) Override `CmsElementManufacturerLogo`: brand đang bật + người vận hành chưa đặt link → dẫn về trang brand nội bộ, cùng tab (logo, chưa có logo thì tên); còn lại giữ bản gốc của layer — giống template Twig. Đường dẫn qua `useBrandPath` (SEO URL native). Kiểm bằng bấm thật trong Chrome. |
| H4 | Bộ sưu tập `/collections/*`, `/collections/sale` | `AcmeCatalog` (SEO navigation) | ✅ (21/10) Không đúng như dự đoán ban đầu — Phase 4 chỉ đổi template SEO storefront, URL headless vẫn là `Dam/`, `Bo-suu-tap/Sale/`. `AcmeCatalog` 1.6.0: migration đưa template `store-api.category.detail` / `store-api.product.detail` về đúng bản storefront (dùng lại hằng số của 3 migration Phase 4). Frontend: `useSeoCanonicalRedirect` — URL cũ / sai hoa thường → 301 về canonical (giữ query), như `redirectToCanonicalUrl` của core. |
| H5 | Card sản phẩm, swatch Màu × Size, buy box | `AcmeTheme` Twig | ✅ (21/10) Thẻ: khung 2:3 nền kem, ảnh thứ hai khi rê chuột (chỉ `pointer-fine`, điện thoại không tải), badge tách "-x%" / "Bán chạy" (layer luôn ghi "Bán chạy"). Bộ chọn biến thể: ô màu tròn theo `colorHexCode`, size ≥ 48×44, tổ hợp hết hàng làm mờ + gạch (không ẩn), đổi nhóm qua `find-variant` + `switchedGroup` như storefront Twig. Khối mua: "Người mẫu mặc size", **bảng size** từ danh mục chính (`<dialog>` native). Sửa dữ liệu: thứ tự size vi-VN "L, XL, S, M" → S, M, L, XL (`AcmeCatalog` 1.7.0 + `AcmeDemoData` 1.2.1). Khác Twig có chủ ý: tên trên thẻ giữ serif (đồng bộ trang chi tiết); bỏ ô số lượng trên thẻ (tiện ích phụ, phải chép cả component) |
| H6 | Trang cảm ơn: hướng dẫn chuyển khoản / VietQR | `AcmePayment` Twig | ✅ (21/10) Đặt được đơn COD và Chuyển khoản trước qua frontend (#10003–#10005, cả khổ mobile): VND, VAT 10%, SĐT + tỉnh lưu vào đơn, `manufacturerId` trong dòng đơn, trừ tồn kho. Sửa trên đường: SĐT bắt buộc (khoá sai từ Phase 0 — `AcmeVietnam` 1.4.0), ô SĐT `type=tel` ở thanh toán / sổ địa chỉ / đăng ký, mã bưu chính theo quốc gia, bỏ độ dài tối thiểu của tên/họ, trạng thái đơn tiếng Việt (migration), giờ đặt hàng theo giờ VN, địa chỉ có tỉnh + SĐT, nhãn tiếng Việt, "Miễn phí" ở phí ship, **bố cục thanh toán mobile** (template cố định hai cột). Khối VietQR: chưa port — VietQR tạm gác, không gắn vào channel |
| H7 | Canonical, meta, sitemap, robots | Core + `AcmeBrand` | ✅ (21/10) URL chữ thường + `/collections/` + 301 (cùng H4). Canonical: bỏ query; biến thể trỏ về biến thể canonical (`canonicalProductId`, Phase 4) qua `useProductSeoPath`. Tiêu đề `"<trang> \| ANIVELE"` từ `app.config` `siteName` (một chỗ, chờ chốt tên nền tảng). Sitemap + robots.txt phục vụ ở domain frontend (mục 7). `AcmeBrand` 1.3.1: URL brand vào sitemap của channel headless. Còn: meta trang chủ — chờ tên nền tảng |
| H8 | Phí ship theo khu vực, VAT, khuyến mãi brand | `AcmeShipping`, Rule Builder | ✅ (21/10) Tính ở backend như cũ, frontend chỉ hiển thị. Kiểm qua frontend bằng sản phẩm thử 200.000 ₫: Hà Nội → phí 25.000 (#10009), Đà Nẵng → 35.000 (#10007); 2 brand + mã `MAY10` (Rule Builder: dòng hàng của Mây Atelier) → giảm 10% chỉ trên món Mây Atelier, đủ 500.000 ₫ miễn ship (#10008, #10010). Thêm: ô **mã khuyến mãi** ở giỏ và thanh toán (`CheckoutPromotionCode` — câu lỗi lấy từ backend), dòng khuyến mãi trong giỏ không có ô số lượng; khách vãng lai chọn tỉnh/thành → `PATCH /context` để phí ship trước khi đặt đúng khu vực (trước đó hiện 35.000 cho Hà Nội). Dữ liệu thử: core cấm xoá khuyến mãi đã dùng trong đơn → mã `MAY10` và sản phẩm `LLN-H8-TEST` để **tắt**, không xoá (chỉ có ở DB local) |

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
  | `CmsElementManufacturerLogo.vue` | Logo/tên brand ở trang sản phẩm dẫn về trang brand nội bộ (H3) |
  | `SwVariantConfigurator.vue` | Ô màu tròn, size 48×44, tổ hợp hết hàng, đổi nhóm qua `find-variant` (H5) — script giữ bản gốc trừ `onHandleChange` |
  | `SwProductCardImage.vue` | Khung 2:3, ảnh rê chuột, badge giảm giá (H5) — script giữ bản gốc |
  | `CmsElementBuyBox.vue` | **Bọc** (không chép) bản gốc + `ProductSizeGuide` bên dưới (H5) |
  | `CmsElementProductListing.vue` | Không gửi `limit`/`order` mặc định tự chế — backend áp cấu hình (quy tắc 8, L2/L3) |
  | `SwProductListingPagination.vue` | Lựa chọn số sản phẩm/trang theo `productsPerPage` của backend (L2) |

  Component của template (`app/components/checkout/`, không thuộc layer) đã sửa: `ProductTile.vue` (dòng khuyến mãi: icon thay ảnh, không ô số lượng), `SummaryBox.vue` + `pages/checkout/cart.vue` (thêm `PromotionCode.vue` mới), `pages/checkout/index.vue` (khách vãng lai đổi tỉnh/thành → cập nhật context để tính lại phí ship — H8).

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

## 7. Sitemap & robots.txt (H7)

Frontend phục vụ ở **domain của nó** (server route Nitro trong `frontend/server/routes/`):

| Đường dẫn | Nguồn |
|---|---|
| `/sitemap.xml` | Chỉ mục — liệt kê file qua `GET /store-api/sitemap`, đổi URL file sang `/sitemap/…` của frontend |
| `/sitemap/salesChannel-…/….xml.gz` | Proxy `store-api.sitemap.file` của core (core tự kiểm file thuộc đúng channel; route chặn đường dẫn lạ trước khi gọi) |
| `/robots.txt` | Sinh động (dòng `Sitemap:` cần URL tuyệt đối theo domain). Chặn `/account`, `/checkout`, `/wishlist`, `/search` |

Nội dung sitemap do **core** sinh cho channel headless (type API + domain external storefront) — URL trang đã theo domain frontend. Kiểm 21/10: 23 URL = trang chủ, 12 sản phẩm (bản canonical, không 64 biến thể), 7 danh mục/bộ sưu tập, 3 brand; mọi URL trả 200.

Vận hành:

- Sitemap làm mới bằng scheduled task (`core.sitemap.sitemapRefreshStrategy = 2`, mỗi giờ) → production phải chạy `bin/console scheduled-task:run` và worker `messenger:consume`. Sinh ngay: `bin/console sitemap:generate`.
- Trang `/brands` (trang tổng) không thuộc entity nào nên không tự vào sitemap — thêm ở Administration → Settings → Sitemap → **Custom URLs** (cấu hình native, không cần code).
- Nếu chạy frontend sau CDN: `/sitemap*` và `/robots.txt` đã đặt `cache-control` (1 giờ / 1 ngày).

## 8. Rà soát: frontend chỉ hiển thị dữ liệu backend cung cấp

Quy tắc cứng số 8 (CLAUDE.md), chi tiết [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 6. Rà ngày 21/10/2026 trên code `frontend/` đến hết H5.

### Đúng nguyên tắc

| Hiển thị | Nguồn backend |
|---|---|
| Giá, giá gạch, VAT, tổng đơn, phí ship ("Miễn phí" = định dạng giá trị 0) | Giỏ hàng / sản phẩm do core tính |
| Badge "-x%" | `calculatedPrice.listPrice.percentage` |
| Danh sách brand, thứ tự, brand tắt → 404, meta SEO dự phòng | Store API `AcmeBrand` (H2) |
| SĐT bắt buộc, mã bưu chính bắt buộc | `/store-api/shop-settings` → `loginRegistration`, `country.postalCodeRequired` (`useAddressFieldSettings`; backend vẫn tự kiểm) |
| Tổ hợp biến thể hết hàng, biến thể khi đổi màu/size | `option.combinable`, `/product/{id}/find-variant` + `switchedGroup` |
| Thứ tự size / màu | `position` của giá trị thuộc tính (core sắp) |
| Bảng size, "Người mẫu mặc size" | Custom field danh mục chính / sản phẩm |
| URL, canonical, 301 về URL chuẩn | `seo_url` của core (`useSeoCanonicalRedirect`, `useProductSeoPath`) |
| Sắp xếp kết quả tìm kiếm | `core.listing.defaultSearchResultSorting` (đã bỏ "name-asc" gán cứng — đợt sửa tìm kiếm 21/10) |
| Tìm kiếm không dấu | Backend (`AcmeVietnam`) |
| Trạng thái đơn, thời gian giao | Bản dịch trong DB (`AcmeVietnam`) |
| Sitemap | Core sinh, frontend chỉ proxy |
| Mã khuyến mãi: áp/không áp, câu lỗi, số tiền giảm | Giỏ hàng core (`addPromotionCode`, `translatedMessage` của lỗi — H8) |
| Phí ship theo tỉnh trước khi đặt | `AcmeShipping` theo `countryStateId` trong context (frontend chỉ gửi tỉnh khách chọn) |

### Đang lệch — cần sửa

| # | Gán cứng ở frontend | Backend đã có / cần có | Hướng sửa |
|---|---|---|---|
| L1 | ✅ (21/10) ~~Tên shop `siteName: "ANIVELE"`~~ | `shop-settings` → `general.shopName` | `useShopSettings`: tiêu đề trang, tiêu đề brand, logo chữ đều đọc tên shop từ backend. Tên trong Admin hiện là `anivele` (chữ thường) → tiêu đề "Đầm \| anivele"; sửa ở Administration → Settings → Basic information khi chốt tên nền tảng |
| L2 | ✅ (21/10) ~~Số sản phẩm/trang `15`~~ | `listing.productsPerPage` (hiện 24) | Không gửi `limit` khi URL không có (trang brand, tìm kiếm, override `CmsElementProductListing`); ô chọn = 1×/2×/3× giá trị backend (override `SwProductListingPagination`, bỏ lựa chọn cứng 1/15/30/45) |
| L3 | ✅ (21/10) ~~Sắp xếp mặc định `"name-asc"`~~ | `core.listing.defaultSorting` | Không gửi `order` khi URL không có; so khớp URL dùng `listing.sorting` backend trả |
| L4 | Quy tắc chặn trong `/robots.txt` (`server/routes/robots.txt.get.ts`) | `core.basicInformation.robotsRules` — có trong DB nhưng **chưa có Store API** | Route Store API nhỏ trong plugin (hoặc chấp nhận gán cứng, ghi lý do) — cần quyết định |
| L5 | 🔄 Logo: **chữ** đã lấy tên shop từ backend (L1); **ảnh** logo vẫn chưa có nguồn cho headless | Logo Twig nằm trong theme | Quyết định nơi lưu ảnh logo khi có file logo thật |
| L6 | Múi giờ `Asia/Ho_Chi_Minh` ở trang cảm ơn | Store API không trả múi giờ shop | Chấp nhận — một thị trường VN; ghi lại nếu mở thị trường khác |

**Chưa rà:** các cấu hình `shop-settings` khác mà template có thể đang bỏ qua — `cart.showDeliveryTime`, `cart.wishlistEnabled`, `cart.showTosCheckbox`, `listing.allowBuyInListing`, `loginRegistration.requireDataProtectionCheckbox`… Rà khi làm tới từng màn hình tương ứng.
