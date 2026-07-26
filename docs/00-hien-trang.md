# Hiện trạng hệ thống

Khảo sát ngày 26/07/2026. Tất cả số liệu dưới đây lấy trực tiếp từ database và CLI của hệ thống đang chạy.

## Nền tảng

| Thành phần | Phiên bản | Ghi chú |
|---|---|---|
| Shopware | 6.7.12.2 | env `prod`, debug off |
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
| product | 76 | 12 mẫu demo + 64 variant (màu × size) |
| category | 33 | 5 danh mục chính + 14 cấp 3 (megamenu) + 14 footer |
| customer | 0 | |
| order | 0 | Chưa có đơn nào |
| property_group | 3 | Màu sắc (6), Kích cỡ (S/M/L/XL), Hoạ tiết (5) |
| product_configurator_setting | 64 | Trục chọn màu/size trên storefront |
| media | 85 | 66 ảnh sản phẩm + ảnh hệ thống |
| theme | 2 | Storefront mặc định + `AniveleTheme` (đang dùng) |
| cms_page | 13 | +1 trang chủ Anivele (5 block) |

Catalog hiện tại do plugin tự viết `AniveleDemoData` sinh ra từ ảnh thật của brand — xem [runbook/05-demo-data.md](runbook/05-demo-data.md). Hệ thống **không dùng** `SwagPlatformDemoData`. **Vẫn cần thay bằng hàng thật trước go-live.**

**Cấu trúc variant đã hoàn tất cho cả 12 mẫu** — khách chọn được màu/size, tồn kho tách riêng từng biến thể, `is_closeout` chặn bán quá tồn kho trên toàn bộ 76 dòng sản phẩm. Tổng tồn kho variant khớp chính xác tồn kho mẫu cha ở cả 12/12. Chi tiết: [10-phase2-catalog.md](10-phase2-catalog.md).

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
| Invoice | active |
| Cash on delivery | active |
| Paid in advance | active |
| PayPal (SwagPayPal 10.7.0) | đã cài nhưng **inactive** |

Không có cổng thanh toán nội địa VN nào. COD có sẵn là điểm cộng vì COD chiếm tỷ trọng lớn ở VN, nhưng thiếu VNPay/MoMo là thiếu hụt lớn.

### Vận chuyển

```
Standard   active
Express    active
```

Đây là shipping method generic, chưa nối với đơn vị vận chuyển VN nào (GHN, GHTK, Viettel Post, J&T...).

### Quốc gia ✅

Từ **250 quốc gia active → chỉ còn Việt Nam**. Đã bổ sung tên tiếng Việt "Việt Nam", nạp **34 tỉnh/thành phố**, bỏ bắt buộc mã bưu chính và bắt buộc chọn Tỉnh/Thành phố khi đăng ký.

## Sales channel

| Tên | Currency | Country | Ngôn ngữ |
|---|---|---|---|
| anivele | VND | Việt Nam | vi-VN, en-US |
| Headless | VND | Việt Nam | de-DE, en-US |

`anivele` là storefront chính. `Headless` là API channel mặc định của Shopware.

## Plugin đang cài

| Plugin | Version | Active |
|---|---|---|
| SwagExtensionStore | 4.2.2 | Yes |
| SwagLanguagePack | dev-master | Yes |
| SwagPayPal | 10.7.0 | **No** |
| AniveleDemoData | 1.0.0 | Yes (plugin tự viết) |
| AniveleTheme | 1.0.0 | Yes (theme tự viết) |

`custom/apps/` rỗng — chưa có app nào.

## Tổng kết khoảng cách

Hệ thống đã là **shop tiếng Việt với nền tảng thị trường VN hoàn chỉnh** (VND, VAT, địa chỉ, catalog mẫu). Để bán được hàng thật còn cần:

1. ~~VND + thuế VN + tắt bớt quốc gia~~ — ✅ **xong ở Phase 0**
2. Cổng thanh toán nội địa — *hạng mục tốn kém nhất*
3. Đơn vị vận chuyển nội địa
4. Theme thương hiệu — *nền đã có, còn logo + size chart + ảnh theo màu*
5. Catalog thật thay demo data
6. Hạ tầng production (HTTPS, mail, backup tự động)

> ✅ Phiên bản MySQL và nền tảng thị trường VN không nằm trong danh sách rủi ro chặn go-live.

Chi tiết lộ trình: [01-roadmap.md](01-roadmap.md).
