# Plugin & App

> ⚠️ **Nhắc lại quy tắc bắt buộc:** mọi tuỳ biến phải viết qua Plugin, App hoặc Theme — **không sửa `vendor/`**. Xem [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md).

## Đọc phần này trước

Khảo sát thực tế trên Packagist ngày 26/07/2026 cho các từ khóa `vnpay`, `momo`, `zalopay`, `onepay`, `ghn`, `ghtk`, `viettelpost`:

> **Không tìm thấy plugin Shopware 6 nào cho các dịch vụ thanh toán và vận chuyển nội địa Việt Nam.**

Toàn bộ kết quả trả về là thư viện cho Laravel, Magento, hoặc Omnipay — **không dùng trực tiếp được cho Shopware**. Ví dụ `phpviet/omnipay-vnpay`, `jackiedo/omnipay-vnpay` là driver Omnipay; `boolfly/module-zalopay` là module Magento.

Điều này khác hẳn với hệ sinh thái Shopware ở châu Âu, nơi mọi cổng thanh toán lớn đều có plugin chính thức. **Đây là hạng mục tốn công và tốn tiền nhất của dự án.**

Shopware Store (api.shopware.com) đã được thử tra cứu nhưng API trả về kết quả không khớp từ khóa nên **chưa kiểm chứng được**. Cần tự tìm trực tiếp trên store.shopware.com trước khi quyết định tự viết.

---

## Nhóm 1 — Đã cài

| Plugin | Version | Trạng thái | `managed_by_composer` | Việc cần làm |
|---|---|---|---|---|
| AcmeVietnam | 1.1.0 | Active | 0 | Plugin tự viết — nền tảng thị trường VN (Phase 0) |
| AcmeCatalog | 1.4.0 | Active | 0 | Plugin tự viết — cấu trúc catalog: biến thể, bảng size, bộ sưu tập, SEO URL |
| AcmePayment | 1.1.0 | Active | 0 | Plugin tự viết — thanh toán: VietQR (Phase 1) |
| AcmeShipping | 1.0.0 | Active | 0 | Plugin tự viết — vận chuyển nội địa: phí theo khu vực; sau này GHN/GHTK |
| AcmeBrand | 1.2.0 | Active | 0 | Plugin tự viết — thương hiệu (Phase MB) |
| AcmeTheme | 1.0.0 | Active | 0 | Plugin tự viết — chỉ hiển thị: theme, template (Phase 3) |
| AcmeDemoData | 1.0.0 | Active | 0 | Plugin tự viết — **gỡ trước khi go-live**, thay bằng hàng thật |
| SwagLanguagePack | dev-master | Active | 1 | Theo dõi bản release ổn định hỗ trợ 6.7 để chuyển sang. Phụ thuộc `shopware/storefront` — chặn bước 5 ADR-006 |
| FroshTools | 3.16.0 | Active | 1 | Cài 26/10/2026 (composer). Sức khoẻ hệ thống, hàng đợi, log, scheduled task trong Admin. Không cần storefront |
| FroshPlatformMailArchive | 3.8.0 | Active | 1 | Cài 26/10/2026 (composer). Lưu email đã gửi để tra cứu / gửi lại; tự dọn theo scheduled task. Không cần storefront |

