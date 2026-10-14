# Tổng hợp lệnh Symfony & Shopware

Toàn bộ lệnh trong tài liệu này **đã được kiểm chứng tồn tại** trên hệ thống (Shopware 6.7.12.2, 184 lệnh khả dụng). Mô tả lấy trực tiếp từ `bin/console list`.

Mọi lệnh chạy từ thư mục gốc dự án:

```bash
cd /Users/dat/Ecommerce/shopware
```

## Quy ước ký hiệu

| Ký hiệu | Ý nghĩa |
|---|---|
| 🟢 | An toàn, chạy thoải mái |
| 🟡 | Cần cân nhắc — ảnh hưởng hiệu năng hoặc gây downtime ngắn |
| 🔴 | **Nguy hiểm** — có thể mất dữ liệu, backup trước khi chạy |

---

## 1. Lệnh dùng hằng ngày

Ba lệnh chiếm phần lớn công việc thường ngày:

```bash
# 🟢 Xoá cache — chạy sau mọi thay đổi cấu hình, template, snippet
php bin/console cache:clear

# 🟢 Biên dịch theme — sau khi sửa SCSS/Twig
php bin/console theme:compile

# 🟢 Đánh lại chỉ mục — sau khi sửa dữ liệu trực tiếp trong DB
php bin/console dal:refresh:index
```

> **Nguyên tắc:** sửa gì trong Administration mà không thấy đổi ngoài storefront → `cache:clear` trước khi nghi ngờ lỗi.
>
> 🔴 **`cache:clear` không xoá HTTP cache** (đã kiểm ở Shopware 6.7.15.1). Sửa template/Twig/code mà trang vẫn cũ → chạy thêm `php bin/console cache:clear:http`. Kiểm nhanh: header `Age:` khác 0 là đang nhận bản cache.

> 🔴 **Vừa xoá class trong plugin** (đổi tên, refactor) → `rm -rf var/cache/prod_*` trước. Container đã biên dịch vẫn trỏ tới class cũ, mọi lệnh `bin/console` kể cả `cache:clear` đều lỗi `Class ... not found`. Thư mục này chỉ là cache, xoá an toàn.

```bash
# 🟢 Sinh lại URL /brands/... — sau khi cài/cập nhật AcmeBrand hoặc sửa template SEO
php bin/console dal:refresh:index --only=acme_brand.indexer
```

---

## 2. Cache

| Lệnh | Mô tả | Mức |
|---|---|---|
| `cache:clear` | Xoá cache | 🟢 |
| `cache:clear:http` | Chỉ xoá HTTP cache — nhanh hơn nhiều | 🟢 |
| `cache:clear:all` | Xoá toàn bộ cache pool, cache cũ của system và twig | 🟡 |
| `cache:warmup` | Làm nóng cache — chạy sau `cache:clear` trên production | 🟢 |
| `cache:pool:list` | Liệt kê các cache pool | 🟢 |
| `cache:pool:prune` | Dọn cache hết hạn | 🟢 |

Tuỳ chọn hữu ích:

```bash
# Xoá cache nhanh, bỏ qua warmer không bắt buộc
php bin/console cache:clear --no-optional-warmers

# Xoá mà không tự warmup (nhanh nhất, nhưng request đầu sẽ chậm)
php bin/console cache:clear --no-warmup
```

**Trên production:** luôn `cache:clear` rồi `cache:warmup`, tránh để khách đầu tiên gánh chi phí dựng cache.

---

## 3. Plugin

```bash
# 🟢 Xem danh sách plugin và trạng thái
php bin/console plugin:list

# 🟢 Quét lại thư mục plugin — chạy sau khi thêm plugin mới vào custom/plugins/
php bin/console plugin:refresh

# 🟡 Cài + kích hoạt
php bin/console plugin:install --activate TenPlugin

# 🟡 Bật / tắt
php bin/console plugin:activate TenPlugin
php bin/console plugin:deactivate TenPlugin

# 🔴 Gỡ cài đặt — CÓ THỂ XOÁ DỮ LIỆU của plugin
php bin/console plugin:uninstall TenPlugin

# 🟡 Cập nhật
php bin/console plugin:update TenPlugin
php bin/console plugin:update:all
```

