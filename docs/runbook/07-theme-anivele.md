# Runbook: Theme AniveleTheme

Theme thương hiệu kế thừa Storefront mặc định. Hướng và lý do: [../02-theme.md](../02-theme.md).

Plugin nằm ở `custom/plugins/AniveleTheme/` — **không sửa `vendor/`**, theo [../08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md).

---

## Lệnh

```bash
# Sinh khung theme (đã chạy, chỉ cần khi dựng lại từ đầu)
php bin/console theme:create AniveleTheme

# Cài và kích hoạt
php bin/console plugin:refresh
php bin/console plugin:install --activate AniveleTheme
php bin/console cache:clear

# Gán cho sales channel anivele
php bin/console theme:change AniveleTheme -s 019f9d8157c5724999d4b32d220a5b41

# Biên dịch lại sau khi sửa SCSS hoặc theme.json
php bin/console theme:compile
```

> ⚠️ **Sửa `theme.json` xong phải `cache:clear` trước khi `theme:compile`.** Nếu không, bản build cũ vẫn được dùng và thay đổi không hiện ra. Dấu hiệu nhận biết: hash thư mục trong `public/theme/` không đổi.

---

## Cấu trúc

```
custom/plugins/AniveleTheme/
├── composer.json
└── src/
    ├── AniveleTheme.php
    └── Resources/
        ├── theme.json                          # kế thừa + biến cấu hình
        └── app/storefront/src/scss/
            ├── overrides.scss                  # nạp TRƯỚC @Storefront
            └── base.scss                       # nạp SAU @Storefront
```

**Thứ tự nạp quyết định cách ghi đè:**

| File | Vị trí | Dùng để |
|---|---|---|
| `overrides.scss` | Trước `@Storefront` | Đặt **biến** SCSS (`$border-radius`, `$btn-padding-y`…) — thắng giá trị `!default` của core |
| `base.scss` | Sau `@Storefront` | Viết **quy tắc CSS** thật — ghi đè được CSS của core |

Đặt nhầm chỗ thì không có tác dụng: biến đặt ở `base.scss` là quá muộn, CSS đặt ở `overrides.scss` bị core ghi đè lại.

---

## Biến cấu hình

Chỉnh trong Administration → Content → Themes → Anivele Theme. Giá trị mặc định lấy từ tông ảnh thật của brand (kem, be, nâu):

| Biến | Mặc định | Tác dụng |
|---|---|---|
| `sw-color-brand-primary` | `#6b4a34` | Màu chính, Bootstrap suy ra `$primary` |
| `sw-color-brand-secondary` | `#8a7256` | Màu phụ |
| `anivele-color-surface` | `#faf7f2` | Nền trang |
| `anivele-color-accent` | `#b08d57` | Giá, viền lựa chọn đang chọn |
| `sw-color-buy-button` | `#6b4a34` | Nút "Thêm vào giỏ" |
| `sw-color-buy-button-text` | `#ffffff` | Chữ trên nút mua |

> ⚠️ **Nút mua có biến riêng.** Core dùng `$sw-color-buy-button` (mặc định xanh `#0042a0`) cho `.btn-buy`, **không** lấy theo `$primary`. Chỉ đổi `sw-color-brand-primary` thì nút mua vẫn xanh. Phải khai báo cả `sw-color-buy-button` trong `theme.json`.

---

## Bộ chọn biến thể — phần quan trọng nhất

Core đánh dấu lựa chọn **còn hàng** bằng class `is-combinable`. Lựa chọn hết hàng **không có** class đó.

Theme dùng `:not(.is-combinable)` để làm mờ + gạch chéo:

```scss
.product-detail-configurator-option-label {
    &:not(.is-combinable) {
        opacity: 0.4;
        cursor: not-allowed;
        // gạch chéo bằng linear-gradient ở ::after
    }
}
```

Đây là phần bù cho hạn chế đã ghi ở Phase 2: mặc định Shopware vẫn cho **chọn** size hết hàng, chỉ chặn ở bước thêm vào giỏ. Theme làm cho trạng thái đó **nhìn thấy được** mà không ẩn size đi.

> Ẩn size hết hàng là lỗi UX phổ biến — khách không biết brand có size đó hay không nên không quay lại.

