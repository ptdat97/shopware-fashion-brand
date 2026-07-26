# Runbook: Backup & Khôi phục

**Chạy trước mọi thao tác thay đổi cấu hình hoặc dữ liệu.**

## Phiên bản công cụ

Đã kiểm chứng trên máy hiện tại (26/07/2026):

```
mysqldump  Ver 8.0.33 for macos13 on arm64
Server version: 8.0.33
```

> ✅ **Client và server đã khớp phiên bản.** Trước đây server chạy 9.7.1 trong khi `mysqldump` là 8.0.33 — chênh một major version khiến backup không đáng tin. Đã xử lý bằng cách migrate về 8.0.33, xem [../decisions/ADR-001-mysql.md](../decisions/ADR-001-mysql.md).

Dù vậy nguyên tắc vẫn giữ nguyên: **backup chưa từng được khôi phục thử thì không phải backup.** Xem mục test khôi phục bên dưới.

---

## Backup database

```bash
BACKUP_DIR=~/shopware-backups
mkdir -p "$BACKUP_DIR"

mysqldump -h shopware.test -u root \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --set-gtid-purged=OFF \
  shopware > "$BACKUP_DIR/shopware-$(date +%Y%m%d-%H%M%S).sql"
```

Giải thích tham số:

| Tham số | Vì sao cần |
|---|---|
| `--single-transaction` | Dump nhất quán mà không khóa bảng |
| `--routines` | Kèm stored procedure/function |
| `--triggers` | Kèm trigger |
| `--events` | Kèm scheduled event |
| `--set-gtid-purged=OFF` | Không nhúng GTID vào dump — nếu thiếu sẽ có warning và có thể gây lỗi khi khôi phục sang server khác |

> Thiếu `--set-gtid-purged=OFF`, `mysqldump` sẽ cảnh báo:
> *"A partial dump from a server that has GTIDs will by default include the GTIDs of all transactions..."*

## Backup file

Database không chứa file ảnh và file cấu hình:

```bash
tar -czf "$BACKUP_DIR/files-$(date +%Y%m%d-%H%M%S).tar.gz" \
  -C /Users/dat/Ecommerce/shopware \
  public/media \
  public/thumbnail \
  custom \
  .env.local \
  composer.json \
  composer.lock
```

> `public/media` chứa toàn bộ ảnh sản phẩm. Mất thư mục này thì database còn nguyên vẫn không hiển thị được ảnh.

---

## Backup nhanh một bảng

Khi chỉ sửa một phần nhỏ, ví dụ trước khi đổi currency:

```bash
mysqldump -h shopware.test -u root shopware \
  currency currency_translation sales_channel \
  > ~/shopware-backups/currency-before-$(date +%Y%m%d-%H%M%S).sql
```

Nhanh hơn nhiều so với dump toàn bộ.

---

## Khôi phục

```bash
# Khôi phục database
mysql -h shopware.test -u root shopware < ~/shopware-backups/shopware-XXXXXX.sql

# Xóa cache sau khi khôi phục — bắt buộc
php bin/console cache:clear
```

Khôi phục file:

```bash
tar -xzf ~/shopware-backups/files-XXXXXX.tar.gz \
  -C /Users/dat/Ecommerce/shopware
```

---

## Test khôi phục (không được bỏ qua)

Backup chưa test không có giá trị. Quy trình test:

```bash
# 1. Tạo database trống
mysql -h shopware.test -u root -e "CREATE DATABASE shopware_test_restore;"

# 2. Khôi phục vào đó
mysql -h shopware.test -u root shopware_test_restore < ~/shopware-backups/shopware-XXXXXX.sql

# 3. Kiểm tra dữ liệu có đủ không
mysql -h shopware.test -u root shopware_test_restore -e \
  "SELECT (SELECT COUNT(*) FROM product) p,
          (SELECT COUNT(*) FROM \`order\`) o,
          (SELECT COUNT(*) FROM customer) c;"

# 4. Dọn dẹp
mysql -h shopware.test -u root -e "DROP DATABASE shopware_test_restore;"
```

So sánh số lượng với database gốc. Lệch là backup có vấn đề.

### Kiểm tra file dump có đủ schema không

> ⚠️ **Không dùng `grep` để đếm trên file dump.** Dump của Shopware chứa dòng `INSERT` dài trên 31.000 ký tự, khiến `grep -c "CREATE TABLE"` trả về **rỗng** — trông hệt như dump hỏng dù file hoàn toàn bình thường. Đây là bẫy đã gặp thật khi migrate.

Dùng script đọc theo dòng:

```bash
php -r '
$h = fopen($argv[1], "r"); $c = 0; $i = 0;
while (($l = fgets($h)) !== false) {
  if (strpos($l, "CREATE TABLE") === 0) $c++;
  if (strpos($l, "INSERT INTO") === 0) $i++;
}
echo "CREATE TABLE: $c\nINSERT INTO: $i\n";
' ~/shopware-backups/shopware-XXXXXX.sql
```

Đối chiếu với số bảng thật:

```bash
mysql -h shopware.test -u root -N -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='shopware';"
```

Hai số phải khớp (hiện tại: **260**).

---

## Khuyến nghị cho production

| Hạng mục | Yêu cầu |
|---|---|
| Tần suất | Hằng ngày, tự động |
| Lưu trữ | **Khác máy chủ** — backup cùng máy vô nghĩa khi máy hỏng |
| Thời gian giữ | Tối thiểu 30 ngày |
| Test khôi phục | Định kỳ, có ghi nhận kết quả |
| Trước khi deploy | Luôn backup thủ công |

> Backup lưu cùng máy với production không bảo vệ được trong tình huống hỏng ổ cứng hoặc mất máy chủ.
