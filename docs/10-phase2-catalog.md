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

## 5. Quyết định phải chốt trước khi làm

Đây là phần **đắt nhất nếu chốt sai** — sửa cấu trúc variant sau khi đã nhập hàng trăm SKU rất tốn kém.

### 5.1 Trục variant: chỉ Màu × Size

Khuyến nghị **không** đưa Chất liệu / Kiểu dáng thành trục variant. Lý do: mỗi trục nhân số SKU lên.

```
Màu (7) × Size (4)              =  28 SKU/mẫu
Màu (7) × Size (4) × Chất liệu (3) = 84 SKU/mẫu  ← quá tải vận hành
```

Chất liệu và Kiểu dáng nên là **property mô tả + bộ lọc**, giữ nguyên như hiện tại.

### 5.2 Bảng size chuẩn VN

Size VN nhỏ hơn EU/US khoảng 1–2 bậc. Cần chốt dải size **theo từng loại hàng**, vì đầm và áo không cùng thang:

| Loại | Dải size đề xuất |
|---|---|
| Áo / Đầm | S, M, L, XL (đang có) |
| Quần / Chân váy | 26, 27, 28, 29, 30 *(theo số eo)* |
| Free size | Một giá trị riêng — rất phổ biến ở VN |

> **Cần chốt với người bán hàng**, không phải quyết định kỹ thuật. Đây là câu hỏi mở của phase này.

### 5.3 Quy tắc mã SKU

Hiện tại `ANV-1001` là mã mẫu. Khi có variant cần quy tắc mở rộng, đề xuất:

```
ANV-1001-DEN-M     (mã mẫu - màu - size)
```

Chốt sớm vì mã SKU đi vào đơn hàng, hoá đơn, và không nên đổi về sau.

---

## 6. Thứ tự thực hiện

Sắp theo phụ thuộc — làm sai thứ tự phải làm lại.

| # | Việc | Ghi chú |
|---|---|---|
| 1 | Chốt dải size theo loại hàng (5.2) | **Chặn mọi việc sau** — cần người bán hàng quyết |
| 2 | Chốt quy tắc SKU (5.3) | |
| 3 | Tách "Hoa nhiều màu" khỏi nhóm Màu sắc → nhóm Hoạ tiết | Làm trước khi sinh variant |
| 4 | Bổ sung giá trị size còn thiếu (quần, free size) | |
| 5 | **Backup** | Bắt buộc — xem [runbook/00-backup.md](runbook/00-backup.md) |
| 6 | Sinh variant cho 1 sản phẩm thử | Kiểm chứng trước khi làm hàng loạt |
| 7 | Kiểm tra storefront: chọn được size/màu, tồn kho đúng | |
| 8 | Bật `is_closeout` để chặn bán quá tồn kho | |
| 9 | Nhân ra toàn bộ catalog | |
| 10 | Dựng size chart bằng CMS block | |

Bước 6–7 là **cổng kiểm chứng**: nếu variant đầu tiên không chạy đúng, dừng lại xem xét thay vì nhân rộng lỗi.

---

## 7. Rủi ro

| Rủi ro | Mức | Xử lý |
|---|---|---|
| Chốt sai trục variant, phải làm lại sau khi có nhiều SKU | **Cao** | Chốt mục 5.1 trước, thử 1 sản phẩm ở bước 6 |
| 12 sản phẩm hiện tại là demo, sẽ bị thay bằng hàng thật | Trung bình | Không đầu tư công sức vào dữ liệu demo — chỉ dùng để kiểm chứng cấu trúc |
| Dải size chốt sai với thực tế hàng | Trung bình | Cần người bán hàng xác nhận, không tự quyết |
| Số SKU phình to ngoài tầm vận hành | Trung bình | Giữ đúng 2 trục variant |

---

## 8. Câu hỏi mở

Cần trả lời trước khi bắt đầu bước 1:

1. **Dải size thực tế** của từng loại hàng là gì? Có bán Free size không?
2. Quần/chân váy dùng **số eo** (26–30) hay **chữ** (S–XL)?
3. Giá có **khác nhau theo size** không? (XL đắt hơn S là chuyện bình thường)
4. Một mẫu áo trung bình có bao nhiêu màu — để ước lượng số SKU thật?

---

## Liên quan

- Thiết kế catalog chi tiết: [05-catalog-fashion.md](05-catalog-fashion.md)
- Dữ liệu demo hiện tại: [runbook/05-demo-data.md](runbook/05-demo-data.md)
- Quy tắc khi viết code: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md), [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md)
