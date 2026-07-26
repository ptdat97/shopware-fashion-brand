# Phase 2 — Catalog thời trang: đánh giá & kế hoạch

Đánh giá dựa trên truy vấn trực tiếp database ngày 26/07/2026, không dựa trên giả định của roadmap.

---

## 1. Phát hiện quan trọng nhất

**Hệ thống hiện KHÔNG có variant nào. Toàn bộ 12 sản phẩm là sản phẩm đơn lẻ.**

```
product:                     12 tổng — 12 parent, 0 variant
product_configurator_setting: 0 dòng
product_option:               0 dòng
product_property:            64 dòng   ← chỉ là nhãn mô tả
```

Nghĩa là Size và Màu hiện đang gắn vào sản phẩm dưới dạng **thuộc tính mô tả** (`product_property`), **không phải trục sinh biến thể** (`product_option` + `product_configurator_setting`).

### Hệ quả thực tế — đây là lỗi nghiệp vụ, không phải vấn đề thẩm mỹ

| Vấn đề | Chi tiết |
|---|---|
| **Tồn kho sai** | `ANV-1001` có `stock = 78` cho *cả mẫu*. Không biết còn bao nhiêu size M. Khách đặt XL khi chỉ còn S → vẫn bán được |
| **Không chọn được size khi mua** | Storefront không hiện bộ chọn size/màu vì không có configurator |
| **Giá theo size không làm được** | Size XL thường đắt hơn S — hiện không biểu diễn được |
| **`is_closeout = 0`** | Không chặn bán quá tồn kho |

> Đây là hạng mục **chặn go-live**, không phải việc tối ưu. Một shop thời trang không chọn được size thì không bán được hàng.

---

## 2. Đính chính roadmap

Ba điểm trong [01-roadmap.md](01-roadmap.md) không khớp thực tế:

| Roadmap ghi | Thực tế |
|---|---|
| "Đã có sẵn Colour/Size/Material — cần Việt hóa" | Chỉ có **2 group**, và **đã Việt hoá sẵn** ("Màu sắc", "Kích cỡ") ở cả `vi-VN` lẫn `en-US`. **Không có** group Chất liệu |
| "Xóa toàn bộ demo data — 16 sản phẩm demo phải đi" | Chỉ còn **12 sản phẩm** `ANV-*` do plugin tự viết sinh ra. Demo cũ không còn |
| "Cấu hình variant matrix" (ngụ ý đã có nền) | **Chưa có gì** — phải xây từ đầu |

Việc Việt hoá property group coi như xong. Việc thật của phase này là **dựng variant**.

---

## 3. Hiện trạng dữ liệu

**Màu sắc** — `display_type = color` (đã đúng, storefront render swatch màu):

| Giá trị | Hex |
|---|---|
| Be | `#d9c9b0` |
| Đen | `#1a1a1a` |
| Hoa nhiều màu | `#c8a2c8` |
| Kem | `#f0e6d2` |
| Nâu | `#6b4a34` |
| Trắng | `#ffffff` |
| Xanh pastel | `#b9d4d8` |

**Kích cỡ** — `display_type = text`, 4 giá trị: S, M, L, XL.

> ⚠️ "Hoa nhiều màu" là **hoạ tiết, không phải màu**. Dùng nó làm giá trị trong nhóm Màu sắc sẽ hỏng bộ lọc: khách lọc "màu tím" sẽ ra váy hoa. Nên tách sang nhóm **Hoạ tiết** riêng.

---

## 4. Plugin cần thiết — kết luận: **gần như không cần**

Đã kiểm chứng trong `vendor/shopware/`: Shopware 6.7.12.2 có sẵn mọi thứ Phase 2 cần.

| Nhu cầu | Trạng thái | Kết luận |
|---|---|---|
| Sinh variant từ ma trận màu × size | `sw-product-detail-variants` có trong Administration | ✅ Core |
| Swatch màu ở bộ lọc & trang sản phẩm | `display_type = color` (đang dùng) | ✅ Core |
| Tồn kho riêng từng variant | Mỗi variant là một row `product` | ✅ Core |
| Giá riêng theo variant | `product_price` theo variant | ✅ Core |
| Import/Export hàng loạt | Có sẵn profile `product`, `property_group_option`, `media` | ✅ Core |
| Cross-selling (mix & match) | `Content/Product/Aggregate/ProductCrossSelling` | ✅ Core |
| Product feed (Google/Facebook) | `Content/ProductExport` | ✅ Core |

