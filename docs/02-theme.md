# Theme thương hiệu

> ✅ **Đã thực hiện giai đoạn 1** (08/08/2026) — plugin `AniveleTheme`. Tài liệu này giữ nguyên phần lý do và nguyên tắc; phần "đã kiểm chứng" bổ sung ở cuối.

Hướng đã chốt: **tự xây theme riêng kế thừa Storefront mặc định**.

## Vì sao chọn hướng này

| Tiêu chí | Theme riêng | Theme mua sẵn |
|---|---|---|
| Bản sắc thương hiệu | Toàn quyền kiểm soát | Bị giới hạn trong khuôn mẫu, dễ "đụng hàng" |
| Chi phí ban đầu | Công dev | Tiền license |
| Nâng cấp Shopware | Tự chủ, chỉ sửa phần mình viết | Phụ thuộc nhà cung cấp có cập nhật hay không |
| Hiệu năng | Chỉ nạp thứ mình cần | Thường kèm nhiều tính năng thừa |

Với brand fashion, khác biệt thị giác là tài sản. Theme mua sẵn khiến shop trông giống hàng trăm shop khác — đây là lý do chính.

## Nguyên tắc kỹ thuật

### Kế thừa, không sửa trực tiếp Storefront

Theme mới phải kế thừa `Storefront` chứ không chỉnh sửa file trong `vendor/`. Mọi thay đổi trong `vendor/` sẽ mất khi chạy `composer update`.

> Đây là hệ quả của quy tắc bắt buộc toàn dự án — xem [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md).

Cơ chế của Shopware: khai báo `extends Storefront` → chỉ cần override đúng block Twig cần đổi, phần còn lại tự kế thừa.

### Chỉ override block cần thiết

Sai lầm phổ biến: copy nguyên file template rồi sửa vài dòng. Hậu quả là khi Shopware cập nhật template gốc, phần copy bị "đóng băng" ở phiên bản cũ và sinh lỗi khó truy vết.

Cách đúng: dùng `{% sw_extends %}` và chỉ ghi đè block cần đổi.

## Trọng tâm thiết kế cho thời trang

### Mobile-first — không phải tùy chọn

Tỷ trọng mua sắm qua điện thoại ở Việt Nam rất cao. Thiết kế phải bắt đầu từ màn hình nhỏ rồi mở rộng lên, không phải làm ngược lại.

### Ảnh sản phẩm là yếu tố quyết định

Thời trang bán bằng hình ảnh. Cần:
- Gallery nhiều góc chụp, hỗ trợ zoom
- Ảnh theo từng màu — khách chọn màu đỏ thì gallery phải đổi sang ảnh đỏ
- Định dạng ảnh hiện đại (WebP/AVIF) để giảm dung lượng
- Lazy loading cho ảnh dưới màn hình đầu

Đây cũng là mâu thuẫn lớn nhất cần cân bằng: **ảnh đẹp ↔ tốc độ tải**. Mạng di động VN không đồng đều, ảnh 5MB sẽ khiến khách thoát trang.

### Bộ chọn biến thể trực quan

Mặc định Shopware hiển thị variant bằng dropdown. Với thời trang cần:
- **Màu**: swatch hình tròn hiển thị màu thật, không phải chữ
- **Size**: nút bấm dạng lưới, size hết hàng phải hiện rõ (gạch chéo/mờ) chứ không ẩn đi

Ẩn size hết hàng là lỗi UX phổ biến — khách không biết shop có size đó hay không nên không quay lại.

### Bảng size (size chart)

Khách VN thường phân vân giữa các bảng size khác nhau. Cần popup bảng size ngay tại trang sản phẩm, đối chiếu số đo cơ thể (ngực–eo–mông) sang size của brand. Xem [05-catalog-fashion.md](05-catalog-fashion.md).

### Các thành phần nên có

- Lookbook / phối đồ — bán theo bộ thay vì từng món
- "Sản phẩm liên quan" theo phong cách, không chỉ theo danh mục
- Đánh giá kèm ảnh thật của khách
- Thông tin đổi trả hiển thị rõ ràng — rào cản tâm lý lớn khi mua quần áo online

## Cấu trúc thư mục dự kiến

```
custom/plugins/AniveleTheme/
├── composer.json
├── src/
│   ├── AniveleTheme.php
│   └── Resources/
│       ├── theme.json              # khai báo kế thừa + biến cấu hình
│       ├── app/storefront/
│       │   ├── src/                # JavaScript
│       │   └── dist/               # build output
│       ├── views/storefront/       # template Twig override
│       └── snippet/                # chuỗi văn bản riêng của theme
```

## Lưu ý về thứ tự công việc

**Không bắt đầu theme trước khi chốt xong currency VND.** Lý do: chuỗi giá VND dài hơn USD đáng kể (`1.250.000 ₫` so với `$52.00`). Layout thiết kế theo giá USD sẽ vỡ khi chuyển sang VND — đặc biệt ở khung sản phẩm trên mobile.

Thao tác cụ thể: [runbook/02-tao-theme.md](runbook/02-tao-theme.md)

---

## Đã kiểm chứng khi làm

### 🔴 Phải CHỤP ẢNH storefront, không chỉ đọc CSS

