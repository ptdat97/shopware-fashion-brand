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
| G11 | Bộ sưu tập `/collections/...` | ✅ Category + product stream | **Chỉ cấu hình**, không code — cần kiểm chứng SEO template |

`slug` **không** lưu thành cột riêng: slug chính là bản ghi `seo_url` mà route G3 sinh ra — sửa được trong Administration theo từng ngôn ngữ, không trùng lặp dữ liệu. Xem ADR-003.

---

## 5. Kiến trúc đề xuất

Quyết định và các phương án bị loại: [decisions/ADR-003-mo-hinh-thuong-hieu.md](decisions/ADR-003-mo-hinh-thuong-hieu.md).

### 5.1 Brand = `product_manufacturer` + phần mở rộng

```
product_manufacturer (core)            acme_brand (plugin)                 acme_brand_translation (plugin)
├─ id  ────────────────────────────┐   ├─ product_manufacturer_id  PK/FK ┐ ├─ acme_brand_id      FK
├─ name          (dịch)            └──►├─ active          bool          │ ├─ language_id        FK
├─ description   (dịch, HTML)          ├─ banner_media_id  FK media       │ ├─ meta_title
├─ link                                ├─ cms_page_id      FK cms_page    │ ├─ meta_description
├─ media_id      (logo)                ├─ position         int            │ └─ keywords
└─ customFields                        ├─ featured         bool           │
                                       └─ created_at / updated_at         │
product.manufacturer_id ───► brand     seo_url  (route brand) ◄───────────┘  ← slug
```

- **Không tạo entity brand thứ hai.** Tên, mô tả, logo giữ ở manufacturer — nơi Administration, Import/Export, bộ lọc, tìm kiếm, rule khuyến mãi đều đã dùng.
- Bảng `acme_brand` quan hệ **1–1**, xoá manufacturer thì xoá theo (`ON DELETE CASCADE`). Gắn vào manufacturer qua `EntityExtension` → đọc được bằng `manufacturer.extensions.acmeBrand` ở mọi API.

### 5.2 URL & điều hướng

| URL | Cơ chế | Native? |
|---|---|---|
| `/brands` | Route `frontend.acme.brand.list` (plugin) | ❌ |
| `/brands/{slug}` | Route `frontend.acme.brand.page` + SEO URL route | ❌ |
| `/collections/new-arrivals` | Category trong cây "Bộ sưu tập" + product stream | ✅ |
| Lọc brand trong danh mục | `?manufacturer=<id>` | ✅ |

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
| Đa kho | Decorate `AbstractStockStorage` trong plugin, hoặc Shopware Commercial `[CHƯA KIỂM CHỨNG giấy phép]` | Chỉ làm khi có nhu cầu thật |

Đúng với khuyến nghị ở [03-plugin-app.md](03-plugin-app.md) Nhóm 5: Plugin cho tuỳ biến sâu, App cho tích hợp bên ngoài.

---

## 6. Nguyên tắc riêng cho đa thương hiệu

Bổ sung cho [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) và [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md):

1. **Brand là `product_manufacturer`.** Không tạo entity brand song song, không dùng category hay tag để đánh dấu brand.
2. **Một sales channel cho mọi brand.** Không tạo channel / theme / domain theo brand.
3. **Commerce không biết brand.** Không chèn logic brand vào cart, checkout, payment handler. Chính sách theo brand (khuyến mãi, phí ship) viết bằng **Rule Builder** (`LineItemOfManufacturerRule`).
4. **Không trùng lặp dữ liệu.** Tên/mô tả/logo ở manufacturer; slug ở `seo_url`; plugin chỉ lưu thứ core thật sự không có.
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

> 🔴 **Rủi ro kỹ thuật cần spike trước MB3:** `AbstractProductListingRoute::load()` bắt buộc `$categoryId`. Trang brand không phải category nên phải chứng minh được cách lấy listing có **phân trang + sắp xếp + bộ lọc native** mà vẫn lọc theo manufacturer (qua `ProductListingCriteriaEvent` hay gọi listing với category gốc + filter). Làm PoC nhỏ, ghi kết quả vào ADR-003 trước khi viết route.

---

## 8. Câu hỏi mở — quyết định kinh doanh

Đã chốt 12/10/2026 (câu 1–4):

| # | Câu hỏi | Quyết định | Ảnh hưởng |
|---|---|---|---|
| 1 | Anivele trong mô hình mới? | ✅ **Anivele là một brand** trong danh sách. Tên nền tảng/tên miền **chưa chốt** | Logo header sẽ đổi sang tên nền tảng khi có; storefront `anivele` đổi tên theo |
| 2 | Dữ liệu brand cho phát triển | ✅ **Brand demo giả định** — `AcmeDemoData` sinh Anivele + 2 brand giả, chia 12 mẫu | Thay bằng brand thật trước go-live |
| 3 | Brand ngừng hoạt động thì sản phẩm? | ✅ **Ẩn trang brand, vẫn bán sản phẩm** — `/brands/{slug}` trả 404, brand rời `/brands` và sitemap; sản phẩm vẫn ở danh mục, tìm kiếm, mua được | `active` chỉ điều khiển storefront brand, **không** đụng visibility sản phẩm hay cart |
| 4 | Quy tắc SKU | ✅ **Mã brand 3 ký tự**: `NKE-1001-01` (brand – mẫu – biến thể). `ANV` = Anivele, hàng `ANV-*` giữ nguyên | Mã brand lưu ở đâu: quyết định trong MB1 |

Còn mở:

| # | Câu hỏi | Chặn giai đoạn |
|---|---|---|
| 1b | **Tên nền tảng / tên miền** | Không chặn kỹ thuật — chỉ nội dung, logo header |
| 5 | Có brand nào **giao từ kho riêng** (ảnh hưởng phí ship, tách kiện)? | MB5 |
| 6 | Bảng size theo brand × danh mục: brand cung cấp số đo hay tự đo? | sau MB3 |

---

## Liên quan

- Quyết định kiến trúc: [decisions/ADR-003-mo-hinh-thuong-hieu.md](decisions/ADR-003-mo-hinh-thuong-hieu.md)
- Catalog & SKU: [05-catalog-fashion.md](05-catalog-fashion.md)
- Theme: [02-theme.md](02-theme.md)
- Tạo plugin mới: [runbook/06-tao-plugin.md](runbook/06-tao-plugin.md)
