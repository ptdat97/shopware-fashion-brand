# Runbook: Dữ liệu demo Anivele

Plugin tự viết để tạo sản phẩm demo thời trang từ bộ ảnh thật của brand.

## Vị trí

```
custom/plugins/AcmeDemoData/
├── composer.json
└── src/
    ├── AcmeDemoData.php
    ├── Command/SeedDemoCommand.php          ← bản dòng lệnh
    ├── Controller/DemoDataController.php    ← API cho nút bấm
    ├── Service/
    │   ├── DemoDataImporter.php             ← toàn bộ logic nhập, dùng chung
    │   ├── DemoProductCatalog.php           ← dữ liệu 12 sản phẩm
    │   └── DemoDataException.php
    └── Resources/
        ├── config/{services,routes,config}.xml
        ├── media/demo/                      ← 66 ảnh + 1 video (78 MB)
        └── public/administration/           ← JS của nút bấm (viết tay, xem bên dưới)
```

Ảnh được **đóng gói kèm plugin**, không phụ thuộc `SwagPlatformDemoData`. Plugin tự chứa, chạy được trên máy khác.

## Plugin thường — xoá được từ Administration

Plugin nằm trong `custom/plugins/` và **không khai báo trong `composer.json`**, nên Shopware ghi nhận `managed_by_composer = 0`:

```
name: AcmeDemoData
path: custom/plugins/AcmeDemoData/
managed_by_composer: 0          ← nút Xoá trong Admin hoạt động
```

> **Vì sao điều này quan trọng:** nếu plugin được cài qua `composer require` (nằm ở `vendor/` và có trong `composer.json`), Shopware đặt `managed_by_composer = 1` và **khoá nút Xoá trong Administration** — bắt buộc phải gỡ bằng `composer remove` trên dòng lệnh. Đây là lý do plugin này được chuyển từ `custom/static-plugins/` (quản lý bằng composer) sang `custom/plugins/` (quản lý thủ công).

So sánh trong hệ thống hiện tại:

| Plugin | Vị trí | managed_by_composer | Xoá từ Admin |
|---|---|---|---|
| AcmeDemoData | `custom/plugins/` | 0 | ✅ Được |
| SwagExtensionStore | `custom/plugins/` | 0 | ✅ Được |
| SwagLanguagePack | `vendor/` | 1 | ❌ Phải dùng `composer remove` |

## Thương hiệu demo

Từ Phase MB2, 12 mẫu chia cho 3 brand (khai báo ở `DemoProductCatalog::brands()`):

| Mã | Brand | Ghi chú |
|---|---|---|
| `ANV` | Anivele | Brand thật của dự án |
| `MAY` | Mây Atelier | **Giả định** — chỉ để thử bộ lọc, trang brand, đơn nhiều brand |
| `LLN` | Linh Lan | **Giả định** |

Brand là **nhà sản xuất** của core (id `acme-demo-manufacturer-<mã>`). Có cài `AcmeBrand` thì importer ghi kèm mã brand, bật trang thương hiệu và meta title; không cài thì chỉ tạo nhà sản xuất — plugin này **không bắt buộc** phụ thuộc `AcmeBrand`.

### Bộ sưu tập demo

Hai danh mục con dưới "Bộ sưu tập", bật cờ `acme_collection` → URL `/collections/…`:

| Bộ sưu tập | Product stream | Kết quả |
|---|---|---|
| Hàng mới về | `createdAt` trong 30 ngày (`since P30D`) | Cả 12 mẫu khi dữ liệu còn mới; tự rút dần theo thời gian |
| Thu Đông 2026 | tag `Thu Đông 2026` | 5 mẫu, đủ 3 brand |
| Sale | giảm so với giá niêm yết (`cheapestPrice.percentage > 0`) | 3 mẫu có `listPrice`, đủ 3 brand |

### Khoá id ≠ mã SKU

```
key     ANV-1005   ← sinh id sản phẩm, ảnh sản phẩm, visibility — KHÔNG BAO GIỜ ĐỔI
number  MAY-1005   ← mã SKU hiển thị, mang tiền tố brand
```