> Mỗi plugin một nghiệp vụ và độc lập nhau — bản đồ trách nhiệm: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 5.
>
> `SwagPlatformDemoData` **không còn tồn tại** — đã gỡ ở Phase 0 và thay bằng `AcmeDemoData`. Xem [runbook/05-demo-data.md](runbook/05-demo-data.md).
>
> `SwagPayPal` **đã gỡ hẳn** (08/08/2026). Thư mục plugin bị xoá khỏi git nhưng `composer.json` vẫn require → autoloader trỏ vào file không tồn tại và **làm hỏng toàn bộ `bin/console`**. Đã `composer remove swag/paypal` và dọn thư mục thừa. Nếu sau này cần bán quốc tế thì cài lại từ đầu.
>
> **14/10/2026:** sau đó plugin bị cài lại (vào `custom/plugins/SwagPayPal`, *Installed* nhưng không bật). Gỡ lần hai: `plugin:uninstall` → `composer remove swag/paypal` → **tự xoá thư mục `custom/plugins/SwagPayPal`** (composer không xoá thư mục plugin đã cài vào `custom/plugins/`) → `plugin:refresh`. Uninstall của PayPal để sót 4 bảng `swag_paypal_*` rỗng — đã xoá.
>
> `SwagExtensionStore` cũng không còn — gỡ cùng đợt dọn dẹp.
>
> Cột `managed_by_composer` phải là **0** với plugin tự viết, nếu không Administration sẽ khoá nút Xoá — xem [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 2.

---

### Phase MB (đa thương hiệu)

| Plugin | Mục đích | Trạng thái |
|---|---|---|
| `AcmeBrand` | Mở rộng `product_manufacturer` (mã brand, active, banner, layout, SEO meta) + trang `/brands`, `/brands/{slug}`, SEO URL, sitemap | ✅ Dữ liệu + Administration (MB1) · ⏳ Storefront (MB3) — [ADR-003](decisions/ADR-003-mo-hinh-thuong-hieu.md) |

Không cần plugin bên thứ ba cho đa thương hiệu — brand, bộ lọc brand, rule khuyến mãi theo brand đều có sẵn trong core. Xem [12-da-thuong-hieu.md](12-da-thuong-hieu.md).

---

## Nhóm 2 — Thanh toán (hạng mục then chốt)

### Hiện có sẵn, dùng được ngay

| Phương thức | Đánh giá cho thị trường VN |
|---|---|
| Cash on delivery (COD) | **Quan trọng nhất.** COD chiếm tỷ trọng rất lớn trong TMĐT Việt Nam |
| Paid in advance (chuyển khoản) | Phổ biến, khách chuyển khoản qua app ngân hàng rồi gửi ảnh |
| Invoice | Ít dùng cho bán lẻ B2C |

**Kết luận quan trọng:** shop **có thể go-live với COD + chuyển khoản** mà không cần chờ tích hợp cổng thanh toán. Đây là con đường ra thị trường nhanh nhất.

### Cần bổ sung, phải tự làm

| Dịch vụ | Vì sao cần | Trạng thái |
|---|---|---|
| Chuyển khoản QR (VietQR) | Đang tăng rất nhanh, chi phí thấp | ✅ **Đã làm** — plugin `AcmePayment` |
| VNPay | Cổng phổ biến nhất, hỗ trợ hầu hết ngân hàng nội địa | ⏳ Tự viết payment handler |
| MoMo | Ví điện tử thị phần lớn | ⏳ Tự viết |
| ZaloPay | Gắn với hệ sinh thái Zalo | ⏳ Tự viết |

**Thứ tự đã chọn:** VietQR → VNPay → MoMo. VietQR đơn giản nhất (chỉ sinh mã QR theo chuẩn NAPAS, không cần redirect) mà hiệu quả cao — đã hoàn thành. Ghi chú kỹ thuật để làm VNPay/MoMo: [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md).

Cơ chế kỹ thuật: phương thức không redirect (COD, chuyển khoản, VietQR) **dùng handler sẵn có của core** (`CashPayment`, `PrePayment`) — VietQR chỉ thêm mã QR ở trang cảm ơn. Chỉ cổng có redirect/API (VNPay, MoMo) mới cần handler riêng kế thừa `AbstractPaymentHandler`. Xem [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md).

---

## Nhóm 3 — Vận chuyển

Tình trạng tương tự: **không có plugin sẵn cho GHN, GHTK, Viettel Post, J&T, Ninja Van.**

### Phương án theo giai đoạn

**Giai đoạn 1 — bảng phí phẳng** ✅ **đã làm** (migration trong `AcmePayment`)

| Điều kiện | Phí |
|---|---|
| Nội thành Hà Nội / Hồ Chí Minh | 25.000 ₫ |
| Các tỉnh thành khác | 35.000 ₫ |
| Đơn từ 500.000 ₫ | miễn phí |

Dùng Rule Builder có sẵn, không cần tích hợp API. Đủ dùng để mở bán.

> 🔴 **Bẫy khi tạo rule bằng migration:** Shopware bắt buộc cây điều kiện `orContainer → andContainer → điều kiện`. Chèn thẳng `rule_condition` không có parent thì rule **không bao giờ khớp** — không báo lỗi, chỉ âm thầm không áp dụng. So sánh với rule sẵn có của core ("Cart >= 0") để đối chiếu cấu trúc.

**Giai đoạn 2 — tích hợp API đơn vị vận chuyển**

Khi lượng đơn tăng, tích hợp API để:
- Tính phí ship chính xác theo cân nặng và địa chỉ thật
- Tự đẩy đơn sang hệ thống đơn vị vận chuyển
- Đồng bộ trạng thái giao hàng về shop

GHN và GHTK đều có API công khai, tài liệu tương đối đầy đủ.

---

## Nhóm 4 — Vận hành & Chất lượng

| Plugin | Mục đích | Ghi chú |
|---|---|---|
| `frosh/tools` | Công cụ chẩn đoán, kiểm tra hiệu năng, xem log | Có bản 3.12.0 trên Packagist — **cần xác nhận tương thích 6.7 trước khi cài** |

> Các plugin SEO / cache / tối ưu ảnh chưa được liệt kê tên cụ thể vì **chưa kiểm chứng được khả năng tương thích với Shopware 6.7**. Bài học từ `swag/language-pack` (bản release mới nhất chỉ hỗ trợ đến 6.6) cho thấy nhiều plugin phổ biến vẫn chưa theo kịp 6.7. Luôn kiểm tra trước khi cài:
> ```
> composer show -a <tên-plugin> | grep -A5 requires
> ```

---

## Nhóm 5 — App (khác Plugin)

`custom/apps/` hiện rỗng.

Khác biệt cần nắm:

| | Plugin | App |
|---|---|---|
| Nơi chạy code | Trong server Shopware (PHP) | Server riêng bên ngoài, giao tiếp qua HTTP |
| Quyền hạn | Rất sâu, gần như không giới hạn | Giới hạn theo permission khai báo |
| Phù hợp | Tùy biến sâu, payment handler, theme | Tích hợp dịch vụ ngoài (chatbot, CRM, ERP) |
| Yêu cầu hạ tầng | Không | Cần server riêng chạy liên tục |

**Với đa thương hiệu:** tích hợp ERP / PIM (đồng bộ sản phẩm, giá, tồn kho theo brand) đi qua **Admin API / Sync API** hoặc **App** — đúng loại việc App sinh ra để làm. Phần storefront và dữ liệu brand vẫn là Plugin.

**Khuyến nghị:** giai đoạn đầu ưu tiên Plugin. App chỉ nên dùng khi cần tích hợp hệ thống bên ngoài — ví dụ nối với phần mềm quản lý kho, hoặc chatbot Zalo OA. Dùng App sớm sẽ phát sinh chi phí vận hành server mà chưa cần thiết.

---

## Quy tắc trước khi cài bất kỳ plugin nào

1. Kiểm tra ràng buộc phiên bản: `composer show -a <plugin> | grep -A5 requires`
2. Ưu tiên bản release ổn định, tránh `dev-*` trên production (hiện `SwagLanguagePack` đang là ngoại lệ bắt buộc)
3. Cài trên môi trường staging trước, không cài thẳng lên production
4. Ghi lại lý do chọn vào [decisions/](decisions/)
