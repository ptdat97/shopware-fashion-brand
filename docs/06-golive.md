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

- [ ] 🔴 **Xóa sạch demo data** — 12 sản phẩm `ANV-*`, xem [runbook/03-xoa-demo-data.md](runbook/03-xoa-demo-data.md)
- [ ] 🔴 Gỡ plugin `AcmeDemoData` (xoá được trực tiếp từ Administration)
- [ ] Xóa khách hàng demo
- [ ] Catalog thật đã nhập đầy đủ, có ảnh
- [x] ✅ **Mọi sản phẩm đã có variant** màu × size — xem [10-phase2-catalog.md](10-phase2-catalog.md)
- [x] ✅ Bật `is_closeout` để chặn bán quá tồn kho
- [ ] Tồn kho khớp thực tế **theo từng variant** (hiện là số chia đều của dữ liệu demo)
- [ ] Dựng size chart bằng CMS block — khách VN rất hay cần

> `SwagPlatformDemoData` đã gỡ từ Phase 0, không còn trong hệ thống.

## Cấu hình thị trường VN

Phần lớn đã xong ở Phase 0 qua plugin `AcmeVietnam` — chạy `plugin:install --activate AcmeVietnam` là tái lập được trên production.

- [x] ✅ **VND là tiền tệ mặc định**, `decimals = 0`
- [x] ✅ Giá hiển thị đã bao gồm thuế (`display_gross = 1`)
- [x] ✅ Chỉ Việt Nam active
- [x] ✅ Số điện thoại là trường bắt buộc
- [x] ✅ 34 tỉnh/thành đã nạp
- [ ] 🔴 **Xác nhận mức VAT với kế toán** (10% hay 8% cho hàng may mặc tại thời điểm go-live)
- [ ] 🔴 **Đối chiếu danh mục tỉnh/thành với nguồn chính thức** — cơ cấu hành chính VN có thay đổi theo các đợt sáp nhập
- [ ] Timezone `Asia/Ho_Chi_Minh`, ngày `dd/mm/yyyy`

## Thanh toán & Vận chuyển

- [x] ✅ Phương thức thanh toán hoạt động: COD, chuyển khoản trước, VietQR
- [x] ✅ Phí vận chuyển theo khu vực (25k nội thành / 35k tỉnh / miễn phí từ 500k)
- [x] ✅ Đã test đặt hàng thật từ đầu đến cuối qua Store API
- [ ] 🔴 **Nhập số tài khoản ngân hàng thật cho VietQR** — Administration → Extensions → Acme Payment. Chưa nhập thì khách không chuyển khoản được
- [ ] 🔴 **Quét thử mã QR bằng app ngân hàng thật** trên đơn hàng thật, xác nhận đúng số tiền và nội dung
- [ ] 🔴 Chốt quy trình đối soát: ai kiểm tra sao kê, bao lâu một lần, đổi trạng thái đơn thế nào
- [ ] Cân nhắc ngưỡng miễn phí ship 500.000 ₫ có phù hợp biên lợi nhuận không
- [ ] PayPal đã gỡ hẳn — xem [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md)

## Theme & Nội dung

- [x] ✅ Theme `AcmeTheme` đã compile và gán cho sales channel
- [x] ✅ Việt hoá nhãn còn sót ("Home" → "Trang chủ", "Pattern" → "Hoạ tiết")
- [ ] 🔴 **Tải logo thật của brand** — Administration → Themes → Giao diện Acme → Media. Hiện đang dùng chữ ANIVELE tạm
- [ ] 🔴 **Thay số đo bảng size bằng số đo THẬT** — Administration → Danh mục → tab Tuỳ chỉnh → Bảng size. Số hiện tại chỉ là tham khảo theo thang thị trường VN; số đo sai là nguyên nhân đổi trả hàng đầu
- [ ] 🔴 **Favicon** — vẫn là favicon mặc định của Shopware
- [ ] Kiểm tra trên thiết bị thật: điện thoại, tablet, máy tính
- [ ] Kiểm tra trên nhiều trình duyệt (Safari iOS rất phổ biến ở VN)
- [ ] Rà lại toàn bộ giao diện bằng **ảnh chụp màn hình**, không chỉ `curl` — xem [02-theme.md](02-theme.md)
- [ ] Ảnh đã tối ưu dung lượng (chưa có WebP/AVIF)
- [ ] Trang 404 và trang lỗi hiển thị đúng
- [ ] Thông tin liên hệ đúng (hiện footer còn số điện thoại mẫu `0180 - 000000`)

## Pháp lý

- [ ] 🔴 Chính sách đổi trả / hoàn tiền
- [ ] 🔴 Chính sách bảo mật thông tin cá nhân
- [ ] 🔴 Điều khoản sử dụng
- [ ] Chính sách vận chuyển
- [ ] Thông tin doanh nghiệp (tên, mã số thuế, địa chỉ, điện thoại)
- [ ] Tham vấn tư vấn pháp lý về nghĩa vụ đăng ký website TMĐT và bảo vệ dữ liệu cá nhân

## Toàn vẹn core

- [ ] 🔴 **Xác nhận không có thay đổi nào trong `vendor/`** — xem [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md)

```bash
# Cách kiểm tra: cài lại sạch vendor rồi chạy thử toàn bộ luồng
composer install
php bin/console cache:clear && php bin/console theme:compile
```

Nếu sau lệnh trên có tính năng nào hỏng → nghĩa là tính năng đó phụ thuộc vào code đã sửa trong `vendor/` và **sẽ hỏng ngay lần nâng cấp Shopware đầu tiên**. Phải viết lại qua plugin/theme trước khi go-live.

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
