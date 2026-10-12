# Cấu hình cho thị trường Việt Nam

> ✅ **Phase 0 đã hoàn tất.** Tài liệu này giải thích **vì sao** từng cấu hình phải như vậy — phần "Vấn đề" mô tả trạng thái *trước khi sửa*, giữ lại vì đó là bối cảnh dẫn tới quyết định.
>
> - Đã làm những gì, bằng cách nào: [runbook/04-phase0-da-thuc-hien.md](runbook/04-phase0-da-thuc-hien.md)
> - Hiện trạng đã kiểm chứng: [00-hien-trang.md](00-hien-trang.md)
> - Toàn bộ được tái lập bằng migration trong plugin `AcmeVietnam`, không phải SQL thủ công.

## 1. Tiền tệ VND

### Vấn đề ban đầu

Hệ thống chỉ có USD. Sales channel `anivele` đặt country là Viet Nam nhưng bán bằng USD — sai hoàn toàn với thực tế kinh doanh.

### Đặc thù của VND cần lưu ý

VND **không có đơn vị nhỏ hơn** đang lưu hành. Không có "xu". Đây là điểm khác biệt cơ bản so với USD/EUR và là nguồn lỗi phổ biến nhất khi cấu hình.

| Cấu hình | Giá trị đúng | Nếu để mặc định (sai) |
|---|---|---|
| `decimal_precision` | `0` | `2` → hiển thị `250.000,00 ₫` |
| Ký hiệu | `₫` | |
| Vị trí ký hiệu | Sau số (`250.000 ₫`) | Trước số (`₫250.000`) — không đúng thói quen VN |
| Dấu phân cách nghìn | `.` (dấu chấm) | `,` (dấu phẩy) — kiểu Anh/Mỹ |

Chuẩn VN: `1.250.000 ₫` — chấm phân cách nghìn, ký hiệu đứng sau.

### Ảnh hưởng dây chuyền

Đổi currency ảnh hưởng tới:
- **Độ dài chuỗi giá** — `1.250.000 ₫` dài hơn `$52.00` rất nhiều, dễ vỡ layout trên mobile
- **Cách làm tròn** — mọi phép tính giảm giá, thuế, phí ship phải làm tròn về đơn vị đồng
- **Giá sản phẩm demo cũ** — trở thành vô nghĩa, nhưng không sao vì đã xoá hết cùng `SwagPlatformDemoData`

Đây chính là lý do currency phải chốt **trước** theme.

Thao tác: [runbook/01-cau-hinh-vnd.md](runbook/01-cau-hinh-vnd.md)

---

## 2. Thuế VAT

### Hiện tại: biểu thuế Đức

```
Standard rate    19.000%
Reduced rate      7.000%
Reduced rate 2    0.000%
```

### Cần đổi sang VAT Việt Nam

Hàng may mặc thuộc nhóm chịu VAT **10%**.

> ⚠️ **Cần xác nhận với kế toán:** Việt Nam đã có những giai đoạn áp dụng chính sách giảm VAT từ 10% xuống 8% theo nghị quyết của Quốc hội, với thời hạn hiệu lực nhất định và danh mục hàng hóa áp dụng cụ thể. **Phải kiểm tra hiệu lực tại đúng thời điểm go-live** — tài liệu này không khẳng định mức thuế đang áp dụng.

### Giá đã bao gồm thuế

Ở Việt Nam, giá niêm yết cho người tiêu dùng **thường đã bao gồm VAT**. Cần cấu hình Shopware hiển thị giá gross (đã gồm thuế) thay vì net — khác với thói quen B2B châu Âu.

Nếu để hiển thị giá net, khách sẽ thấy giá tăng lên ở bước thanh toán và tỷ lệ bỏ giỏ hàng sẽ cao.

---

## 3. Quốc gia

### Vấn đề ban đầu

Gần như **toàn bộ ~250 quốc gia đang active**, kể cả Antarctica, Pitcairn, Western Sahara. Khách hoàn toàn có thể chọn giao hàng tới đó.

