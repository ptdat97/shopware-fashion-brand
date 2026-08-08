# Roadmap triển khai

Lộ trình được sắp theo **thứ tự phụ thuộc kỹ thuật**, không phải theo mức độ hào hứng. Làm sai thứ tự sẽ phải làm lại.

## Nguyên tắc sắp xếp

Ba ràng buộc quyết định thứ tự:

1. **Currency phải chốt trước theme.** Đổi USD → VND làm thay đổi cách hiển thị giá (số chữ số thập phân, vị trí ký hiệu, độ dài chuỗi). Nếu làm theme trước, toàn bộ layout giá phải chỉnh lại.
2. **Cấu trúc variant phải chốt trước khi nhập catalog.** Sửa property group sau khi đã có 500 sản phẩm là việc rất tốn kém.
3. **Thanh toán phải xong trước khi test checkout.** Không thể nghiệm thu luồng mua hàng nếu chưa có cổng thanh toán.

---

## Phase 0 — Nền tảng thị trường VN ✅ **HOÀN TẤT** (26/07/2026)

**Mục tiêu:** hệ thống hiểu đúng "bán hàng tại Việt Nam".

| Việc | Trạng thái |
|---|---|
| Currency VND, factor=1, decimals=0, ký hiệu sau số | ✅ |
| Biểu thuế VN: 10% / 8% / 0% | ✅ |
| Tắt 250 quốc gia → chỉ còn Việt Nam | ✅ |
| Tên "Việt Nam" tiếng Việt cho storefront | ✅ |
| Mã bưu chính không bắt buộc, SĐT bắt buộc | ✅ |
| 34 tỉnh/thành phố kèm bản dịch | ✅ |
| Thứ tự trường địa chỉ `city-state-zip` | ✅ |
| Xoá demo data cũ + tạo 12 sản phẩm thời trang giá VND | ✅ |

**Kết quả kiểm chứng:** giá render `1.250.000 ₫` đúng chuẩn VN, menu tiếng Việt, form đăng ký hiện "Việt Nam" và 34 tỉnh.

Chi tiết đã thực hiện: [runbook/04-phase0-da-thuc-hien.md](runbook/04-phase0-da-thuc-hien.md)
Dữ liệu demo: [runbook/05-demo-data.md](runbook/05-demo-data.md)

> **Còn lại cần xác nhận:** mức VAT áp dụng (10% hay 8%) với kế toán, và danh mục tỉnh/thành theo nguồn chính thức tại thời điểm go-live.

---

## Phase 1 — Thanh toán & Vận chuyển ✅ **HOÀN TẤT giai đoạn 1** (08/08/2026)

**Mục tiêu:** khách đặt được hàng và trả được tiền.

| Việc | Trạng thái |
|---|---|
| COD (thanh toán khi nhận hàng) | ✅ Việt hoá, gắn vào storefront |
| Chuyển khoản trước | ✅ Việt hoá, gắn vào storefront |
| **VietQR** — QR chuẩn NAPAS | ✅ Plugin `AnivelePayment` |
| Dọn 17 phương thức PayPal mồ côi | ✅ |
| Bảng phí vận chuyển theo khu vực | ✅ 25k nội thành / 35k tỉnh / miễn phí từ 500k |
| VNPay, MoMo | ⏳ Giai đoạn sau, khi lượng đơn đủ lớn |
| API GHN/GHTK | ⏳ Giai đoạn 2 |

**Kiểm chứng bằng đơn hàng thật:** đặt đơn qua Store API với VietQR → trạng thái giao dịch `open` (chờ đối soát), tồn kho biến thể trừ đúng, mã QR trên trang cảm ơn **giải mã ra đúng payload** (ngân hàng, số tài khoản, số tiền, nội dung `ANV10001`).

> ⚠️ **Cần nhập thông tin tài khoản ngân hàng thật** tại Administration → Extensions → Anivele Payment (VietQR) trước khi mở bán. Chưa nhập thì trang cảm ơn hiện cảnh báo thay vì mã QR sai.
>
> ⚠️ **Đối soát vẫn thủ công.** Đơn VietQR không tự chuyển sang "đã thanh toán" — nhân viên phải kiểm tra sao kê rồi đổi trạng thái. Tự động cần webhook ngân hàng, chưa làm.

Chi tiết bên dưới là bối cảnh dẫn tới quyết định này.

**Cảnh báo quan trọng:** khảo sát Packagist cho thấy **không có plugin Shopware 6 chính thức cho VNPay, MoMo, ZaloPay, GHN, GHTK**. Kết quả tìm kiếm chỉ ra thư viện Laravel/Magento/Omnipay — không dùng trực tiếp được cho Shopware. Đây là hạng mục tốn công nhất của cả dự án.

Ba hướng xử lý, xem phân tích tại [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md):

| Hướng | Chi phí | Rủi ro |
|---|---|---|
| Tự viết plugin payment handler | Cao (công dev) | Chủ động, không phụ thuộc bên thứ ba |
| Thuê agency VN làm tích hợp | Trung bình–cao (tiền) | Phụ thuộc nhà cung cấp khi Shopware lên version |
| Giai đoạn đầu chỉ COD + chuyển khoản | Thấp | Mất khách muốn trả online, nhưng **go-live được sớm** |

