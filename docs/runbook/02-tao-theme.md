# Runbook: Tạo theme thương hiệu

> Chỉ bắt đầu sau khi đã chốt xong VND — xem [01-cau-hinh-vnd.md](01-cau-hinh-vnd.md) để hiểu lý do.

## Lệnh đã kiểm chứng trên hệ thống

```
theme:create [--static] [<theme-name>]
theme:change [-s SALES-CHANNEL | --all] [--no-compile] [--sync] [<theme-name>]
theme:compile
theme:dump
```

---

## Bước 1 — Sinh khung theme

```bash
cd /Users/dat/Ecommerce/shopware
php bin/console theme:create AniveleTheme
```

Lệnh này tạo plugin theme trong `custom/plugins/AniveleTheme/`.

> Dùng `--static` nếu muốn đặt trong `custom/static-plugins/` (phù hợp khi theme được quản lý cùng repo dự án thay vì cài qua composer).

## Bước 2 — Cài và kích hoạt

```bash
php bin/console plugin:refresh
php bin/console plugin:install --activate AniveleTheme
php bin/console cache:clear
```

## Bước 3 — Gán theme cho sales channel

```bash
# Sales channel anivele
php bin/console theme:change AniveleTheme -s 019f9d8157c5724999d4b32d220a5b41
```

Hoặc chạy tương tác để chọn từ danh sách:

```bash
php bin/console theme:change
```

## Bước 4 — Biên dịch

```bash
php bin/console theme:compile
```

---

## Cấu trúc file quan trọng

### `src/Resources/theme.json`

Nơi khai báo kế thừa và các biến cấu hình (màu, font) hiện ra trong Administration.

Điểm cần lưu ý: khai báo `"views"` để theme kế thừa template từ Storefront thay vì phải copy toàn bộ.

### Template Twig

Đặt tại `src/Resources/views/storefront/`, giữ **đúng cấu trúc thư mục** như Storefront gốc thì Shopware mới nhận diện được file cần override.

Ví dụ để sửa trang chi tiết sản phẩm:
```
src/Resources/views/storefront/page/product-detail/index.html.twig
```

### Nguyên tắc override

Dùng `sw_extends` và **chỉ ghi đè block cần đổi**:

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_name %}
    {# nội dung tùy biến #}
{% endblock %}
```

**Không copy nguyên file rồi sửa.** Khi Shopware cập nhật template gốc, bản copy sẽ bị kẹt ở phiên bản cũ và sinh lỗi rất khó truy vết.

---

## Quy trình phát triển

Sau mỗi lần sửa SCSS hoặc Twig:

```bash
php bin/console theme:compile
php bin/console cache:clear
```

Nếu sửa nhiều lần liên tục, cân nhắc bật chế độ dev để không phải compile thủ công (cần đổi `APP_ENV=dev` trong `.env.local` — **nhớ đổi lại `prod` trước khi go-live**).

---

## Kiểm chứng

```bash
mysql -h shopware.test -u root shopware -e \
  "SELECT technical_name, name FROM theme;"
```

Kỳ vọng: xuất hiện `AniveleTheme` bên cạnh `Storefront`.

Kiểm tra theme đang áp cho sales channel:

```bash
mysql -h shopware.test -u root shopware -e \
  "SELECT t.technical_name, sct.name
   FROM theme_sales_channel tsc
   JOIN theme t ON t.id = tsc.theme_id
   JOIN sales_channel_translation sct ON sct.sales_channel_id = tsc.sales_channel_id
   WHERE sct.language_id = UNHEX('019f9d8e87d379bfaf35fa4338dcd841');"
```

Cuối cùng mở storefront kiểm tra bằng mắt.

---

## Sự cố thường gặp

| Triệu chứng | Nguyên nhân | Cách xử lý |
|---|---|---|
| Sửa SCSS không thấy đổi | Chưa compile | `theme:compile` rồi `cache:clear` |
| Trang trắng sau khi sửa Twig | Lỗi cú pháp | Xem `var/log/`, bật `APP_ENV=dev` để thấy chi tiết |
| Theme không hiện trong Admin | Plugin chưa active | `plugin:refresh` rồi `plugin:install --activate` |
| Mất style sau `composer update` | Cần compile lại | `theme:compile` |

---

## Cảnh báo

**Không sửa file trong `vendor/shopware/storefront/`.** Mọi thay đổi ở đó sẽ mất khi chạy `composer update` và không thể theo dõi bằng git.
