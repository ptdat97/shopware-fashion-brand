# Quy tắc phát triển

> **Đây là quy tắc bắt buộc của dự án.** Áp dụng cho mọi người tham gia phát triển, mọi giai đoạn.

## Quy tắc số 1 — Không can thiệp core Shopware

**Mọi thay đổi phải viết qua Plugin, App hoặc Theme. Tuyệt đối không sửa mã nguồn core.**

### Cụ thể là không được đụng vào

| Đường dẫn | Nội dung |
|---|---|
| `vendor/shopware/core/` | Nhân hệ thống |
| `vendor/shopware/storefront/` | Giao diện mặc định |
| `vendor/shopware/administration/` | Giao diện quản trị |
| `vendor/` (toàn bộ) | Mọi thư viện bên thứ ba |
| `public/bundles/` | File build tự sinh |
| `public/theme/` | Theme đã biên dịch |

### Chỉ được viết vào

| Đường dẫn | Dùng cho |
|---|---|
| `custom/plugins/` | Plugin tự viết hoặc cài thủ công |
| `custom/static-plugins/` | Plugin quản lý cùng repo dự án |
| `custom/apps/` | App tích hợp dịch vụ ngoài |
| `config/` | Cấu hình cấp dự án |
| `docs/` | Tài liệu |

---

## Quy tắc số 2 — Không sửa `composer.json` gốc cho plugin tự viết

**Plugin tự viết đặt trong `custom/plugins/` là đủ. Không thêm vào `composer.json` của dự án.**

❌ **Sai:**

```bash
composer require "anivele/vietnam:*"     # thêm dòng vào require của dự án
```

```json
"require": {
    "anivele/vietnam": "*",              ← không cần dòng này
    "shopware/core": "v6.7.12.2"
}
```

✅ **Đúng:** tạo thư mục `custom/plugins/TenPlugin/` rồi:

```bash
php bin/console plugin:refresh
php bin/console plugin:install --activate TenPlugin
```

### Vì sao

Shopware **tự quét** `custom/plugins/` để tìm plugin — không đọc qua `composer.json` của dự án. Cơ chế nằm ở `PluginFinder::loadLocalPlugins()` (`vendor/shopware/core/Framework/Plugin/Util/PluginFinder.php`): nó duyệt thư mục và đọc `composer.json` **của từng plugin**, hoàn toàn độc lập với `require` cấp dự án.

Đã kiểm chứng trên chính dự án này: gỡ `anivele/vietnam` khỏi `composer.json`, chạy `plugin:refresh` → plugin vẫn **Installed + Active**, storefront vẫn render `690.000 ₫` bình thường. Cột duy nhất đổi là *"Required by composer"* → `No`. Plugin `AniveleDemoData` chưa từng có trong `composer.json` mà vẫn chạy suốt từ đầu.

Tác hại nếu cứ thêm vào:

| Vấn đề | Hệ quả |
|---|---|
| **Khoá nút Xoá trong Admin** | `composer require` đặt `managed_by_composer = 1` → Administration **chặn xoá plugin**, bắt buộc phải `composer remove` trên dòng lệnh |
| **Xung đột khi merge** | `composer.json` và `composer.lock` là file dùng chung; mỗi plugin thêm một dòng là mỗi lần merge phải xử lý tay |
| **`composer.lock` phình vô ích** | Mỗi lần require ghi thêm ~39 dòng metadata cho thứ vốn đã nằm sẵn trong repo |
| **Nhầm lẫn nguồn gốc** | Đọc `require` sẽ tưởng plugin tải từ Packagist, thực ra là symlink tới thư mục ngay trong repo |
| **Gỡ plugin thành hai bước** | Phải nhớ `composer remove` ngoài việc `plugin:uninstall`, quên là lỗi khi cài lại |

Kiểm tra cột này bất cứ lúc nào:

```bash
mysql -h 127.0.0.1 -u root shopware -e "SELECT name, managed_by_composer FROM plugin ORDER BY name;"
```

Plugin tự viết phải là `0`. Hiện trạng đúng của dự án:

```
AniveleDemoData      0     ← tự viết, xoá được từ Admin
AniveleVietnam       0     ← tự viết, xoá được từ Admin
SwagLanguagePack     1     ← bên thứ ba, đúng là phải qua composer
SwagPayPal           1     ← bên thứ ba
```

> Chi tiết bối cảnh của bài học này: [runbook/05-demo-data.md](runbook/05-demo-data.md) — `AniveleDemoData` từng phải chuyển từ `custom/static-plugins/` sang `custom/plugins/` chính vì lý do bị khoá nút Xoá.

### Khi nào **được** đụng `composer.json`

| Trường hợp | Lý do |
|---|---|
| Cài plugin **bên thứ ba** từ Packagist / Shopware Store | Không có sẵn trong repo, buộc phải tải về |
| Nâng version `shopware/core` và các gói core | Đây đúng là phụ thuộc của dự án |
| Plugin tự viết cần **thư viện ngoài** | Khai báo trong `composer.json` **của plugin đó**, không phải của dự án |

> Trường hợp cuối cần chú ý: thư viện khai báo trong plugin không tự động được cài. Xem [03-plugin-app.md](03-plugin-app.md) trước khi thêm phụ thuộc ngoài cho plugin.

---

## Quy tắc số 3 — Đọc kiến trúc Shopware trước khi viết. Không viết bừa, không bịa API

**Trước khi viết dòng code plugin đầu tiên: đọc code core của đúng phiên bản đang chạy, hoặc tài liệu chính thức của đúng phiên bản đó.**

Không đoán tên class. Không nhớ mang máng. Không copy từ blog/tutorial mà chưa kiểm chứng. Không để AI sinh ra API rồi tin luôn.

### Vì sao — bằng chứng ngay trong dự án này

Shopware **xoá interface giữa các phiên bản major**. Kiểm chứng trên chính `vendor/` của dự án (6.7.12.2):

```bash
grep -rl "interface SynchronousPaymentHandlerInterface" vendor/shopware/core/
```

| Interface tutorial hay dạy | Trong 6.7 |
|---|---|
| `SynchronousPaymentHandlerInterface` | **Không còn tồn tại** |
| `AsynchronousPaymentHandlerInterface` | **Không còn tồn tại** |
| `PaymentHandlerInterface` | **Không còn tồn tại** |
| `PreparedPaymentHandlerInterface` | **Không còn tồn tại** |

Cả 4 đều đã bị xoá. Phần lớn hướng dẫn viết payment plugin trên mạng vẫn dạy chúng — **code chép về sẽ không chạy nổi, lỗi ngay lúc nạp class.**

Cái đúng cho 6.7 là **một** abstract class duy nhất:

```
vendor/shopware/core/Checkout/Payment/Cart/PaymentHandler/AbstractPaymentHandler.php
```

```php
abstract public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool;
abstract public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse;
public function validate(Cart $cart, RequestDataBag $dataBag, SalesChannelContext $context): ?Struct;
public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void;
```

Đây là thông tin **đọc trực tiếp từ `vendor/`**, không phải trí nhớ. Phase 1 (VNPay/VietQR) sẽ cần đúng class này.

> Bài học tương tự đã xảy ra ở Phase 0: chỉ nhờ **đọc** `vendor/shopware/core/Defaults.php` mới phát hiện `Defaults::CURRENCY` là hằng số hardcode, từ đó chọn chuyển đổi tại chỗ thay vì tạo currency mới — nếu làm theo phỏng đoán thì toàn bộ giá đã sai.

### Quy trình bắt buộc trước khi viết plugin

**1. Tìm điểm mở rộng thật trong `vendor/`**

```bash
# Tìm abstract class / interface theo lĩnh vực
find vendor/shopware/core/Checkout/Payment -name "*PaymentHandler*"
ls vendor/shopware/core/Checkout/Shipping/

# Xem chữ ký method thật, không đoán
grep -n "abstract public function\|public function" \
  vendor/shopware/core/Checkout/Payment/Cart/PaymentHandler/AbstractPaymentHandler.php
```