> 🔴 **`plugin:uninstall` thường xoá luôn dữ liệu plugin tạo ra.** Backup trước. Ví dụ thực tế đã gặp: gỡ `SwagPlatformDemoData` ở Phase 0 đã xoá sạch 16 sản phẩm demo và toàn bộ property group.
>
> Ngoại lệ có chủ đích: `AcmeVietnam` **không** hoàn tác cấu hình VND/thuế khi gỡ — đưa currency về USD trong khi shop đã có đơn giá VND sẽ làm sai toàn bộ số tiền.

**Quy trình chuẩn khi cài plugin mới:**

Plugin **tự viết** — đặt thư mục vào `custom/plugins/` rồi:

```bash
php bin/console plugin:refresh
php bin/console plugin:install --activate TenPlugin
php bin/console cache:clear
```

> ⚠️ **Không chạy `composer require` cho plugin tự viết.** Shopware tự quét `custom/plugins/`, thêm vào `composer.json` chỉ gây xung đột merge và phình `composer.lock`. Xem [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) — Quy tắc số 2.

Plugin **bên thứ ba** từ Packagist / Shopware Store — buộc phải tải về:

```bash
composer require nha-cung-cap/ten-plugin
php bin/console plugin:refresh
php bin/console plugin:install --activate TenPlugin
php bin/console cache:clear
```

---

## 4. Theme

```bash
# 🟢 Tạo theme mới (thêm --static để đặt trong custom/static-plugins/)
php bin/console theme:create AcmeTheme

# 🟡 Gán theme cho sales channel
php bin/console theme:change AcmeTheme -s <sales-channel-id>
php bin/console theme:change AcmeTheme --all

# 🟢 Biên dịch
php bin/console theme:compile

# 🟢 Nạp lại cấu hình theme sau khi sửa theme.json
php bin/console theme:refresh

# 🟢 Xem cấu hình theme hiện tại
php bin/console theme:dump
```

ID sales channel `anivele` của dự án này:

```
019f9d8157c5724999d4b32d220a5b41
```

> Sửa `theme.json` mà không thấy biến mới trong Administration → cần `theme:refresh`, không phải `cache:clear`.

---

## 5. Người dùng Administration

```bash
# 🟢 Liệt kê user
php bin/console user:list

# 🟡 Tạo user admin
php bin/console user:create <username> \
  --admin \
  --password='<mat-khau>' \
  --email='<email>' \
  --firstName='<ten>' \
  --lastName='<ho>'

# 🟡 Đổi mật khẩu
php bin/console user:change-password <username> --password='<mat-khau-moi>'
```

> **Shopware đăng nhập bằng username, không phải email.** Tài khoản dự án này: `ptdat97`.

> ⚠️ Truyền mật khẩu qua `--password` sẽ lưu vào lịch sử shell. Bỏ cờ này để lệnh hỏi tương tác, an toàn hơn cho production.

---

## 6. Sales Channel

```bash
# 🟢 Liệt kê — hiển thị cả ngôn ngữ, tiền tệ, domain
php bin/console sales-channel:list

# 🟡 Tạo storefront mới
php bin/console sales-channel:create:storefront --name="Tên" --url="https://..."

# 🟡 Bật/tắt chế độ bảo trì
php bin/console sales-channel:maintenance:enable -s <id>
php bin/console sales-channel:maintenance:disable -s <id>

# 🟡 Đổi domain
php bin/console sales-channel:update:domain <domain-moi>
```

`sales-channel:list` rất hữu ích để kiểm tra nhanh cấu hình ngôn ngữ/tiền tệ mà không cần truy vấn SQL.

---

## 7. Database & Migration

```bash
# 🟢 Kiểm tra hệ thống đã cài chưa (dùng trong script CI)
php bin/console system:is-installed

# 🟡 Chạy migration
php bin/console database:migrate --all

# 🔴 Migration phá huỷ — XOÁ CỘT/BẢNG CŨ
php bin/console database:migrate-destructive

# 🔴 Xoá dữ liệu cá nhân
php bin/console database:clean-personal-data
```

> 🔴 **`database:migrate-destructive` không thể hoàn tác.** Mặc định chạy ở chế độ `--version-selection-mode=safe`. **Luôn backup trước.**

---

## 8. Chỉ mục (Indexing)

