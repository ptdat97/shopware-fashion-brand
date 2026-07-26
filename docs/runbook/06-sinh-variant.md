# Runbook: Sinh variant Màu × Size

Quy trình dựng cấu trúc variant cho catalog thời trang. Cấu trúc đã chốt tại [../decisions/ADR-003-cau-truc-variant.md](../decisions/ADR-003-cau-truc-variant.md).

Lệnh do plugin `AniveleDemoData` cung cấp — **không sửa core**, theo [../08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md).

---

## Lệnh

```bash
# Chỉ tạo nhóm Hoạ tiết (chạy lại nhiều lần được)
php bin/console anivele:catalog:build-variants

# Sinh variant cho một sản phẩm
php bin/console anivele:catalog:build-variants --sku=ANV-1004

# Sinh variant cho MỌI sản phẩm chưa có variant
php bin/console anivele:catalog:build-variants --all

# Gán màu nền cho sản phẩm chỉ có hoạ tiết
php bin/console anivele:catalog:build-variants --set-color=ANV-1001:Kem

# Bật is_closeout chặn bán quá tồn kho cho toàn bộ sản phẩm
php bin/console anivele:catalog:build-variants --closeout
```

`--all` bỏ qua sản phẩm thiếu trục màu hoặc size và **báo lại danh sách** ở cuối, không làm dừng cả lượt chạy.

Lệnh **idempotent**: chạy lại không tạo trùng. Sản phẩm đã có variant sẽ bị bỏ qua.

---

## Backup trước khi chạy

Bắt buộc — xem [00-backup.md](00-backup.md).

```bash
mysqldump -h 127.0.0.1 -P 3306 -u root \
  --single-transaction --routines --triggers --events \
  --set-gtid-purged=OFF shopware \
  > ~/shopware-backups/phase2-before-$(date +%Y%m%d-%H%M%S).sql
```

> Máy hiện tại chạy **DBngin + Herd**, không phải Docker. MySQL ở `127.0.0.1:3306`, storefront ở `http://shopware.test`.

---

## Lệnh làm những gì

**Nhóm Hoạ tiết** — tạo nhóm property mới, chuyển `Hoa nhiều màu` từ nhóm Màu sắc sang, thêm 4 giá trị: Hoa nhí, Kẻ sọc, Trơn, Hoạ tiết khác.

Option được chuyển **giữ nguyên id** nên liên kết `product_property` không đứt.

**Sinh variant** — với mỗi sản phẩm:

1. Đọc property của sản phẩm, tách thành trục Màu và trục Size
2. Sinh variant cho mọi tổ hợp màu × size
3. Đặt `configuratorSettings` để storefront hiện bộ chọn
4. Chia đều tồn kho của sản phẩm cha cho các variant, **phần dư rải vào các variant đầu** để tổng khớp đúng tồn kho cha
5. Bật `isCloseout` trên từng variant

SKU sinh theo ADR-003: `ANV-1004-DEN-M` (mã mẫu – màu 3 ký tự – size).

> Giá **không** đặt riêng cho variant — thừa kế từ sản phẩm cha. Chỉ đặt giá riêng khi size đó thực sự khác giá.

---

## Kiểm chứng sau khi chạy

### Cấu trúc trong DB

```sql
SELECT product_number, stock, available_stock, available, is_closeout
FROM product
WHERE parent_id = (SELECT id FROM product WHERE product_number='ANV-1004')
ORDER BY product_number;
```

Kỳ vọng: mỗi tổ hợp một dòng, `is_closeout = 1`.

**Luôn đối chiếu tổng tồn kho variant với tồn kho cha:**

```sql
SELECT (SELECT SUM(stock) FROM product WHERE parent_id=c.id) AS tong_variant, c.stock AS ton_cha
FROM product c WHERE c.product_number='ANV-1005';
```

Hai số phải **bằng nhau**. Nếu lệch là tồn kho bị mất khi chia — ví dụ 68 chia 8 variant, nếu không xử lý phần dư sẽ chỉ còn 64.

> ⚠️ Lỗi này không lộ ra khi tồn kho chia hết (72 ÷ 4 = 18). Chỉ phát hiện được ở sản phẩm chia lẻ, nên phải kiểm tra ở mẫu nhiều màu.

Đối chiếu cả catalog một lượt:

```sql
SELECT c.product_number,
       (SELECT SUM(v.stock) FROM product v WHERE v.parent_id=c.id) AS tong_variant,
       c.stock AS ton_cha
FROM product c
WHERE c.parent_id IS NULL
  AND (SELECT SUM(v.stock) FROM product v WHERE v.parent_id=c.id) <> c.stock;
```

Kết quả phải **rỗng**.

### Sản phẩm hoạ tiết phải có màu nền

Sản phẩm chỉ gắn hoạ tiết mà không có màu trong nhóm **Màu sắc** sẽ không sinh được variant (thiếu một trục).

`ANV-1001` gặp đúng lỗi này sau bước tách nhóm Hoạ tiết: màu duy nhất của nó là "Hoa nhiều màu", vốn đã chuyển sang nhóm Hoạ tiết. Xử lý bằng cách gán màu nền theo ảnh thật:

```bash
php bin/console anivele:catalog:build-variants --set-color=ANV-1001:Kem
```

> Khi nhập hàng thật: **hoạ tiết là thuộc tính riêng, không thay thế màu nền.** Váy hoa nền kem phải có cả `Kem` (màu) lẫn `Hoa nhí` (hoạ tiết).

### Storefront

```bash
php bin/console dal:refresh:index
curl -s "http://shopware.test/Dam-den-co-chu-V-dang-xoe/ANV-1004" | grep -c "configurator-option"
```

Trang sản phẩm phải hiện bộ chọn size (S/M/L/XL) và màu.

### Chặn bán khi hết hàng — kiểm chứng quan trọng nhất

Đây là lý do tồn tại của cả phase. Test bằng Store API:

```bash
AK=$(mysql -uroot -h127.0.0.1 -P3306 shopware -N -e \
  "SELECT access_key FROM sales_channel WHERE id=(SELECT sales_channel_id FROM sales_channel_domain WHERE url LIKE 'http://shopware.test%' LIMIT 1);")

CTX=$(curl -s "http://shopware.test/store-api/context" -H "sw-access-key: $AK" \
  | python3 -c "import sys,json;print(json.load(sys.stdin)['token'])")

PID=$(mysql -uroot -h127.0.0.1 -P3306 shopware -N -e \
  "SELECT LOWER(HEX(id)) FROM product WHERE product_number='ANV-1004-DEN-M';")

curl -s -X POST "http://shopware.test/store-api/checkout/cart/line-item" \
  -H "sw-access-key: $AK" -H "sw-context-token: $CTX" -H "Content-Type: application/json" \
  -d "{\"items\":[{\"type\":\"product\",\"referencedId\":\"$PID\",\"quantity\":1}]}"
```

Kết quả đã kiểm chứng ngày 26/07/2026 trên `ANV-1004`:

| Trường hợp | Kết quả |
|---|---|
| Size M, tồn kho 18 | ✅ Vào giỏ — 1 dòng, giá `1.390.000 ₫` |
| Size S, tồn kho 0 | ✅ Bị chặn — 0 dòng, lỗi `"no longer available"` |

Trang variant hết hàng **không render nút mua**, hiện "Không còn khả dụng nữa".

> ⚠️ Bộ chọn size trên trang cha **vẫn hiện size hết hàng** ở trạng thái chọn được (`is-combinable`). Đây là hành vi mặc định của Shopware: khách chọn được nhưng bị chặn ở bước thêm vào giỏ. Nếu muốn gạch/mờ size hết hàng ngay trên bộ chọn thì phải tuỳ biến theme ở Phase 3.

---

## Khôi phục nếu sai

```bash
mysql -h 127.0.0.1 -P 3306 -u root shopware < ~/shopware-backups/phase2-before-<timestamp>.sql
php bin/console cache:clear
php bin/console dal:refresh:index
```

Xoá variant của một sản phẩm mà không khôi phục toàn bộ:

```sql
DELETE FROM product WHERE parent_id = (SELECT id FROM (SELECT id FROM product WHERE product_number='ANV-1004') AS t);
DELETE FROM product_configurator_setting WHERE product_id = (SELECT id FROM (SELECT id FROM product WHERE product_number='ANV-1004') AS t);
UPDATE product SET child_count = 0 WHERE product_number = 'ANV-1004';
```

> Phải reset `child_count`, nếu không lệnh thấy sản phẩm "đã có variant" và bỏ qua. Xoá bằng SQL không cập nhật cột này.

Sau đó chạy lại lệnh.
