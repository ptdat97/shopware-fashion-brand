# Hiện trạng hệ thống

Khảo sát ngày 26/07/2026. Tất cả số liệu dưới đây lấy trực tiếp từ database và CLI của hệ thống đang chạy.

## Nền tảng

| Thành phần | Phiên bản | Ghi chú |
|---|---|---|
| Shopware | 6.7.12.2 | env `prod`, debug off |
| PHP | 8.4.22 | |
| MySQL | 8.0.33 | ✅ Nằm trong ma trận hỗ trợ |
| APP_URL | http://shopware.test | chưa có HTTPS thật |

> ✅ **Đã xử lý (26/07/2026):** hệ thống trước đó chạy MySQL 9.7.1 — ngoài ma trận hỗ trợ của Shopware 6.7, đồng thời `mysqldump` cũ hơn server khiến backup không đáng tin. Đã migrate sang **MySQL 8.0.33**, kiểm chứng đủ 260 bảng và test khôi phục thành công. Chi tiết: [decisions/ADR-001-mysql.md](decisions/ADR-001-mysql.md).

## Ngôn ngữ (đã hoàn thành)

Tiếng Việt đã được cài và đang hoạt động:

- Plugin `swag/language-pack` bản `dev-master` — **đây là nhánh dev, không phải release ổn định**. Bản release mới nhất (5.63.0) chỉ hỗ trợ `>=6.6.0 <6.7` nên không dùng được.
- Ngôn ngữ `vi-VN` bật cho cả Storefront lẫn Administration.
- Snippet set `LanguagePack vi-VN`, 811 key storefront.
- Domain `http://shopware.test` và `https://shopware.test` đã chuyển sang `vi-VN`.
- Domain `default.headless0` (API) vẫn giữ `en-US` — đúng như mong muốn.

## Dữ liệu hiện có

| Bảng | Số lượng | Đánh giá |
|---|---|---|
| product | 16 | Demo data, **không phải hàng thật** |
| category | 9 | Demo |
| customer | 1 | Demo |
| order | 0 | Chưa có đơn nào |
| property_group | 5 | Target group, Colour, Ingredients, Size, Material |
| theme | 1 | Chỉ có Storefront mặc định |

Toàn bộ catalog hiện tại đến từ plugin `SwagPlatformDemoData`. **Cần xóa sạch trước khi go-live.**

## Cấu hình thương mại — đây là phần cần sửa nhiều nhất

### Tiền tệ: chỉ có USD

```
iso_code  factor
USD       1
```

**Không có VND trong hệ thống.** Sales channel `anivele` đang bán bằng USD dù country đã đặt là Viet Nam. Đây là vấn đề nghiêm trọng nhất phải xử lý trước tiên — xem [04-thi-truong-vn.md](04-thi-truong-vn.md).

### Thuế: đang là biểu thuế Đức

```
Standard rate     19.000%
Reduced rate       7.000%
Reduced rate 2     0.000%
```

19% và 7% là VAT Đức. VAT Việt Nam cho hàng may mặc là **10%** (một số giai đoạn có chính sách giảm còn 8% theo nghị quyết Quốc hội — cần xác nhận hiệu lực tại thời điểm go-live với kế toán).

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

### Quốc gia

Gần như **toàn bộ ~250 quốc gia đang active**. Với shop bán nội địa VN, đây là cấu hình sai: khách có thể chọn giao hàng đi Antarctica. Cần tắt bớt, chỉ để VN (và một số nước nếu có kế hoạch bán quốc tế).

## Sales channel

| Tên | Currency | Country | Ngôn ngữ |
|---|---|---|---|
| anivele | USD | Viet Nam | vi-VN, en-US |
| Headless | USD | Germany | de-DE, en-US |

`anivele` là storefront chính. `Headless` là API channel mặc định của Shopware.

## Plugin đang cài

| Plugin | Version | Active |
|---|---|---|
| SwagExtensionStore | 4.2.2 | Yes |
| SwagLanguagePack | dev-master | Yes |
| SwagPayPal | 10.7.0 | **No** |
| SwagPlatformDemoData | 2.1.0 | Yes |

`custom/apps/` rỗng — chưa có app nào.

## Tổng kết khoảng cách

Hệ thống hiện là **bản cài Shopware demo đã Việt hóa giao diện**, chưa phải shop thương mại. Để bán được hàng thật cần:

1. VND + thuế VN + tắt bớt quốc gia — *chặn mọi thứ khác*
2. Cổng thanh toán nội địa
3. Đơn vị vận chuyển nội địa
4. Theme thương hiệu
5. Catalog thật thay demo data
6. Hạ tầng production (HTTPS, mail, backup tự động)

> ✅ Rủi ro phiên bản MySQL đã được xử lý — không còn nằm trong danh sách chặn.

Chi tiết lộ trình: [01-roadmap.md](01-roadmap.md).