```bash
# 🟢 Đánh lại toàn bộ chỉ mục
php bin/console dal:refresh:index

# 🟢 Chỉ đánh lại một indexer cụ thể — nhanh hơn nhiều
php bin/console dal:refresh:index --only=product.indexer

# 🟢 Bỏ qua indexer nào đó
php bin/console dal:refresh:index --skip=product.indexer

# 🟡 Đẩy vào hàng đợi thay vì chạy trực tiếp
php bin/console dal:refresh:index --use-queue
```

**Khi nào cần:** sau khi sửa dữ liệu trực tiếp bằng SQL, hoặc khi sản phẩm không hiện đúng ngoài storefront.

> Trên site nhiều sản phẩm, `dal:refresh:index` chạy rất lâu. Dùng `--only` để giới hạn phạm vi.

---

## 9. Media

```bash
# 🟢 Xem trước file sẽ bị xoá — LUÔN chạy trước
php bin/console media:delete-unused --dry-run

# 🔴 Xoá thật
php bin/console media:delete-unused

# 🟡 Sinh lại thumbnail
php bin/console media:generate-thumbnails
```

> ⚠️ **`media:delete-unused` mặc định bỏ qua file tải lên trong 20 ngày gần nhất** (`--grace-period-days=20`). Nếu thấy lệnh "không xoá gì", nguyên nhân thường là đây:
>
> ```bash
> php bin/console media:delete-unused --grace-period-days=0 --dry-run
> ```

---

## 10. Scheduled Task & Message Queue

Hai thành phần này **bắt buộc chạy nền trên production**, nếu không sẽ không có email, không sinh sitemap, không dọn log.

```bash
# 🟢 Xem danh sách task và lần chạy kế tiếp
php bin/console scheduled-task:list

# 🟡 Chạy worker xử lý task định kỳ
php bin/console scheduled-task:run

# 🟡 Chạy worker xử lý hàng đợi
php bin/console messenger:consume async --time-limit=300

# 🟢 Xem số message đang chờ
php bin/console messenger:stats

# 🟢 Xem message lỗi
php bin/console messenger:failed:show
```

> **Trên production** cần chạy các worker này qua supervisor/systemd, kèm `--time-limit` để worker tự khởi động lại định kỳ, tránh rò rỉ bộ nhớ.

---

## 11. Kiểm tra hệ thống

```bash
# 🟢 Kiểm tra sức khoẻ hệ thống
php bin/console system:check

# 🟢 Xem/đặt config
php bin/console system:config:get core.basicInformation.shopName
php bin/console system:config:set <key> <value>

# 🟢 Thông tin môi trường
php bin/console about
```

> ⚠️ **`system:check` chạy qua CLI sẽ bỏ qua phần lớn kiểm tra.** Kết quả thực tế trên hệ thống này:
> ```
> LocalesReadiness          OK
> AdministrationReadiness   SKIPPED  (không chạy được trong CLI)
> SalesChannelsReadiness    SKIPPED
> ProductDetailReadiness    SKIPPED
> ProductListingReadiness   SKIPPED
> ```
> Muốn kiểm tra đầy đủ phải gọi qua HTTP, không phải CLI. Đừng nhầm "SKIPPED" là "OK".

---

## 12. Debug (Symfony)

```bash
# 🟢 Xem route
php bin/console debug:router
php bin/console debug:router | grep <tu-khoa>

# 🟢 Xem service trong container
php bin/console debug:container <ten-service>

# 🟢 Xem biến môi trường Symfony đọc được
php bin/console debug:dotenv

# 🟢 Xem cấu hình bundle
php bin/console debug:config <ten-bundle>

# 🟢 Xem event listener
php bin/console debug:event-dispatcher
```

Rất hữu ích khi phát triển plugin: `debug:container` giúp xác nhận service đã đăng ký đúng chưa.

---

## 13. Sinh mã (phát triển plugin)

```bash
php bin/console plugin:create <TenPlugin>

php bin/console make:plugin:storefront-controller
php bin/console make:plugin:entity
php bin/console make:plugin:event-subscriber
php bin/console make:plugin:scheduled-task
php bin/console make:plugin:config
```

Xem đầy đủ 14 lệnh `make:*`:

```bash
php bin/console list make
```

---

## 14. Sitemap & SEO

```bash
# 🟡 Sinh sitemap
php bin/console sitemap:generate
```

