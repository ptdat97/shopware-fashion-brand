# Nền tảng bán lẻ thời trang đa thương hiệu

> **Định hướng mới từ 12/10/2026.** Dự án chuyển từ *shop của một thương hiệu* sang *nền tảng bán lẻ nhiều thương hiệu* — theo mô hình các nhà bán lẻ đa thương hiệu như ACFC, Tam Sơn, FORMAT, Front Row.
>
> Tài liệu này là **báo cáo khảo sát + kiến trúc đề xuất**, chưa có dòng code nào được viết. Quyết định kiến trúc chính (✅ đã duyệt 12/10/2026): [decisions/ADR-003-mo-hinh-thuong-hieu.md](decisions/ADR-003-mo-hinh-thuong-hieu.md). Lộ trình: [01-roadmap.md](01-roadmap.md) mục *Phase MB*.

---

## 1. Mục tiêu kinh doanh

Một công ty bán nhiều thương hiệu thời trang trên:

| Dùng chung | Riêng từng thương hiệu |
|---|---|
| Một website, một tên miền, **một sales channel** | Logo, banner |
| Một catalog | Landing page, nội dung giới thiệu |
| Một giỏ hàng, checkout, thanh toán, vận chuyển | Danh sách sản phẩm của thương hiệu |
| Một hệ thống quản lý đơn, khuyến mãi, khách hàng | SEO (URL, meta) |

URL mục tiêu: `/brands/nike`, `/brands/adidas`, `/collections/new-arrivals`.

**Một đơn hàng được chứa sản phẩm của nhiều thương hiệu** — đây là lợi thế chính của mô hình so với mỗi brand một website.

> Mô hình ACFC / Tam Sơn / FORMAT / Front Row dùng làm **tham chiếu mô hình kinh doanh**. Chưa khảo sát chi tiết cấu trúc website của từng bên `[CHƯA KIỂM CHỨNG]`.

---

## 2. Hiện trạng — những gì đang gắn chặt với "một thương hiệu"

Dự án hiện được dựng cho **một brand duy nhất (Anivele)**. Các điểm sau phải xem lại khi chuyển sang đa thương hiệu:

| Chỗ | Hiện trạng | Ảnh hưởng |
|---|---|---|
| Theme `AcmeTheme` | Bảng màu, logo chữ, typography mang bản sắc **một** brand | Theme trở thành giao diện **của nền tảng**; bản sắc từng brand chuyển vào landing page (CMS) — xem mục 5.4 |
| Logo header | Chữ lấy từ `core.basicInformation.shopName` | Logo header là logo **nhà bán lẻ**, logo brand chỉ xuất hiện ở trang brand / trang sản phẩm |
| Mã SKU | `ANV-1001-01` — tiền tố của một brand | Cần quy tắc SKU có **mã brand** — câu hỏi mở mục 8 |
| Bảng size | Gắn theo **danh mục** (`acme_size_chart_*` trên `category`) | Số đo khác nhau **giữa các brand** cùng danh mục — [05-catalog-fashion.md](05-catalog-fashion.md) đã nêu "cùng size M nhưng số đo mỗi brand mỗi khác". Bảng size phải theo **brand × danh mục** |
| Nhà sản xuất | DB chỉ có 1 bản ghi `shopware AG` (mặc định cài đặt), demo data không gán brand | Toàn bộ sản phẩm chưa có thương hiệu |
| Sales channel | Một storefront `anivele` | **Giữ nguyên một channel** — đúng với mô hình |

> ✅ DB local đã dựng lại khớp [00-hien-trang.md](00-hien-trang.md) (12/10/2026, MB0) chỉ bằng `plugin:install` + lệnh seed — đúng tinh thần "cấu hình phải viết thành migration".

---

## 3. Chức năng native tái sử dụng được

Đã kiểm chứng trong `vendor/shopware/` bản 6.7.12.2.