**2. Đọc luôn class core đã implement sẵn** — mẫu tốt nhất luôn nằm trong core:

```
vendor/shopware/core/Checkout/Payment/Cart/PaymentHandler/PrePayment.php     ← chuyển khoản
vendor/shopware/core/Checkout/Payment/Cart/PaymentHandler/CashPayment.php    ← COD
```

**3. Đối chiếu tài liệu chính thức — nhớ chọn đúng version**

[developer.shopware.com](https://developer.shopware.com/docs/) có bộ chọn phiên bản. **Tài liệu 6.5/6.6 không áp dụng cho 6.7.**

**4. Kiểm tra tính thử nghiệm**

```bash
grep -rn "@experimental" vendor/shopware/core/<đường-dẫn>
```

Class đánh dấu `@experimental` có thể đổi API ở minor tiếp theo — cân nhắc trước khi phụ thuộc vào nó.

### Dấu hiệu đang viết bừa

| Dấu hiệu | Xử lý |
|---|---|
| Không mở `vendor/` lần nào trước khi code | Dừng, đọc trước |
| Tên class lấy từ trí nhớ hoặc blog, chưa `grep` xác nhận tồn tại | `grep -rl "class TenClass" vendor/shopware/` |
| Chép nguyên đoạn code từ tutorial, không rõ nó cho version nào | Kiểm chứng từng class trong `vendor/` |
| AI sinh code trông hợp lý nhưng chưa ai xác nhận API có thật | Bắt buộc `grep` lại từng tên class/method |
| Nghĩ "chắc Shopware có sẵn cái này" | Tìm thật; **không có** thì ghi rõ vào [03-plugin-app.md](03-plugin-app.md) |

> ⚠️ **Điều này áp dụng cho cả AI lẫn người.** AI rất dễ sinh ra tên class nghe hợp lý nhưng không tồn tại, hoặc đúng cho 6.5 mà sai cho 6.7. Mọi API do AI đề xuất phải xác nhận trong `vendor/` trước khi dùng — **không có ngoại lệ**.

### Kiểm tra tự động

Không cần tự nhớ — có script kiểm tra sẵn:

```bash
php bin/check-plugin-api.php                           # toàn bộ custom/plugins
php bin/check-plugin-api.php custom/plugins/TenPlugin  # một plugin
```

Script duyệt mọi câu `use Shopware\...` trong plugin và dùng **chính autoloader của Shopware** để xác nhận class có tồn tại — đáng tin hơn đoán theo đường dẫn file. Exit code `1` nếu phát hiện class ma.

Chạy trước mỗi commit có đụng code plugin.

### Khi tìm mãi không thấy

Có thể Shopware **thật sự không có** thứ đó — như trường hợp cổng thanh toán VN. Lúc đó:

1. Ghi rõ **cái gì không có** vào [03-plugin-app.md](03-plugin-app.md), kèm ngày kiểm chứng
2. Ghi quyết định tự viết vào [decisions/](decisions/)
3. **Không** bịa ra một API tưởng tượng rồi viết code quanh nó

Đây chính là quy ước số 3 của bộ tài liệu: *"Nói rõ cái gì KHÔNG có sẵn."*

---

## Vì sao quy tắc này quan trọng

### 1. Mọi thay đổi trong `vendor/` sẽ bị xoá sạch

`composer install` và `composer update` **ghi đè toàn bộ `vendor/`**. Công sức sửa core biến mất không dấu vết, thường vào đúng lúc nâng cấp — thời điểm tệ nhất để phát hiện.

### 2. Git không hề theo dõi `vendor/`

Đã kiểm chứng trên chính dự án này:

```
vendor/shopware/core/Defaults.php                       → IGNORED (không track)
vendor/shopware/storefront/.../base.html.twig           → IGNORED (không track)
public/bundles/storefront/app.js                        → IGNORED (không track)
custom/plugins/AniveleDemoData/src/AniveleDemoData.php  → tracked ✅
```

Sửa core nghĩa là: **không có lịch sử, không review được, không rollback được, đồng đội không nhận được thay đổi.** Máy bạn chạy được nhưng máy người khác thì không — loại lỗi cực kỳ tốn thời gian truy vết.

### 3. Khoá chặt khả năng nâng cấp

Shopware ra bản vá bảo mật và bản nâng cấp thường xuyên. Nếu core đã bị sửa, mỗi lần nâng cấp buộc phải chọn:

- Nâng cấp và mất hết tuỳ chỉnh, hoặc
- Không nâng cấp và ôm luôn lỗ hổng bảo mật

Cả hai đều tệ. Dự án dần rơi vào tình trạng **không dám nâng cấp** — rủi ro lớn cho một shop TMĐT xử lý thanh toán và dữ liệu khách hàng.

---

## Cách làm đúng cho từng nhu cầu

### Muốn đổi giao diện

❌ **Sai:** sửa `vendor/shopware/storefront/Resources/views/...`

✅ **Đúng:** tạo theme riêng, dùng `sw_extends` và chỉ ghi đè block cần đổi:

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_name %}
    {# nội dung tuỳ biến #}
{% endblock %}
```

Xem [02-theme.md](02-theme.md) và [runbook/02-tao-theme.md](runbook/02-tao-theme.md).

> Kể cả trong theme cũng **không copy nguyên file template rồi sửa vài dòng**. Khi Shopware cập nhật template gốc, bản copy bị kẹt ở phiên bản cũ và sinh lỗi khó truy vết.

### Muốn đổi hành vi nghiệp vụ

❌ **Sai:** sửa service class trong `vendor/`

✅ **Đúng:** dùng một trong các cơ chế mở rộng của Shopware:

| Cơ chế | Dùng khi |
|---|---|
| **Event subscriber** | Phản ứng khi có sự kiện (đơn hàng tạo, khách đăng ký...) |
| **Service decorator** | Bọc và thay đổi hành vi service có sẵn |
| **Custom entity / field** | Thêm dữ liệu riêng |
| **Store API route** | Thêm endpoint cho frontend |
| **Scheduled task** | Chạy tác vụ định kỳ |

Shopware có sẵn lệnh sinh khung:

```bash
php bin/console make:plugin:event-subscriber
php bin/console make:plugin:entity
php bin/console make:plugin:storefront-controller
php bin/console make:plugin:store-api-route
php bin/console make:plugin:scheduled-task
```

Xem đầy đủ: `php bin/console list make`

### Muốn thêm cổng thanh toán / vận chuyển

✅ Viết plugin implement handler tương ứng. Xem [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md).

### Muốn tích hợp hệ thống ngoài (CRM, ERP, chatbot)

✅ Cân nhắc **App** thay vì Plugin — chạy trên server riêng, giao tiếp qua HTTP. So sánh Plugin vs App tại [03-plugin-app.md](03-plugin-app.md).

---

## Trường hợp ngoại lệ: cấu hình dữ liệu

Quy tắc trên nói về **mã nguồn**. Có những thay đổi thuộc về **cấu hình/dữ liệu**, làm qua Administration hoặc SQL là hợp lệ:

- Cấu hình tiền tệ, thuế, quốc gia (Phase 0 đã làm theo cách này)
- Cấu hình sales channel, ngôn ngữ, domain
- Dữ liệu sản phẩm, danh mục, khách hàng

Nguyên tắc phân biệt:

> **Sửa file trong `vendor/` → cấm.**
> **Sửa dữ liệu trong database qua API/Admin/SQL → được, nhưng phải backup trước và ghi lại vào [runbook/](runbook/).**

Với thay đổi cấu hình có tính lặp lại (cần tái lập trên staging/production), nên viết thành **migration trong plugin** thay vì SQL thủ công, để có thể chạy lại một cách nhất quán.

---

## Đọc core thì sao?

**Đọc là bắt buộc, không phải tuỳ chọn** — xem [Quy tắc số 3](#quy-tắc-số-3--đọc-kiến-trúc-shopware-trước-khi-viết-không-viết-bừa-không-bịa-api). Đọc core là cách duy nhất đáng tin để biết điểm mở rộng nào thật sự tồn tại trong phiên bản đang chạy.

Ví dụ thực tế trong dự án này: khi cấu hình VND, việc đọc `vendor/shopware/core/Defaults.php` giúp phát hiện `Defaults::CURRENCY` là hằng số hardcode, từ đó chọn cách chuyển đổi tại chỗ thay vì tạo currency mới — tránh được lỗi nghiêm trọng. Xem [runbook/04-phase0-da-thuc-hien.md](runbook/04-phase0-da-thuc-hien.md).

**Đọc để hiểu ✅ — Sửa để dùng ❌**

---

## Kiểm tra trước khi commit

```bash
# Không được có thay đổi nào trong vendor/
git status --short vendor/ 2>/dev/null

# Xác nhận file mình sửa có được git track không
git check-ignore -v <đường-dẫn-file>

# composer.json chỉ được đổi khi cài plugin BÊN THỨ BA hoặc nâng version core
git --no-pager diff composer.json composer.lock
```

Nếu `git check-ignore` báo file bị ignore mà bạn vừa sửa nó để thay đổi hành vi hệ thống → **đang vi phạm quy tắc, phải làm lại qua plugin/theme.**

Nếu `composer.json` có thêm dòng trỏ tới plugin nằm trong `custom/plugins/` → **vi phạm Quy tắc số 2**, gỡ bằng `composer remove <ten-goi>` rồi `plugin:refresh`.

---

## Khi thấy core có lỗi thật sự

Đôi khi lỗi nằm ở chính Shopware. Thứ tự xử lý:

1. **Kiểm tra bản mới** — lỗi có thể đã được vá: `composer show -a shopware/core`
2. **Tìm cách vòng qua** bằng decorator hoặc event subscriber
3. **Báo lỗi lên** [github.com/shopware/shopware](https://github.com/shopware/shopware)
4. **Nếu buộc phải vá gấp:** dùng `composer patches` (cnp/composer-patches) để vá có kiểm soát — patch được lưu trong repo, áp lại tự động sau mỗi `composer install`, và ghi rõ lý do vào [decisions/](decisions/)

Tuyệt đối không sửa tay trực tiếp rồi để đó.

---

## Tóm tắt

| | |
|---|---|
| ✅ Viết code trong | `custom/plugins/`, `custom/static-plugins/`, `custom/apps/` |
| ✅ Mở rộng bằng | Theme kế thừa, event subscriber, decorator, custom entity |
| ✅ **Đọc core TRƯỚC khi viết** | Xác nhận class/method có thật trong đúng version đang chạy |
| ✅ Cài plugin tự viết bằng | `plugin:refresh` + `plugin:install --activate` |
| ❌ Không sửa | `vendor/`, `public/bundles/`, `public/theme/` |
| ❌ Không copy | Nguyên file template rồi sửa vài dòng |
| ❌ Không `composer require` | Plugin tự viết trong `custom/plugins/` |
| ❌ **Không bịa API** | Tên class từ trí nhớ/blog/AI mà chưa `grep` xác nhận |

**Ba câu hỏi tự kiểm tra trước mỗi thay đổi:**

1. *"Sau khi chạy `composer update`, thay đổi này còn không?"* — Nếu **không** → đang sửa nhầm chỗ, phải làm qua plugin/theme.
2. *"Thay đổi này có buộc phải sửa file dùng chung (`composer.json`, `composer.lock`) không?"* — Nếu **có** mà chỉ để cài plugin tự viết → thừa, bỏ đi.
3. *"Mọi class và method mình đang dùng, đã mở `vendor/` xác nhận có thật trong 6.7 chưa?"* — Nếu **chưa** → dừng lại, `grep` trước khi viết tiếp.

---

## Nguyên tắc kỹ thuật chi tiết

Tài liệu này quy định **được sửa ở đâu**. Còn **sửa như thế nào** — phạm vi thay đổi, kiến trúc PHP, migration, viết test, code Administration — nằm ở [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md).
