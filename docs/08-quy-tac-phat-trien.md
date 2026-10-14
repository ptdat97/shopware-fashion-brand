# Quy tắc phát triển

> Năm quy tắc nền tảng. Luật chi tiết có mã để trích khi review (`DATA-04`, `MIG-03`…), Definition of Done và hiện trạng tuân thủ: [13-chuan-phat-trien-plugin.md](13-chuan-phat-trien-plugin.md).

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
composer require "acme/vietnam:*"     # thêm dòng vào require của dự án
```

```json
"require": {
    "acme/vietnam": "*",              ← không cần dòng này
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

Đã kiểm chứng trên chính dự án này: gỡ `acme/vietnam` khỏi `composer.json`, chạy `plugin:refresh` → plugin vẫn **Installed + Active**, storefront vẫn render `690.000 ₫` bình thường. Cột duy nhất đổi là *"Required by composer"* → `No`. Plugin `AcmeDemoData` chưa từng có trong `composer.json` mà vẫn chạy suốt từ đầu.

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
AcmeCatalog       0     ← tự viết, xoá được từ Admin
AcmeDemoData      0     ← tự viết, xoá được từ Admin
AcmeVietnam       0     ← tự viết, xoá được từ Admin
SwagLanguagePack     1     ← bên thứ ba, đúng là phải qua composer
```

> 🔴 **Chiều ngược lại cũng nguy hiểm:** xoá thư mục plugin mà quên gỡ khỏi `composer.json`. Autoloader sẽ trỏ vào file không tồn tại và **làm hỏng toàn bộ `bin/console`** — mọi lệnh Shopware đều lỗi. Đã xảy ra thật với `SwagPayPal`, xem [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md). Gỡ plugin bên thứ ba phải dùng `composer remove`, không xoá thư mục bằng tay.

> Chi tiết bối cảnh của bài học này: [runbook/05-demo-data.md](runbook/05-demo-data.md) — `AcmeDemoData` từng phải chuyển từ `custom/static-plugins/` sang `custom/plugins/` chính vì lý do bị khoá nút Xoá.

### Khi nào **được** đụng `composer.json`

| Trường hợp | Lý do |
|---|---|
| Cài plugin **bên thứ ba** từ Packagist / Shopware Store | Không có sẵn trong repo, buộc phải tải về |
| Nâng version `shopware/core` và các gói core | Đây đúng là phụ thuộc của dự án |
| Plugin tự viết cần **thư viện ngoài** | Khai báo trong `composer.json` của plugin **và** `composer require` ở cấp dự án — xem bên dưới |

### Thư viện ngoài cho plugin: phải cài ở cấp dự án

> 🔴 **Shopware KHÔNG nạp `vendor/autoload.php` của plugin.** `KernelPluginLoader` chỉ đăng ký các namespace `psr-4` khai báo trong `composer.json` của plugin, không đụng tới thư mục `vendor/` bên trong plugin.

Đã trả giá thật khi làm VietQR: cài `endroid/qr-code` vào `custom/plugins/AcmePayment/vendor/`, `composer install` báo thành công, `php -r` test riêng cũng chạy — nhưng storefront trả **500**:

```
ClassNotFoundError: Attempted to load class "QrCode" from namespace "Endroid\QrCode"
```

Cách đúng — khai báo ở **cả hai nơi**:

```bash
# 1. Trong composer.json của plugin — để biết plugin cần gì
#    "require": { "endroid/qr-code": "^6.0" }

# 2. Cài thật ở cấp dự án — để Shopware nạp được
composer require "endroid/qr-code:^6.0"
```

Đây là **ngoại lệ hợp lệ của Quy tắc số 2**: thư viện bên thứ ba, không phải plugin tự viết. Plugin `swag/paypal` cũng làm đúng như vậy với `shopware/paypal-sdk`.

> Đừng để lại `vendor/` bên trong plugin sau khi đã cài ở cấp dự án — hai bản sao cùng thư viện sẽ lệch phiên bản về sau.

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

Chạy trực tiếp từ thư mục gốc dự án (không lưu thành script trong repo):

```bash
rc=0; for p in custom/plugins/Acme*; do
  printf "%-14s " "${p##*/}"
  grep -rhoE "^use (Shopware|Symfony|Doctrine)\\\\[A-Za-z0-9_\\\\]+" "$p/src" | sed 's/^use //' | sort -u |
  php -r 'require "vendor/autoload.php"; $n = 0; $bad = 0;
    foreach (file("php://stdin", FILE_IGNORE_NEW_LINES) as $c) { $n++;
      if (!class_exists($c) && !interface_exists($c) && !trait_exists($c) && !enum_exists($c)) { echo "\n  KHÔNG TỒN TẠI: $c"; $bad = 1; } }
    echo "\n  $n class đã kiểm\n"; exit($bad);' || rc=1
done; echo "Kết quả: $([ $rc = 0 ] && echo SẠCH || echo 'CÓ CLASS MA')"
```

Lệnh gom mọi câu `use Shopware\…` / `Symfony\…` / `Doctrine\…` trong `src/` của từng plugin và hỏi **chính autoloader của dự án** xem class/interface/trait/enum có tồn tại không — đáng tin hơn đoán theo đường dẫn file. Có class ma thì in `KHÔNG TỒN TẠI: …` và dòng cuối `Kết quả: CÓ CLASS MA`. Ví dụ với `SynchronousPaymentHandlerInterface` (đã xoá ở 6.7):

```
KHÔNG TỒN TẠI: Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface
```

Giới hạn: chỉ bắt tên **class**. Tên method, tham số, service id vẫn phải đọc trong `vendor/` và được test bắt (xem [13-chuan-phat-trien-plugin.md](13-chuan-phat-trien-plugin.md)).

Chạy trước mỗi commit có đụng code plugin. Kết quả 14/10/2026: 7 plugin Acme, 158 class, 0 class ma.

### Khi tìm mãi không thấy

Có thể Shopware **thật sự không có** thứ đó — như trường hợp cổng thanh toán VN. Lúc đó:

1. Ghi rõ **cái gì không có** vào [03-plugin-app.md](03-plugin-app.md), kèm ngày kiểm chứng
2. Ghi quyết định tự viết vào [decisions/](decisions/)
3. **Không** bịa ra một API tưởng tượng rồi viết code quanh nó

Đây chính là quy ước số 3 của bộ tài liệu: *"Nói rõ cái gì KHÔNG có sẵn."*

---

## Quy tắc số 4 — Không tạo lại thứ Shopware đã có

**Trước khi tạo bảng, entity, trường, ô nhập Admin, subscriber hay lệnh console mới: tìm xem Shopware đã có cơ chế làm đúng việc đó chưa. Có thì dùng, không có mới viết — và viết phần nhỏ nhất còn thiếu.**

Quy tắc số 3 chống *bịa API*. Quy tắc này chống điều ngược lại: *API có thật nhưng không dùng*, tự làm một bản song song. Bản song song tốn công viết, tốn công bảo trì, và các tính năng core (bộ lọc, Import/Export, Rule Builder, Admin, cache, quyền) **không biết tới nó**.

### Thứ tự tìm — dừng ở bước đầu tiên đáp ứng được

| # | Tìm ở đâu | Ví dụ trong dự án |
|---|---|---|
| 1 | **Trường / entity sẵn có** của core | Brand = `product_manufacturer` (tên, mô tả, logo, website) |
| 2 | **Cấu hình / dữ liệu native** (Rule Builder, CMS, product stream, SEO template, snippet) | Bộ sưu tập = category + product stream; khuyến mãi theo brand = `LineItemOfManufacturerRule`; nhãn "Thương hiệu" = snippet |
| 3 | **Custom field** trên entity core — tự dịch, tự hiện trong Admin, quyền theo entity | Meta SEO brand = custom field dịch được của nhà sản xuất; ô "Là bộ sưu tập" trên danh mục |
| 4 | **Điểm mở rộng chính thức** (EntityExtension, EntityIndexer, SeoUrlRoute, UrlProvider, Twig block, decorator) | SEO URL brand qua `EntityIndexer` (như `LandingPageIndexer` của core); listing brand qua `ProductListingRoute` |
| 5 | **Bảng / entity riêng** — chỉ khi 1–4 không làm được, và ghi lý do | `acme_brand`: mã brand cần `UNIQUE`, banner/layout cần khoá ngoại thật |

Bước 5 phải ghi **vì sao bước 3 không đủ** (cần ràng buộc DB, khoá ngoại, index…) vào ADR hoặc docblock của class.

### Ví dụ thật — rà soát `AcmeBrand` (14/10/2026)

Bản đầu của plugin đã dùng nhà sản xuất làm brand, nhưng vẫn còn hai chỗ tự làm lại thứ core có sẵn:

| Đã tự làm | Shopware có sẵn | Sau rà soát |
|---|---|---|
| Bảng dịch `acme_brand_translation` + 3 class entity + 3 ô nhập JS + quyền ACL riêng cho meta SEO | `customFields` của nhà sản xuất **dịch được**, trang nhà sản xuất **tự hiện** thẻ custom field, quyền theo `product_manufacturer` | Chuyển sang custom field `acme_brand_meta_*`; bỏ bảng, 3 class, 3 ô nhập, quyền riêng |
| Subscriber tự bắt sự kiện ghi + lệnh `acme:brand:seo-urls` để sinh SEO URL | `EntityIndexer` — chạy khi ghi dữ liệu và trong `dal:refresh:index` | `BrandIndexer`; bỏ subscriber và lệnh riêng |

Những gì **giữ lại có lý do**: bảng `acme_brand` (mã `UNIQUE`, khoá ngoại banner/layout), trang storefront `/brands` (core không có trang nhà sản xuất), route SEO và sitemap (điểm mở rộng chính thức, không phải bản sao).

### Rà soát các plugin còn lại (14/10/2026)

| Plugin | Đã tự làm | Shopware có sẵn | Xử lý | Kiểm chứng |
|---|---|---|---|---|
| `AcmePayment` | `VietQrPaymentHandler` — `pay()` trả null, giao dịch giữ "open" | Giống hệt `DefaultPayment`/`PrePayment` của core | VietQR dùng handler **`PrePayment`** ("Chuyển khoản trước"); bỏ class. Phần riêng của VietQR chỉ còn mã QR trên trang cảm ơn | Đặt đơn VietQR thật qua Store API → giao dịch `open`; huỷ + xoá đơn thử |
| `AcmeTheme` | `SizeChartSubscriber` truy vấn lại danh mục của sản phẩm + struct `SizeChartData` | Trang chi tiết đã nạp **danh mục chính** `page.product.seoCategory` (đủ custom field; chọn được trong Admin) | Template đọc thẳng `seoCategory`; bỏ subscriber, struct, một truy vấn DB mỗi trang | HTML bảng size của 12 trang sản phẩm **giống hệt** trước khi sửa; chụp ảnh popup |
| `AcmeDemoData` | Hàm `slugify()` tự viết | Thư viện `Cocur\Slugify` mà core dùng | Dùng Cocur, cấu hình chữ thường | So 67/67 tên file khớp tuyệt đối; seed lại không sinh thêm media nào |
| `AcmeBrand` | Nhãn Admin khai báo bằng `Locale.extend` trong JS | Administration nạp file snippet `Resources/app/administration/src/snippet/<locale>.json` của plugin **phía server** (`SnippetFinder`), không cần build | Chuyển hết nhãn sang file JSON; nhờ vậy đổi được cả nhãn core "Các nhà sản xuất" → "Thương hiệu" (`Locale.extend` bị gói ngôn ngữ đè) | API `/api/_admin/snippets` trả nhãn mới; chụp ảnh Admin |

Giữ nguyên sau khi kiểm:

| Plugin | Phần | Vì sao không trùng |
|---|---|---|
| `AcmeCatalog` | Lệnh `acme:catalog:variants` | Core chỉ có bộ sinh biến thể trong Admin (JS, từng sản phẩm), không có service/lệnh phía server. Lệnh ghi đúng cấu trúc native |
| `AcmeVietnam` | Migration VND, VAT, quốc gia, 34 tỉnh/thành | Toàn bộ là dữ liệu cấu hình trên entity core; core không có tỉnh/thành Việt Nam |
| `AcmePayment` | Phí ship theo khu vực | Rule Builder + shipping price matrix của core, chỉ là dữ liệu |
| `AcmeTheme` | `ListingMediaSubscriber`, override Twig, migration nhãn tiếng Việt | Dùng đúng điểm mở rộng (`ProductListingCriteriaEvent`, block Twig) và dịch dữ liệu native |
| `AcmeDemoData` | Importer + nút trong Admin | Core không có cơ chế nhập dữ liệu demo kèm ảnh |

> 🔴 **Xoá class của plugin → phải xoá container đã biên dịch** (`rm -rf var/cache/prod_*`) trước khi chạy `bin/console`. Container cũ vẫn tham chiếu class đã xoá và mọi lệnh, kể cả `cache:clear`, đều lỗi `Class ... not found`.

### Tự kiểm trước khi tạo mới

```bash
# Entity core có trường này chưa?
grep -n "Field('" vendor/shopware/core/Content/<Khu vực>/<Entity>Definition.php

# Có cơ chế native cùng mục đích chưa? (indexer, SEO route, rule, CMS element…)
grep -rln "extends EntityIndexer\|implements SeoUrlRouteInterface" vendor/shopware/core vendor/shopware/storefront
ls vendor/shopware/core/Checkout/Cart/Rule/          # điều kiện Rule Builder có sẵn
ls vendor/shopware/core/Content/Product/Cms/         # CMS element sản phẩm có sẵn

# Trang Admin của entity đó đã có thẻ custom field chưa?
grep -n "custom-field-set-renderer" vendor/shopware/administration/Resources/app/administration/src/module/<module>/page/*/*.twig
```

---

## Quy tắc số 5 — Mỗi plugin một nghiệp vụ, độc lập với plugin khác

**Mỗi plugin trong `custom/plugins/` sở hữu đúng một mảng nghiệp vụ. Bật, tắt hay gỡ một plugin không được làm hỏng nghiệp vụ chung (xem hàng, giỏ hàng, đặt hàng, thanh toán COD) và không làm hỏng plugin khác — chỉ mất đúng chức năng của chính nó.**

### Bản đồ trách nhiệm

| Plugin | Nghiệp vụ | Tắt thì mất gì | Ghi chú |
|---|---|---|---|
| `AcmeVietnam` | Thị trường VN: VND, VAT, quốc gia, 34 tỉnh/thành, vi-VN, **Việt hoá dữ liệu core** (danh mục gốc, nhãn sắp xếp) | Không gì — cấu hình đã ghi vào entity core, cố ý không hoàn tác | |
| `AcmeCatalog` | Cấu trúc catalog: thuộc tính, sinh biến thể, **canonical biến thể**, **custom field bảng size**, bộ sưu tập, template SEO URL | Lệnh `acme:catalog:variants` | Dữ liệu và template SEO là của core → vẫn chạy |
| `AcmePayment` | Thanh toán: dọn phương thức, VietQR (QR + CSS của khối QR) | Phương thức VietQR (tự tắt khi plugin tắt) | COD, chuyển khoản là của core |
| `AcmeShipping` | Vận chuyển nội địa: phí theo khu vực; sau này GHN/GHTK | Không gì — bảng phí là cấu hình core | Tách khỏi `AcmePayment` ngày 14/10/2026 |
| `AcmeBrand` | Thương hiệu: phần mở rộng nhà sản xuất, `/brands`, SEO URL, sitemap, link brand ở trang sản phẩm | Trang `/brands` (404), thẻ "Thương hiệu" trong Admin | Nhà sản xuất, bộ lọc thương hiệu là của core → vẫn chạy |
| `AcmeTheme` | **Chỉ hiển thị**: theme, SCSS, override Twig (gồm nút bảng size), ảnh hover, bật wishlist | Giao diện thương hiệu, nút bảng size | Core **chặn** tắt khi theme đang gán — đổi sang theme khác trước |
| `AcmeDemoData` | Dữ liệu demo | Nút nhập demo | Gỡ trước go-live |

### Điều kiện bắt buộc

1. **Không phụ thuộc class/service của plugin khác.** Không `use Acme\Khác\…`, không inject service của plugin khác. Kiểm: `grep -rn "use Acme" custom/plugins/AcmeX/src` — mọi dòng phải là namespace của chính AcmeX.
2. **Phụ thuộc dữ liệu phải "có thì dùng, không có thì bỏ qua".** Đọc custom field / bảng / tỉnh do plugin khác tạo → thiếu thì không hiện, không lỗi. Ví dụ: theme đọc `acme_size_chart_*` (của `AcmeCatalog`), thiếu thì không có nút; `AcmeDemoData` kiểm entity `acme_brand` có tồn tại trước khi ghi; `AcmeShipping` thiếu tỉnh thì rule nội thành rỗng và đồng bộ lại khi bật plugin.
3. **Markup của plugin nào thì CSS của plugin đó.** Theme không định dạng hộ khối của plugin khác (CSS VietQR đã chuyển từ `AcmeTheme` về `AcmePayment`). Plugin ngoài theme chỉ dùng biến Bootstrap.
4. **Logic đặt theo nghiệp vụ, không theo người viết hay theo phase.** Việt hoá dữ liệu là thị trường (`AcmeVietnam`), số đo là catalog (`AcmeCatalog`), vận chuyển không phải thanh toán. Không đặt quy tắc catalog vào plugin demo.
5. **Tắt / gỡ không hoàn tác cấu hình kinh doanh** đang nằm trên entity core (tiền tệ, thuế, phí ship…): hoàn tác sẽ làm sai đơn hàng. Ghi rõ trong docblock class plugin.
6. **Chuyển logic giữa plugin bằng migration mới, idempotent** ở plugin đích; xoá file ở plugin nguồn. Không sửa migration đã chạy. Bản mới phải an toàn khi chạy trên môi trường đã có bản cũ — không ghi đè dữ liệu người vận hành đã sửa.

### Cách kiểm — chạy trên máy dev, sau mỗi lần thêm/chuyển logic giữa plugin

Với mỗi plugin `Acme*`: tắt → xoá cache → kiểm → bật lại.

```bash
php bin/console plugin:deactivate AcmeX
php bin/console cache:clear && php bin/console cache:clear:http
# Trang: /, /dam/, một trang sản phẩm, /collections/sale, /brands, /brands/<slug> — phải 200
#        (riêng /brands* được phép 404 khi tắt AcmeBrand)
# Giỏ: Store API thêm 1 sản phẩm → không lỗi, có phí ship, COD còn trong danh sách thanh toán
php bin/console plugin:activate AcmeX
```

`AcmeTheme`: `bin/console theme:change Storefront -s <salesChannelId>` trước khi tắt, `theme:change AcmeTheme` sau khi bật lại.

### Kết quả kiểm 14/10/2026

| Tắt | Trang chính | Giỏ + COD | Khác biệt duy nhất |
|---|---|---|---|
| `AcmeVietnam` | ✅ | ✅ | — |
| `AcmeCatalog` | ✅ | ✅ | — |
| `AcmePayment` | ✅ | ✅ | VietQR không còn trong danh sách thanh toán |
| `AcmeShipping` | ✅ | ✅ | — |
| `AcmeBrand` | ✅ | ✅ | `/brands`, `/brands/…` → 404 |
| `AcmeDemoData` | ✅ | ✅ | — |
| `AcmeTheme` (sau khi đổi sang theme Storefront) | ✅ | ✅ | Nút bảng size biến mất; link thương hiệu (`AcmeBrand`) vẫn còn |

Chuyển logic đã làm cùng đợt: phí vận chuyển `AcmePayment` → `AcmeShipping` (bảng giá đang có giữ nguyên, cùng id); Việt hoá dữ liệu core `AcmeTheme` → `AcmeVietnam`; custom field bảng size `AcmeTheme` → `AcmeCatalog`; canonical biến thể `AcmeDemoData` → `AcmeCatalog`; CSS VietQR `AcmeTheme` → `AcmePayment`.

> Phát hiện chưa xử lý: `AcmeDemoData` ghi custom field `acme_material`, `acme_model_size` vào sản phẩm nhưng **không plugin nào định nghĩa hay hiển thị** hai trường này — dữ liệu không ai dùng.

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
custom/plugins/AcmeDemoData/src/AcmeDemoData.php  → tracked ✅
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
| ❌ **Không để plugin phụ thuộc nhau** | Mỗi plugin một nghiệp vụ; tắt một plugin chỉ mất chức năng của chính nó (Quy tắc số 5) |
| ❌ **Không tạo lại thứ core có** | Bảng/entity/ô nhập/lệnh riêng cho việc core đã làm được — dùng trường sẵn có → cấu hình native → custom field → điểm mở rộng, bảng riêng là cuối cùng |

**Năm câu hỏi tự kiểm tra trước mỗi thay đổi:**

1. *"Sau khi chạy `composer update`, thay đổi này còn không?"* — Nếu **không** → đang sửa nhầm chỗ, phải làm qua plugin/theme.
2. *"Thay đổi này có buộc phải sửa file dùng chung (`composer.json`, `composer.lock`) không?"* — Nếu **có** mà chỉ để cài plugin tự viết → thừa, bỏ đi.
3. *"Mọi class và method mình đang dùng, đã mở `vendor/` xác nhận có thật trong 6.7 chưa?"* — Nếu **chưa** → dừng lại, `grep` trước khi viết tiếp.
4. *"Shopware đã có trường, cấu hình, custom field hay điểm mở rộng làm được việc này chưa?"* — Nếu **có** → dùng nó, không tạo bản song song (Quy tắc số 4).
5. *"Logic này thuộc nghiệp vụ của plugin nào? Tắt plugin đó thì nghiệp vụ chung còn chạy không?"* — Đặt đúng plugin, và chỉ phụ thuộc dữ liệu kiểu "có thì dùng" (Quy tắc số 5).

---

## Nguyên tắc kỹ thuật chi tiết

Tài liệu này quy định **được sửa ở đâu**. Còn **sửa như thế nào** — phạm vi thay đổi, kiến trúc PHP, migration, viết test, code Administration — nằm ở [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md).
