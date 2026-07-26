# Tài liệu dự án — Fashion Brand trên Shopware 6.7

Bộ tài liệu triển khai shop thời trang cho thị trường Việt Nam.

## Bắt đầu từ đâu

| Nếu bạn muốn... | Đọc file |
|---|---|
| ⚠️ **Quy tắc phát triển (BẮT BUỘC đọc trước khi code)** | **[08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md)** |
| Hiểu hiện trạng hệ thống đang ở đâu | [00-hien-trang.md](00-hien-trang.md) |
| Xem lộ trình tổng thể theo phase | [01-roadmap.md](01-roadmap.md) |
| Làm theme riêng cho brand | [02-theme.md](02-theme.md) |
| Chọn plugin / app cần cài | [03-plugin-app.md](03-plugin-app.md) |
| Cấu hình cho thị trường VN (VND, thuế, địa chỉ) | [04-thi-truong-vn.md](04-thi-truong-vn.md) |
| Thiết kế catalog thời trang (size, màu, variant) | [05-catalog-fashion.md](05-catalog-fashion.md) |
| Chuẩn bị go-live | [06-golive.md](06-golive.md) |
| **Tra cứu lệnh Symfony/Shopware** | **[07-lenh-thuong-dung.md](07-lenh-thuong-dung.md)** |
| Chạy lệnh cụ thể theo quy trình | [runbook/](runbook/) |
| **Phase 0 đã làm gì** | **[runbook/04-phase0-da-thuc-hien.md](runbook/04-phase0-da-thuc-hien.md)** |
| Dữ liệu demo thời trang | [runbook/05-demo-data.md](runbook/05-demo-data.md) |
| Hiểu vì sao chọn giải pháp X | [decisions/](decisions/) |

## Quy tắc quan trọng nhất

> **Không can thiệp core Shopware.** Mọi thay đổi phải viết qua Plugin, App hoặc Theme — không sửa `vendor/`.
>
> Lý do: `composer update` ghi đè toàn bộ `vendor/`, git không track thư mục này, và sửa core khoá chặt khả năng nâng cấp Shopware về sau.
>
> Chi tiết và cách làm đúng cho từng nhu cầu: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md)

## Nguyên tắc của bộ tài liệu này

1. **Mọi con số đều được kiểm chứng trên hệ thống thật**, không copy từ tutorial. Phần nào chưa kiểm chứng được sẽ ghi rõ `[CHƯA KIỂM CHỨNG]`.
2. **Nói rõ cái gì KHÔNG có sẵn.** Thị trường VN thiếu nhiều plugin mà tài liệu Shopware quốc tế mặc định là có — xem [03-plugin-app.md](03-plugin-app.md).
3. **Ưu tiên thứ tự phụ thuộc.** Không làm theme trước khi chốt currency, vì giá hiển thị sai sẽ phải sửa lại toàn bộ template.

## Ngày lập

26/07/2026 — dựa trên khảo sát hệ thống tại `/Users/dat/Ecommerce/shopware`.