Swatch màu dùng `display_type = color` của core, theme bo tròn 50% để phân biệt với nút size.

---

## Trang chủ

Dựng bằng **CMS block có sẵn của core** — không tự viết block mới.

```bash
php bin/console anivele:theme:build-homepage --assign
```

5 block, theo thứ tự:

| # | Block core | Nội dung |
|---|---|---|
| 1 | `text-hero` | Tên brand + câu định vị |
| 2 | `product-slider` | Sản phẩm nổi bật (6 mẫu đầu) |
| 3 | `text` | Câu chuyện thương hiệu |
| 4 | `product-slider` | Hàng mới về (các mẫu còn lại) |
| 5 | `text-three-column` | 3 cam kết: đổi trả 7 ngày · COD · giao toàn quốc |

Lệnh **chạy lại được**: xoá trang cũ rồi dựng lại. Sửa nội dung sau đó trong Administration → Content → Shopping Experiences.

> ⚠️ **`product-slider` phải có `products` là mảng ID thật.** Truyền mảng rỗng thì resolver của core thoát sớm (`if (!$productConfig->getValue()) return null;`) và slider hiện 0 sản phẩm mà không báo lỗi.

> ⚠️ **Không xoá được CMS page khi còn category trỏ tới nó.** DAL báo `RestrictDeleteViolationException`. Lệnh tự gỡ `cmsPageId` của category trước khi xoá.

---

## Module Administration: Menu & Footer

Giao diện quản lý trong Admin: **Content → Menu & Footer**.

| Làm được gì | Cách dùng |
|---|---|
| Dựng lại menu/footer | Nút **Dựng lại** ở thanh trên, có checkbox chọn dựng menu / footer / ảnh teaser |
| **Ẩn một mục** khỏi storefront | Nút **Ẩn** cạnh từng mục — đặt `visible = false`, mục vẫn truy cập được bằng URL trực tiếp |
| Thêm mục mới | Nút **Thêm mục con** ở từng danh mục |
| Xem mục nào có ảnh teaser | Nhãn "Có ảnh" |

Module cảnh báo khi `navigation_category_depth < 3` — megamenu sẽ không xổ.

> **Ẩn (`visible`) khác tắt (`active`).** Ẩn chỉ gỡ khỏi menu, URL vẫn vào được — hợp khi tạm ngưng bán một dòng hàng. Tắt hẳn thì dùng Catalogues → Categories → Active.

### Kiến trúc

Cả CLI lẫn module Admin đều gọi chung `Service\NavigationBuilder`, nên hai đường không lệch hành vi.

```
src/
├── Service/NavigationBuilder.php          # toàn bộ logic
├── Controller/NavigationBuilderController.php  # 4 route Admin API
├── Command/BuildNavigationCommand.php     # CLI
└── Resources/app/administration/src/
    ├── main.js
    ├── service/anivele-navigation.api.service.js
    ├── snippet/{vi-VN,en-GB}.json
    └── module/anivele-navigation/
```

Route Admin API (đều có ACL `category:*`):

| Method | Path |
|---|---|
| GET | `/api/_action/anivele/navigation` |
| POST | `/api/_action/anivele/navigation/build` |
| POST | `/api/_action/anivele/navigation/visibility` |
| POST | `/api/_action/anivele/navigation/item` |

> ⚠️ **Sửa code Administration xong phải build lại**, nếu không module không hiện trong Admin:
>
> ```bash
> bash bin/build-administration.sh
> ```
>
> Kiểm tra bundle đã đăng ký: `GET /api/_info/config` phải có `AniveleTheme` trong `bundles`.

---

## Megamenu và Footer (CLI)

```bash
php bin/console anivele:theme:build-navigation              # cả hai
php bin/console anivele:theme:build-navigation --menu-only
php bin/console anivele:theme:build-navigation --footer-only
php bin/console cache:clear
```

### Megamenu

> **Megamenu là tính năng có sẵn của core**, không cần override template. Flyout nhiều cột (kèm chỗ cho ảnh teaser) tự render khi thoả **hai** điều kiện:
> 1. Danh mục có **con ở cấp 3**
> 2. Sales channel có `navigation_category_depth >= 3`
>
> Mặc định depth = 2 nên dù thêm danh mục con, flyout vẫn không xổ. Lệnh tự đặt lên 3.