Tách hai thứ này để đổi brand của một mẫu (đổi tiền tố SKU) mà chạy lại seed vẫn **cập nhật** sản phẩm cũ thay vì tạo sản phẩm thứ hai. Biến thể do `acme:catalog:variants` sinh ra (`ANV-1005-01`) được importer đổi theo mã cha (`MAY-1005-01`), giữ nguyên hậu tố.

Unit test bảo vệ các bất biến này: `vendor/bin/phpunit -c custom/plugins/AcmeDemoData`.

## Cách dùng

> ⚠️ **Cài và kích hoạt plugin KHÔNG tự nhập sản phẩm.** Phải bấm nút (hoặc chạy lệnh) — đây là chủ ý, khác `SwagPlatformDemoData` (plugin đó nhập ngay khi activate).

**Cách 1 — nút bấm trong Administration (khuyến nghị)**

Extensions → My extensions → **Acme Demo Data** → ⋯ → Configure → thẻ **Dữ liệu demo** → nút **Nhập dữ liệu demo**.

Thẻ này hiện sẵn tình trạng (`Ảnh đã nạp: 66/66 · Sản phẩm demo: 12/12`) và một thanh tiến trình khi đang chạy.

**Cách 2 — dòng lệnh**

```bash
php bin/console acme:demo:seed
```

Hai đường chạy **cùng một service** `DemoDataImporter` nên cho kết quả giống hệt nhau.

### Chạy lại bao nhiêu lần cũng được

