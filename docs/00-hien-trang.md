# Hiện trạng hệ thống

Khảo sát ngày 26/07/2026. Tất cả số liệu dưới đây lấy trực tiếp từ database và CLI của hệ thống đang chạy.

> ✅ **Đối chiếu lại 12/10/2026:** DB local đã dựng lại từ đầu bằng các plugin `Acme*` và khớp số liệu dưới đây. `product_manufacturer` vẫn chỉ có `shopware AG` — chưa gán brand cho sản phẩm (Phase MB2).
>
> 🧭 **Định hướng mới:** nền tảng đa thương hiệu — [12-da-thuong-hieu.md](12-da-thuong-hieu.md).

## Nền tảng

| Thành phần | Phiên bản | Ghi chú |
|---|---|---|
| Shopware | 6.7.15.1 | env `prod`, debug off — nâng từ 6.7.12.2 qua `composer update` |
| PHP | 8.4.22 | |
| MySQL | 8.0.33 | ✅ Nằm trong ma trận hỗ trợ |
| APP_URL | http://shopware.test | chưa có HTTPS thật |

> ✅ **MySQL 8.0.33** nằm trong ma trận hỗ trợ của Shopware 6.7, và `mysqldump` client cùng phiên bản với server nên backup đáng tin cậy. Đã kiểm chứng đủ 260 bảng và test khôi phục thành công. Lý do chọn phiên bản này: [decisions/ADR-001-mysql.md](decisions/ADR-001-mysql.md).

## Ngôn ngữ ✅

Tiếng Việt đã được cài và đang hoạt động:

- Plugin `swag/language-pack` bản `dev-master` — **đây là nhánh dev, không phải release ổn định**. Bản release mới nhất (5.63.0) chỉ hỗ trợ `>=6.6.0 <6.7` nên không dùng được.
- Ngôn ngữ `vi-VN` bật cho cả Storefront lẫn Administration.
- Snippet set `LanguagePack vi-VN`, 811 key storefront.
- Domain `http://shopware.test` và `https://shopware.test` dùng `vi-VN`.
- Domain `default.headless0` (API) vẫn giữ `en-US` — đúng như mong muốn.

## Dữ liệu hiện có

| Bảng | Số lượng | Đánh giá |
|---|---|---|
| product | 76 | 12 sản phẩm cha + **64 biến thể**, giá VND |
| category | 5 | Danh mục chính + Đầm/Áo/Chân váy/Bộ sưu tập |
| customer | 0 | |
| order | 0 | Chưa có đơn nào |
| property_group | 3 | Màu sắc (6 giá trị, swatch), Kích cỡ (S/M/L/XL), Hoạ tiết (1) |
| product_configurator_setting | 64 | Trục sinh biến thể — trước Phase 2 là 0 |
| media | 85 | 66 ảnh sản phẩm + ảnh hệ thống |
| theme | 2 | Storefront mặc định + **AcmeTheme** (đang dùng) |

Catalog hiện tại do plugin tự viết `AcmeDemoData` sinh ra từ ảnh thật của brand — xem [runbook/05-demo-data.md](runbook/05-demo-data.md). Hệ thống **không dùng** `SwagPlatformDemoData`. **Vẫn cần thay bằng hàng thật trước go-live.**

> ✅ **Đã có variant** (Phase 2). 12 sản phẩm cha sinh ra **64 biến thể** Màu × Size, mỗi biến thể có SKU riêng (`ANV-1001-01`), tồn kho riêng và `is_closeout = 1`. Khách chọn được size khi mua; hết size S không ảnh hưởng size M. Chi tiết: [10-phase2-catalog.md](10-phase2-catalog.md).

## Cấu hình thương mại — đây là phần cần sửa nhiều nhất

### Tiền tệ: VND ✅

```
iso_code  factor  symbol  position  decimals
VND       1       ₫       sau số    0
```

VND nằm ở **chính row `Defaults::CURRENCY`**, không phải row mới thêm vào — đây là điều kiện để mọi entity mặc định tham chiếu đúng tiền tệ. Storefront render `1.250.000 ₫` đúng chuẩn VN. Cách thực hiện: [runbook/04-phase0-da-thuc-hien.md](runbook/04-phase0-da-thuc-hien.md).

### Thuế: VAT Việt Nam ✅

```
VAT 10%                10.000%
VAT 8%                  8.000%
Không chịu thuế (0%)    0.000%
```

`display_gross = 1` — giá hiển thị đã gồm VAT, đúng thói quen bán lẻ VN.

