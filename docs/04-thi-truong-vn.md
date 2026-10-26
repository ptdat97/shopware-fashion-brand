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

### Đã kiểm qua đơn thật trên frontend headless (21/10/2026)

| Quy tắc | Cấu hình native | Ghi chú |
|---|---|---|
| SĐT bắt buộc ở địa chỉ | `core.loginRegistration.phoneNumberFieldRequired` (`AcmeVietnam` 1.4.0) | Phase 0 đặt nhầm khoá của form liên hệ — xem đính chính ở runbook/04 |
| Mã bưu chính không bắt buộc | `country.postal_code_required = 0` | Frontend đọc theo quốc gia, không gán cứng |
| Tỉnh/thành bắt buộc | `country.force_state_in_registration = 1` | 34 tỉnh/thành |
| Tên/họ ngắn hợp lệ | Core chỉ `NotBlank` | Template frontend từng bắt tối thiểu 3 ký tự → họ "Lê", "Hồ", "Võ" không đặt hàng được; đã bỏ |

Frontend đọc các quy tắc này từ Shopware (`/store-api/shop-settings`, `country.postalCodeRequired`) qua `useAddressFieldSettings` — đổi trong Administration là form theo, không sửa code.

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

## 7. Tìm kiếm tiếng Việt ✅ (21/10/2026)

Khách VN gõ không dấu rất nhiều. Hai vấn đề đã sửa, cả hai trong `AcmeVietnam` 1.3.0:

**Gõ không dấu với chữ "đ".** Tìm kiếm chạy trên MySQL (`SHOPWARE_ES_ENABLED=0`), bảng từ khoá dùng collation `utf8mb4_unicode_ci`. Collation này **đã** coi mọi nguyên âm có dấu bằng chữ không dấu (á = a, ặ = a, ơ = o, ữ = u — kiểm từng chữ cái bằng MySQL 8.0), nhưng **"đ" là một chữ cái riêng, không bằng "d"**. Hậu quả: "ao polo" vẫn ra "áo polo" nhưng "dam" trả 0 kết quả thay vì 7 chiếc đầm. Sửa bằng hai decorator chỉ áp cho tìm kiếm **sản phẩm**:

- `VietnameseSearchKeywordAnalyzer` (decorate `ProductSearchKeywordAnalyzer`) — lập chỉ mục thêm bản gấp "đầm → dầm" cùng ranking
- `VietnameseSearchTermInterpreter` (decorate `ProductSearchTermInterpreter`) — gấp từ khách gõ trước khi tra

Không decorate `Tokenizer` chung vì tìm kiếm trong Administration (khách hàng "Đức"…) cũng dùng nó. Core không có sự kiện/extension point nào ở hai bước này (EXT-01).

**Stopword tiếng Anh áp cho tiếng Việt.** vi-VN không có `product_search_config` riêng nên dùng cấu hình English với 181 stopword ("to", "do", "an", "no" bị bỏ khi lập chỉ mục tên sản phẩm). Migration tạo cấu hình vi-VN (chép trường + ranking) với 12 hư từ tiếng Việt — sửa tiếp ở Administration → Settings → Search.

Kết quả (Store API `search`): `dam` 0 → 7 · `dam den` 7, đầm đen xếp đầu · `den` 5 · `chan vay` 4. Frontend: trang tìm kiếm bỏ thứ tự gán cứng "name-asc" của template, dùng cấu hình "Sắp xếp kết quả tìm kiếm" của Admin (đang là độ liên quan).

> ⚠️ **Sau khi cài/cập nhật plugin phải lập chỉ mục lại sản phẩm** (`bin/console dal:refresh:index --only=product.indexer`) — trước đó từ có "đ" sẽ không khớp vì từ khách gõ đã gấp mà chỉ mục chưa có bản gấp.
>
> ⚠️ **Nếu bật OpenSearch** (`SHOPWARE_ES_ENABLED=1`): hai decorator không áp cho đường OpenSearch. Phải cấu hình analyzer có `asciifolding` (gấp cả "đ") cho vi-VN.

## 8. Email tiếng Việt ✅ (26/10/2026)

Trước đây cả 45 mẫu email của core chỉ có tiếng Anh — khách VN nhận "Order confirmation". `AcmeVietnam` 1.6.0 thêm **bản dịch vi-VN** (dữ liệu native `mail_template_translation`) cho 16 mẫu khách hay nhận: xác nhận đơn, nhắc thanh toán, đã nhận / huỷ thanh toán, đơn đang xử lý / hoàn tất / bị huỷ, đang giao / huỷ giao, đổi phương thức thanh toán, đăng ký tài khoản, quên / đã đổi mật khẩu, form liên hệ, đăng ký nhận tin (2 mẫu). 29 mẫu còn lại (B2B, sản phẩm số, đánh giá, đổi trả…) vẫn tiếng Anh.

- **Nguồn:** `AcmeVietnam/src/Resources/mail-templates/vi-VN/<technical name>/{html,plain}.twig` + `templates.json` (tiêu đề, mô tả). Sinh bằng `python3 tools/generate-mail-templates.py` — khối dùng chung (bảng đơn hàng, khối chuyển khoản) viết một lần trong script.
- **Nạp:** migration chỉ thêm khi mẫu chưa có bản vi-VN — sửa tiếp trong Administration → Settings → Email templates không bị ghi đè. Đổi file sau khi đã nạp → cần migration mới.
- **Nội dung theo VN:** chào "Họ Tên", giờ VN (`22:19, 26/10/2026`), địa chỉ có tỉnh/thành + SĐT, đơn COD ghi số tiền trả khi nhận, "Miễn phí" ship, bỏ đoạn mã số VAT châu Âu. Người gửi = tên shop (Settings → Basic information), không phải tên sales channel "Headless".
- **Chuyển khoản:** `AcmePayment` 2.1.0 gắn biến `acmeBankTransfer` vào email có đơn chuyển khoản → email xác nhận / nhắc thanh toán / đổi phương thức hiện tài khoản nhận, số tiền, nội dung CK (cùng nguồn với trang cảm ơn).
- **Kiểm:** Mailpit local (`brew services start mailpit`, `MAILER_DSN=smtp://127.0.0.1:1025` trong `backend/.env.local`, xem http://127.0.0.1:8025). Test e2e COD đọc email từ Mailpit (tiêu đề, lời chào, SĐT, link domain frontend).

## Thứ tự thực hiện

1. Tạo VND, đặt mặc định, sửa `decimal_precision = 0`
2. Sửa thuế VAT (sau khi xác nhận mức với kế toán)
3. Bật giá gross
4. Tắt quốc gia thừa
5. Nạp dữ liệu tỉnh/huyện/xã
6. Đặt timezone và định dạng ngày
7. Soạn trang pháp lý

Kiểm chứng sau mỗi bước, đừng làm hết rồi mới kiểm tra.