### Đã làm

- Tắt toàn bộ, chỉ để **Việt Nam** active ✅
- Nếu sau này bán quốc tế, chỉ bật thêm đúng những nước thực sự giao được

> ⚠️ **Bẫy:** tắt quốc gia phải kiểm tra lại `sales_channel.country_id` của **mọi** channel — channel trỏ tới quốc gia vừa tắt sẽ lỗi khi gọi API. Migration đã xử lý sẵn, nhưng cần nhớ nếu tắt thêm quốc gia bằng tay.

Việc này vừa tránh đơn hàng không giao được, vừa làm gọn dropdown chọn quốc gia ở trang thanh toán.

---

## 4. Địa chỉ Việt Nam

### Khác biệt cơ bản

Shopware thiết kế theo chuẩn địa chỉ châu Âu: `Đường + Số nhà → Mã bưu chính → Thành phố → Quốc gia`.

Địa chỉ Việt Nam có cấu trúc phân cấp: `Số nhà, Đường → Phường/Xã → Quận/Huyện → Tỉnh/Thành phố`.

### Điểm cần xử lý

| Vấn đề | Ghi chú |
|---|---|
| Mã bưu chính | Người Việt hầu như không dùng. Nên để không bắt buộc, tránh cản trở thanh toán |
| Ba cấp hành chính | Cần dropdown phụ thuộc: chọn Tỉnh → lọc Huyện → lọc Xã |
| Đơn vị hành chính thay đổi | Việt Nam có các đợt sáp nhập tỉnh/huyện/xã. Dữ liệu địa giới cần cập nhật theo danh mục mới nhất của Tổng cục Thống kê |
| Số điện thoại | **Bắt buộc.** Đơn vị giao hàng VN gọi điện trước khi giao — quan trọng hơn cả email |

> ⚠️ Danh mục đơn vị hành chính Việt Nam đã có thay đổi lớn trong thời gian gần đây. **Phải lấy dữ liệu từ nguồn chính thức tại thời điểm triển khai**, không dùng lại danh sách cũ.

### Cách triển khai

Shopware có sẵn khái niệm `country_state` (tỉnh/bang) — dùng được cho cấp Tỉnh/Thành phố. Hai cấp còn lại (Quận/Huyện, Phường/Xã) cần custom field hoặc plugin riêng.

---

## 5. Định dạng & Múi giờ

| Cấu hình | Giá trị |
|---|---|
| Múi giờ | `Asia/Ho_Chi_Minh` (UTC+7) |
| Định dạng ngày | `dd/mm/yyyy` |
| Định dạng giờ | 24 giờ |
| Ngày đầu tuần | Thứ Hai |

Định dạng ngày `mm/dd/yyyy` kiểu Mỹ sẽ gây nhầm lẫn nghiêm trọng — `03/07` là 3 tháng 7 hay 7 tháng 3?

---

## 6. Nội dung pháp lý bắt buộc

Shop TMĐT tại Việt Nam cần có các trang:

- Chính sách đổi trả / hoàn tiền
- Chính sách bảo mật thông tin cá nhân
- Điều khoản sử dụng
- Chính sách vận chuyển
- Thông tin doanh nghiệp (tên, mã số thuế, địa chỉ, điện thoại)

> Quy định về thông báo/đăng ký website TMĐT với Bộ Công Thương và về bảo vệ dữ liệu cá nhân **cần tham vấn tư vấn pháp lý** — nằm ngoài phạm vi tài liệu kỹ thuật này.

---

## Thứ tự thực hiện

1. Tạo VND, đặt mặc định, sửa `decimal_precision = 0`
2. Sửa thuế VAT (sau khi xác nhận mức với kế toán)
3. Bật giá gross
4. Tắt quốc gia thừa
5. Nạp dữ liệu tỉnh/huyện/xã
6. Đặt timezone và định dạng ngày
7. Soạn trang pháp lý

Kiểm chứng sau mỗi bước, đừng làm hết rồi mới kiểm tra.
