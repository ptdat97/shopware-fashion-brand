# Nền tảng bán lẻ thời trang đa thương hiệu trên Shopware 6.7

Một website, một catalog, một checkout cho **nhiều thương hiệu** thời trang tại thị trường Việt Nam; mỗi thương hiệu có logo, banner, landing page, SEO riêng (`/brands/{slug}`, `/collections/{slug}`). Toàn bộ tài liệu kỹ thuật nằm ở [docs/](docs/); kiến trúc đa thương hiệu ở [docs/12-da-thuong-hieu.md](docs/12-da-thuong-hieu.md).

**Mục tiêu từ 18/10/2026: headless.** Shopware chỉ làm backend (Admin + Store API + plugin nghiệp vụ), giao diện khách hàng là Nuxt 4 trên Shopware Frontends. Quyết định: [ADR-005](docs/decisions/ADR-005-headless-monorepo.md) · hướng dẫn & công nghệ: [docs/15-headless.md](docs/15-headless.md).

## Cấu trúc monorepo

```
backend/    Shopware 6.7 (Composer) — mọi lệnh bin/console, composer, phpunit chạy TRONG thư mục này
frontend/   Nuxt 4 + Shopware Frontends (Vue Starter Template) — pnpm
docs/       tài liệu chung
```

Lệnh JS chạy ở gốc repo: `pnpm install`, `pnpm dev` (http://localhost:3000), `pnpm build`, `pnpm lint`, `pnpm typecheck`, `pnpm generate-types`. Node theo `.nvmrc` (22.x — template yêu cầu `>=22.22.3`).

**CSS frontend: Tailwind CSS v4** (`@tailwindcss/vite`, không còn UnoCSS). Cấu hình và màu thương hiệu ở `frontend/app/assets/css/tailwind.css` (`@theme`); icon `i-carbon-*` qua `frontend/tailwind/carbon-icons.mjs`. Nâng cấp `@shopware/cms-base-layer` → kiểm class mới của layer + so ảnh. Quy ước: [docs/15-headless.md](docs/15-headless.md) mục 6.

Nghiệp vụ mới viết thành **route Store API trong plugin**, frontend chỉ vẽ UI; không đặt logic nghiệp vụ trong Nuxt. Tuỳ biến UI qua override component / Nuxt layer, không patch `node_modules/@shopware/*`. Storefront Twig không còn phục vụ channel nào (channel `anivele` đã xoá); gói `shopware/storefront` còn giữ tới khi xong H1–H8 (docs/15) vì code plugin còn phụ thuộc.

## Bảy quy tắc cứng

**1. Không sửa `backend/vendor/` (và `node_modules/`).** Mọi tuỳ biến backend viết qua Plugin, App hoặc Theme trong `backend/custom/`. `composer update` ghi đè sạch `vendor/`, git không track thư mục này.

**2. Không `composer require` plugin tự viết.** Shopware tự quét `custom/plugins/`. Thêm vào `composer.json` sẽ đặt `managed_by_composer = 1` và **khoá nút Xoá plugin trong Administration**. Chỉ dùng `composer require` cho plugin bên thứ ba hoặc nâng version core.

**3. Đọc kiến trúc Shopware trước khi viết plugin. Không bịa API.** Shopware xoá interface giữa các bản major — `SynchronousPaymentHandlerInterface`, `AsynchronousPaymentHandlerInterface`, `PaymentHandlerInterface` đều **không còn tồn tại** trong 6.7 dù tutorial trên mạng vẫn dạy. Mọi tên class phải xác nhận trong `vendor/` trước khi dùng:

Lệnh kiểm tra (hỏi autoloader, bắt class ma): [docs/08-quy-tac-phat-trien.md](docs/08-quy-tac-phat-trien.md) mục "Kiểm tra tự động".

**4. Backup trước mọi thay đổi dữ liệu.** Xem [docs/runbook/00-backup.md](docs/runbook/00-backup.md).

**5. Brand là `product_manufacturer` — mở rộng, không xây lại.** Không tạo entity brand song song, không dùng category/tag để đánh dấu brand, không tạo sales channel/theme/domain theo brand, không chèn logic brand vào cart/checkout/payment (chính sách theo brand dùng Rule Builder). Phần core thiếu đặt trong plugin `AcmeBrand`: bảng `acme_brand` chỉ cho thứ cần ràng buộc DB (mã UNIQUE, banner/layout khoá ngoại, active/featured/position); meta SEO là custom field của nhà sản xuất. Xem [docs/decisions/ADR-003-mo-hinh-thuong-hieu.md](docs/decisions/ADR-003-mo-hinh-thuong-hieu.md).

**6. Không tạo lại thứ Shopware đã có.** Trước khi thêm bảng/entity/trường/ô nhập Admin/subscriber/lệnh: tìm theo thứ tự trường sẵn có → cấu hình native (Rule Builder, CMS, product stream, snippet) → custom field → điểm mở rộng (EntityIndexer, SeoUrlRoute, decorator) → bảng riêng là cuối cùng, phải ghi lý do. Xem [docs/08-quy-tac-phat-trien.md](docs/08-quy-tac-phat-trien.md) Quy tắc số 4.

**7. Mỗi plugin một nghiệp vụ, độc lập.** Không `use`/inject class của plugin khác; phụ thuộc dữ liệu kiểu "có thì dùng, không có thì bỏ qua"; markup plugin nào thì CSS plugin đó. Tắt một plugin chỉ mất chức năng của chính nó — xem hàng, giỏ hàng, COD vẫn chạy. Bản đồ trách nhiệm 7 plugin và cách kiểm tắt/bật: [docs/08-quy-tac-phat-trien.md](docs/08-quy-tac-phat-trien.md) Quy tắc số 5.

Chi tiết và lý do: [docs/08-quy-tac-phat-trien.md](docs/08-quy-tac-phat-trien.md) · [docs/09-nguyen-tac-ky-thuat.md](docs/09-nguyen-tac-ky-thuat.md)

**Luật chi tiết khi viết plugin:** [docs/13-chuan-phat-trien-plugin.md](docs/13-chuan-phat-trien-plugin.md) — mỗi quy tắc có mã (`ARCH`, `DATA`, `MIG`, `API`, `EXT`, `SF`, `ADM`, `PERF`, `SEC`, `OBS`, `TEST`, `REL`) để trích khi review; mục 15 là Definition of Done, mục 16 là hiện trạng tuân thủ và nợ kỹ thuật.

## Ngôn ngữ

Tài liệu, commit message và comment trong code viết bằng **tiếng Việt**. Tên biến/class/hàm giữ tiếng Anh theo chuẩn Shopware.

## Môi trường

```
APP_URL   http://shopware-fashion-brand.test   (Herd symlink → backend/)
Store API http://shopware-fashion-brand.test/store-api/
Frontend  http://localhost:3000                (pnpm dev)
Database  mysql://root@127.0.0.1:3306/shopware
Shopware  6.7.15.1 · PHP 8.4 · MySQL 8.0.33 · Node 22 · pnpm 12
```

Sales channel **`Headless`** (type API) chạy `vi-VN`, VND — channel **duy nhất** cho mọi thương hiệu, frontend Nuxt dùng access key của nó. Channel Storefront `anivele` đã xoá 18/10/2026 (ADR-005). Domain frontend tạo theo biến `ACME_FRONTEND_URL` trong `backend/.env.local`.

## Cấu hình dữ liệu phải viết thành migration

Thay đổi cấu hình cần tái lập trên staging/production (tiền tệ, thuế, quốc gia, sales channel) **phải** viết thành migration trong plugin, không dùng SQL thủ công. Bài học đã trả giá: Phase 0 làm bằng SQL tay nên không đi theo git, dựng lại môi trường là mất sạch.

Cách làm: [docs/runbook/06-tao-plugin.md](docs/runbook/06-tao-plugin.md)

## Kiểm tra trước khi commit

```bash
git status --short backend/vendor/                                  # phải rỗng
git --no-pager diff backend/composer.json backend/composer.lock     # chỉ đổi khi cài plugin bên thứ ba
pnpm lint && pnpm typecheck                                         # khi sửa frontend/
```

## Tiến độ

- **Phase 0** (nền tảng thị trường VN) ✅ — plugin `AcmeVietnam`
- **Phase 2** (biến thể Màu × Size) ✅ — plugin `AcmeCatalog`, 12 mẫu → 64 biến thể
- **Phase 1** (thanh toán & vận chuyển) ✅ giai đoạn 1 — `AcmePayment`: COD, chuyển khoản, VietQR · `AcmeShipping`: phí ship theo khu vực (tách khỏi `AcmePayment` 14/10/2026)
- **Phase 3** (theme thương hiệu) ✅ giai đoạn 1 — plugin `AcmeTheme`: bảng màu nâu/be/kem, swatch tròn, mobile-first

> 🧭 **COD là phương thức thanh toán ưu tiên** (14/10/2026). VietQR tạm gác — chưa có số tài khoản thật, không chặn go-live; chỉ cấu hình khi bật lại.
>
> ⚠️ **Chưa có file logo thật.** Đang dùng chữ ANIVELE; tải logo tại Administration → Themes → Giao diện Acme → Media.
>
> ⚠️ **Dữ liệu demo không tự nhập khi cài plugin.** Bấm nút tại Administration → Extensions → Acme Demo Data → Configure, hoặc chạy `php bin/console acme:demo:seed`. Chạy lại không nhân bản dữ liệu. Xem [docs/runbook/05-demo-data.md](docs/runbook/05-demo-data.md).

**Sửa giao diện thì phải chụp ảnh kiểm chứng** (storefront Twig hoặc frontend Nuxt), không tin `curl`/`grep` — xem [docs/02-theme.md](docs/02-theme.md).

- **Phase MB** (đa thương hiệu) 🔄 — MB0 ✅: ADR-003 đã duyệt, DB local dựng lại, kinh doanh đã chốt — Anivele là *một* brand, brand tắt chỉ ẩn trang brand (sản phẩm vẫn bán), SKU mã brand 3 ký tự (`ANV-1001-01`), dev dùng brand demo giả định. Tên nền tảng chưa chốt

- **MB1** ✅ — plugin `AcmeBrand`: bảng mở rộng `product_manufacturer`, mã brand, thẻ "Thương hiệu" trong Administration, ACL. Test: `cd backend && vendor/bin/phpunit -c custom/plugins/AcmeBrand` (DB riêng `shopware_test`)

- **MB2** ✅ — 3 brand demo (`ANV` Anivele, `MAY`/`LLN` giả định), SKU mang mã brand, bộ lọc "Thương hiệu" ở listing (native, chỉ đổi nhãn). Id demo suy ra từ khoá `ANV-10xx` cố định, **không** từ SKU — xem [docs/runbook/05-demo-data.md](docs/runbook/05-demo-data.md)

- **MB3** ✅ — `/brands`, `/brands/{slug}` (listing core trên category gốc + lọc manufacturer, lọc/sắp xếp AJAX), SEO URL + canonical + sitemap, brand tắt → 404, link brand ở trang sản phẩm. 23 test đạt

> 🔴 **`cache:clear` không xoá HTTP cache** (lệnh chạy trong `backend/`). Đổi template/code → thêm `bin/console cache:clear:http`. Đổi template SEO brand → `bin/console dal:refresh:index --only=acme_brand.indexer`.

- **MB4** ✅ — bộ sưu tập `/collections/{tên}` = category + product stream của core; `AcmeCatalog` thêm ô "Là bộ sưu tập" + template SEO navigation. Sau khi cập nhật `AcmeCatalog`: `bin/console dal:refresh:index`

- **Phase 4 — SEO kỹ thuật** ✅ (14/10) — canonical biến thể native (sitemap 64 → 12 URL sản phẩm), meta description danh mục/bộ sưu tập/sản phẩm, URL chữ thường (`/dam/`, `/ao-polo-…/may-1002-01`), trang tổng `/collections/` (thay `/bo-suu-tap/`, 301). Còn: meta trang chủ chờ tên nền tảng, robots.txt 200 trên server thật
- **Dọn MB** ✅ (14/10) — nhãn Admin "Thương hiệu" qua file snippet native, menu không tô "Trang chủ" ở trang brand, bộ sưu tập `/collections/sale` (stream % giảm giá native)
- **MB5** ✅ — đơn COD thật 2 brand (VAT, phí ship, tồn kho, huỷ hoàn kho đúng), khuyến mãi theo brand bằng Rule Builder, `manufacturerId` trong payload dòng đơn. Không code — chỉ test. Mọi brand dùng chung **một kho** → một kiện/đơn, một bảng phí ship

- **Phase H — headless** 🔄 (18/10) — monorepo `backend/` + `frontend/` ✅, channel `Headless` thành channel bán hàng (migration AcmeVietnam/AcmePayment 1.2.0) ✅, frontend chạy trên dữ liệu thật: trang chủ, danh mục, sản phẩm tiếng Việt ✅, H1 ✅ (i18n, bảng màu, font, logo chữ — 21/10), tìm kiếm không dấu ✅ (`AcmeVietnam` 1.3.0 — sau khi cập nhật: `dal:refresh:index --only=product.indexer`), H2 trang thương hiệu ✅ (Store API `AcmeBrand` 1.3.0 — sau khi cập nhật: `dal:refresh:index --only=acme_brand.indexer`), H3 link brand ở trang sản phẩm ✅, H4 bộ sưu tập + URL chữ thường + 301 ✅ (`AcmeCatalog` 1.6.0 — sau khi cập nhật: `dal:refresh:index`). H7 SEO ✅ (canonical, tiêu đề, sitemap/robots ở domain frontend — trừ meta trang chủ chờ tên nền tảng). H6 thanh toán + trang cảm ơn ✅ (đơn COD thật qua frontend; `AcmeVietnam` 1.4.0 — SĐT bắt buộc, trạng thái đơn tiếng Việt). Còn H5, H8 (một phần) (vi-VN, theme, trang brand qua Store API, swatch, trang cảm ơn, SEO) — [docs/15-headless.md](docs/15-headless.md) mục 4

Tiếp theo: **Phase H** (H1 → H8). Song song: **MB6 — sẵn sàng mở rộng** (ERP/PIM qua Admin API/App, đa kho, bảng size theo brand × danh mục) — phần lớn là thiết kế, chờ nhu cầu thật. Báo cáo khảo sát ✅ [docs/14-mb6-khao-sat.md](docs/14-mb6-khao-sat.md) (14/10), chờ quyết định ở mục 5. Phase 4 (marketing & vận hành) làm sau. Xem [docs/01-roadmap.md](docs/01-roadmap.md).
