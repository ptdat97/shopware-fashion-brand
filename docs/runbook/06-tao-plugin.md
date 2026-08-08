# Runbook: Tạo plugin mới

Đây là việc **lặp lại nhiều nhất** từ giờ tới go-live: mỗi cổng thanh toán, mỗi đơn vị vận chuyển là một plugin riêng.

Quy trình dưới đây đã kiểm chứng khi tạo `AniveleVietnam` — dùng nó làm mẫu tham chiếu.

---

## 0. Đọc kiến trúc trước — bước không được bỏ qua

**Trước khi gõ dòng code đầu tiên**, xác nhận điểm mở rộng có thật trong `vendor/` của **đúng phiên bản đang chạy**:

```bash
# Ví dụ cho payment handler (Phase 1 sắp cần)
ls vendor/shopware/core/Checkout/Payment/Cart/PaymentHandler/
grep -n "abstract public function" \
  vendor/shopware/core/Checkout/Payment/Cart/PaymentHandler/AbstractPaymentHandler.php

# Xác nhận một class có tồn tại không
grep -rl "class TenClass\|interface TenInterface" vendor/shopware/
```

> 🔴 **Shopware xoá interface giữa các bản major.** `SynchronousPaymentHandlerInterface`, `AsynchronousPaymentHandlerInterface`, `PaymentHandlerInterface` — cả ba **không còn tồn tại** trong 6.7, dù phần lớn tutorial trên mạng vẫn dạy. Code chép về sẽ lỗi ngay lúc nạp class.
>
> Chi tiết và quy trình đầy đủ: [../08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md) Quy tắc số 3.

Mẫu tốt nhất luôn là class core đã implement sẵn — ví dụ `PrePayment.php` (chuyển khoản), `CashPayment.php` (COD).

---

## 1. Tạo cấu trúc thư mục

```bash
mkdir -p custom/plugins/TenPlugin/src
```

> 🔴 **Đặt trong `custom/plugins/`, không phải `custom/static-plugins/`.** Plugin ở `static-plugins` được quản lý bằng composer → `managed_by_composer = 1` → **Administration khoá nút Xoá**. Bài học đã trả giá: xem [05-demo-data.md](05-demo-data.md).

## 2. `composer.json` của plugin

Đây là `composer.json` **của riêng plugin**, hoàn toàn khác `composer.json` của dự án:

```json
{
    "name": "anivele/ten-plugin",
    "description": "Mô tả ngắn bằng tiếng Việt",
    "type": "shopware-platform-plugin",
    "license": "proprietary",
    "version": "1.0.0",
    "require": {
        "shopware/core": "~6.7"
    },
    "autoload": {
        "psr-4": {
            "Anivele\\TenPlugin\\": "src/"
        }
    },
    "extra": {
        "shopware-plugin-class": "Anivele\\TenPlugin\\TenPlugin",
        "label": {
            "en-GB": "Anivele Ten Plugin",
            "de-DE": "Anivele Ten Plugin"
        }
    }
}
```

> ⛔ **Không chạy `composer require` cho plugin này.** Shopware tự quét `custom/plugins/`. Xem [../08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md) Quy tắc số 2.

## 3. Class plugin

`src/TenPlugin.php`:

```php
<?php declare(strict_types=1);

namespace Anivele\TenPlugin;

use Shopware\Core\Framework\Plugin;

class TenPlugin extends Plugin
{
}
```

Tên class phải khớp `shopware-plugin-class` ở bước 2, và khớp tên thư mục.

## 4. Cài & kích hoạt

```bash
php bin/console plugin:refresh
php bin/console plugin:install --activate TenPlugin
php bin/console cache:clear
```

## 5. Kiểm tra bắt buộc

```bash
mysql -h 127.0.0.1 -u root shopware -e \
  "SELECT name, active, managed_by_composer FROM plugin WHERE name='TenPlugin';"
```

Phải ra `active = 1` và **`managed_by_composer = 0`**. Nếu là `1` → đã lỡ `composer require`, gỡ bằng `composer remove anivele/ten-plugin` rồi `plugin:refresh` lại.

---

## Thêm migration

Migration là cách **duy nhất** đúng để thay đổi cấu hình/dữ liệu có tính lặp lại — thay cho SQL thủ công.

```bash
date +%s      # lấy Unix timestamp THẬT tại thời điểm tạo
```

`src/Migration/Migration<timestamp>MoTaNgan.php`:

```php
<?php declare(strict_types=1);

namespace Anivele\TenPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1786157044MoTaNgan extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786157044;   // phải khớp tên class và tên file
    }

    public function update(Connection $connection): void
    {
        // thay đổi không phá huỷ
    }
}
```