**Không có hạng mục nào của Phase 2 bắt buộc phải mua/viết plugin.**

### Hai thứ core không có sẵn

| Nhu cầu | Cách xử lý | Ưu tiên |
|---|---|---|
| **Bảng size chart** (khách VN rất hay cần) | Dùng **CMS block** gắn vào trang sản phẩm, hoặc **custom field** HTML theo danh mục. Không cần plugin | Nên có trước go-live |
| **Quy đổi size VN ↔ EU/US** | Ghi trực tiếp trong size chart. Chỉ cần plugin nếu muốn tự động đổi theo vùng — chưa cần ở giai đoạn này | Có thể để sau |

> Nếu sau này cần swatch **bằng ảnh** (vải hoạ tiết) thay vì màu phẳng: `display_type = media` cũng có sẵn trong core, vẫn không cần plugin.

---

## 5. Quyết định đã chốt

Chi tiết và lý do: [decisions/ADR-003-cau-truc-variant.md](decisions/ADR-003-cau-truc-variant.md)

| # | Quyết định |
|---|---|
| 1 | **Trục variant: Màu × Size.** Chất liệu/Kiểu dáng/Hoạ tiết giữ làm property lọc |
| 2 | **Hai thang size:** Đầm/Áo/Set dùng `S–XL`; Chân váy/Quần dùng số eo `26–30` |
| 3 | **Giá khác theo size:** cho phép, mỗi variant đặt giá riêng được |
| 4 | **SKU:** `ANV-1001-DEN-M` (mã mẫu – màu 3 ký tự không dấu – size) |
| 5 | **Tách "Hoa nhiều màu"** sang nhóm Hoạ tiết mới |

### Quy mô SKU

Với **3 màu trung bình mỗi mẫu**:

```
3 màu × 4 size  =  12 SKU/mẫu
100 mẫu thật    =  ~1.200 SKU   ← nằm trong khả năng xử lý của Shopware
```

Nếu thêm trục Chất liệu: 36 SKU/mẫu → 3.600 SKU. Đây là lý do giữ đúng 2 trục.

> Dữ liệu demo hiện chỉ gắn 1–2 màu mỗi sản phẩm, nhưng kế hoạch tính theo **3 màu** như hàng thật.

---

## 6. Thứ tự thực hiện

Sắp theo phụ thuộc — làm sai thứ tự phải làm lại.

Mục 5 đã chốt, bắt đầu được từ bước 1.

| # | Việc | Trạng thái |
|---|---|---|
| 1 | **Backup** | ✅ `phase2-before-20260726-190525.sql`, 260 bảng |
| 2 | Tạo nhóm **Hoạ tiết**, chuyển "Hoa nhiều màu" sang | ✅ 5 giá trị, 64 liên kết `product_property` nguyên vẹn |
| 3 | Thêm thang size số eo `26–30` | ⏸ Hoãn — catalog chưa có quần/chân váy đủ nhiều |
| 4 | Sinh variant cho `ANV-1004` | ✅ 1 màu × 4 size = 4 variant, tồn kho 18/variant |
| 5 | Kiểm chứng storefront + chặn bán khi hết hàng | ✅ Xem kết quả bên dưới |
| 6 | Bật `is_closeout` cho toàn bộ catalog | ✅ 16/16 sản phẩm |
| 7 | Sinh variant cho mẫu **nhiều màu** (`ANV-1005`) | ✅ 2 màu × 4 size = 8 variant |
| 8 | Nhân ra toàn bộ catalog | ✅ 12 mẫu → **64 variant**, tồn kho khớp 12/12 |
| 9 | Size chart bằng CMS block | ⬜ Còn lại |

Quy trình và lệnh: [runbook/06-sinh-variant.md](runbook/06-sinh-variant.md)

### Kết quả kiểm chứng bước 5

| Trường hợp | Kết quả |
|---|---|
| Trang sản phẩm hiện bộ chọn size | ✅ S / M / L / XL + màu Đen, giá `1.390.000 ₫` |
| Thêm size M (tồn 18) vào giỏ | ✅ Vào giỏ, 1 dòng, `1.390.000 ₫` |
| Thêm size S (tồn 0) vào giỏ | ✅ **Bị chặn** — `"no longer available"`, giỏ trống |
| Trang variant hết hàng | ✅ Không có nút mua, hiện "Không còn khả dụng nữa" |

