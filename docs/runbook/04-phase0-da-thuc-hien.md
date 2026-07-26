# Runbook: Phase 0 — Đã thực hiện

**Ngày:** 26/07/2026 · **Trạng thái:** ✅ Hoàn tất

Ghi lại chính xác những gì đã làm, để tái lập trên môi trường khác (staging/production).

## Backup trước khi bắt đầu

```
~/shopware-backups/phase0-before-20260726-164635.sql  (2.5M)
```

---

## 1. Tiền tệ VND

### Quyết định quan trọng: chuyển đổi tại chỗ, không tạo row mới

Khi khảo sát phát hiện row USD hiện có **chính là `Defaults::CURRENCY`** — hằng số hardcode trong core Shopware:

```php
// vendor/shopware/core/Defaults.php:25
public const CURRENCY = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';
```

Hằng số này được dùng khắp logic giá của core (`PriceCollection`, `PriceFieldSerializer`, `PriceFieldAccessorBuilder`...). Nếu tạo currency VND mới rồi đặt làm mặc định, hệ thống vẫn coi USD là đồng tiền gốc → sinh currency "ma" và giá bị quy đổi sai.

**Cách đã làm:** chuyển đổi chính row đó thành VND với `factor = 1`.

```sql
UPDATE currency SET
  iso_code = 'VND',
  factor = 1,
  symbol = '₫',
  position = 2,                    -- ký hiệu đứng sau số
  item_rounding  = '{"decimals": 0, "interval": 1, "roundForNet": true}',
  total_rounding = '{"decimals": 0, "interval": 1, "roundForNet": true}'
WHERE id = UNHEX('b7d2554b0ce847cd82f3ac9bd1c0dfca');
```

Kèm cập nhật `currency_translation` cho vi-VN / en-US / de-DE.

**Kết quả kiểm chứng trên storefront:** `1.250.000 ₫` — dấu chấm phân cách nghìn, ký hiệu sau số, không có phần thập phân.

---

## 2. Thuế VAT

```sql
UPDATE tax SET name='VAT 10%', tax_rate=10.000 WHERE id=UNHEX('019f9d80c96d7344a963e905a7ec4f43');
UPDATE tax SET name='VAT 8%',  tax_rate=8.000  WHERE id=UNHEX('019f9d80c96d7344a963e905a7ffb6da');
UPDATE tax SET name='Không chịu thuế (0%)', tax_rate=0.000 WHERE id=UNHEX('019f9d80ceb073eb95b2e17a1867d70b');
```

Giữ cả 10% và 8% để linh hoạt theo chính sách giảm thuế từng thời kỳ.

> ⚠️ **Vẫn cần xác nhận với kế toán** mức VAT áp dụng cho hàng may mặc tại thời điểm go-live.

`display_gross = 1` đã bật sẵn — giá hiển thị đã gồm VAT, đúng thói quen bán lẻ VN.

---

## 3. Quốc gia

```sql
UPDATE country SET active = 0 WHERE iso <> 'VN';
UPDATE country SET active = 1, shipping_available = 1 WHERE iso = 'VN';

-- Dọn liên kết sales channel tới quốc gia đã tắt
DELETE scc FROM sales_channel_country scc
JOIN country c ON c.id = scc.country_id WHERE c.active = 0;
```

Từ **250 quốc gia active → 1**.

### Bẫy đã gặp

Sau khi tắt Germany, sales channel `Headless` vẫn trỏ `country_id` tới Germany (đã inactive) → sẽ lỗi khi gọi API. Đã sửa:

```sql
UPDATE sales_channel SET country_id = (SELECT id FROM country WHERE iso='VN')
WHERE id = UNHEX('98432def39fc4624b33213a56b8c944d');
```

> **Bài học:** tắt quốc gia phải kiểm tra lại `sales_channel.country_id` của **mọi** channel, kể cả channel không dùng tới.

---

## 4. Tên tiếng Việt cho quốc gia

Phát hiện: **không quốc gia nào có bản dịch `vi-VN`** (0 dòng trong `country_translation`). Storefront tiếng Việt sẽ hiện "Viet Nam" tiếng Anh.

