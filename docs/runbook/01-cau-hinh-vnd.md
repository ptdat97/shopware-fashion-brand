# Runbook: Cấu hình VND

> **Backup trước khi bắt đầu.** Xem [00-backup.md](00-backup.md).

## Bối cảnh

Hệ thống hiện chỉ có USD:

```
iso_code  symbol  position  item_rounding
USD       $       1         {"decimals": "2", "interval": 0.01, "roundForNet": true}
```

Cần tạo VND và đặt làm mặc định cho sales channel `anivele`.

## Điểm mấu chốt: làm tròn

VND không có đơn vị nhỏ hơn đồng. Cấu hình rounding phải là:

```json
{"decimals": 0, "interval": 1, "roundForNet": true}
```

| Trường | VND | Nếu để như USD (sai) |
|---|---|---|
| `decimals` | `0` | `2` → `250.000,00 ₫` |
| `interval` | `1` | `0.01` → làm tròn tới phần trăm đồng |

Sai chỗ này sẽ sinh lỗi lệch tiền khi tính giảm giá và thuế.

---

## Cách 1 — Qua Administration (khuyến nghị)

An toàn hơn vì Shopware tự xử lý các ràng buộc liên quan.

1. Đăng nhập `/admin`
2. **Settings → Currencies → Add currency**
3. Điền:

| Trường | Giá trị |
|---|---|
| Name | Việt Nam Đồng |
| Short name | VND |
| ISO code | VND |
| Symbol | ₫ |
| Symbol position | After price (sau giá) |
| Factor | tỷ giá so với USD hiện tại |
| Decimal precision | **0** |
| Rounding interval | **1** |

4. **Settings → Sales Channels → anivele → Currency**: thêm VND, đặt làm mặc định
5. Kiểm tra lại storefront

> **Về `factor`:** đây là tỷ giá quy đổi so với đồng tiền hệ thống (hiện là USD, factor = 1). Cần điền tỷ giá thực tế tại thời điểm cấu hình. Tài liệu này không ghi con số vì tỷ giá biến động — điền số cũ sẽ làm sai toàn bộ giá.

---

## Cách 2 — Qua SQL

Chỉ dùng khi cần tự động hóa. **Rủi ro cao hơn**, phải backup trước.

```sql
-- Thay <FACTOR> bằng tỷ giá thực tế so với USD
SET @vnd = UNHEX(REPLACE(UUID(), '-', ''));
SET @lang_vi = UNHEX('019f9d8e87d379bfaf35fa4338dcd841');
SET @lang_en = UNHEX('2fbb5fe2e29a4d70aa5854ce7ce3e20b');

INSERT INTO currency
  (id, iso_code, factor, symbol, position, item_rounding, total_rounding, created_at)
VALUES (
  @vnd, 'VND', <FACTOR>, '₫', 2,
  '{"decimals": 0, "interval": 1, "roundForNet": true}',
  '{"decimals": 0, "interval": 1, "roundForNet": true}',
  NOW(3)
);

INSERT INTO currency_translation (currency_id, language_id, short_name, name, created_at)
VALUES
  (@vnd, @lang_vi, 'VND', 'Việt Nam Đồng', NOW(3)),
  (@vnd, @lang_en, 'VND', 'Vietnamese Dong', NOW(3));
```

Gán cho sales channel `anivele`:

```sql
SET @sc = UNHEX('019f9d8157c5724999d4b32d220a5b41');

INSERT IGNORE INTO sales_channel_currency (sales_channel_id, currency_id)
SELECT @sc, id FROM currency WHERE iso_code = 'VND';

UPDATE sales_channel
SET currency_id = (SELECT id FROM currency WHERE iso_code = 'VND')
WHERE id = @sc;
```

Xóa cache:

```bash
php bin/console cache:clear
```

---

## Kiểm chứng

```bash
mysql -h shopware.test -u root shopware -e \
  "SELECT iso_code, symbol, position, item_rounding FROM currency;"
```

Kỳ vọng: có dòng VND với `position = 2`, `decimals: 0`.

```bash
mysql -h shopware.test -u root shopware -e \
  "SELECT t.name, c.iso_code FROM sales_channel sc
   JOIN sales_channel_translation t ON t.sales_channel_id = sc.id
   JOIN currency c ON c.id = sc.currency_id;"
```

Kỳ vọng: `anivele` → `VND`.

Cuối cùng mở storefront, kiểm tra giá hiển thị dạng `1.250.000 ₫`.

---

## Nếu hiển thị sai

| Triệu chứng | Nguyên nhân | Cách sửa |
|---|---|---|
| `250.000,00 ₫` | `decimals` = 2 | Sửa về 0, xóa cache |
| `₫250.000` | `position` = 1 | Đổi thành 2 |
| `250,000 ₫` | Định dạng số theo locale khác | Kiểm tra locale của ngôn ngữ `vi-VN` |
| Giá vẫn USD | Cache | `php bin/console cache:clear` |

---

## Việc cần làm tiếp

Sau khi đổi currency, **giá của toàn bộ sản phẩm cần nhập lại** theo VND. Giá cũ được lưu theo USD sẽ được quy đổi theo `factor` — kết quả thường ra số lẻ xấu (`1.247.383 ₫`). Với hàng thật cần đặt giá tròn (`1.250.000 ₫`).

Vì demo data sẽ bị xóa nên vấn đề này chỉ thực sự phát sinh khi nhập catalog thật.
