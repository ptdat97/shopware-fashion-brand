# Checklist Go-live

Các mục đánh dấu 🔴 là **chặn go-live** — không được bỏ qua.

## Hạ tầng

- [ ] 🔴 **HTTPS thật.** Hiện `APP_URL=http://shopware.test` là môi trường local. Cần domain thật + chứng chỉ SSL. Không có HTTPS thì không được nhận thanh toán.
- [x] ✅ **MySQL** — đã migrate 9.7.1 → 8.0.33, nằm trong ma trận hỗ trợ. Xem [decisions/ADR-001-mysql.md](decisions/ADR-001-mysql.md)
- [ ] 🔴 **MySQL local đang là 9.7.2** (phát hiện 26/10/2026 — DB `shopware` nằm trên server 9.7.2 của DBngin, trái ADR-001). Production phải dùng 8.0 / 8.4; `mysqldump` phải cùng major với server (backup bằng client 8.0.33 trên server 9.7.2 là đúng rủi ro ADR-001 nêu — 26/10 đã tạo thêm bản bằng client 9.7.2, 258/258 bảng). Quyết định: đưa local về 8.0.33 hay giữ 9.7.2 cho dev
- [ ] 🔴 **Backup tự động, lưu khác máy chủ, đã test khôi phục thật** — xem [runbook/00-backup.md](runbook/00-backup.md)
- [ ] 🔴 **Mail server thật.** Chưa cấu hình (`MAILER_DSN`). Không có mail thì khách không nhận được xác nhận đơn hàng. Local dùng Mailpit; mẫu email đã có tiếng Việt ([04](04-thi-truong-vn.md) mục 8)
- [x] ✅ **Quy trình build + deploy** — [runbook/07-deploy.md](runbook/07-deploy.md): `shopware/deployment-helper` (cài/cập nhật plugin, chỉ mục, xoá cache), cấu hình `backend/.config/shopware-project.yml`
- [ ] `APP_ENV=prod` và `APP_DEBUG=0`
- [ ] 🔴 **Đổi `APP_SECRET`** — giá trị hiện tại đã nằm trong file làm việc, coi như đã lộ
- [ ] Đặt mật khẩu cho user database (hiện `root` không mật khẩu)
- [ ] Cấu hình cron/message queue chạy nền (`messenger:consume`, `scheduled-task:run` — [runbook/07-deploy.md](runbook/07-deploy.md) mục 2)
- [ ] Giám sát uptime và cảnh báo lỗi
- [x] ✅ **FroshTools** (sức khoẻ hệ thống, hàng đợi, log, scheduled task trong Admin) và **Mail Archive** (lưu email đã gửi — CSKH tra cứu / gửi lại) — cài 26/10/2026 bằng composer

## Bảo mật

- [ ] 🔴 Đổi mật khẩu tài khoản admin mặc định
- [ ] Rà soát `.env.local` không bị commit lên git
- [ ] Giới hạn truy cập `/admin` nếu có thể
- [ ] Kiểm tra không còn thông tin nhạy cảm trong log

## Dữ liệu

- [ ] 🔴 **Xóa sạch demo data** — 12 sản phẩm `ANV-*`/`MAY-*`/`LLN-*` và 2 brand giả định Mây Atelier, Linh Lan — xem [runbook/05-demo-data.md](runbook/05-demo-data.md), [runbook/03-xoa-demo-data.md](runbook/03-xoa-demo-data.md)
- [ ] 🔴 Gỡ plugin `AcmeDemoData` (xoá được trực tiếp từ Administration)
- [ ] Xóa khách hàng demo
- [ ] Catalog thật đã nhập đầy đủ, có ảnh
- [x] ✅ **Mọi sản phẩm đã có variant** màu × size — xem [10-phase2-catalog.md](10-phase2-catalog.md)
- [x] ✅ Bật `is_closeout` để chặn bán quá tồn kho
- [ ] Tồn kho khớp thực tế **theo từng variant** (hiện là số chia đều của dữ liệu demo)
- [ ] Dựng size chart bằng CMS block — khách VN rất hay cần
- [ ] 🔴 **Mọi sản phẩm đã gán thương hiệu** (trường Nhà sản xuất) — thiếu brand thì sản phẩm không xuất hiện ở trang brand và bộ lọc brand
- [ ] Xoá nhà sản xuất mặc định `shopware AG`

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
- [ ] **COD là phương thức ưu tiên** (14/10/2026) — kiểm thử đặt đơn COD từ đầu đến cuối
- [ ] Nhập số tài khoản ngân hàng thật cho VietQR — *chỉ khi bật lại VietQR*, hiện tạm gác. Administration → Extensions → Acme Payment
- [ ] 🔴 **Quét thử mã QR bằng app ngân hàng thật** trên đơn hàng thật, xác nhận đúng số tiền và nội dung
- [ ] 🔴 Chốt quy trình đối soát: ai kiểm tra sao kê, bao lâu một lần, đổi trạng thái đơn thế nào
- [ ] Cân nhắc ngưỡng miễn phí ship 500.000 ₫ có phù hợp biên lợi nhuận không
- [ ] PayPal đã gỡ hẳn — xem [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md)

