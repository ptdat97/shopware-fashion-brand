# Catalog thời trang

Phần này quyết định chất lượng dữ liệu lâu dài. **Sai cấu trúc ở đây sẽ rất đắt để sửa** khi đã có hàng trăm sản phẩm.

> ⚠️ **Tài liệu này là thiết kế mục tiêu, không phải hiện trạng.** Hiện trạng đã kiểm chứng nằm ở [10-phase2-catalog.md](10-phase2-catalog.md) — đọc file đó trước khi thao tác.

## Hiện trạng

**2 property group**, đều đã Việt hoá sẵn ở cả `vi-VN` lẫn `en-US`:

| Group | `display_type` | Giá trị | Đánh giá |
|---|---|---|---|
| Màu sắc | `color` | 7 (có mã hex, storefront render swatch) | Cần tách "Hoa nhiều màu" sang nhóm Hoạ tiết |
| Kích cỡ | `text` | 4 (S/M/L/XL) | Cần thiết kế lại theo chuẩn VN từng loại hàng |

**Chưa có** nhóm Chất liệu, Kiểu dáng, Giới tính, Mùa — các thuộc tính đề xuất ở mục dưới đều là *việc cần làm*, không phải thứ đang có.

12 sản phẩm demo hiện tại do plugin `AniveleDemoData` sinh ra, **chưa có variant nào** — đây là hạng mục chặn go-live. Kế hoạch xử lý: [10-phase2-catalog.md](10-phase2-catalog.md).

---

## Mô hình biến thể cho thời trang

### Đặc thù ngành

Một mẫu áo thường có: **Màu × Size**. Ví dụ 5 màu × 5 size = **25 biến thể** cho một mẫu. Với 100 mẫu là 2.500 SKU.

Đây là điểm khác biệt lớn nhất so với các ngành khác và là nguyên nhân khiến catalog thời trang khó quản lý.

### Quyết định cần chốt trước khi nhập liệu

**1. Mỗi biến thể có mã SKU riêng không?**

Nên có. Cần cho quản lý kho, đối soát với đơn vị vận chuyển. Quy ước gợi ý:
```
ANV-AOTHUN-001-DEN-M
   └ brand └ loại └ mã └ màu └ size
```

**2. Tồn kho theo biến thể**

Bắt buộc. Áo đen size M hết hàng không có nghĩa áo đỏ size L hết. Shopware hỗ trợ sẵn.

**3. Giá theo biến thể**

Thường tất cả biến thể cùng giá. Ngoại lệ: size lớn (2XL, 3XL) đôi khi cộng thêm phí. Cần quyết định chính sách từ đầu.

**4. Ảnh theo màu**

Quan trọng với thời trang: khách chọn màu đỏ thì gallery phải đổi sang ảnh áo đỏ. Shopware hỗ trợ gán ảnh theo giá trị thuộc tính — cần cấu hình đúng ngay từ khi nhập.

---

## Bảng size

### Vấn đề

Size quần áo **không có chuẩn thống nhất**. Cùng ghi "size M" nhưng số đo mỗi brand mỗi khác. Khách VN thường quen với size châu Á, vốn nhỏ hơn size EU/US cùng ký hiệu.

Đây là nguyên nhân đổi trả hàng đầu với thời trang online.

### Cách xử lý

**1. Chốt một hệ size và dùng nhất quán**

Gợi ý dùng ký hiệu chữ (S, M, L, XL, XXL) cho áo và số (28, 29, 30...) cho quần jean — theo thói quen thị trường.

**2. Bắt buộc có bảng size chi tiết**

Không chỉ ghi "M" mà phải kèm số đo thực tế:

| Size | Ngực (cm) | Eo (cm) | Dài áo (cm) | Chiều cao gợi ý |
|---|---|---|---|---|
| S | ... | ... | ... | ... |
| M | ... | ... | ... | ... |

> Các con số cụ thể phải do brand tự đo trên sản phẩm thật. Tài liệu này không đưa số mẫu vì mỗi form dáng mỗi khác — đưa số sai còn tệ hơn không đưa.

**3. Hiển thị bảng size ngay tại trang sản phẩm**

Dạng popup, không bắt khách rời trang. Nên có thêm hướng dẫn cách tự đo.

---

## Thuộc tính nên có

| Thuộc tính | Kiểu | Vì sao cần |
|---|---|---|
| Màu sắc | Property (swatch) | Lọc + chọn biến thể |
| Size | Property | Chọn biến thể |
| Chất liệu | Property | Khách VN quan tâm (cotton, linen thoáng mát) |
| Kiểu dáng | Property | Form rộng, ôm, suông |
| Giới tính | Property | Nam / Nữ / Unisex |
| Mùa / Bộ sưu tập | Property | Quản lý hàng theo mùa |
| Hướng dẫn giặt | Custom field | Giảm khiếu nại hỏng sản phẩm |
| Người mẫu mặc size | Custom field | Giúp khách ước lượng — rất hiệu quả |

### Lưu ý về khí hậu

Việt Nam khí hậu nóng ẩm, phân hóa hai miền rõ rệt. Chất liệu thoáng mát là điểm bán hàng quan trọng — nên đưa thành thuộc tính lọc được, không chỉ ghi trong mô tả.

---

## Cấu trúc danh mục

Nguyên tắc: **theo cách khách tìm kiếm**, không theo cách nội bộ quản lý.

Gợi ý:
```
Nam / Nữ
 ├─ Áo (thun, sơ mi, khoác...)
 ├─ Quần (jean, tây, short...)
 ├─ Đầm / Váy
 └─ Phụ kiện
Bộ sưu tập (theo mùa/chiến dịch)
Sale
Hàng mới về
```

Tránh lồng quá 3 cấp — khách sẽ lạc.

---

## Xóa demo data

Trước khi nhập hàng thật phải xoá sạch 12 sản phẩm demo `ANV-*` do plugin `AniveleDemoData` sinh ra.

> `SwagPlatformDemoData` đã được gỡ từ Phase 0, không còn liên quan.

> ⚠️ Gỡ plugin có thể **không tự xóa hết** dữ liệu đã tạo. Cần kiểm tra lại và xóa thủ công phần còn sót. Luôn backup trước khi thao tác.

Thao tác: [runbook/03-xoa-demo-data.md](runbook/03-xoa-demo-data.md)

---

## Chất lượng nội dung sản phẩm

Với thời trang, nội dung ảnh hưởng trực tiếp tới tỷ lệ chuyển đổi và tỷ lệ đổi trả:

- **Ảnh**: nhiều góc, có ảnh người mẫu mặc thật, ảnh cận chất liệu
- **Mô tả**: chất liệu, form dáng, size người mẫu mặc, hướng dẫn bảo quản
- **Tên sản phẩm**: theo cách khách gọi, không dùng mã nội bộ

**Không dùng ảnh chỉnh màu quá đà.** Màu áo thực tế khác ảnh là nguyên nhân đổi trả và mất niềm tin thương hiệu.
