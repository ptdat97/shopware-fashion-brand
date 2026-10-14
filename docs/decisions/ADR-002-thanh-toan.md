# ADR-002: Chiến lược thanh toán cho thị trường VN

**Trạng thái:** ✅ Đã quyết định và thực hiện giai đoạn 1 (08/08/2026)
**Ngày:** 26/07/2026 · cập nhật 08/08/2026

> **Đã chọn phương án A + B:** go-live với COD + chuyển khoản, đồng thời tự viết VietQR.
> VietQR đã hoàn thành trong plugin `AcmePayment`. VNPay/MoMo để giai đoạn sau.

## Bối cảnh

### Hiện có

| Phương thức | Trạng thái |
|---|---|
| Cash on delivery (COD) | Active |
| Paid in advance (chuyển khoản) | Active |
| Invoice | Active |
| PayPal (SwagPayPal) | **Đã gỡ hẳn 08/08/2026**, gỡ lại lần hai 14/10/2026 |

### Phát hiện quan trọng

Khảo sát Packagist ngày 26/07/2026 với các từ khóa `vnpay`, `momo`, `zalopay`, `onepay`:

> **Không tồn tại plugin Shopware 6 cho các cổng thanh toán nội địa Việt Nam.**

Kết quả tìm được đều thuộc hệ sinh thái khác:
- `phpviet/omnipay-vnpay`, `jackiedo/omnipay-vnpay`, `vinhkosd/omnipay-vnpay` — driver Omnipay
- `boolfly/module-zalopay` — module Magento
- `phpviet/omnipay-momo` — driver Omnipay

**Không dùng trực tiếp được cho Shopware.** Có thể tham khảo phần xử lý giao thức, nhưng lớp tích hợp với Shopware phải tự viết.

> Đã thử tra Shopware Store qua API nhưng kết quả trả về không khớp từ khóa, nên **chưa kiểm chứng được** store có plugin thương mại hay không. Cần tự tìm trên store.shopware.com trước khi quyết định tự viết.

## Vấn đề

Đây là khoảng cách lớn nhất giữa hệ sinh thái Shopware (mạnh ở châu Âu) và thị trường Việt Nam. Ở châu Âu mọi cổng lớn đều có plugin chính thức; ở VN thì không.

Hệ quả: **thanh toán online là hạng mục tốn công và tốn tiền nhất của dự án.**

## Các lựa chọn

### A. Go-live với COD + chuyển khoản (khuyến nghị cho giai đoạn đầu)

- **Chi phí:** gần như bằng 0 — đã có sẵn
- **Thời gian:** không tốn thêm
- **Ưu:** ra thị trường sớm nhất; COD chiếm tỷ trọng rất lớn trong TMĐT Việt Nam nên đây không phải lựa chọn tệ
- **Nhược:** mất nhóm khách muốn trả online; COD có tỷ lệ hoàn hàng cao hơn và ảnh hưởng dòng tiền

### B. Tự viết plugin payment handler

- **Chi phí:** công dev, cộng chi phí duy trì lâu dài
- **Ưu:** chủ động hoàn toàn, không phụ thuộc bên thứ ba, không tốn phí license
- **Nhược:** cần hiểu cả cơ chế payment của Shopware lẫn API cổng thanh toán; phải tự xử lý bảo mật, đối soát, hoàn tiền, và webhook

Thứ tự triển khai gợi ý theo độ khó tăng dần:

| Thứ tự | Phương thức | Vì sao |
|---|---|---|
| 1 | VietQR | Đơn giản nhất — chỉ sinh mã QR theo chuẩn NAPAS, không cần luồng redirect phức tạp |
| 2 | VNPay | Phổ biến nhất, hỗ trợ hầu hết ngân hàng nội địa |
| 3 | MoMo | Ví điện tử thị phần lớn |

### C. Thuê agency Việt Nam tích hợp

- **Chi phí:** tiền thuê ngoài
- **Ưu:** nhanh, agency đã có kinh nghiệm với cổng VN
- **Nhược:** phụ thuộc nhà cung cấp khi Shopware nâng version; cần đánh giá kỹ chất lượng code bàn giao

## Khuyến nghị

**Kết hợp A rồi B:**

1. **Go-live ngay với COD + chuyển khoản.** Không để việc thiếu cổng thanh toán chặn ngày mở bán.
2. **Song song phát triển VietQR** — chi phí thấp, hiệu quả cao, phù hợp xu hướng chuyển khoản QR đang tăng nhanh tại VN.
3. **Bổ sung VNPay** khi lượng đơn đủ lớn để bù chi phí tích hợp và phí giao dịch.

Lý do: doanh thu từ COD bắt đầu chảy ngay trong lúc phát triển cổng thanh toán, thay vì chờ tích hợp xong mới mở bán.

