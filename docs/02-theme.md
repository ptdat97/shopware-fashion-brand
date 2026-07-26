# Theme thương hiệu

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