> ⚠️ **Vẫn cần xác nhận với kế toán** mức áp dụng cho hàng may mặc tại thời điểm go-live.

### Thanh toán: chỉ có phương thức offline

| Phương thức | Trạng thái |
|---|---|
| Thanh toán khi nhận hàng (COD) | active — gắn storefront |
| Chuyển khoản trước | active — gắn storefront |
| **Chuyển khoản QR (VietQR)** | active — plugin `AcmePayment` |
| Thanh toán bằng hoá đơn | active, không gắn storefront |
| PayPal | **đã gỡ hẳn** (08/08/2026) — xem [03-plugin-app.md](03-plugin-app.md) |

Đã có VietQR (chuyển khoản QR chuẩn NAPAS) tự viết. Chưa có VNPay/MoMo — xem [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md).

> ⚠️ VietQR **chưa cấu hình số tài khoản thật**. Phải nhập tại Administration → Extensions trước khi mở bán.

### Vận chuyển

```
Giao hàng tiêu chuẩn   active   — bảng phí theo khu vực
Giao hàng nhanh        active   — chưa cấu hình phí
```

Bảng phí phẳng của "Giao hàng tiêu chuẩn", đã kiểm chứng bằng giỏ hàng thật:

| Điều kiện | Phí |
|---|---|
| Địa chỉ giao ở Hà Nội / Hồ Chí Minh | 25.000 ₫ |
| Các tỉnh thành khác | 35.000 ₫ |
| Đơn từ 500.000 ₫ | **miễn phí** |

Chưa nối API đơn vị vận chuyển VN nào (GHN, GHTK, Viettel Post, J&T) — đó là giai đoạn 2.

### Quốc gia ✅

Từ **250 quốc gia active → chỉ còn Việt Nam**. Đã bổ sung tên tiếng Việt "Việt Nam", nạp **34 tỉnh/thành phố**, bỏ bắt buộc mã bưu chính và bắt buộc chọn Tỉnh/Thành phố khi đăng ký.

## Sales channel

| Tên | Currency | Country | Ngôn ngữ |
|---|---|---|---|
| anivele | VND | Việt Nam | vi-VN, en-US |
| Headless | VND | Việt Nam | de-DE, en-US |

`anivele` là storefront chính — và là storefront **duy nhất** cho mọi thương hiệu theo mô hình đa thương hiệu. `Headless` là API channel mặc định của Shopware.

## Plugin đang cài

| Plugin | Version | Active | Ghi chú |
|---|---|---|---|
| AcmeVietnam | 1.0.0 | Yes | Plugin tự viết — nền tảng thị trường VN |
| AcmeCatalog | 1.0.0 | Yes | Plugin tự viết — biến thể Màu × Size |
| AcmePayment | 1.0.0 | Yes | Plugin tự viết — VietQR + phí vận chuyển |
| AcmeTheme | 1.0.0 | Yes | Plugin tự viết — giao diện thương hiệu |
| AcmeDemoData | 1.0.0 | Yes | Plugin tự viết — dữ liệu demo |
| AcmeBrand | 1.0.0 | Yes | Plugin tự viết — thương hiệu (Phase MB1) |
| SwagLanguagePack | dev-master | Yes | Plugin bên thứ ba duy nhất còn lại |

Thư viện ngoài: `endroid/qr-code` (sinh ảnh QR) — cài ở cấp dự án vì Shopware không nạp `vendor/` của plugin, xem [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md).

`custom/apps/` rỗng — chưa có app nào.

## Tổng kết khoảng cách

Hệ thống đã là **shop tiếng Việt với nền tảng thị trường VN hoàn chỉnh** (VND, VAT, địa chỉ, catalog mẫu). Để bán được hàng thật còn cần:

1. ~~VND + thuế VN + tắt bớt quốc gia~~ — ✅ **xong ở Phase 0**
2. Cổng thanh toán nội địa — *hạng mục tốn kém nhất*
3. Đơn vị vận chuyển nội địa
4. Theme thương hiệu
5. Catalog thật thay demo data — **có gán thương hiệu** cho từng sản phẩm
6. Hạ tầng production (HTTPS, mail, backup tự động)
7. **Đa thương hiệu** — trang brand, bộ sưu tập, SEO theo brand: [12-da-thuong-hieu.md](12-da-thuong-hieu.md)

> ✅ Phiên bản MySQL và nền tảng thị trường VN không nằm trong danh sách rủi ro chặn go-live.

Chi tiết lộ trình: [01-roadmap.md](01-roadmap.md).