**Khuyến nghị:** giai đoạn đầu chạy COD + chuyển khoản (đã có sẵn), song song phát triển VNPay. COD chiếm tỷ trọng rất lớn trong TMĐT Việt Nam nên đây không phải lựa chọn tệ để mở bán.

Vận chuyển: tương tự, cần tự tích hợp API GHN/GHTK hoặc giai đoạn đầu dùng bảng phí phẳng theo khu vực (nội thành / ngoại thành / tỉnh).

---

## Phase 2 — Catalog thời trang ✅ **HOÀN TẤT** (08/08/2026)

**Mục tiêu:** cấu trúc dữ liệu sản phẩm đúng đặc thù ngành thời trang.

| Việc | Trạng thái |
|---|---|
| Tách "Hoa nhiều màu" khỏi nhóm Màu sắc → nhóm Hoạ tiết | ✅ |
| Bản dịch vi-VN cho thuộc tính (trước chỉ có en-US) | ✅ |
| Cấu hình variant matrix (màu × size) — 64 biến thể | ✅ |
| Quy tắc mã SKU cho variant (`ANV-1001-01`) | ✅ |
| Bật `is_closeout` chặn bán quá tồn kho | ✅ |
| Chốt dải size theo loại hàng | ⏳ **Cần người bán hàng quyết** — hiện dùng S/M/L/XL |
| Size chart | ⏳ Dùng CMS block, không cần plugin |

**Kết quả kiểm chứng:** storefront hiện bộ chọn size/màu; đặt size S về 0 tồn kho thì `available = 0` riêng size đó, M/L/XL vẫn bán được; bộ lọc màu không còn trả về váy hoa.

Thực hiện bằng plugin `AniveleCatalog` — migration chuẩn hoá thuộc tính + lệnh `anivele:catalog:variants`.

> **Còn lại cần chốt:** dải size cho quần/chân váy (số eo 26–30 hay chữ S–XL), có bán Free size không, và giá có khác theo size không. Đây là quyết định kinh doanh, không phải kỹ thuật.

**Không cần plugin bên ngoài nào** cho phase này — variant generator, swatch màu, import/export đều có sẵn trong core 6.7. Đúng như đánh giá ban đầu.

Đánh giá đầy đủ và bài học thu được: [10-phase2-catalog.md](10-phase2-catalog.md)

---

## Phase 3 — Theme thương hiệu

**Mục tiêu:** giao diện mang bản sắc brand, tối ưu cho thời trang.

Đã chốt hướng: **tự làm theme riêng kế thừa Storefront**.

Trọng tâm cho fashion:
- Trang sản phẩm ảnh lớn, gallery nhiều góc
- Bộ chọn màu/size trực quan (swatch màu thay vì dropdown)
- Bảng size chart (khách VN rất hay cần)
- Mobile-first — tỷ trọng mua sắm qua mobile ở VN rất cao
- Tối ưu tốc độ tải ảnh

Chi tiết: [02-theme.md](02-theme.md), thao tác tại [runbook/02-tao-theme.md](runbook/02-tao-theme.md)

---

## Phase 4 — Marketing & Vận hành

| Việc | Ghi chú |
|---|---|
| SEO (URL tiếng Việt không dấu, meta, sitemap) | |
| Tích hợp Zalo OA / Facebook Messenger | Kênh CSKH chủ đạo tại VN |
| Email transactional (xác nhận đơn, vận chuyển) | Cần SMTP thật, hiện chưa cấu hình |
| Analytics (GA4, Facebook Pixel) | Lưu ý tuân thủ quy định dữ liệu cá nhân |
| Chương trình khuyến mãi, mã giảm giá | Shopware có sẵn Rule Builder |

---

## Phase 5 — Go-live

Xem checklist đầy đủ tại [06-golive.md](06-golive.md). Tóm tắt các hạng mục chặn:

- [ ] HTTPS thật (hiện mới có `http://shopware.test`)
- [ ] Xóa sạch demo data
- [ ] Đổi `SwagLanguagePack` khỏi `dev-master` nếu đã có bản release ổn định
- [x] ✅ MySQL — đã migrate 9.7.1 → 8.0.33 (26/07/2026)
- [ ] Backup tự động + phương án khôi phục **đã test thật**
- [ ] Cấu hình mail server
- [ ] Đổi APP_SECRET, rà soát thông tin nhạy cảm
- [ ] Kiểm thử toàn bộ luồng mua hàng bằng đơn thật

---

## Về ước lượng thời gian

Tài liệu này **cố tình không ghi số ngày/tuần** cho từng phase. Thời gian thực tế phụ thuộc quy mô đội ngũ, việc tự làm hay thuê ngoài phần thanh toán, và số lượng SKU cần nhập — những thông tin chưa có. Đưa ra con số lúc này sẽ là phỏng đoán vô căn cứ.

Khi đã chốt được hướng ở Phase 1 (tự làm hay thuê agency) và biết quy mô catalog, có thể bổ sung timeline cụ thể.
