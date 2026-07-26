# Runbook: Xóa demo data

> **Backup trước.** Xem [00-backup.md](00-backup.md). Thao tác này xóa dữ liệu và không tự hoàn tác được.

## Bối cảnh

Hệ thống đang có dữ liệu demo từ plugin `SwagPlatformDemoData` (2.1.0):

| Bảng | Số lượng |
|---|---|
| product | 16 |
| category | 9 |
| customer | 1 |
| order | 0 |
| property_group | 5 |

Toàn bộ phải xóa trước khi nhập hàng thật, nếu không sản phẩm demo sẽ lẫn vào catalog và có thể hiển thị ra storefront.

---

## Thời điểm thực hiện

Xóa **sau khi** đã chốt cấu trúc catalog ([05-catalog-fashion.md](../05-catalog-fashion.md)) nhưng **trước khi** nhập sản phẩm thật.

Lý do: demo data hữu ích để test theme và luồng mua hàng trong lúc phát triển. Xóa quá sớm sẽ không còn gì để thử.

---

## Bước 1 — Kiểm tra hiện trạng

```bash
mysql -h shopware.test -u root shopware -e \
  "SELECT (SELECT COUNT(*) FROM product) products,
          (SELECT COUNT(*) FROM category) categories,
          (SELECT COUNT(*) FROM \`order\`) orders,
          (SELECT COUNT(*) FROM customer) customers;"
```

> ⚠️ Nếu `orders > 0`, **dừng lại**. Đã có đơn hàng thật thì không được xóa bừa — đơn hàng tham chiếu tới sản phẩm. Cần rà soát thủ công.

## Bước 2 — Gỡ plugin

```bash
cd /Users/dat/Ecommerce/shopware

php bin/console plugin:deactivate SwagPlatformDemoData
php bin/console plugin:uninstall SwagPlatformDemoData
php bin/console cache:clear
```

Gỡ hẳn khỏi composer:

```bash
COMPOSER_HOME=/Users/dat/Ecommerce/shopware/var/cache/composer \
  composer remove swag/demo-data
```

## Bước 3 — Kiểm tra dữ liệu còn sót

**Gỡ plugin không đảm bảo xóa hết dữ liệu đã tạo.** Kiểm tra lại:

```bash
mysql -h shopware.test -u root shopware -e \
  "SELECT (SELECT COUNT(*) FROM product) products,
          (SELECT COUNT(*) FROM category) categories,
          (SELECT COUNT(*) FROM property_group) props,
          (SELECT COUNT(*) FROM media) media;"
```

Nếu vẫn còn sản phẩm, xóa thủ công.

## Bước 4 — Xóa thủ công phần còn sót

> Chỉ chạy khi đã chắc chắn **không có đơn hàng thật**.

```sql
-- Xem trước những gì sẽ bị xóa
SELECT p.product_number, t.name
FROM product p
JOIN product_translation t ON t.product_id = p.id
LIMIT 50;
```

Nếu đúng là demo data:

```sql
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE product;
TRUNCATE TABLE product_translation;
TRUNCATE TABLE product_category;
TRUNCATE TABLE product_media;
TRUNCATE TABLE product_property;
TRUNCATE TABLE product_visibility;

SET FOREIGN_KEY_CHECKS = 1;
```

> ⚠️ `SET FOREIGN_KEY_CHECKS = 0` tắt kiểm tra toàn vẹn dữ liệu. **Bắt buộc bật lại** ở cuối. Nếu quên, dữ liệu sai có thể lọt vào mà không báo lỗi.

### Property group

Nhóm `Ingredients` dành cho thực phẩm, không liên quan thời trang:

```sql
-- Xem trước
SELECT g.id, t.name
FROM property_group g
JOIN property_group_translation t ON t.property_group_id = g.id
WHERE t.language_id = UNHEX('2fbb5fe2e29a4d70aa5854ce7ce3e20b');
```

Giữ lại `Colour`, `Size`, `Material` (sẽ Việt hóa và tái dùng). Xóa `Ingredients`.

## Bước 5 — Dọn media không dùng

Ảnh demo vẫn nằm trong `public/media/` sau khi xóa sản phẩm:

```bash
php bin/console media:delete-unused --dry-run
```

Xem danh sách trước. Nếu đúng, chạy thật:

```bash
php bin/console media:delete-unused
```

> `--dry-run` là bắt buộc ở lần chạy đầu. Xóa nhầm ảnh không khôi phục được nếu không có backup.

> ⚠️ **Lệnh này mặc định bỏ qua media tải lên trong 20 ngày gần nhất** (`--grace-period-days=20`). Nếu demo data mới được cài gần đây, ảnh sẽ **không bị xóa** và bạn sẽ tưởng lệnh chạy không hiệu quả. Khi chắc chắn muốn xóa cả file mới:
>
> ```bash
> php bin/console media:delete-unused --grace-period-days=0 --dry-run
> ```

## Bước 6 — Đánh lại chỉ mục

```bash
php bin/console dal:refresh:index
php bin/console cache:clear
```

---

## Kiểm chứng cuối

```bash
mysql -h shopware.test -u root shopware -e \
  "SELECT (SELECT COUNT(*) FROM product) products,
          (SELECT COUNT(*) FROM category) categories;"
```

Mở storefront kiểm tra: không còn sản phẩm demo, trang không lỗi.

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://shopware.test/
```

Kỳ vọng `200`.

---

## Nếu xóa nhầm

Khôi phục từ backup — xem [00-backup.md](00-backup.md). Đây là lý do bước backup không được bỏ qua.
