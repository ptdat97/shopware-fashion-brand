# Checklist Go-live

Các mục đánh dấu 🔴 là **chặn go-live** — không được bỏ qua.

## Hạ tầng

- [ ] 🔴 **HTTPS thật.** Hiện `APP_URL=http://shopware.test` là môi trường local. Cần domain thật + chứng chỉ SSL. Không có HTTPS thì không được nhận thanh toán.
- [x] ✅ **MySQL** — đã migrate 9.7.1 → 8.0.33, nằm trong ma trận hỗ trợ. Xem [decisions/ADR-001-mysql.md](decisions/ADR-001-mysql.md)
- [ ] 🔴 **Backup tự động, lưu khác máy chủ, đã test khôi phục thật** — xem [runbook/00-backup.md](runbook/00-backup.md)
- [ ] 🔴 **Mail server thật.** Chưa cấu hình. Không có mail thì khách không nhận được xác nhận đơn hàng.
- [ ] `APP_ENV=prod` và `APP_DEBUG=0`
- [ ] 🔴 **Đổi `APP_SECRET`** — giá trị hiện tại đã nằm trong file làm việc, coi như đã lộ
- [ ] Đặt mật khẩu cho user database (hiện `root` không mật khẩu)
- [ ] Cấu hình cron/message queue chạy nền
- [ ] Giám sát uptime và cảnh báo lỗi

## Bảo mật

- [ ] 🔴 Đổi mật khẩu tài khoản admin mặc định
- [ ] Rà soát `.env.local` không bị commit lên git
- [ ] Giới hạn truy cập `/admin` nếu có thể
- [ ] Kiểm tra không còn thông tin nhạy cảm trong log

## Dữ liệu

- [ ] 🔴 **Xóa sạch demo data** — xem [runbook/03-xoa-demo-data.md](runbook/03-xoa-demo-data.md)
- [ ] 🔴 Gỡ plugin `SwagPlatformDemoData`
- [ ] Xóa khách hàng demo
- [ ] Catalog thật đã nhập đầy đủ, có ảnh
- [ ] Tồn kho khớp thực tế

## Cấu hình thị trường VN

- [ ] 🔴 **VND là tiền tệ mặc định**, `decimals = 0` — xem [runbook/01-cau-hinh-vnd.md](runbook/01-cau-hinh-vnd.md)
- [ ] 🔴 **Thuế VAT đúng** (đã xác nhận mức với kế toán)
- [ ] Giá hiển thị đã bao gồm thuế
- [ ] Chỉ bật quốc gia thực sự giao hàng được
- [ ] Timezone `Asia/Ho_Chi_Minh`, ngày `dd/mm/yyyy`
- [ ] Dữ liệu tỉnh/huyện/xã theo danh mục mới nhất
- [ ] Số điện thoại là trường bắt buộc

## Thanh toán & Vận chuyển

- [ ] 🔴 Ít nhất một phương thức thanh toán hoạt động (COD là đủ để mở bán)
- [ ] 🔴 Phí vận chuyển cấu hình đúng theo khu vực
- [ ] Đã test đặt hàng thật từ đầu đến cuối
- [ ] Nếu có cổng online: đã test cả trường hợp **thanh toán thất bại** và **hủy giữa chừng**
- [ ] Quyết định giữ hay bật PayPal — xem [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md)

## Theme & Nội dung

- [ ] Theme đã compile bản production
- [ ] Kiểm tra trên nhiều thiết bị: điện thoại, tablet, máy tính
- [ ] Kiểm tra trên nhiều trình duyệt
- [ ] Không còn chữ tiếng Anh sót trên giao diện tiếng Việt
- [ ] Ảnh đã tối ưu dung lượng
- [ ] Trang 404 và trang lỗi hiển thị đúng
- [ ] Logo, favicon, thông tin liên hệ đúng

## Pháp lý

- [ ] 🔴 Chính sách đổi trả / hoàn tiền
- [ ] 🔴 Chính sách bảo mật thông tin cá nhân
- [ ] 🔴 Điều khoản sử dụng
- [ ] Chính sách vận chuyển
- [ ] Thông tin doanh nghiệp (tên, mã số thuế, địa chỉ, điện thoại)
- [ ] Tham vấn tư vấn pháp lý về nghĩa vụ đăng ký website TMĐT và bảo vệ dữ liệu cá nhân

## Plugin

- [ ] Rà soát `SwagLanguagePack` còn ở `dev-master` không — nếu đã có bản release ổn định hỗ trợ 6.7 thì chuyển sang
- [ ] Gỡ mọi plugin không dùng
- [ ] Ghi lại phiên bản tất cả plugin đang chạy

## SEO & Analytics

- [ ] URL thân thiện, tiếng Việt không dấu
- [ ] Sitemap sinh tự động
- [ ] `robots.txt` đúng — **kiểm tra kỹ không chặn nhầm toàn site**
- [ ] Meta title/description cho trang chính
- [ ] Analytics đã gắn và nhận được dữ liệu

## Hiệu năng

- [ ] Bật HTTP cache
- [ ] Đo tốc độ tải trên mạng di động — quan trọng vì đa số khách VN mua qua điện thoại
- [ ] Kiểm tra tốc độ trang danh mục nhiều sản phẩm

---

## Kiểm thử cuối trước khi mở bán

Chạy **đơn hàng thật** (không phải test mode) qua toàn bộ luồng:

1. Tìm sản phẩm → xem chi tiết → chọn màu/size
2. Thêm giỏ hàng → sửa số lượng
3. Đăng ký tài khoản mới
4. Nhập địa chỉ VN đầy đủ ba cấp
5. Chọn vận chuyển → kiểm tra phí đúng
6. Thanh toán
7. **Nhận được email xác nhận**
8. Kiểm tra đơn hiện đúng trong Admin
9. Đổi trạng thái đơn → khách nhận được thông báo
10. Thử hủy đơn / hoàn tiền

Chỉ khi cả 10 bước chạy đúng mới mở bán.

---

## Sau khi go-live

- Theo dõi log lỗi sát trong tuần đầu
- Theo dõi tỷ lệ bỏ giỏ hàng — cao bất thường thường là dấu hiệu lỗi ở bước thanh toán
- Kiểm tra backup có chạy đúng lịch không
- Chuẩn bị sẵn phương án rollback
