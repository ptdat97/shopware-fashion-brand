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

12 sản phẩm demo hiện tại do plugin `AcmeDemoData` sinh ra, **chưa có variant nào** — đây là hạng mục chặn go-live. Kế hoạch xử lý: [10-phase2-catalog.md](10-phase2-catalog.md).

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

> 🧭 **Đa thương hiệu:** tiền tố brand trong SKU trở thành bắt buộc — nhiều brand có thể cùng dùng mã mẫu `1001`. **Đã chốt (12/10/2026): mã brand 3 ký tự** — `NKE-1001-01` (brand – mẫu – biến thể), mã brand phải khớp brand gán qua `product.manufacturerId`. `ANV` = Anivele, hàng `ANV-*` giữ nguyên. Xem [12-da-thuong-hieu.md](12-da-thuong-hieu.md) mục 8.

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

**3. Hiển thị bảng size ngay tại trang sản phẩm** ✅ **đã làm**

Popup ngay dưới bộ chọn size, kèm hướng dẫn cách đo. Không bắt khách rời trang.

### Sửa số đo ở đâu

> 🧭 **Đa thương hiệu:** gắn theo danh mục chỉ đúng khi cả danh mục là một brand. Với nhiều brand, cùng "Đầm" nhưng số đo mỗi brand khác nhau — bảng size phải theo **brand × danh mục**. Thiết kế lại ở Phase MB6, xem [12-da-thuong-hieu.md](12-da-thuong-hieu.md).

Hiện tại bảng size gắn theo **danh mục**, không phải từng sản phẩm — đầm và chân váy không cùng thang số đo, nhưng mọi mẫu đầm dùng chung một bảng. Nhập một lần cho cả danh mục thay vì lặp lại cho hàng trăm SKU.

```
Administration → Danh mục → chọn danh mục (Đầm / Áo / Chân váy)
  → tab "Tuỳ chỉnh" → Bảng size
     ├── Bảng số đo   (trình soạn thảo HTML)
     └── Ghi chú dưới bảng
```

Sản phẩm lấy bảng của **danh mục chính** — chính danh mục core dùng cho breadcrumb và SEO ở trang chi tiết (`page.product.seoCategory`). Sản phẩm nằm ở nhiều danh mục thì chọn danh mục chính trong Administration: sản phẩm → tab *SEO* → "Danh mục chính". Danh mục chưa nhập bảng thì nút "Bảng size" **không hiện** — không có popup rỗng.

> 🔴 **Số đo hiện tại là SỐ THAM KHẢO** theo thang phổ biến của thị trường VN, không phải số đo thật của Anivele. Phải đo trên sản phẩm thật rồi thay lại trước go-live.

Kỹ thuật: custom field `acme_size_chart_table` / `acme_size_chart_note` trên entity `category`, xem `custom/plugins/AcmeTheme/src/Migration/Migration1786195057SizeChartCustomField.php`.

---

## Thuộc tính nên có

| Thuộc tính | Kiểu | Vì sao cần |
|---|---|---|
| Màu sắc | Property (swatch) | Lọc + chọn biến thể |
| Size | Property | Chọn biến thể |
| Chất liệu | Property | Khách VN quan tâm (cotton, linen thoáng mát) |
| Kiểu dáng | Property | Form rộng, ôm, suông |
| Giới tính | Property | Nam / Nữ / Unisex |
| **Thương hiệu** | `product.manufacturerId` (native) | Trang brand, bộ lọc brand, khuyến mãi theo brand — **không** làm thành property |
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
Bộ sưu tập (theo mùa/chiến dịch)   → /collections/...  (category + product stream)
Sale
Hàng mới về
```

**Thương hiệu không phải một nhánh của cây danh mục.** Brand gán qua trường *Nhà sản xuất* của sản phẩm; trang `/brands/{slug}` và bộ lọc brand ở listing lấy từ đó. Đặt brand vào cây danh mục sẽ tạo hai nguồn sự thật — xem [decisions/ADR-003-mo-hinh-thuong-hieu.md](decisions/ADR-003-mo-hinh-thuong-hieu.md) phương án D.

Tránh lồng quá 3 cấp — khách sẽ lạc.

## Bộ sưu tập (`/collections/…`)

Bộ sưu tập là **danh mục bình thường của core** lấy sản phẩm bằng *Nhóm sản phẩm động* (product stream). Plugin `AcmeCatalog` chỉ thêm một ô đánh dấu để URL thành `/collections/{tên}`.

Tạo bộ sưu tập mới:

```
1. Danh mục → Nhóm sản phẩm động → Thêm
     vd. "Tag = Xuân Hè 2027"  hoặc  "Ngày tạo trong vòng 30 ngày"
2. Danh mục → tạo danh mục con dưới "Bộ sưu tập"
     → tab Sản phẩm: Kiểu gán = Nhóm sản phẩm động → chọn nhóm ở bước 1
     → tab Tuỳ chỉnh → bật "Là bộ sưu tập (URL /collections/…)"
3. Lưu → URL /collections/xuan-he-2027 tự sinh
```

- **Sale** không cần tag: Nhóm sản phẩm động với điều kiện *Giá rẻ nhất → Phần trăm* lớn hơn 0 tự gom mọi sản phẩm có giá niêm yết cao hơn giá bán. Nhập giá niêm yết ở tab *Giá* của sản phẩm — storefront tự gạch giá cũ và gắn huy hiệu %.
- Không bật ô ở bước 2 thì URL theo cây danh mục (`/Bo-suu-tap/Xuan-He-2027/`) — vẫn chạy, chỉ khác dạng URL.
- Tên bộ sưu tập là slug: đổi tên → URL mới, URL cũ tự chuyển hướng 301.
- Bộ lọc "Thương hiệu" có sẵn trong trang bộ sưu tập — một bộ sưu tập dùng chung cho mọi brand, không tạo bản riêng theo brand.
- Template SEO nằm ở Settings → SEO → *Trang danh mục*. Sửa tay template thì migration không ghi đè lại.

---

## Xóa demo data

Trước khi nhập hàng thật phải xoá sạch 12 sản phẩm demo (`ANV-*`, `MAY-*`, `LLN-*`) và hai brand giả định do plugin `AcmeDemoData` sinh ra — xem [runbook/05-demo-data.md](runbook/05-demo-data.md).

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