14 danh mục cấp 3 được tạo:

| Danh mục cha | Danh mục con |
|---|---|
| Đầm | Đầm maxi · Đầm midi · Đầm công sở · Đầm dạ hội |
| Áo | Áo sơ mi · Áo thun · Áo polo · Áo khoác |
| Chân váy | Chân váy dài · Chân váy ngắn · Chân váy xếp ly |
| Bộ sưu tập | Set công sở · Set dạo phố · Hàng mới về |

#### Ảnh teaser trong megamenu

> **Ảnh teaser cũng là tính năng sẵn có của core.** Flyout hiện ảnh khi `category.media` khác null: cột link co từ `col` xuống `col-8 col-xl-9`, cột ảnh chiếm `col-4 col-xl-3`. Không cần override template.

```bash
# Tự lấy ảnh bìa của sản phẩm trong từng danh mục
php bin/console anivele:theme:build-navigation --menu-only --with-images

# Gán ảnh chỉ định
php bin/console anivele:theme:build-navigation --set-image="Đầm:019f9ddbec96710c8426c75360c5cf29"

# Gỡ ảnh
php bin/console anivele:theme:build-navigation --clear-images
```

`--with-images` lấy **ảnh bìa của sản phẩm cha đầu tiên** trong danh mục — chỉ hợp cho dữ liệu demo. Ảnh campaign thật thì upload trong Administration → Catalogues → chọn danh mục → Media, hoặc dùng `--set-image` với media id.

Lấy media id:

```sql
SELECT LOWER(HEX(id)), file_name FROM media WHERE file_name LIKE '%ten-anh%';
```

Theme cắt ảnh teaser theo tỷ lệ 3:4 (`aspect-ratio`) cho hợp ảnh thời trang dạng đứng, kèm hiệu ứng phóng nhẹ khi rê chuột.

Lệnh còn **gán sản phẩm vào danh mục con theo từ khoá** trong tên (`KEYWORD_MAP`). Danh mục con rỗng là ngõ cụt — khách bấm menu rồi thấy trang trắng.

> ⚠️ Gán theo từ khoá chỉ là giải pháp cho **dữ liệu demo**. Khi có hàng thật, gán danh mục trong Administration. Lệnh báo rõ số sản phẩm chưa khớp để không ai tưởng đã xong.

### Footer

> Footer đọc từ `footer_category_id` của sales channel. Mặc định **NULL** nên storefront chỉ hiện cột "Đường dây nóng dịch vụ" cứng trong template — thêm danh mục thường không làm footer đổi.

Lệnh dựng cây riêng `Footer Anivele` (type `link`) rồi trỏ `footer_category_id` vào:

| Cột | Mục |
|---|---|
| Về Anivele | Câu chuyện thương hiệu · Hệ thống cửa hàng · Tuyển dụng |
| Hỗ trợ khách hàng | Hướng dẫn chọn size · Chính sách đổi trả · Chính sách bảo mật · Câu hỏi thường gặp |
| Mua sắm | Hướng dẫn đặt hàng · Phương thức thanh toán · Chính sách vận chuyển |

Các mục dùng `type = link` nên **chưa trỏ đi đâu**. Gán trang đích trong Administration → Catalogues → Footer Anivele.

---

## Trang sản phẩm — ảnh cuộn, thông tin dính

Core chia 2 cột: `.product-detail-media` (col-lg-7) và `.product-detail-buy` (col-lg-5). Theme cho cột mua hàng `position: sticky` để nút "Thêm vào giỏ" luôn trong tầm mắt khi khách cuộn xem gallery.

```scss
@include media-breakpoint-up(lg) {
    .product-detail-buy {
        position: sticky;
        top: 2rem;
        align-self: flex-start;   // bắt buộc: cột flex mặc định stretch, sticky sẽ vô hiệu
        max-height: calc(100vh - 4rem);
        overflow-y: auto;
    }
}
```

Chỉ bật từ `lg` trở lên — trên mobile hai cột xếp chồng, sticky sẽ che nội dung.

---

## Thanh toán 1 trang

