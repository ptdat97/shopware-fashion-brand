# Tài liệu kỹ thuật — Fashion Brand trên Shopware 6.7

Shop thời trang cho thị trường Việt Nam. Toàn bộ số liệu trong bộ tài liệu này lấy trực tiếp từ hệ thống đang chạy tại `/Users/dat/Ecommerce/shopware`.

## Đọc trước khi code

| | Tài liệu | Nội dung |
|---|---|---|
| ⚠️ | **[08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md)** | **Được sửa ở đâu** — không can thiệp core, mở rộng qua Plugin/App/Theme |
| ⚠️ | **[09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md)** | **Sửa như thế nào** — phạm vi thay đổi, kiến trúc PHP, migration, test |

## Tra cứu nhanh

| Cần gì | Tài liệu |
|---|---|
| Hệ thống đang ở trạng thái nào | [00-hien-trang.md](00-hien-trang.md) |
| Lệnh Symfony/Shopware | [07-lenh-thuong-dung.md](07-lenh-thuong-dung.md) |
| Còn bao nhiêu việc tới go-live | [01-roadmap.md](01-roadmap.md) |

## Theo chủ đề

**Cấu hình & vận hành**

| Chủ đề | Tài liệu |
|---|---|
| Thị trường VN — VND, thuế, địa chỉ | [04-thi-truong-vn.md](04-thi-truong-vn.md) |
| Plugin / app cần cài | [03-plugin-app.md](03-plugin-app.md) |
| Chuẩn bị go-live | [06-golive.md](06-golive.md) |

**Xây dựng shop**

| Chủ đề | Tài liệu |
|---|---|
| Theme riêng cho brand | [02-theme.md](02-theme.md) |
| Catalog thời trang — size, màu, variant | [05-catalog-fashion.md](05-catalog-fashion.md) |
| **Phase 2 — đánh giá catalog & kế hoạch variant** | **[10-phase2-catalog.md](10-phase2-catalog.md)** |

**Quy trình thao tác** — [runbook/](runbook/)

| Quy trình | Tài liệu |
|---|---|
| Backup & khôi phục | [runbook/00-backup.md](runbook/00-backup.md) |
| Cấu hình VND | [runbook/01-cau-hinh-vnd.md](runbook/01-cau-hinh-vnd.md) |
| Tạo theme | [runbook/02-tao-theme.md](runbook/02-tao-theme.md) |
| Xoá demo data | [runbook/03-xoa-demo-data.md](runbook/03-xoa-demo-data.md) |
| Tái lập Phase 0 trên môi trường khác | [runbook/04-phase0-da-thuc-hien.md](runbook/04-phase0-da-thuc-hien.md) |
| Dữ liệu demo thời trang | [runbook/05-demo-data.md](runbook/05-demo-data.md) |
| Sinh variant Màu × Size | [runbook/06-sinh-variant.md](runbook/06-sinh-variant.md) |
| Theme thương hiệu AniveleTheme | [runbook/07-theme-anivele.md](runbook/07-theme-anivele.md) |

**Quyết định kỹ thuật** — [decisions/](decisions/)

| Quyết định | Tài liệu |
|---|---|
| Chọn MySQL 8.0.33 | [decisions/ADR-001-mysql.md](decisions/ADR-001-mysql.md) |
| Hướng đi cho thanh toán | [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md) |
| Cấu trúc variant catalog | [decisions/ADR-003-cau-truc-variant.md](decisions/ADR-003-cau-truc-variant.md) |

## Quy tắc quan trọng nhất

> **Không can thiệp core Shopware.** Mọi thay đổi phải viết qua Plugin, App hoặc Theme — không sửa `vendor/`.
>
> Lý do: `composer update` ghi đè toàn bộ `vendor/`, git không track thư mục này, và sửa core khoá chặt khả năng nâng cấp Shopware về sau.
>
> Chi tiết và cách làm đúng cho từng nhu cầu: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md)

## Quy ước của bộ tài liệu

1. **Mô tả trạng thái hiện tại, không kể lịch sử thay đổi.** Ngoại lệ duy nhất là tiến trình dự án ([01-roadmap.md](01-roadmap.md)) và các ADR — nơi bối cảnh dẫn tới quyết định là phần nội dung chính.
2. **Mọi con số đều kiểm chứng trên hệ thống thật**, không copy từ tutorial. Phần chưa kiểm chứng được ghi rõ `[CHƯA KIỂM CHỨNG]`.
3. **Nói rõ cái gì KHÔNG có sẵn.** Thị trường VN thiếu nhiều plugin mà tài liệu Shopware quốc tế mặc định là có — xem [03-plugin-app.md](03-plugin-app.md).
4. **Ưu tiên thứ tự phụ thuộc.** Không làm theme trước khi chốt currency, vì giá hiển thị sai sẽ phải sửa lại toàn bộ template.