Tồn kho sản phẩm cha được Shopware tự tính lại = tổng tồn kho variant (72).

### Kết quả kiểm chứng bước 6–7

| Trường hợp | Kết quả |
|---|---|
| `ANV-1005` — swatch màu trên storefront | ✅ Kem `#f0e6d2`, Be `#d9c9b0` + 4 size, giá `1.690.000 ₫` |
| Thêm `ANV-1005-BEI-M` ×2 vào giỏ | ✅ Đúng variant `[M, Be]`, tổng `3.380.000 ₫` |
| Thêm `ANV-1005-KEM-M` ×2 vào giỏ | ✅ Đúng variant `[M, Kem]` — chọn màu ra đúng hàng |
| Đặt 50 cái khi chỉ còn 8 | ✅ **Cắt còn 8** — `"is only available 8 times"` |

`is_closeout` bật cho cả 16 sản phẩm; không sản phẩm nào bị ẩn ngoài ý muốn (không có sản phẩm nào tồn kho 0).

### Kết quả bước 8 — toàn catalog

12 mẫu → **64 variant**, không SKU trùng, `is_closeout` bật cho cả 76 dòng sản phẩm.

| Kiểm tra | Kết quả |
|---|---|
| Tổng tồn kho variant = tồn kho cha | ✅ **12/12 mẫu khớp chính xác** |
| SKU trùng | ✅ Không có |
| Storefront `ANV-1001`, `ANV-1008`, `ANV-1011` | ✅ HTTP 200, hiện đủ bộ chọn màu + size |
| Mua thử `ANV-1008-XPA-L` | ✅ Vào giỏ đúng variant `[L, Xanh pastel]`, `590.000 ₫` |

> **`ANV-1001` từng không sinh được variant.** Sản phẩm này chỉ có "Hoa nhiều màu" làm màu, mà giá trị đó đã chuyển sang nhóm Hoạ tiết ở bước 2 nên còn **0 màu**. Đã gán màu nền **Kem** (đối chiếu ảnh thật: váy nền kem in hoa nhiều màu) rồi mới sinh variant được. Đây là hệ quả trực tiếp của bước 2, cần lưu ý khi nhập hàng thật: sản phẩm hoạ tiết vẫn phải có màu nền.

> ⚠️ Bộ chọn size vẫn cho **chọn** size hết hàng, chỉ chặn ở bước thêm vào giỏ — hành vi mặc định của Shopware. Muốn gạch/mờ ngay trên bộ chọn thì tuỳ biến theme ở Phase 3.

---

## 7. Rủi ro

| Rủi ro | Mức | Xử lý |
|---|---|---|
| 12 sản phẩm hiện tại là demo, sẽ bị thay bằng hàng thật | Trung bình | Không đầu tư công sức vào dữ liệu demo — chỉ dùng để kiểm chứng cấu trúc |
| Cấu trúc variant chốt sai, phải làm lại khi đã nhiều SKU | Trung bình | Đã chốt ở [ADR-003](decisions/ADR-003-cau-truc-variant.md); thử 1 sản phẩm ở bước 4 trước khi nhân rộng |
| Số SKU phình to ngoài tầm vận hành | Thấp | Giữ đúng 2 trục — 3 màu × 4 size = 12 SKU/mẫu |

---

## 8. Còn treo

Hai điểm chưa chốt, **không chặn** việc bắt đầu (chi tiết ở [ADR-003](decisions/ADR-003-cau-truc-variant.md)):

1. **Free size** — có bán không? Thêm được bất cứ lúc nào trước khi nhập hàng thật.
2. **Thang số eo `26–30`** — chỉ cần khi dòng hàng thật có quần/chân váy. Catalog hiện chỉ có 1 chân váy.

---

## Liên quan

- Quyết định cấu trúc variant: [decisions/ADR-003-cau-truc-variant.md](decisions/ADR-003-cau-truc-variant.md)
- Thiết kế catalog chi tiết: [05-catalog-fashion.md](05-catalog-fashion.md)
- Dữ liệu demo hiện tại: [runbook/05-demo-data.md](runbook/05-demo-data.md)
- Quy tắc khi viết code: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md), [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md)