> **Shopware 6.7 vốn đã là checkout 1 trang.** Chỉ có 3 route: `cart` → `confirm` → `finish`, không có wizard nhiều bước. Trang `confirm` gom địa chỉ, vận chuyển, thanh toán và xem lại đơn vào cùng một chỗ; khách chưa đăng nhập thì `/checkout/confirm` chuyển sang `/checkout/register` — trang này cũng gom đủ "Thông tin vận chuyển", "Thông tin cá nhân", "Tóm tắt".

Không cần đổi luồng. Theme chỉ làm rõ thị giác:

- Viền nhẹ + giãn cách giữa các thẻ trong `.checkout-main`
- Khối tổng tiền `position: sticky` khi danh sách sản phẩm dài
- Nút đặt hàng to hơn, không lẫn với nút phụ

---

## Kiểm chứng

### CSS đã build đúng chưa

```bash
CSS=$(curl -s "http://shopware.test/Dam-den-co-chu-V-dang-xoe/ANV-1004" \
  | grep -oE '/theme/[a-f0-9]+/css/all.css' | head -1)

grep -o "\.btn-buy{--bs-btn-color[^}]*}" "public${CSS}" | cut -c1-110
grep -c "is-combinable" "public${CSS}"
```

Nút mua phải là `#6b4a34`, không phải `#0042a0`.

### Trạng thái hết hàng

```bash
mysql -uroot -h127.0.0.1 -P3306 shopware \
  -e "UPDATE product SET stock=0, available_stock=0, available=0 WHERE product_number='ANV-1004-DEN-S';"
php bin/console dal:refresh:index --skip=category.indexer,media.indexer,rule.indexer
```

Mở trang sản phẩm: size S phải mờ + gạch chéo, M/L/XL bình thường.

**Nhớ khôi phục sau khi test:**

```bash
mysql -uroot -h127.0.0.1 -P3306 shopware \
  -e "UPDATE product SET stock=18, available_stock=18, available=1 WHERE product_number='ANV-1004-DEN-S';"
```

### Ảnh chụp màn hình

```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless --disable-gpu \
  --window-size=1280,900 --screenshot=pdp.png --virtual-time-budget=8000 \
  "http://shopware.test/Dam-den-co-chu-V-dang-xoe/ANV-1004"
```

### Megamenu và footer

```bash
curl -s "http://shopware.test/" | grep -c "navigation-flyout-link"       # phải > 0
curl -s "http://shopware.test/" | grep -c "navigation-flyout-teaser-image" # ảnh teaser
curl -s "http://shopware.test/" | grep -c "col-8 col-xl-9"                 # = số danh mục có ảnh
curl -s "http://shopware.test/" | grep -c "footer-column-headline"   # phải >= 4
curl -s "http://shopware.test/Dam/Dam-maxi/" | grep -c "product-box" # danh mục con có hàng
```

### Trang chủ và thanh toán

```bash
curl -s "http://shopware.test/" | grep -c "product-box"          # phải > 0
```

Kiểm tra checkout cần **session có giỏ hàng**, headless Chrome không mang cookie nên sẽ thấy giỏ trống:

```bash
curl -s -c /tmp/ck -b /tmp/ck "http://shopware.test/checkout/cart" -o /dev/null
curl -s -c /tmp/ck -b /tmp/ck -X POST "http://shopware.test/checkout/product/add-by-number" \
  -d "number=ANV-1005-KEM-M&quantity=1" -L -o /dev/null
curl -s -b /tmp/ck -L "http://shopware.test/checkout/confirm" | grep -c "checkout-main"
```

> ⚠️ **Không dùng headless Chrome để kiểm tra responsive.** Nó render ở chiều rộng desktop rồi cắt ảnh theo `--window-size`, nên trang trông như bị tràn ngang dù CSS đúng. Kiểm tra mobile bằng DevTools device mode trên trình duyệt thật.

---

## Khôi phục

```bash
# Quay lại theme mặc định
php bin/console theme:change Storefront -s 019f9d8157c5724999d4b32d220a5b41

# Gỡ hẳn theme
php bin/console plugin:deactivate AniveleTheme
php bin/console plugin:uninstall AniveleTheme
```