```sql
INSERT INTO country_translation (country_id, language_id, name, created_at)
SELECT c.id, UNHEX('019f9d8e87d379bfaf35fa4338dcd841'), 'Việt Nam', NOW(3)
FROM country c WHERE c.iso='VN'
ON DUPLICATE KEY UPDATE name='Việt Nam';
```

Kiểm chứng: form đăng ký hiển thị **"Việt Nam"**.

---

## 5. Địa chỉ Việt Nam

```sql
UPDATE country SET
  postal_code_required = 0,              -- người Việt hầu như không dùng mã bưu chính
  check_postal_code_pattern = 0,
  check_advanced_postal_code_pattern = 0,
  display_state_in_registration = 1,     -- hiện dropdown Tỉnh/Thành phố
  force_state_in_registration = 1        -- bắt buộc chọn
WHERE iso = 'VN';
```

Cấu hình thứ tự trường địa chỉ:

```bash
php bin/console system:config:set core.loginRegistration.addressInputFieldArrangement city-state-zip
```

Ba giá trị hợp lệ: `city-zip-state`, `zip-city-state`, `city-state-zip`. Chọn `city-state-zip` vì mã bưu chính là tuỳ chọn ở VN nên đưa xuống cuối.

`phoneNumberFieldRequired` đã bật sẵn — số điện thoại bắt buộc, đúng nhu cầu giao hàng VN.

---

## 6. Danh mục Tỉnh/Thành phố

**Bẫy quan trọng:** sau khi bật `force_state_in_registration = 1` mà bảng `country_state` có **0 dòng** cho VN → khách không thể hoàn tất đăng ký. Phải nạp dữ liệu ngay.

Đã nạp **34 tỉnh/thành phố** theo cơ cấu hành chính hiện hành, kèm bản dịch cho vi-VN / en-US / de-DE (102 dòng dịch).

Kiểm chứng qua Store API:

```bash
curl -X POST "http://shopware.test/store-api/country-state/<VN_ID>" \
  -H "sw-access-key: <KEY>" -H "Content-Type: application/json" -d '{}'
# → 34 tỉnh, tên tiếng Việt đúng dấu
```

> ⚠️ Danh mục đơn vị hành chính VN có thay đổi theo các đợt sáp nhập. **Kiểm tra lại với nguồn chính thức trước khi go-live.**

---

## 7. Xoá demo data cũ

```bash
php bin/console plugin:deactivate SwagPlatformDemoData
php bin/console plugin:uninstall SwagPlatformDemoData
composer remove swag/demo-data
```

Kiểm tra an toàn trước khi xoá: `order = 0`, `order_line_item = 0` ✅

Gỡ plugin đã dọn sạch: 16 product → 0, property_group → 0, customer → 0. Chỉ còn category gốc (bắt buộc phải có cho sales channel), đã đổi tên thành "Danh mục chính".

---

## 8. Dữ liệu demo mới

Tạo plugin riêng `custom/static-plugins/AniveleDemoData` với ảnh đóng gói kèm — xem [05-demo-data.md](05-demo-data.md).

---

## Kết quả cuối

| Hạng mục | Trước | Sau |
|---|---|---|
| Tiền tệ | USD, decimals=2 | **VND, factor=1, decimals=0, ký hiệu sau** |
| Thuế | 19% / 7% (Đức) | **10% / 8% / 0% (VN)** |
| Quốc gia active | 250 | **1 (Việt Nam)** |
| Tỉnh/Thành phố | 0 | **34** |
| Mã bưu chính | bắt buộc | **không bắt buộc** |
| Tên nước tiếng Việt | không có | **"Việt Nam"** |
| Sản phẩm | 16 demo (giá USD) | **12 sản phẩm thời trang, giá VND** |

Kiểm chứng storefront:
```
Trang chủ / danh mục / chi tiết sản phẩm: HTTP 200
Giá hiển thị: 1.250.000 ₫  (đúng chuẩn VN)
Menu: Đầm · Áo · Chân váy · Bộ sưu tập
Form đăng ký: "Việt Nam", 34 tỉnh qua API
```