Toàn bộ id (sản phẩm, ảnh, danh mục, thuộc tính, product_media, visibility) suy ra từ tên file / mã sản phẩm bằng `Uuid::fromStringToHex()` rồi ghi bằng `upsert`. Bấm lần hai chỉ cập nhật, không nhân bản — cách này học từ [SwagPlatformDemoData](https://github.com/shopware/SwagPlatformDemoData).

Đã kiểm chứng: chạy lần hai vẫn đúng 12 sản phẩm / 47 product_media / 12 visibility, và chỉ mất 0,6 giây thay vì 10 giây.

> Vì id gắn với **mã sản phẩm**, mà mã sinh theo vị trí trong mảng `DemoProductCatalog::definitions()`, nên **không được chèn sản phẩm vào giữa mảng** — làm thế sẽ dịch mã của mọi sản phẩm phía sau và tạo ra bản sao thay vì cập nhật. Thêm mới thì thêm vào cuối.

### Nhập theo từng đợt

Bộ ảnh 78 MB nạp một lần trong một request HTTP dễ chạm `max_execution_time`. Nên `import()` nhận `offset` và mỗi lần chỉ nạp 8 ảnh; Administration gọi lặp cho tới khi `done = true`:

| Route | Việc |
|---|---|
| `GET /api/_action/acme-demo-data/status` | tình trạng hiện tại, và lý do chưa nhập được (nếu có) |
| `POST /api/_action/acme-demo-data/import` | nhập một đợt, trả về `offset` để gọi tiếp |

Đo thực tế: 66 ảnh + 12 sản phẩm mất **10,5 giây** chia thành 9 request.

## Lệnh làm gì

| Bước | Nội dung |
|---|---|
| 1 | Tạo 4 danh mục: Đầm, Áo, Chân váy, Bộ sưu tập |
| 2 | Tạo thuộc tính **Màu sắc** (7 giá trị, có mã hex để hiển thị swatch) và **Kích cỡ** (S/M/L/XL) |
| 3 | Nạp 66 ảnh vào thư viện media |
| 4 | Tạo 12 sản phẩm, gán ảnh + thuộc tính + danh mục + visibility |

## Dữ liệu sinh ra

12 sản phẩm với giá VND thực tế (590.000 – 1.890.000 ₫), mô tả tiếng Việt viết theo từng ảnh cụ thể:

| Mã | Tên | Giá |
|---|---|---|
| ANV-1001 | Đầm maxi hoa nhí dáng xoè | 1.250.000 ₫ |
| ANV-1002 | Áo polo dệt kim tay ngắn | 690.000 ₫ |
| ANV-1003 | Đầm midi cổ thuyền xếp ly | 1.450.000 ₫ |
| ANV-1004 | Đầm đen cổ chữ V dáng xoè | 1.390.000 ₫ |
| ANV-1005 | Set áo sơ mi & chân váy tông kem | 1.690.000 ₫ |
| ANV-1006 | Đầm trắng sát nách cúc bọc | 1.290.000 ₫ |
| ANV-1007 | Đầm đen tay loe dáng ôm | 1.490.000 ₫ |
| ANV-1008 | Áo thun kẻ sọc phối chân váy | 590.000 ₫ |
| ANV-1009 | Đầm lụa xanh pastel dáng ôm | 1.590.000 ₫ |
| ANV-1010 | Set áo lụa & chân váy xếp ly | 1.790.000 ₫ |
| ANV-1011 | Chân váy dài xếp ly tông be | 790.000 ₫ |
| ANV-1012 | Đầm dạ hội dáng dài tay bồng | 1.890.000 ₫ |

Mỗi sản phẩm có 3–4 ảnh, custom field `acme_material` (chất liệu) và `acme_model_size` (người mẫu mặc size) — đúng các trường đã đề xuất trong [../05-catalog-fashion.md](../05-catalog-fashion.md).

Giá net tính ngược từ giá gross theo VAT 10%: `net = round(gross / 1.10)`.

---

## Bẫy đã gặp: bản dịch chỉ vào en-US

DAL ghi text theo ngôn ngữ của `Context`, mà `Context::createDefaultContext()` là ngôn ngữ hệ thống (**en-US**), trong khi storefront chạy **vi-VN**.

**Đã xử lý trong code**, không cần SQL tay nữa: `DemoDataImporter::translate()` nhân bản các trường dịch được sang cả hai ngôn ngữ ngay trong payload.

```php
'translations' => [
    Defaults::LANGUAGE_SYSTEM => ['name' => …, 'description' => …],
    $vietnameseLanguageId     => ['name' => …, 'description' => …],
],
```

Áp dụng cho `product`, `category`, `property_group` và `property_group_option`. Kiểm chứng bằng SQL sau khi nhập:

```sql
SELECT lo.code, count(*) FROM product_translation pt
JOIN language l ON l.id = pt.language_id
JOIN locale lo ON lo.id = l.locale_id GROUP BY lo.code;
-- vi-VN 12 · en-US 12
```

> Nếu shop chưa cài gói ngôn ngữ vi-VN thì importer chỉ ghi ngôn ngữ hệ thống — không lỗi, storefront vẫn hiển thị nhờ fallback.

---

## JS của Administration viết tay, không qua Vite

`src/Resources/public/administration/` bình thường là **thư mục build** do Vite sinh ra từ `src/Resources/app/administration/src/main.js`. Ở plugin này nó là **mã nguồn viết tay**.

Lý do: `bin/build-administration.sh` cần `npm install` trong `vendor/shopware/administration` (~1 GB phụ thuộc), trong khi thứ cần thêm chỉ là một component nhỏ. Administration nạp JS của plugin bằng `<script type="module">` (xem `ApplicationBootstrapper::injectPlugin`), nên một ES module thuần chỉ dùng biến toàn cục `Shopware` chạy được ngay — không cần build.

Hai file phải khớp nhau:

| File | Vai trò |
|---|---|
| `public/administration/js/acme-demo-data.js` | component + api service |
| `public/administration/.vite/entrypoints.json` | Shopware đọc file này để biết nạp JS nào |

Khoá trong `entryPoints` phải đúng **technical name** của bundle — `AcmeDemoData` → `acme_demo_data` → `acme-demo-data` (xem `ViteFileAccessorDecorator::getTechnicalBundleName`). Sai khoá thì không có lỗi nào hiện ra, nút chỉ đơn giản là không xuất hiện.

> **Nhãn (snippet) của Administration không đặt trong JS** mà trong `src/Resources/app/administration/src/snippet/<locale>.json` — Administration đọc file này phía server, không cần build. Mẫu: `AcmeBrand`.
>
> 🔴 **Không tạo `src/Resources/app/administration/src/main.js`.** Có file đó thì lần chạy `bin/build-administration.sh` đầu tiên sẽ ghi đè cả thư mục `public/administration/`, xoá mất file viết tay.

Sau mỗi lần sửa JS:

```bash
php bin/console assets:install     # chép sang public/bundles/acmedemodata/
```

---

## Kiểm chứng

```bash
# Trang danh mục
curl -s "http://shopware-fashion-brand.test/dam/" | grep -oE '[0-9]{1,3}(\.[0-9]{3})+\s*₫' | sort -u

# Trang chi tiết
curl -s "http://shopware-fashion-brand.test/dam-maxi-hoa-nhi-dang-xoe/anv-1001" -o /dev/null -w "%{http_code}\n"

# Route API của nút bấm có tồn tại không
php bin/console debug:router | grep acme-demo-data

# File JS có được phục vụ không (Shopware nạp bằng URL có hai dấu gạch chéo)
curl -s -o /dev/null -w "%{http_code}\n" \
  "http://shopware-fashion-brand.test//bundles/acmedemodata/administration/js/acme-demo-data.js"
```

Kết quả thực tế đã kiểm chứng (30/08/2026):
- Giá render `1.250.000 ₫` đúng chuẩn VN
- SEO URL tiếng Việt không dấu: `Dam-maxi-hoa-nhi-dang-xoe/ANV-1001` (từ 14/10/2026 chữ thường: `dam-maxi-hoa-nhi-dang-xoe/anv-1001`)
- Ảnh gốc phục vụ HTTP 200, trang chi tiết hiện đủ 4 ảnh
- Hai route API trả 401 khi chưa đăng nhập → đúng scope `api`
- **Chụp ảnh màn hình storefront** (danh mục + chi tiết), không chỉ đọc HTML — theo [../02-theme.md](../02-theme.md)

---

## Gỡ bỏ khi nhập catalog thật

**Cách 1 — từ Administration (đơn giản nhất)**

Settings → System → Plugins → AcmeDemoData → Gỡ cài đặt → Xoá.

**Cách 2 — dòng lệnh**

```bash
php bin/console plugin:deactivate AcmeDemoData
php bin/console plugin:uninstall AcmeDemoData
rm -rf custom/plugins/AcmeDemoData
php bin/console plugin:refresh
php bin/console cache:clear
```

Không cần `composer remove` vì plugin không được composer quản lý.

> ⚠️ Plugin này **không tự xoá sản phẩm** khi gỡ (khác `SwagPlatformDemoData`). Đã kiểm chứng: sau `plugin:uninstall`, 12 sản phẩm vẫn còn nguyên. Muốn dọn sạch phải xoá thủ công sản phẩm của **cả ba** tiền tố demo, rồi hai brand giả định:
>
> ```sql
> DELETE FROM product WHERE product_number REGEXP '^(ANV|MAY|LLN)-';
> -- Brand giả định — phần mở rộng acme_brand tự xoá theo (CASCADE)
> DELETE pm FROM product_manufacturer pm
>   JOIN product_manufacturer_translation t ON t.product_manufacturer_id = pm.id
>  WHERE t.name IN ('Mây Atelier', 'Linh Lan');
> ```
>
> Anivele là brand thật — giữ lại, chỉ thay logo/banner/mô tả thật.
>
> Đây là chủ ý: dữ liệu demo vẫn dùng được để test theme kể cả khi đã gỡ plugin sinh ra nó.

---

## Lưu ý về dung lượng ảnh

Ảnh gốc rất nặng (**0.5–1.5 MB/ảnh**, tổng 78 MB). Với môi trường thật cần tối ưu trước khi upload — xem phần hiệu năng trong [../02-theme.md](../02-theme.md). Ảnh nặng là nguyên nhân hàng đầu khiến khách dùng mạng di động thoát trang.