Bài học lớn nhất của phase này. CSS build ra đúng, `grep` thấy đủ class, mọi trang trả HTTP 200 — nhưng **chụp ảnh màn hình mới lộ ra 5 lỗi**, không lỗi nào phát hiện được bằng cách đọc code:

| Lỗi | Vì sao đọc code không thấy |
|---|---|
| Logo vẫn là "Demostore" | Ảnh mặc định của Shopware, không có trong CSS lẫn Twig của theme |
| Bộ lọc hiện "Pattern" thay vì "Hoạ tiết" | Bản dịch vi-VN trong database, không nằm trong code |
| Tiêu đề trang chủ hiện "Home" | Tên danh mục gốc trong database |
| Swatch màu bị dẹt thành bầu dục | CSS của tôi đúng cú pháp, nhưng core đặt `min-width` thắng |
| Tên sản phẩm dài bị cắt trên mobile | Chỉ xảy ra ở khổ hẹp |

Cách chụp (Chrome có sẵn trên máy):

```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless --disable-gpu --no-sandbox --hide-scrollbars \
  --window-size=1280,900 --screenshot=/tmp/shot.png \
  "http://shopware-fashion-brand.test/"

# Mobile — đừng bỏ qua, tỷ trọng mua qua điện thoại ở VN rất cao
--window-size=414,900
```

> **Mẹo phân biệt lỗi của mình hay của core:** tạm đổi về theme mặc định
> (`theme:change Storefront -s <id>`) rồi chụp lại. Nếu lỗi vẫn còn thì
> không phải do theme mình viết. Đã dùng đúng cách này để loại trừ một
> hiện tượng cắt chữ tưởng là bug nhưng thực ra là artifact của headless.

### Ghi đè CSS của core: phải đọc SCSS gốc trước

Core đặt `min-width: 3.75rem; height: 3.75rem` cho swatch màu tại
`vendor/shopware/storefront/Resources/app/storefront/src/scss/page/product-detail/_configurator.scss`.
Chỉ khai báo `width` là không đủ — phải ghi đè cả `min-width`.

Tương tự, core đặt `--bs-btn-padding-*` dạng biến CSS ngay trên class đó,
nên `padding: 0` thường không thắng; phải ghi đè chính biến.

> Core cũng đã có sẵn `.disabled { text-decoration: line-through }` cho size
> hết hàng — kiểm tra trước khi tự viết lại, tránh trùng lặp.

### Đối chiếu với theme thương mại (SPHERE Pro €468/năm)

Đã khảo sát [SPHERE Pro](https://store.shopware.com/en/zenit46893265141m/sphere-pro-multipurpose-premium-theme.html) để xem có nên mua thay vì tự làm. Kết quả đối chiếu từng nhóm tính năng với core 6.7:

| SPHERE quảng cáo | Thực tế core 6.7 |
|---|---|
| "67+ CMS elements" | Core **đã có 22 element + 40 block** |
| Native lazy loading | ✅ Đã có — ảnh listing đều `loading="lazy"` |
| Magnifier, zoom ảnh, slider | ✅ Đã có JS plugin `magnifier`, `image-zoom`, `zoom-modal`, `slider` |
| Offcanvas cart / filter | ✅ Đã có `offcanvas-cart`, `offcanvas-filter` |
| Wishlist | ✅ Core có, chỉ cần bật `core.cart.wishlistEnabled` |
| Sticky header, sticky buybox | ❌ Core không có → tự viết, mỗi cái ~10 dòng SCSS |
| Ảnh thứ 2 khi hover | ❌ Core không có → tự viết |

**Kết luận:** phần lớn giá trị của theme thương mại nằm ở **tính đa dụng** (5 layout, 17 template demo, child theme generator) — dành cho agency bán lại cho nhiều shop. Anivele là *một* shop thời trang nữ, không dùng tới.

Những thứ thực sự thiếu chỉ là vài chục dòng SCSS + một subscriber. Đã tự làm, không mua.

> ⚠️ **Không sao chép mã nguồn theme thương mại.** Học ý tưởng tính năng rồi tự viết bằng cơ chế mở rộng của Shopware là hợp lệ; copy code là vi phạm bản quyền.

### Bẫy: association lồng nhau trong listing

Khi làm ảnh hover, `product.media` **không được nạp** trong listing (chỉ có `cover`). Thêm subscriber gọi `addAssociation('media')` vẫn chưa đủ — `product.media` là bảng nối `ProductMedia`, entity `Media` thật nằm bên trong.

```php
// Sai — template thấy product.media có phần tử nhưng productMedia.media là null
$criteria->addAssociation('media');

// Đúng
$criteria->addAssociation('media.media');
```

Lỗi này **không báo gì cả**, chỉ âm thầm không hiện ảnh. Phát hiện bằng cách in tạm `{{ pm.media ? 'Y':'N' }}` ra HTML.

### Bộ chọn biến thể: core 6.7 đã đủ tốt

Mục "Bộ chọn biến thể trực quan" ở trên lo rằng Shopware hiển thị variant bằng
dropdown. **Không đúng với 6.7**: template `buy-widget/configurator.html.twig`
đã render radio button, và tự đổ màu nền khi `displayType == 'color'`.

Theme chỉ cần tinh chỉnh hình dáng (bo tròn, kích thước vùng chạm), không cần
viết lại template.