| Nhu cầu | Native | Nguồn |
|---|---|---|
| **Thực thể thương hiệu** | `product_manufacturer`: `name`, `description` (HTML), `link`, `media` (logo), `customFields` — đều **dịch được** | `core/Content/Product/Aggregate/ProductManufacturer/ProductManufacturerDefinition.php` |
| Gán brand cho sản phẩm | `product.manufacturerId`, có sẵn trong form sản phẩm của Administration, biến thể **kế thừa** từ sản phẩm cha | như trên (`ReverseInherited('manufacturer')`) |
| Lọc theo brand ở listing | Bộ lọc `manufacturer-filter`, query `?manufacturer=<id>` | `core/Content/Product/SalesChannel/Listing/Filter/ManufacturerListingFilterHandler.php` |
| Logo brand ở trang sản phẩm | CMS element `manufacturer-logo` | `core/Content/Product/Cms/ManufacturerLogoCmsElementResolver.php` |
| Tìm kiếm theo tên brand | `name` có `SearchRanking::HIGH_SEARCH_RANKING` | `ProductManufacturerDefinition` |
| Nhóm sản phẩm động theo brand | Product stream có điều kiện `manufacturer` | `administration/.../product-stream-condition.service.js` |
| **Bộ sưu tập** (`/collections/...`) | Category (kiểu page) gán **product stream** + CMS layout + SEO URL | Category / ProductStream / Seo |
| Landing page tự do | Entity `landing_page`: `url`, `cmsPage`, `metaTitle`, `metaDescription`, `keywords`, `active`, sales channel | `core/Content/LandingPage/LandingPageDefinition.php` |
| Khuyến mãi theo brand | Rule `LineItemOfManufacturerRule` | `core/Checkout/Cart/Rule/LineItemOfManufacturerRule.php` |
| Brand lưu trong đơn hàng | Payload line item có `manufacturerId` → đi vào `order_line_item.payload` | `core/Content/Product/Cart/ProductCartProcessor.php:402` |
| Import/Export hàng loạt | Profile `product`, `product_manufacturer` (có sẵn từ Phase 2) | Core |
| URL thân thiện cho route mới | `SeoUrlRouteInterface` (`getConfig`, `prepareCriteria`, `getMapping`), đăng ký bằng tag `shopware.seo_url.route` | `core/Content/Seo/SeoUrlRoute/`, `storefront/DependencyInjection/seo.php` |
| Sitemap cho trang mới | `AbstractUrlProvider` | `core/Content/Sitemap/Provider/AbstractUrlProvider.php` |
| Mở rộng thực thể không sửa core | `EntityExtension` + bảng riêng của plugin | `core/Framework/DataAbstractionLayer/EntityExtension.php` |
| Đa kho / tồn kho ngoài | `AbstractStockStorage` (decorate được) | `core/Content/Product/Stock/AbstractStockStorage.php` |
| Tích hợp ERP/PIM | Admin API, Sync API, Integration (API key), webhook/App | Core |

**Kết luận:** giỏ hàng, checkout, thanh toán, khuyến mãi, đơn hàng **không cần sửa gì** — chúng vốn không phân biệt brand. Bản thân "brand" cũng đã có (`product_manufacturer`). Phần thiếu tập trung ở **storefront của brand** và **vài thuộc tính quản trị**.

---

## 4. Khoảng trống chức năng

| # | Thiếu | Native có không | Cách lấp |
|---|---|---|---|
| G1 | Trang danh sách brand `/brands` | ❌ Storefront không có route manufacturer | Route mới trong plugin |
| G2 | Landing page brand `/brands/{slug}` có sản phẩm của brand | ❌ | Route mới + CMS layout + listing lọc theo manufacturer |
| G3 | SEO URL cho brand | ❌ Chỉ có product / navigation / landing page | `SeoUrlRouteInterface` mới, template `brands/{{ ... }}` |
| G4 | Trạng thái hoạt động của brand | ❌ Manufacturer không có `active` | Bảng mở rộng của plugin |
| G5 | Banner brand (khác logo) | ❌ Chỉ có một `media` | Bảng mở rộng — FK tới `media` |
| G6 | Layout landing riêng từng brand | ❌ | Bảng mở rộng — FK tới `cms_page` |
| G7 | Meta title / description riêng cho brand | ❌ | Bảng dịch của plugin |
| G8 | Thứ tự / brand nổi bật | ❌ | Bảng mở rộng |
| G9 | Sitemap có URL brand | ❌ | `AbstractUrlProvider` mới |
| G10 | Bảng size theo brand × danh mục | ❌ (hiện theo danh mục) | Thiết kế lại ở `AcmeTheme` — giai đoạn sau |
| G11 | Bộ sưu tập `/collections/...` | ✅ Category + product stream | ✅ MB4 — ô "Là bộ sưu tập" + template SEO navigation (migration `AcmeCatalog`) |

