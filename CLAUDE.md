# Anivele — Shop thời trang trên Shopware 6.7

Shop bán lẻ thời trang cho thị trường Việt Nam. Toàn bộ tài liệu kỹ thuật nằm ở [docs/](docs/).

## Bốn quy tắc cứng

**1. Không sửa `vendor/`.** Mọi tuỳ biến viết qua Plugin, App hoặc Theme trong `custom/`. `composer update` ghi đè sạch `vendor/`, git không track thư mục này.

**2. Không `composer require` plugin tự viết.** Shopware tự quét `custom/plugins/`. Thêm vào `composer.json` sẽ đặt `managed_by_composer = 1` và **khoá nút Xoá plugin trong Administration**. Chỉ dùng `composer require` cho plugin bên thứ ba hoặc nâng version core.

**3. Đọc kiến trúc Shopware trước khi viết plugin. Không bịa API.** Shopware xoá interface giữa các bản major — `SynchronousPaymentHandlerInterface`, `AsynchronousPaymentHandlerInterface`, `PaymentHandlerInterface` đều **không còn tồn tại** trong 6.7 dù tutorial trên mạng vẫn dạy. Mọi tên class phải xác nhận trong `vendor/` trước khi dùng:

```bash
php bin/check-plugin-api.php     # bắt lỗi class không tồn tại
```

**4. Backup trước mọi thay đổi dữ liệu.** Xem [docs/runbook/00-backup.md](docs/runbook/00-backup.md).

Chi tiết và lý do: [docs/08-quy-tac-phat-trien.md](docs/08-quy-tac-phat-trien.md) · [docs/09-nguyen-tac-ky-thuat.md](docs/09-nguyen-tac-ky-thuat.md)

## Ngôn ngữ

Tài liệu, commit message và comment trong code viết bằng **tiếng Việt**. Tên biến/class/hàm giữ tiếng Anh theo chuẩn Shopware.

## Môi trường

```
APP_URL   http://shopware-fashion-brand.test
Database  mysql://root@127.0.0.1:3306/shopware
Shopware  6.7.12.2 · PHP 8.4 · MySQL 8.0.33
```

Storefront `anivele` chạy `vi-VN`, tiền tệ VND. Channel `Headless` giữ `en-US` — đúng như mong muốn.

## Cấu hình dữ liệu phải viết thành migration

Thay đổi cấu hình cần tái lập trên staging/production (tiền tệ, thuế, quốc gia, sales channel) **phải** viết thành migration trong plugin, không dùng SQL thủ công. Bài học đã trả giá: Phase 0 làm bằng SQL tay nên không đi theo git, dựng lại môi trường là mất sạch.

Cách làm: [docs/runbook/06-tao-plugin.md](docs/runbook/06-tao-plugin.md)

## Kiểm tra trước khi commit

```bash
git status --short vendor/                          # phải rỗng
git --no-pager diff composer.json composer.lock     # chỉ đổi khi cài plugin bên thứ ba
```

## Tiến độ

- **Phase 0** (nền tảng thị trường VN) ✅ — plugin `AniveleVietnam`
- **Phase 2** (biến thể Màu × Size) ✅ — plugin `AniveleCatalog`, 12 mẫu → 64 biến thể
- **Phase 1** (thanh toán & vận chuyển) ✅ giai đoạn 1 — plugin `AnivelePayment`: COD, chuyển khoản, VietQR + phí ship theo khu vực
- **Phase 3** (theme thương hiệu) ✅ giai đoạn 1 — plugin `AniveleTheme`: bảng màu nâu/be/kem, swatch tròn, mobile-first

> ⚠️ **VietQR chưa có số tài khoản thật.** Nhập tại Administration → Extensions → Anivele Payment trước khi mở bán. Đối soát vẫn thủ công — đơn không tự chuyển "đã thanh toán".
>
> ⚠️ **Chưa có file logo thật.** Đang dùng chữ ANIVELE; tải logo tại Administration → Themes → Giao diện Anivele → Media.

**Sửa giao diện thì phải chụp ảnh storefront kiểm chứng**, không tin `curl`/`grep` — xem [docs/02-theme.md](docs/02-theme.md).

Tiếp theo: **Phase 4 — marketing & vận hành** (SEO, Zalo OA, email, analytics). Xem [docs/01-roadmap.md](docs/01-roadmap.md).