## Theme & Nội dung

- [x] ✅ Theme `AcmeTheme` đã compile và gán cho sales channel
- [x] ✅ Việt hoá nhãn còn sót ("Home" → "Trang chủ", "Pattern" → "Hoạ tiết")
- [ ] 🔴 **Tải logo thật của nhà bán lẻ** (logo header, khác logo từng brand) — Administration → Themes → Giao diện Acme → Media. Hiện đang dùng chữ ANIVELE tạm
- [ ] 🔴 **Thay số đo bảng size bằng số đo THẬT** — Administration → Danh mục → tab Tuỳ chỉnh → Bảng size. Số hiện tại chỉ là tham khảo theo thang thị trường VN; số đo sai là nguyên nhân đổi trả hàng đầu
- [ ] 🔴 **Favicon** — vẫn là favicon mặc định của Shopware
- [ ] Kiểm tra trên thiết bị thật: điện thoại, tablet, máy tính
- [ ] Kiểm tra trên nhiều trình duyệt (Safari iOS rất phổ biến ở VN)
- [ ] Rà lại toàn bộ giao diện bằng **ảnh chụp màn hình**, không chỉ `curl` — xem [02-theme.md](02-theme.md)
- [ ] Ảnh đã tối ưu dung lượng (chưa có WebP/AVIF)
- [ ] Trang 404 và trang lỗi hiển thị đúng
- [ ] Thông tin liên hệ đúng (hiện footer còn số điện thoại mẫu `0180 - 000000`)

## Đa thương hiệu

Xem [12-da-thuong-hieu.md](12-da-thuong-hieu.md) — chỉ áp dụng khi Phase MB đã xong.

- [ ] 🔴 Mỗi brand đang hoạt động có đủ: logo, banner, mô tả, layout landing, meta title/description
- [ ] 🔴 `/brands` và `/brands/{slug}` hiển thị đúng trên mobile — **có ảnh chụp kiểm chứng**
- [ ] Brand ngừng hoạt động trả 404, không còn trong `/brands` và sitemap
- [ ] Đặt thử một đơn chứa sản phẩm của **≥2 brand**, thanh toán và trừ tồn kho đúng
- [ ] Bảng size đúng theo **từng brand**, không dùng chung số đo giữa các brand

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

- [x] ✅ URL thân thiện, tiếng Việt không dấu, **chữ thường** — sản phẩm, danh mục, `/brands/…`, `/collections/…`
- [x] ✅ Mỗi mẫu một URL canonical: biến thể trỏ về biến thể đầu (`canonicalProductId`), sitemap chỉ có URL canonical
- [ ] Sitemap sinh tự động trên production — scheduled task `sitemap_generate` chạy được (cần worker/cron)
- [ ] 🔴 **`robots.txt` trả HTTP 200** trên server thật — máy dev (Herd) trả **404** dù nội dung đúng, do nginx coi `.txt` là file tĩnh. Cấu hình nginx phải chuyển `/robots.txt` vào `index.php`. Kiểm: `curl -I https://<domain>/robots.txt`
- [ ] `robots.txt` không chặn nhầm toàn site và có dòng `Sitemap:`
- [ ] 🔴 **Meta trang chủ** — Sales Channel → tab SEO → *Meta title / description trang chủ*. Chờ chốt tên nền tảng; hiện tiêu đề trang chủ chỉ là "Trang chủ", mô tả trống
- [ ] Meta description cho danh mục, bộ sưu tập, sản phẩm **thật** (Administration → tab SEO). Dữ liệu demo đã có mẫu
- [ ] Google Search Console: xác minh domain, gửi sitemap
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
