# Anivele — Shop thời trang trên Shopware 6.7

Shop bán lẻ thời trang cho thị trường Việt Nam. Toàn bộ tài liệu kỹ thuật nằm ở [docs/](docs/).

## Ba quy tắc cứng

**1. Không sửa `vendor/`.** Mọi tuỳ biến viết qua Plugin, App hoặc Theme trong `custom/`. `composer update` ghi đè sạch `vendor/`, git không track thư mục này.

**2. Không `composer require` plugin tự viết.** Shopware tự quét `custom/plugins/`. Thêm vào `composer.json` sẽ đặt `managed_by_composer = 1` và **khoá nút Xoá plugin trong Administration**. Chỉ dùng `composer require` cho plugin bên thứ ba hoặc nâng version core.

**3. Backup trước mọi thay đổi dữ liệu.** Xem [docs/runbook/00-backup.md](docs/runbook/00-backup.md).

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

Phase 0 (nền tảng thị trường VN) ✅ xong — plugin `AniveleVietnam`.
Tiếp theo: xem [docs/01-roadmap.md](docs/01-roadmap.md).

> ⚠️ **Chưa có variant** — 12 sản phẩm đều là sản phẩm đơn lẻ, khách không chọn được size. Đây là hạng mục chặn go-live: [docs/10-phase2-catalog.md](docs/10-phase2-catalog.md).
