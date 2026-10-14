# MB6 — Khảo sát sẵn sàng mở rộng

**Ngày:** 14/10/2026 · **Loại:** báo cáo khảo sát — chưa code, chờ quyết định ở [mục 5](#5-cần-chủ-dự-án-quyết)
**Phạm vi:** đồng bộ ERP/PIM, đa kho, bảng size theo brand × danh mục ([01-roadmap.md](01-roadmap.md) · MB6)

Mọi tên class, route, header dưới đây đã đối chiếu trong `vendor/shopware/core` bản 6.7.15.1. Chỗ nào chưa kiểm chứng được ghi rõ **[CHƯA KIỂM CHỨNG]**.

## Tóm tắt

| Hạng mục | Kết luận | Code cần viết |
|---|---|---|
| ERP/PIM | Core đủ: Sync API + Integration có quyền tối thiểu. Thiếu đúng một thứ: tra brand theo **mã brand** khi đồng bộ | 1 class ~30 dòng trong `AcmeBrand` (FK resolver) — chỉ khi có hệ thống thật |
| Đa kho | **Không làm** — mọi brand chung một kho (chốt 14/10/2026). Ghi lại điểm mở rộng để khi cần không phải khảo sát lại | 0 |
| Bảng size brand × danh mục | Mở rộng thứ tự tra cứu hiện có (sản phẩm → brand → danh mục) bằng custom field, **không bảng mới** | 2 custom field + sửa 1 template — chỉ khi brand thật có thang size khác nhau |

Cả ba đều **chờ nhu cầu thật**: hiện chưa có ERP/PIM được chọn, hai brand ngoài Anivele là giả định, mới có một kho.

---

## 1. Đồng bộ ERP / PIM

### 1.1 Core có sẵn những gì

| Nhu cầu | Cơ chế native | Kiểm chứng trong `vendor/` |
|---|---|---|
| Ghi hàng loạt sản phẩm, giá, tồn kho | **Sync API** `POST /api/_action/sync` — nhiều thao tác upsert/delete trong một request | `Framework/Api/Controller/SyncController.php` |
| Tham chiếu sản phẩm bằng **SKU** thay vì UUID | FK resolver `product.number`: `"parentId": {"resolver": "product.number", "value": "ANV-1001"}` | `Content/Product/Api/ProductNumberFkResolver.php`, tag `shopware.sync.fk_resolver` |
| Nhập lớn không làm chậm shop | Header `indexing-behavior: use-queue-indexing` (đẩy index sang hàng đợi), `indexing-skip`, `sw-skip-trigger-flow` | `PlatformRequest::HEADER_INDEXING_BEHAVIOR`, `HEADER_INDEXING_SKIP`, `HEADER_SKIP_TRIGGER_FLOW` |
| Tài khoản máy cho hệ ngoài | **Integration** (Access key + secret) gắn **ACL role**; cờ `admin` mặc định tắt | `System/Integration/IntegrationDefinition.php` |
| Ảnh sản phẩm | `POST /api/_action/media/{mediaId}/upload` (gửi file hoặc URL), quyền `media:update` | `Content/Media/Api/MediaUploadController.php` |
| ERP lấy đơn mới | Admin API search `order` lọc theo `createdAt`/`updatedAt` > lần đồng bộ trước | DAL chuẩn |
| ERP cập nhật trạng thái, mã vận đơn | `POST /api/_action/order/{id}/state/{transition}`, `…/order_delivery/{id}/state/{transition}`; trường `trackingCodes` của `order_delivery` | `Checkout/Order/Api/OrderActionController.php`, `OrderDeliveryDefinition` |
| Nhập/xuất CSV khi chưa có hệ thống | Import/Export profile native + lệnh `import:entity` | `Content/ImportExport/Command/ImportEntityCommand.php` |
| Đẩy sự kiện ra ngoài (đơn mới…) | **App** khai báo webhook trong manifest (ký HMAC bằng secret của app) | `Framework/App/Manifest/Xml/Webhook/` |

Webhook không gắn app: bảng `webhook` có `app_id` không bắt buộc, nhưng việc tạo webhook trực tiếp qua Admin API cho Integration **[CHƯA KIỂM CHỨNG]**. Cách chính thống là App.

### 1.2 Thiết kế đề xuất

```
PIM ──(Sync API: sản phẩm, thuộc tính, ảnh)──▶ Shopware ◀──(Admin API: đơn, trạng thái, mã vận đơn)── ERP
ERP ──(Sync API: tồn kho, giá)───────────────▶
```

1. **Mỗi hệ thống ngoài một Integration**, ACL role riêng, quyền tối thiểu (API-05, SEC-02):
   - PIM: `product`, `product_manufacturer` (đọc), `property_group_option`, `media`, `category` (đọc)
   - ERP: `product` (chỉ giá, tồn kho), `order` (đọc + chuyển trạng thái), `order_delivery`
2. **Khoá định danh là SKU** (`productNumber`, mã brand nằm trong SKU: `ANV-1001-01`). Hệ ngoài giữ bảng ánh xạ SKU → UUID. Lần đầu lấy UUID bằng Admin API search theo `productNumber`; sản phẩm mới do hệ ngoài tạo thì sinh UUID tất định từ SKU, cùng cách `AcmeDemoData` sinh id ([runbook/05](runbook/05-demo-data.md)).
3. **Biến thể** gửi `parentId` bằng resolver `product.number` — không cần biết UUID của sản phẩm cha.
4. **Brand theo mã** (`ANV`, `MAY`): core chỉ có resolver `product.number`. Đề xuất `AcmeBrand` thêm resolver `acme_brand.code` → trả `manufacturerId`, để PIM gửi `"manufacturerId": {"resolver": "acme_brand.code", "value": "MAY"}`. `AbstractFkResolver` là lớp công khai (không `@internal`), đăng ký bằng tag `shopware.sync.fk_resolver` — đúng điểm mở rộng core dành cho việc này (EXT-01). Mã brand đã `UNIQUE` trong `acme_brand`, nên tra cứu luôn ra một kết quả.
5. **Nhập lớn**: header `indexing-behavior: use-queue-indexing`; sau đợt nhập đầu, chạy `dal:refresh:index` ngoài giờ cao điểm.
6. **Không mở route riêng** trong plugin cho ERP/PIM khi API chuẩn đã đủ (API-05).

### 1.3 Rủi ro cần chốt trước khi tích hợp

| Rủi ro | Mô tả | Hướng xử lý |
|---|---|---|
| **Bán quá tồn kho** | Shopware trừ `stock` ngay khi đặt hàng (`StockStorage::alter()`). ERP ghi đè `stock` bằng số tuyệt đối → đơn đặt giữa lúc ERP đọc và ghi bị "hoàn" tồn kho | ERP gửi `stock = tồn thực tế − đơn Shopware ERP chưa nhận`; đồng bộ dày (≤ 5 phút); hoặc ERP chỉ gửi **chênh lệch** khi nhập hàng |
| Nguồn sự thật của từng trường | PIM và người vận hành cùng sửa mô tả trong Administration → ghi đè lẫn nhau | Chốt bảng "trường nào hệ nào làm chủ" trước khi bật đồng bộ |
| Giá & khuyến mãi | ERP ghi giá gốc; khuyến mãi theo brand đang chạy bằng Rule Builder (MB5) | ERP chỉ ghi `price`/`listPrice`; khuyến mãi vẫn ở Shopware |
| Dữ liệu cá nhân | ERP đọc đơn có họ tên, SĐT, địa chỉ | Chỉ Integration của ERP có quyền `order:read`; không log dữ liệu cá nhân (SEC-05) |

### 1.4 App hay Integration

| | Integration (đề xuất bước đầu) | App |
|---|---|---|
| Cách nói chuyện | Hệ ngoài **kéo** (poll) Admin API | Shopware **đẩy** webhook tới server của app |
| Cần hạ tầng | Không | Một server trung gian có HTTPS |
| Hợp với | ERP/PIM có sẵn connector REST, đồng bộ theo lịch | Cần phản ứng tức thì (đơn mới → ERP ngay) |

---

## 2. Đa kho

**Quyết định hiện hành:** mọi brand chung một kho → một kiện mỗi đơn, một bảng phí ship ([12-da-thuong-hieu.md](12-da-thuong-hieu.md), câu hỏi 5). **Không làm gì ở MB6.**

Khi nào xem lại: mở kho thứ hai, brand tự giao hàng từ kho riêng, hoặc cần tồn kho theo cửa hàng.

Điểm mở rộng đã kiểm chứng: `Shopware\Core\Content\Product\Stock\AbstractStockStorage` (công khai, `#[Package('inventory')]`) với `load()`, `alter()`, `index()`, `getDecorated()`. Plugin decorate lớp này để đọc/ghi tồn kho theo kho (EXT-02). Shopware Commercial có tính năng đa kho — gói và giấy phép **[CHƯA KIỂM CHỨNG]**, không có trong `vendor/` của dự án.

Hệ quả cần thiết kế lại cùng lúc nếu bật đa kho: tách kiện theo kho, phí ship theo kiện (`AcmeShipping`), trừ kho khi huỷ đơn.

---

## 3. Bảng size theo brand × danh mục

### 3.1 Hiện trạng

- Dữ liệu: custom field `acme_size_chart_table`, `acme_size_chart_note` trên **danh mục** — định nghĩa trong `AcmeCatalog` (chuyển từ `AcmeTheme` ngày 14/10/2026).
- Hiển thị: `AcmeTheme` đọc từ `page.product.seoCategory` (danh mục chính, core đã nạp sẵn), lọc qua `sw_sanitize`.
- Giới hạn: hai brand khác thang size trong cùng danh mục "Đầm" thì chỉ hiện được một bảng.

> Dòng *"Mở rộng cơ chế size chart của `AcmeTheme`"* trong roadmap đã cũ: dữ liệu giờ thuộc `AcmeCatalog`, theme chỉ hiển thị.

### 3.2 Các phương án

| Phương án | Cách làm | Ưu | Nhược |
|---|---|---|---|
| **A. Tra cứu 3 tầng bằng custom field** (đề xuất) | Thêm cùng cặp field cho **sản phẩm** và **nhà sản xuất**. Theme lấy bảng theo thứ tự: sản phẩm → brand → danh mục | Không bảng mới, nhập bằng ô custom field native; tầng sản phẩm sửa hàng loạt bằng Bulk edit native; brand nào không có bảng riêng thì dùng bảng danh mục như bây giờ | Brand có thang khác nhau giữa đầm và chân váy thì phải đặt bảng ở tầng sản phẩm cho một trong hai |
| B. Layout CMS theo brand × danh mục | Mỗi tổ hợp một layout trang sản phẩm có khối bảng size; gán layout cho sản phẩm | Hoàn toàn native | Nhân bản cả layout trang chỉ để đổi một bảng; sửa giao diện chung phải sửa N layout |
| C. Bảng riêng `acme_size_chart` | (manufacturer_id, category_id) `UNIQUE`, HTML dịch được | Đúng mô hình nhất, mỗi tổ hợp đúng một bảng | Bảng + Admin UI viết tay (ADM-04) — chỉ đáng khi có rất nhiều tổ hợp; ARCH-04 đặt bảng riêng ở bậc cuối |

**Đề xuất A**, chỉ làm khi brand thật có thang size khác nhau. Chuyển sang C khi người vận hành phải đặt bảng ở tầng sản phẩm cho nhiều mẫu.

Phân việc nếu làm A — giữ các plugin độc lập (ARCH-02):

- `AcmeCatalog`: migration gắn thêm custom field set bảng size vào entity `product` và `product_manufacturer` của core — **không** phụ thuộc `AcmeBrand`.
- `AcmeTheme`: template tra 3 tầng, tầng nào có dữ liệu thì dùng (ARCH-03). Không thêm truy vấn: `page.product.manufacturer` đã được nạp ở trang sản phẩm. Cần đối chiếu lại khi code.
- Test: integration test cho migration (MIG-08), ảnh chụp 3 trường hợp (TEST-04).

---

## 4. Việc có thể làm ngay, không chờ hệ thống thật

| Việc | Plugin | Giá trị |
|---|---|---|
| FK resolver `acme_brand.code` + test | `AcmeBrand` | PIM/CSV gửi brand bằng mã thay vì UUID. Dùng được cả cho Import/Export nội bộ qua Sync API |
| Tài liệu hợp đồng đồng bộ (payload mẫu, quyền, header) | docs | Đưa thẳng cho bên làm ERP/PIM |

---

## 5. Cần chủ dự án quyết

1. **ERP / PIM nào?** (tên sản phẩm, có connector Shopware 6 sẵn không) — quyết định chọn Integration hay App.
2. **Hệ nào làm chủ trường nào?** (tên, mô tả, ảnh, giá, tồn kho) — bắt buộc chốt trước khi bật đồng bộ.
3. **Brand thật có thang size khác nhau trong cùng danh mục không?** — "có" thì làm phương án A ở mục 3.
4. **Làm trước FK resolver `acme_brand.code`** (mục 4) hay chờ có hệ thống thật?
