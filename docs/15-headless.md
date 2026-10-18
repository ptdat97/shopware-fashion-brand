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

Frontend gọi Store API bằng access key của sales channel `anivele` (ADR-005 mục 3):

```sql
SELECT sct.name, sc.access_key
  FROM sales_channel sc
  JOIN sales_channel_translation sct ON sct.sales_channel_id = sc.id
 GROUP BY sc.id;
```

Hoặc Administration → Sales Channels → anivele → API access. Điền vào `NUXT_PUBLIC_SHOPWARE_ACCESS_TOKEN` và `OPENAPI_ACCESS_KEY` trong `frontend/.env` (file không vào git). Access key Store API là khoá công khai (trình duyệt gửi kèm mọi request) — không phải bí mật, nhưng vẫn không commit để mỗi môi trường dùng key của mình.

Thêm domain frontend (`http://localhost:3000`, sau này domain thật) vào channel `anivele`, ngôn ngữ vi-VN, tiền tệ VND — cần cho SEO URL và đăng ký tài khoản. Đây là thay đổi cấu hình phải tái lập → viết thành migration trong `AcmeVietnam` (xem CLAUDE.md "Cấu hình dữ liệu phải viết thành migration").

## 3. Công nghệ

| Lớp | Chọn | Ghi chú |
|---|---|---|
| Framework | **Nuxt 4** (Vue 3.5, TypeScript strict) | Đi kèm template chính thức. SSR + ISR cho trang catalog/brand (SEO), CSR cho checkout/account (đã cấu hình sẵn trong `routeRules`). |
| Dữ liệu Shopware | `@shopware/api-client`, `@shopware/composables`, `@shopware/nuxt-module` | Không tự viết fetch tới Store API. Kiểu dữ liệu sinh bằng `pnpm generate-types` từ OpenAPI của backend — có cả route plugin tự viết. |
| Shopping Experiences | `@shopware/cms-base-layer` | Layout CMS dựng trong Admin (landing brand, bộ sưu tập, trang chủ) render được ngay; override block/element trong `frontend/app/components/cms/`. |
| CSS | **UnoCSS** + `@shopware/unocss-design-tokens-layer` | Cú pháp tương thích Tailwind. Chuyển bảng màu nâu/be/kem, swatch tròn của `AcmeTheme` thành design token trong `uno.config.ts`. |
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
| H1 | i18n vi-VN, VND, theme nâu/be/kem, logo ANIVELE | `AcmeTheme`, template | Bản dịch `vi-VN`, design token UnoCSS, header/footer. |
| H2 | Trang `/brands`, `/brands/{slug}`, brand tắt → 404 | `AcmeBrand` Storefront controller | Route Store API trong `AcmeBrand` (danh sách brand, brand theo SEO path); component `FrontendBrandPage` trong `app/components/global/` để resolver `[...all].vue` nhận route brand. |
| H3 | Link brand + logo brand ở trang sản phẩm | `AcmeBrand` Twig | Đọc `product.manufacturer` + extension `acmeBrand` từ Store API. |
| H4 | Bộ sưu tập `/collections/*`, `/collections/sale` | `AcmeCatalog` (SEO navigation) | Không cần code mới nếu SEO URL resolve ra category — kiểm chứng. |
| H5 | Card sản phẩm, swatch Màu × Size, buy box | `AcmeTheme` Twig | Override component `product/` của template. |
| H6 | Trang cảm ơn: hướng dẫn chuyển khoản / VietQR | `AcmePayment` Twig | Đọc thông tin từ đơn hàng / Store API; VietQR đang tạm gác. COD chạy qua flow chuẩn. |
| H7 | Canonical, meta, sitemap, robots | Core + `AcmeBrand` | `useCmsHead` + head của trang brand; sitemap/robots phải phục vụ từ domain frontend. |
| H8 | Phí ship theo khu vực, VAT, khuyến mãi brand | `AcmeShipping`, Rule Builder | Không đổi — tính trong cart backend, frontend chỉ hiển thị. |

Xong H1–H8 + e2e COD → đánh giá gỡ storefront Twig (ADR riêng).
