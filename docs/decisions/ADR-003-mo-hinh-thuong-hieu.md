# ADR-003: Mô hình dữ liệu thương hiệu cho nền tảng đa thương hiệu

**Trạng thái:** ✅ **ĐÃ DUYỆT** — 12/10/2026 (3 điểm ở mục *Cần kiểm chứng* vẫn phải làm trong MB3/MB4)
**Quyết định:** Phương án A — `product_manufacturer` làm thực thể brand, plugin `AcmeBrand` mở rộng bằng bảng riêng

## Bối cảnh

Dự án chuyển sang nền tảng bán lẻ **nhiều thương hiệu trên một website** ([12-da-thuong-hieu.md](../12-da-thuong-hieu.md)). Mỗi brand cần: logo, banner, mô tả, slug, SEO, trạng thái hoạt động, landing page và danh sách sản phẩm riêng. Catalog, giỏ hàng, checkout, đơn hàng phải dùng chung.

Ràng buộc: chỉ mở rộng qua Plugin/App, ưu tiên native, không trùng lặp dữ liệu ([08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md)).

Core 6.7.12.2 đã có `product_manufacturer` với `name`, `description`, `link`, `media`, `customFields` (đều dịch được), được dùng sẵn bởi form sản phẩm, bộ lọc listing, tìm kiếm, CMS element `manufacturer-logo`, rule `LineItemOfManufacturerRule`, và payload đơn hàng. Core **không** có: trạng thái active, banner, layout, SEO meta, route storefront cho manufacturer.

## Các lựa chọn

### A. Manufacturer + bảng mở rộng của plugin (đã chọn)

`product_manufacturer` là brand. Plugin `AcmeBrand` thêm bảng `acme_brand` (1–1, FK cascade) và `acme_brand_translation`, nối bằng `EntityExtension`.

| Ưu | Nhược |
|---|---|
| Mọi tính năng core dùng manufacturer tự động "hiểu" brand | Phải viết Administration cho phần mở rộng |
| FK thật tới `media`, `cms_page` — xoá media không để lại id mồ côi | Thêm 2 bảng |
| `active`, `position` là cột có index, lọc/sắp xếp nhanh | |
| Không đụng core, gỡ plugin là mất phần mở rộng, dữ liệu manufacturer vẫn còn | |

### B. Manufacturer + custom fields

Lưu active/banner/layout/meta vào `customFields` của manufacturer.

| Ưu | Nhược |
|---|---|
| Không cần bảng, Administration tự render form | Không có FK — xoá media/layout để lại id chết |
| | Lọc `active` qua JSON, khó index |
| | Custom field sửa được trong Admin bởi bất kỳ ai có quyền custom field → dễ hỏng cấu trúc |

Hợp lý cho thuộc tính **phụ, không ràng buộc** (vd. màu nhấn của brand). Không hợp cho active/banner/layout.

### C. Entity brand riêng (`acme_brand` độc lập)

| Nhược quyết định |
|---|
| Trùng lặp với manufacturer: tên, logo, mô tả tồn tại ở hai nơi |
| Bộ lọc listing, tìm kiếm, rule khuyến mãi, payload đơn hàng của core **không biết** entity mới → phải viết lại tất cả |

**Loại.** Vi phạm nguyên tắc không xây lại chức năng core đã có.

### D. Brand = category

Cây "Thương hiệu" → mỗi brand một category.

| Nhược quyết định |
|---|
| Sản phẩm phải gán thủ công vào category brand **và** category loại hàng — hai nguồn sự thật |
| Không dùng được rule khuyến mãi theo brand, không có brand trong payload đơn |

**Loại** làm thực thể brand. (Category vẫn dùng cho **bộ sưu tập** — đó đúng là việc của nó.)

### E. Sales channel theo brand

**Loại.** Mâu thuẫn trực tiếp với yêu cầu một website / một giỏ hàng; khách không thể mua nhiều brand trong một đơn.

## Chi tiết quyết định (phương án A)

- **Slug không lưu cột riêng.** Route `frontend.acme.brand.page` đăng ký `SeoUrlRouteInterface` (tag `shopware.seo_url.route`), template mặc định `brands/{{ manufacturer.translated.name }}`. Bản ghi `seo_url` *chính là* slug — sửa tay được trong Administration theo từng ngôn ngữ, Shopware tự giữ redirect URL cũ.
- **Tên, mô tả, logo** giữ ở manufacturer.
- **Brand chưa có bản ghi `acme_brand`** được coi là *không hoạt động* — brand chỉ lên storefront khi được bật chủ động.
- **Admin:** thêm phần "Thương hiệu" vào trang chi tiết nhà sản xuất có sẵn (override component `sw-manufacturer-detail`), không làm module riêng. Đổi nhãn "Nhà sản xuất" → "Thương hiệu" bằng snippet.
- **ACL:** quyền đọc/ghi `acme_brand` gắn theo quyền `product_manufacturer` hiện có.

## Cần kiểm chứng trước khi chốt

| # | Điểm | Cách kiểm |
|---|---|---|
| 1 | Listing có phân trang/sắp xếp/bộ lọc native trên trang **không phải category** (`AbstractProductListingRoute::load()` bắt buộc `$categoryId`) | PoC trong MB3 |
| 2 | Override `sw-manufacturer-detail` an toàn qua bản vá 6.7.x | Đọc component gốc, viết Jest test |
| 3 | SEO URL template cho category tạo được `/collections/new-arrivals` | Cấu hình thử trong MB4 |

Kết quả ghi bổ sung vào ADR này. Nếu PoC #1 thất bại, phải mở lại ADR trước khi viết route.

## Hệ quả

- Không đổi `composer.json`, không sửa `vendor/`.
- Gỡ `AcmeBrand` → mất trang brand và thuộc tính mở rộng; sản phẩm, đơn hàng, manufacturer **không bị ảnh hưởng**.
- Import/Export manufacturer của core không mang theo phần mở rộng — nếu cần nhập hàng loạt banner/active thì bổ sung profile riêng.