`slug` **không** lưu thành cột riêng: slug chính là bản ghi `seo_url` mà route G3 sinh ra — sửa được trong Administration theo từng ngôn ngữ, không trùng lặp dữ liệu. Xem ADR-003.

---

## 5. Kiến trúc đề xuất

Quyết định và các phương án bị loại: [decisions/ADR-003-mo-hinh-thuong-hieu.md](decisions/ADR-003-mo-hinh-thuong-hieu.md).

### 5.1 Brand = `product_manufacturer` + phần mở rộng

```
product_manufacturer (core)                          acme_brand (plugin) — chỉ thứ core không có
├─ id  ──────────────────────────────────────────┐   ├─ product_manufacturer_id  PK/FK
├─ name          (dịch)                          └──►├─ code            char(3) UNIQUE   ← tiền tố SKU
├─ description   (dịch, HTML)                        ├─ active          bool
├─ link                                              ├─ featured        bool
├─ media_id      (logo)                              ├─ position        int
└─ customFields  (dịch)                              ├─ banner_media_id FK media     (SET NULL)
     ├─ acme_brand_meta_title        ← meta SEO      └─ cms_page_id     FK cms_page  (SET NULL)
     ├─ acme_brand_meta_description     (custom field
     └─ acme_brand_keywords              native)

product.manufacturer_id ───► brand          seo_url (route frontend.acme.brand.page) ← slug
```