Chạy:

```bash
php bin/console database:migrate --all TenPlugin
```

### Quy tắc viết migration

| Quy tắc | Lý do |
|---|---|
| **Timestamp thật**, không làm tròn, không placeholder | Quy ước của Shopware; quyết định thứ tự chạy |
| **Idempotent** — chạy lại không sinh dữ liệu trùng | Dùng `INSERT ... ON DUPLICATE KEY UPDATE` hoặc kiểm tra tồn tại trước |
| **Không hardcode ID sinh ngẫu nhiên** | ID của bản cài mới khác máy khác. Dò theo giá trị nghiệp vụ (`iso`, `tax_rate`, `locale.code`) |
| **Bảng `*_translation` phải lọc CẢ `language_id`** | Xem cảnh báo bên dưới |
| Hằng số core thì **được** hardcode | `Defaults::CURRENCY` là hằng số thật trong core, dùng `Uuid::fromHexToBytes(Defaults::CURRENCY)` |
| **Không** test `updateDestructive()` rỗng | Xem [../09-nguyen-tac-ky-thuat.md](../09-nguyen-tac-ky-thuat.md) |

> 🔴 **Bẫy bảng `*_translation`:** các bảng dịch (`category_translation`,
> `product_translation`...) có **một dòng cho MỖI ngôn ngữ đã cài**. Dự án này
> có 31 ngôn ngữ từ SwagLanguagePack, nên `UPDATE ... WHERE category_id = :id`
> ghi vào cả 31 dòng thay vì đúng dòng cần sửa.
>
> ```sql
> -- Sai: ghi vào mọi ngôn ngữ
> UPDATE category_translation SET custom_fields = :v WHERE category_id = :id;
>
> -- Đúng
> UPDATE category_translation SET custom_fields = :v
> WHERE category_id = :id AND language_id = :lang;
> ```
>
> Kiểm tra sau khi chạy migration: đếm số dòng bị ảnh hưởng có khớp kỳ vọng không.

> ⚠️ **Cân nhắc kỹ việc hoàn tác khi gỡ plugin.** `AniveleVietnam` **cố ý không** hoàn tác VND/thuế — đưa currency về USD trong khi shop đã có đơn giá VND sẽ làm sai toàn bộ số tiền. Ghi rõ quyết định này vào docblock của class plugin.

---

## Lệnh sinh khung có sẵn

Không cần viết tay từ đầu:

```bash
php bin/console make:plugin:event-subscriber
php bin/console make:plugin:entity
php bin/console make:plugin:storefront-controller
php bin/console make:plugin:store-api-route
php bin/console make:plugin:scheduled-task
php bin/console make:plugin:command
php bin/console make:plugin:config
php bin/console make:plugin:tests
```

Xem đầy đủ: `php bin/console list make`

---

## Checklist trước khi commit

```bash
# 1. Không đụng vendor/
git status --short vendor/

# 2. Không đụng composer.json của dự án
git --no-pager diff composer.json composer.lock

# 3. Plugin không bị composer quản lý
mysql -h 127.0.0.1 -u root shopware -e \
  "SELECT name, managed_by_composer FROM plugin WHERE name LIKE 'Anivele%';"

# 4. Cú pháp PHP
php -l custom/plugins/TenPlugin/src/**/*.php

# 5. Mọi class Shopware đang dùng đều có thật (bắt lỗi bịa API)
php bin/check-plugin-api.php custom/plugins/TenPlugin
```

Cả 5 phải sạch. Mục 2 chỉ được có thay đổi khi cài plugin **bên thứ ba**.

Mục 5 báo tên nào thì tên đó **không tồn tại trong `vendor/`** — đang bịa API, quay lại bước 0. Script dùng chính autoloader của Shopware nên kết quả đáng tin, không phải đoán theo đường dẫn:

```
❌ Class Shopware KHÔNG TỒN TẠI trong vendor/:

  Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface
      dùng ở: custom/plugins/TenPlugin/src/Service/VnpayHandler.php
```

---

## Liên quan

- Quy tắc bắt buộc: [../08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md)
- Kiến trúc code, migration, test: [../09-nguyen-tac-ky-thuat.md](../09-nguyen-tac-ky-thuat.md)
- Ví dụ thật: `custom/plugins/AniveleVietnam/` và [04-phase0-da-thuc-hien.md](04-phase0-da-thuc-hien.md)
- Công cụ test/lint: [../11-mcp-va-ai-tooling.md](../11-mcp-va-ai-tooling.md)
