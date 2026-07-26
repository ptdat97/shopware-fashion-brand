# ADR-002: Chiến lược thanh toán cho thị trường VN

**Trạng thái:** Cần quyết định — ảnh hưởng lớn tới chi phí và thời gian go-live
**Ngày:** 26/07/2026

## Bối cảnh

### Hiện có

| Phương thức | Trạng thái |
|---|---|
| Cash on delivery (COD) | Active |
| Paid in advance (chuyển khoản) | Active |
| Invoice | Active |
| PayPal (SwagPayPal 10.7.0) | Inactive |

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

Đã cài nhưng đang inactive. **Nên giữ nguyên trạng thái inactive** cho shop bán nội địa VN — PayPal ít phổ biến với người tiêu dùng Việt Nam và phí giao dịch cao. Chỉ bật nếu có kế hoạch bán ra nước ngoài.

## Lưu ý bảo mật

Khi tự viết tích hợp thanh toán:

- **Không tự lưu thông tin thẻ.** Dùng luồng redirect/hosted của cổng thanh toán.
- **Luôn xác thực chữ ký** trên callback/webhook — không tin dữ liệu trả về nếu chưa kiểm tra chữ ký.
- **Đối soát tự động** giữa đơn hàng và giao dịch, tránh sai lệch tiền.
- Lưu khóa bí mật trong biến môi trường, **không commit vào git**.

Xử lý sai callback là lỗ hổng nghiêm trọng: kẻ tấn công có thể giả callback báo "đã thanh toán" cho đơn chưa trả tiền.