## Về PayPal

**Đã gỡ hẳn ngày 08/08/2026.** Trước đó plugin đã inactive — PayPal ít phổ biến với người tiêu dùng Việt Nam và phí giao dịch cao, nên với shop bán nội địa thì không cần.

Lý do gỡ hẳn thay vì để inactive: thư mục plugin bị xoá khỏi git nhưng `composer.json` vẫn require, khiến autoloader trỏ vào file không tồn tại và **làm hỏng toàn bộ `bin/console`** — mọi lệnh Shopware đều lỗi. Plugin inactive mà thiếu file còn nguy hiểm hơn không cài.

Nếu sau này bán ra nước ngoài thì cài lại từ đầu bằng `composer require swag/paypal`.

## Ghi chú kỹ thuật khi làm VietQR

Kiến thức thu được khi triển khai, hữu ích cho VNPay/MoMo về sau.

### Kiến trúc payment handler 6.7

Shopware 6.7 chỉ còn **một** abstract class: `AbstractPaymentHandler`. Bốn interface mà phần lớn tutorial trên mạng vẫn dạy (`SynchronousPaymentHandlerInterface`, `AsynchronousPaymentHandlerInterface`, `PaymentHandlerInterface`, `PreparedPaymentHandlerInterface`) **đã bị xoá** — xem [08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md) Quy tắc số 3.

Phương thức không cần redirect (COD, chuyển khoản, VietQR) thì `pay()` trả `null`, giống `DefaultPayment` của core — và khi đó **dùng luôn handler của core, không viết class mới**. VietQR dùng `PrePayment` ("Chuyển khoản trước") từ `AcmePayment` 1.1.0; bản handler tự viết trước đó giống hệt từng dòng nên đã bỏ ([08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md) Quy tắc số 4). Chỉ viết handler riêng khi có redirect hoặc gọi API cổng thanh toán. Có redirect (VNPay, MoMo) thì trả `RedirectResponse`, và `finalize()` sẽ được gọi sau khi khách quay lại.

Mẫu tham chiếu tốt nhất: `swag/paypal` 10.8.0, class `Swag\PayPal\Checkout\Payment\Method\AbstractPaymentMethodHandler` — gói đã gỡ khỏi dự án (14/10/2026), đọc mã nguồn trên GitHub `shopware/SwagPayPal`.

### Chuẩn VietQR

Payload theo đặc tả EMVCo, định dạng TLV (tag 2 ký tự + độ dài 2 ký tự + giá trị):

```
00 Payload format    01
01 Init method       12 (QR động, có sẵn số tiền)
38 Merchant account  GUID NAPAS A000000727 + BIN ngân hàng + số TK + QRIBFTTA
53 Currency          704 (VND)
54 Amount            số tiền, không phần thập phân
58 Country           VN
62 Additional        nội dung chuyển khoản
63 CRC               CRC-16/CCITT-FALSE trên toàn chuỗi đã gồm "6304"
```

> 🔴 **CRC phải đúng biến thể CCITT-FALSE** (poly 0x1021, init 0xFFFF, không đảo bit). Dùng nhầm biến thể CRC-16 khác sẽ sinh mã QR mà app ngân hàng **từ chối** — và không có cách nào biết ngoài việc quét thử. Kiểm chứng bằng vector chuẩn: `CRC("123456789") = 0x29B1`.

Nội dung chuyển khoản phải là **chữ/số không dấu**; app ngân hàng loại bỏ ký tự đặc biệt.

### Kiểm chứng mã QR — không tin bằng mắt

Mã QR trông đúng vẫn có thể không quét được. Cách kiểm chứng thật:

```bash
brew install zbar
# render QR ra PNG rồi giải mã ngược
zbarimg --raw -q qr.png
```

Chuỗi giải mã phải **khớp từng ký tự** với payload sinh ra. Đã dùng đúng cách này để xác nhận mã QR trên trang cảm ơn mang đúng số tài khoản, số tiền và mã đơn.

## Lưu ý bảo mật

Khi tự viết tích hợp thanh toán:

- **Không tự lưu thông tin thẻ.** Dùng luồng redirect/hosted của cổng thanh toán.
- **Luôn xác thực chữ ký** trên callback/webhook — không tin dữ liệu trả về nếu chưa kiểm tra chữ ký.
- **Đối soát tự động** giữa đơn hàng và giao dịch, tránh sai lệch tiền.
- Lưu khóa bí mật trong biến môi trường, **không commit vào git**.

Xử lý sai callback là lỗ hổng nghiêm trọng: kẻ tấn công có thể giả callback báo "đã thanh toán" cho đơn chưa trả tiền.
