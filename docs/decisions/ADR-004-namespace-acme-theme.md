# ADR-004: Đổi namespace `AcmeTheme\` → `Acme\Theme\`

**Trạng thái:** ✅ Đã làm — 14/10/2026 (AcmeTheme 1.0.2)
**Quyết định:** Đổi namespace PHP của `AcmeTheme` sang `Acme\Theme\` cho đúng [STR-01](../13-chuan-phat-trien-plugin.md). Không còn ngoại lệ nào của STR-01.

Bản đầu của ADR (cùng ngày) chọn **giữ** namespace cũ. Chủ dự án quyết đổi vì chưa có staging/production — đây là lúc rẻ nhất, sau go-live mỗi môi trường đều phải chuyển.

## Vì sao không chỉ sửa code

Shopware nhận diện plugin đã cài theo **`plugin.base_class`** (`PluginService::refreshPlugins()` tìm bằng `filterByProperty('baseClass', …)`), nạp class theo cột **`plugin.autoload`** trong DB lúc khởi động kernel, và ghi migration đã chạy theo **tên class đầy đủ**. Bảng `plugin` có `UNIQUE(name)` và `UNIQUE(base_class)`.

Chỉ sửa code rồi `plugin:refresh`:

1. Không tìm thấy bản ghi theo base class mới → tạo bản ghi `AcmeTheme` mới → vướng `UNIQUE(name)`; bản ghi cũ bị xoá vì "không còn trên đĩa".
2. Lần refresh sau mới tạo được, ở trạng thái **chưa cài** → storefront dùng theme của plugin không chạy cho tới khi cài lại.
3. Migration chạy lại dưới tên mới (`EnableWishlist` ghi đè giá trị wishlist).

## Cách chuyển một bản cài cũ (đã làm trên local 14/10/2026)

Chạy **trước** khi khởi động kernel với code mới. Backup trước ([runbook/00](../runbook/00-backup.md)).

```sql
-- 1. Bản ghi plugin: base class + autoload (giữ nguyên id → theme, ACL không đổi)
UPDATE plugin
   SET base_class = 'Acme\\Theme\\AcmeTheme',
       autoload   = '{"psr-4": {"Acme\\\\Theme\\\\": "src/"}}'
 WHERE name = 'AcmeTheme' AND base_class = 'AcmeTheme\\AcmeTheme';

-- 2. Migration đã chạy: đổi tiền tố để không chạy lại
UPDATE migration
   SET class = CONCAT('Acme\\Theme\\Migration\\', SUBSTRING(class, 21))
 WHERE class LIKE 'AcmeTheme\\\\Migration\\\\%';
```

`SUBSTRING(class, 21)`: bỏ 20 ký tự `AcmeTheme\Migration\`. Sau đó:

```bash
rm -rf var/cache/prod_*
bin/console plugin:refresh
bin/console plugin:update AcmeTheme
bin/console theme:compile
bin/console cache:clear && bin/console cache:clear:http
```

Bản cài **mới** không cần bước SQL — cài thẳng code mới.

> ⚠️ Áp cho **mọi DB có bản ghi plugin `AcmeTheme`**, kể cả khi plugin chưa cài — gồm DB test `shopware_test` (đã gặp: `TestBootstrapper` chạy `plugin:refresh` → `Duplicate entry 'AcmeTheme' for key 'plugin.uniq.name'`, mọi test của plugin khác dừng ở bootstrap).

## Kiểm chứng trên local

- Bản ghi `plugin` giữ nguyên id; bảng `theme` giữ nguyên id, `base_config`, `config_values` (so SHA-256 trước/sau), việc gán theme cho sales channel giữ nguyên.
- Không có migration nào chạy lại.
- `ListingMediaSubscriber` đăng ký dưới `Acme\Theme\…` (`debug:event-dispatcher`); ảnh hover ở trang danh mục vẫn hiện; trang sản phẩm, bảng size chụp ảnh kiểm chứng.
- 2 unit test của AcmeTheme đạt.