> Từ 14/10/2026 (AcmeBrand 1.2.0) meta SEO là **custom field dịch được của nhà sản xuất** — không còn bảng dịch riêng `acme_brand_translation`. Lý do: Shopware đã có cơ chế dịch và ô nhập cho custom field; tạo bảng dịch riêng là làm lại thứ đã có — [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 4.

- **Không tạo entity brand thứ hai.** Tên, mô tả, logo giữ ở manufacturer — nơi Administration, Import/Export, bộ lọc, tìm kiếm, rule khuyến mãi đều đã dùng.
- Bảng `acme_brand` quan hệ **1–1**, xoá manufacturer thì xoá theo (`ON DELETE CASCADE`). Gắn vào manufacturer qua `EntityExtension` → đọc được bằng `manufacturer.extensions.acmeBrand` ở mọi API.

### 5.2 URL & điều hướng

| URL | Cơ chế | Native? |
|---|---|---|
| `/brands` | Headless: trang Nuxt `pages/brands/index.vue` + Store API `store-api.acme.brand.list` · Twig: `frontend.acme.brand.list` | ❌ |
| `/brands/{slug}` | Headless: SEO URL route `store-api.acme.brand.detail` (template `is_headless`) → component `StoreApiAcmeBrandDetail` · Twig: `frontend.acme.brand.page` | ❌ |
| `/collections/new-arrivals` | Category trong cây "Bộ sưu tập" + product stream | ✅ |
| Lọc brand trong danh mục | `?manufacturer=<id>` | ✅ |

### 5.2.1 Store API thương hiệu (H2, 21/10/2026) — hợp đồng HTTP (API-04)

Ba route trong `AcmeBrand` (`src/Core/Content/Brand/SalesChannel/`), theo mẫu `Abstract*Route` decorate được (API-02). Frontend headless và trang Twig dùng chung — `BrandPageLoader` chỉ gọi lại các route này, quy tắc brand nằm một chỗ (`ActiveBrandLoader`). Kiểu TypeScript phía frontend: `frontend/api-types/storeApiTypes.overrides.ts`.

| Route | Đường dẫn (GET \| POST) | Trả về | Lỗi |
|---|---|---|---|
| `store-api.acme.brand.list` | `/store-api/acme/brand` | `{ elements: ProductManufacturer[], total }` — brand đang bật, kèm `media` và `extensions.acmeBrand`; thứ tự: nổi bật → vị trí → tên | — |
| `store-api.acme.brand.detail` | `/store-api/acme/brand/{manufacturerId}` | `acme_brand_detail`: `manufacturer` (+ `acmeBrand.bannerMedia`), `cmsPage` \| null, `metaTitle`, `metaDescription`, `metaKeywords` (đã áp dự phòng: custom field → tên / mô tả) | 404 `ACME_BRAND__NOT_FOUND` — brand tắt, chưa có dữ liệu brand, id sai định dạng |
| `store-api.acme.brand.listing` | `/store-api/acme/brand/{manufacturerId}/listing` | `product_listing` — **cùng tham số và định dạng** `/store-api/product-listing` (`p`, `limit`, `order`, `properties`, `min-price`…), không có bộ lọc theo brand | 404 như trên |

SEO URL headless: `BrandStoreApiUrlRoute` (tag `shopware.entity.seo_url.route`) + template `seo_url_template.is_headless = 1` cùng mẫu `brands/{{ manufacturer.translated.name|lower }}`. Core chỉ sinh URL này cho channel type API có domain **external storefront**; `seo_url.pathInfo` trỏ về route chi tiết. `BrandIndexer` cập nhật cả URL storefront lẫn headless.

### 5.3 Một sales channel

**Không tạo sales channel cho từng brand.** Mọi brand dùng chung storefront `anivele` (Anivele là một brand; tên nền tảng chưa chốt — mục 8). Sales channel riêng chỉ hợp lý khi brand có **tên miền riêng** — điều mô hình này cố ý không làm.

### 5.4 Theme

`AcmeTheme` thành giao diện **của nhà bán lẻ**: trung tính, để logo và ảnh của từng brand nổi lên. Bản sắc brand thể hiện qua **CMS layout của landing page brand** (banner, màu nền khối, ảnh lookbook) — người vận hành tự dựng trong Shopping Experiences, không cần dev.

> Không làm theme con cho từng brand: Shopware gán theme theo **sales channel**, mà mọi brand chung một channel.

### 5.5 Plugin hay App

| Phần | Chọn | Lý do |
|---|---|---|
| Brand master data, storefront brand, SEO, sitemap | **Plugin `AcmeBrand`** | Cần route storefront PHP, `EntityExtension`, `SeoUrlRouteInterface`, subscriber listing — App không làm được hoặc làm rất hạn chế |
| Đồng bộ ERP / PIM | **Admin API / Sync API**, hoặc **App** nếu cần server trung gian | Hệ ngoài nói chuyện qua API — không cần code trong Shopware |
| Đa kho | Decorate `AbstractStockStorage` trong plugin, hoặc Shopware Commercial `[CHƯA KIỂM CHỨNG giấy phép]` | **Không cần** — mọi brand chung một kho (câu hỏi 5). Chỉ xem lại nếu mở kho thứ hai |

Đúng với khuyến nghị ở [03-plugin-app.md](03-plugin-app.md) Nhóm 5: Plugin cho tuỳ biến sâu, App cho tích hợp bên ngoài.

---

## 6. Nguyên tắc riêng cho đa thương hiệu

Bổ sung cho [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) và [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md):

1. **Brand là `product_manufacturer`.** Không tạo entity brand song song, không dùng category hay tag để đánh dấu brand.
2. **Một sales channel cho mọi brand.** Không tạo channel / theme / domain theo brand.
3. **Commerce không biết brand.** Không chèn logic brand vào cart, checkout, payment handler. Chính sách theo brand (khuyến mãi, phí ship) viết bằng **Rule Builder** (`LineItemOfManufacturerRule`).
4. **Không trùng lặp dữ liệu, không làm lại thứ core có.** Tên/mô tả/logo ở manufacturer; slug ở `seo_url`; meta SEO là custom field của manufacturer; SEO URL sinh qua `EntityIndexer`. Bảng riêng chỉ cho thứ cần ràng buộc DB (mã UNIQUE, khoá ngoại) — [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 4.
5. **Bộ sưu tập là category.** Chỉ viết code khi category + product stream không đáp ứng được.
6. **Không đánh dấu ✅ khi chưa kiểm thử** — automated test + chụp ảnh storefront ([02-theme.md](02-theme.md)).

---

## 7. Kế hoạch triển khai

Chi tiết từng giai đoạn và tiêu chí nghiệm thu: [01-roadmap.md](01-roadmap.md) — *Phase MB*.

| Giai đoạn | Nội dung | Code? |
|---|---|---|
| **MB0** 🔄 | ✅ Duyệt ADR-003, ✅ dựng lại DB local; ⏳ câu hỏi mở | Không |
| **MB1** | Plugin `AcmeBrand`: bảng mở rộng, migration, Administration, ACL | Có |
| **MB2** | Gán brand cho sản phẩm (dữ liệu), bật bộ lọc brand ở listing | Chủ yếu cấu hình |
| **MB3** | Storefront brand: `/brands`, `/brands/{slug}`, SEO URL, sitemap | Có |
| **MB4** | Bộ sưu tập `/collections/...` | Cấu hình |
| **MB5** | Nghiệm thu commerce hợp nhất: đơn nhiều brand, khuyến mãi theo brand | Test |
| **MB6** | Sẵn sàng mở rộng: ERP/PIM, đa kho | Thiết kế |

> ✅ **Đã giải quyết ở MB3** — dùng category gốc điều hướng + bộ lọc manufacturer, xem ADR-003 mục *Kết quả spike #1*.
>
> ~~Rủi ro kỹ thuật cần spike trước MB3:~~ `AbstractProductListingRoute::load()` bắt buộc `$categoryId`. Trang brand không phải category nên phải chứng minh được cách lấy listing có **phân trang + sắp xếp + bộ lọc native** mà vẫn lọc theo manufacturer (qua `ProductListingCriteriaEvent` hay gọi listing với category gốc + filter). Làm PoC nhỏ, ghi kết quả vào ADR-003 trước khi viết route.

---

## 8. Câu hỏi mở — quyết định kinh doanh

Đã chốt 12/10/2026 (câu 1–4):

| # | Câu hỏi | Quyết định | Ảnh hưởng |
|---|---|---|---|
| 1 | Anivele trong mô hình mới? | ✅ **Anivele là một brand** trong danh sách. Tên nền tảng/tên miền **chưa chốt** | Logo header sẽ đổi sang tên nền tảng khi có; storefront `anivele` đổi tên theo |
| 2 | Dữ liệu brand cho phát triển | ✅ **Brand demo giả định** — `AcmeDemoData` sinh Anivele + 2 brand giả, chia 12 mẫu | Thay bằng brand thật trước go-live |
| 3 | Brand ngừng hoạt động thì sản phẩm? | ✅ **Ẩn trang brand, vẫn bán sản phẩm** — `/brands/{slug}` trả 404, brand rời `/brands` và sitemap; sản phẩm vẫn ở danh mục, tìm kiếm, mua được | `active` chỉ điều khiển storefront brand, **không** đụng visibility sản phẩm hay cart |
| 4 | Quy tắc SKU | ✅ **Mã brand 3 ký tự**: `NKE-1001-01` (brand – mẫu – biến thể). `ANV` = Anivele, hàng `ANV-*` giữ nguyên | Mã brand lưu ở đâu: quyết định trong MB1 |
| 5 | Brand giao từ kho riêng? | ✅ **Mọi brand dùng chung một kho** (chốt 14/10/2026) | Một kiện cho mỗi đơn, một bảng phí ship chung — không tách kiện theo brand, không cần đa kho |

Còn mở:

| # | Câu hỏi | Chặn giai đoạn |
|---|---|---|
| 1b | **Tên nền tảng / tên miền** | Không chặn kỹ thuật — chỉ nội dung, logo header |
| 6 | Bảng size theo brand × danh mục: brand cung cấp số đo hay tự đo? | sau MB3 |

---

## Liên quan

- Quyết định kiến trúc: [decisions/ADR-003-mo-hinh-thuong-hieu.md](decisions/ADR-003-mo-hinh-thuong-hieu.md)
- Catalog & SKU: [05-catalog-fashion.md](05-catalog-fashion.md)
- Theme: [02-theme.md](02-theme.md)
- Tạo plugin mới: [runbook/06-tao-plugin.md](runbook/06-tao-plugin.md)
