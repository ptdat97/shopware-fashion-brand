# Runbook: Dữ liệu demo Anivele

Plugin tự viết để tạo sản phẩm demo thời trang từ bộ ảnh thật của brand.

## Vị trí

```
custom/static-plugins/AniveleDemoData/
├── composer.json
└── src/
    ├── AniveleDemoData.php
    ├── Command/SeedDemoCommand.php
    └── Resources/
        ├── config/services.xml
        └── media/demo/              ← 66 ảnh + 1 video (78 MB)
```

Ảnh được **đóng gói kèm plugin**, không phụ thuộc `SwagPlatformDemoData` (đã gỡ). Plugin tự chứa, chạy được trên máy khác.

## Cách dùng

```bash
php bin/console anivele:demo:seed
php bin/console dal:refresh:index
php bin/console cache:clear
```

Lệnh có tính **idempotent** — chạy lại sẽ tái sử dụng ảnh/thuộc tính/danh mục đã có thay vì tạo trùng.

## Lệnh làm gì

| Bước | Nội dung |
|---|---|
| 1 | Tạo 4 danh mục: Đầm, Áo, Chân váy, Bộ sưu tập |
| 2 | Tạo thuộc tính **Màu sắc** (7 giá trị, có mã hex để hiển thị swatch) và **Kích cỡ** (S/M/L/XL) |
| 3 | Nạp 66 ảnh vào thư viện media |
| 4 | Tạo 12 sản phẩm, gán ảnh + thuộc tính + danh mục + visibility |

## Dữ liệu sinh ra

12 sản phẩm với giá VND thực tế (590.000 – 1.890.000 ₫), mô tả tiếng Việt viết theo từng ảnh cụ thể:

| Mã | Tên | Giá |
|---|---|---|
| ANV-1001 | Đầm maxi hoa nhí dáng xoè | 1.250.000 ₫ |
| ANV-1002 | Áo polo dệt kim tay ngắn | 690.000 ₫ |
| ANV-1003 | Đầm midi cổ thuyền xếp ly | 1.450.000 ₫ |
| ANV-1004 | Đầm đen cổ chữ V dáng xoè | 1.390.000 ₫ |
| ANV-1005 | Set áo sơ mi & chân váy tông kem | 1.690.000 ₫ |
| ANV-1006 | Đầm trắng sát nách cúc bọc | 1.290.000 ₫ |
| ANV-1007 | Đầm đen tay loe dáng ôm | 1.490.000 ₫ |
| ANV-1008 | Áo thun kẻ sọc phối chân váy | 590.000 ₫ |
| ANV-1009 | Đầm lụa xanh pastel dáng ôm | 1.590.000 ₫ |
| ANV-1010 | Set áo lụa & chân váy xếp ly | 1.790.000 ₫ |
| ANV-1011 | Chân váy dài xếp ly tông be | 790.000 ₫ |
| ANV-1012 | Đầm dạ hội dáng dài tay bồng | 1.890.000 ₫ |

Mỗi sản phẩm có 3–4 ảnh, custom field `anivele_material` (chất liệu) và `anivele_model_size` (người mẫu mặc size) — đúng các trường đã đề xuất trong [../05-catalog-fashion.md](../05-catalog-fashion.md).

Giá net tính ngược từ giá gross theo VAT 10%: `net = round(gross / 1.10)`.

---

## Bẫy đã gặp: bản dịch chỉ vào en-US

DAL tạo sản phẩm theo `Context::createDefaultContext()` → toàn bộ text vào ngôn ngữ hệ thống (**en-US**), trong khi storefront chạy **vi-VN**.

Khắc phục bằng cách sao chép bản dịch sang vi-VN:

```sql
INSERT INTO product_translation
  (product_id, product_version_id, language_id, name, description, ...)
SELECT pt.product_id, pt.product_version_id, UNHEX('<VI_VN_LANG_ID>'),
       pt.name, pt.description, ...
FROM product_translation pt
WHERE pt.language_id = UNHEX('<EN_US_LANG_ID>')
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description);
```

Làm tương tự cho `category_translation`, `property_group_translation`, `property_group_option_translation`.

> ⚠️ **Bắt buộc có `product_version_id`** trong INSERT, nếu thiếu sẽ lỗi:
> `Field 'product_version_id' doesn't have a default value`

---

## Kiểm chứng

```bash
# Trang danh mục
curl -s "http://shopware.test/Dam/" | grep -oE '[0-9]{1,3}(\.[0-9]{3})+\s*₫' | sort -u

# Trang chi tiết
curl -s "http://shopware.test/Dam-maxi-hoa-nhi-dang-xoe/ANV-1001" -o /dev/null -w "%{http_code}\n"
```

Kết quả thực tế đã kiểm chứng:
- Giá render `1.250.000 ₫` đúng chuẩn VN
- SEO URL tiếng Việt không dấu: `Dam-maxi-hoa-nhi-dang-xoe/ANV-1001`
- 264 thumbnail được sinh tự động
- Ảnh gốc phục vụ HTTP 200

---

## Gỡ bỏ khi nhập catalog thật

```bash
php bin/console plugin:deactivate AniveleDemoData
php bin/console plugin:uninstall AniveleDemoData
composer remove anivele/demo-data
```

> Plugin này **không tự xoá sản phẩm** khi gỡ (khác `SwagPlatformDemoData`). Cần xoá thủ công sản phẩm mã `ANV-*` nếu muốn dọn sạch.

---

## Lưu ý về dung lượng ảnh

Ảnh gốc rất nặng (**0.5–1.5 MB/ảnh**, tổng 78 MB). Với môi trường thật cần tối ưu trước khi upload — xem phần hiệu năng trong [../02-theme.md](../02-theme.md). Ảnh nặng là nguyên nhân hàng đầu khiến khách dùng mạng di động thoát trang.