Trên production nên để scheduled task tự chạy thay vì gọi thủ công.

---

## 15. Elasticsearch / OpenSearch

Dự án đã cấu hình `OPENSEARCH_URL=http://localhost:9200` trong `.env.local` nhưng **chưa xác nhận có đang dùng hay không**.

```bash
php bin/console es:status          # 🟢 Xem trạng thái
php bin/console es:index           # 🟡 Đánh chỉ mục toàn bộ
php bin/console es:index:cleanup   # 🟡 Dọn index cũ
php bin/console es:reset           # 🔴 Xoá và tạo lại index
```

---

## 16. Composer

```bash
# Cài plugin
COMPOSER_HOME=/Users/dat/Ecommerce/shopware/var/cache/composer \
  composer require nha-cung-cap/ten-plugin

# Kiểm tra tương thích TRƯỚC KHI cài — bước quan trọng
COMPOSER_HOME=... composer show -a <ten-plugin> | grep -A5 requires

# Gỡ
COMPOSER_HOME=... composer remove nha-cung-cap/ten-plugin
```

> Dự án này đặt `COMPOSER_HOME` trong `.env.local`. Nếu chạy composer mà gặp lỗi quyền, thêm biến này vào đầu lệnh.

> **Luôn kiểm tra `requires` trước khi cài.** Bài học thực tế: `swag/language-pack` bản release mới nhất chỉ hỗ trợ `>=6.6.0 <6.7`, phải dùng `dev-master` mới chạy được trên 6.7.

---

## 17. Database (MySQL trực tiếp)

```bash
# Kết nối
mysql -h shopware.test -u root shopware

# Backup — xem chi tiết tại runbook/00-backup.md
mysqldump -h shopware.test -u root \
  --single-transaction --routines --triggers --events \
  --set-gtid-purged=OFF shopware > backup.sql

# Khôi phục
mysql -h shopware.test -u root shopware < backup.sql
```

Truy vấn hay dùng cho dự án này:

```bash
# Kiểm tra ngôn ngữ + snippet set theo domain
mysql -h shopware.test -u root shopware -e "
SELECT d.url, lo.code, ss.name FROM sales_channel_domain d
JOIN language l ON l.id=d.language_id
JOIN locale lo ON lo.id=l.locale_id
LEFT JOIN snippet_set ss ON ss.id=d.snippet_set_id;"

# Đếm dữ liệu nhanh
mysql -h shopware.test -u root shopware -e "
SELECT (SELECT COUNT(*) FROM product) products,
       (SELECT COUNT(*) FROM \`order\`) orders,
       (SELECT COUNT(*) FROM customer) customers;"
```

---

## Combo hay dùng

**Sau khi sửa template/SCSS:**
```bash
php bin/console theme:compile && php bin/console cache:clear
```

**Sau khi sửa dữ liệu bằng SQL:**
```bash
php bin/console dal:refresh:index && php bin/console cache:clear
```

**Sau khi cài plugin:**
```bash
php bin/console plugin:refresh \
  && php bin/console plugin:install --activate TenPlugin \
  && php bin/console cache:clear
```

**Kiểm tra nhanh hệ thống còn sống:**
```bash
curl -s -o /dev/null -w "storefront: %{http_code}\n" http://shopware.test/
curl -s -o /dev/null -w "admin: %{http_code}\n" http://shopware.test/admin
```

---

## Lệnh 🔴 cần backup trước

Danh sách cần thuộc lòng:

| Lệnh | Hậu quả |
|---|---|
| `plugin:uninstall` | Xoá dữ liệu plugin tạo ra |
| `database:migrate-destructive` | Xoá cột/bảng, không hoàn tác |
| `media:delete-unused` | Xoá file ảnh vật lý |
| `es:reset` | Xoá index tìm kiếm |
| `database:clean-personal-data` | Xoá dữ liệu khách hàng |

Backup: [runbook/00-backup.md](runbook/00-backup.md)

---

## Ghi chú

- Thêm `--no-interaction` (hoặc `-n`) khi chạy trong script tự động
- Thêm `-v` / `-vv` / `-vvv` để xem chi tiết khi gỡ lỗi
- Xem đầy đủ 184 lệnh: `php bin/console list`
- Xem trợ giúp lệnh cụ thể: `php bin/console <lenh> --help`
