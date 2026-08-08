# Phase 2 — Catalog thời trang: đánh giá & kết quả

> ✅ **Đã hoàn tất 08/08/2026.** Thực hiện bằng plugin `AniveleCatalog`.

---

## 1. Vấn đề ban đầu — đã xử lý

**Trước Phase 2 hệ thống KHÔNG có variant nào. Toàn bộ 12 sản phẩm là sản phẩm đơn lẻ.**

```
                              TRƯỚC → SAU
product:                      12    → 76  (12 cha + 64 biến thể)
product_configurator_setting:  0    → 64
product_option:                0    → 128
product_property:             64    → 76  (nhãn mô tả, vẫn giữ)
```

Size và Màu trước đây gắn dưới dạng **thuộc tính mô tả** (`product_property`), **không phải trục sinh biến thể** (`product_option` + `product_configurator_setting`).

### Hệ quả thực tế — đây là lỗi nghiệp vụ, không phải vấn đề thẩm mỹ

| Vấn đề | Trạng thái |
|---|---|
| **Tồn kho sai** — `stock` tính chung cả mẫu, đặt XL khi chỉ còn S vẫn mua được | ✅ Mỗi biến thể có tồn kho riêng |
| **Không chọn được size khi mua** | ✅ Storefront hiện bộ chọn size + swatch màu |
| **Giá theo size không làm được** | ✅ Biểu diễn được qua `product_price` từng biến thể |
| **`is_closeout = 0`** | ✅ Đã bật cho cả 76 dòng |

### Kiểm chứng đã thực hiện

Đặt `ANV-1001-01` (size S) về `stock = 0` rồi chạy `dal:refresh:index`:

```
ANV-1001-01   stock=0   available=0   ← chỉ size S bị chặn
ANV-1001-02   stock=6   available=1
ANV-1001-03   stock=6   available=1
ANV-1001-04   stock=6   available=1
```

Đúng hành vi mong muốn: hết size S không ảnh hưởng M/L/XL.

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

| # | Việc | Trạng thái |
|---|---|---|
| 1 | Chốt dải size theo loại hàng (5.2) | ⏳ Dùng tạm S/M/L/XL — **vẫn cần người bán chốt** |
| 2 | Chốt quy tắc SKU (5.3) | ✅ `ANV-1001-01` (mã mẫu + số thứ tự) |
| 3 | Tách "Hoa nhiều màu" → nhóm Hoạ tiết | ✅ Migration |
| 4 | Bổ sung giá trị size còn thiếu (quần, free size) | ⏳ Chờ bước 1 |
| 5 | **Backup** | ✅ `fb-before-phase2-*.sql` |
| 6 | Sinh variant cho 1 sản phẩm thử | ✅ ANV-1001 → 4 biến thể |
| 7 | Kiểm tra storefront + tồn kho | ✅ Xem mục 1 |
| 8 | Bật `is_closeout` | ✅ 76/76 dòng |
| 9 | Nhân ra toàn bộ catalog | ✅ 64 biến thể / 12 mẫu |
| 10 | Dựng size chart bằng CMS block | ⏳ Chưa làm |

Bước 6–7 là **cổng kiểm chứng**: nếu variant đầu tiên không chạy đúng, dừng lại xem xét thay vì nhân rộng lỗi.

### Cổng kiểm chứng đã bắt được lỗi thật

Chạy `--dry-run` cho ANV-1001 báo **0 màu × 4 size = 0 biến thể**. Nguyên nhân: sản phẩm này ("Đầm maxi hoa nhí") chỉ có đúng một giá trị màu là "Hoa nhiều màu" — vừa bị bước 3 chuyển sang nhóm Hoạ tiết, nên còn 0 màu và không sinh được biến thể.

Nếu bỏ qua dry-run mà chạy thẳng toàn catalog, sản phẩm này sẽ **âm thầm bị bỏ sót** — vẫn là sản phẩm đơn lẻ giữa một catalog đã có variant, rất khó phát hiện về sau.

Cách xử lý: migration gán màu nền ("Kem") cho mọi sản phẩm không còn màu nào sau khi tách hoạ tiết. Đây cũng là cách shop thời trang thật phân loại — hàng hoạ tiết vẫn có màu nền, khách lọc theo màu nền và lọc riêng theo hoạ tiết.

### Bài học kỹ thuật

Hai lỗi gặp khi viết lệnh sinh biến thể, đều do **đoán API thay vì đọc core** — đúng thứ [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 3 cảnh báo:

| Lỗi | Nguyên nhân | Đúng phải là |
|---|---|---|
| `Cannot use object of type VariantListingConfig as array` | Truyền struct vào DAL | Ghi bằng **mảng** — `VariantListingConfigFieldSerializer::encode()` nhận array, chỉ `decode()` mới trả struct |
| `Expected command to be UpdateCommand (Got: InsertCommand)` | Dùng `update()` | Dùng **`upsert()`** — payload vừa sửa cha vừa tạo mới con |

Lỗi thứ ba về **tính idempotent**, phát hiện khi đối chiếu số liệu docs với database: migration tạo nhóm Hoạ tiết rồi dò trùng bằng tên tiếng Việt `'Hoạ tiết'`, nhưng lần chạy đầu nhóm được tạo với tên `'Pattern'` (bản dịch vi-VN thêm sau). Chạy lại migration → **sinh ra nhóm trùng rỗng**.

Cách sửa: dò theo **mọi tên ngôn ngữ** của nhóm (`IN ('Hoạ tiết', 'Pattern', 'Muster')`), không chỉ một. Đã kiểm chứng chạy migration hai lần vẫn ra đúng 3 nhóm.

> **Bài học chung:** migration idempotent phải dò trùng bằng thứ **không đổi giữa các lần chạy**. Dò bằng tên đã dịch là bẫy — bản dịch có thể được thêm/sửa ở lần chạy sau.

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
