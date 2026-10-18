# Roadmap triển khai

Lộ trình được sắp theo **thứ tự phụ thuộc kỹ thuật**, không phải theo mức độ hào hứng. Làm sai thứ tự sẽ phải làm lại.

> 🧭 **Định hướng từ 12/10/2026: nền tảng bán lẻ thời trang đa thương hiệu.** Một website, một catalog, một checkout cho nhiều brand — mỗi brand có logo, banner, landing page, SEO riêng. Phase 0–3 là nền móng dùng lại nguyên vẹn; phần mới nằm ở **Phase MB** bên dưới. Khảo sát và kiến trúc: [12-da-thuong-hieu.md](12-da-thuong-hieu.md), quyết định: [decisions/ADR-003-mo-hinh-thuong-hieu.md](decisions/ADR-003-mo-hinh-thuong-hieu.md).

> 🧭 **Từ 18/10/2026: headless.** Shopware thành backend, giao diện là Nuxt 4 ở `frontend/` — [ADR-005](decisions/ADR-005-headless-monorepo.md). Phase H (H1–H8) liệt kê ở [15-headless.md](15-headless.md) mục 4; các Phase/MB đã xong giữ nguyên giá trị phía backend.

## Nguyên tắc sắp xếp

Ba ràng buộc quyết định thứ tự:

1. **Currency phải chốt trước theme.** Đổi USD → VND làm thay đổi cách hiển thị giá (số chữ số thập phân, vị trí ký hiệu, độ dài chuỗi). Nếu làm theme trước, toàn bộ layout giá phải chỉnh lại.
2. **Cấu trúc variant phải chốt trước khi nhập catalog.** Sửa property group sau khi đã có 500 sản phẩm là việc rất tốn kém.
3. **Thanh toán phải xong trước khi test checkout.** Không thể nghiệm thu luồng mua hàng nếu chưa có cổng thanh toán.
4. **Mô hình brand phải chốt trước khi nhập catalog thật.** Gán brand, mã SKU theo brand và bảng size theo brand đều đi vào từng sản phẩm — đổi sau khi đã có hàng trăm SKU rất tốn kém.

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
| **VietQR** — QR chuẩn NAPAS | ✅ Plugin `AcmePayment` |
| Dọn 17 phương thức PayPal mồ côi | ✅ |
| Bảng phí vận chuyển theo khu vực | ✅ 25k nội thành / 35k tỉnh / miễn phí từ 500k |
| VNPay, MoMo | ⏳ Giai đoạn sau, khi lượng đơn đủ lớn |
| API GHN/GHTK | ⏳ Giai đoạn 2 |

**Kiểm chứng bằng đơn hàng thật:** đặt đơn qua Store API với VietQR → trạng thái giao dịch `open` (chờ đối soát), tồn kho biến thể trừ đúng, mã QR trên trang cảm ơn **giải mã ra đúng payload** (ngân hàng, số tài khoản, số tiền, nội dung `ANV10001`).

> 🧭 **14/10/2026: COD là phương thức ưu tiên, VietQR tạm gác.** Plugin VietQR vẫn giữ nguyên nhưng không còn là hạng mục chặn go-live; hai cảnh báo dưới chỉ áp dụng khi bật lại VietQR.
>
> ⚠️ **Cần nhập thông tin tài khoản ngân hàng thật** tại Administration → Extensions → Acme Payment (VietQR) trước khi mở bán. Chưa nhập thì trang cảm ơn hiện cảnh báo thay vì mã QR sai.
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

Thực hiện bằng plugin `AcmeCatalog` — migration chuẩn hoá thuộc tính + lệnh `acme:catalog:variants`.

> **Còn lại cần chốt:** dải size cho quần/chân váy (số eo 26–30 hay chữ S–XL), có bán Free size không, và giá có khác theo size không. Đây là quyết định kinh doanh, không phải kỹ thuật.

**Không cần plugin bên ngoài nào** cho phase này — variant generator, swatch màu, import/export đều có sẵn trong core 6.7. Đúng như đánh giá ban đầu.

Đánh giá đầy đủ và bài học thu được: [10-phase2-catalog.md](10-phase2-catalog.md)

---

## Phase 3 — Theme thương hiệu ✅ **HOÀN TẤT giai đoạn 1** (08/08/2026)

**Mục tiêu:** giao diện mang bản sắc brand, tối ưu cho thời trang.

Đã làm: **theme riêng `AcmeTheme` kế thừa Storefront**.

| Việc | Trạng thái |
|---|---|
| Bảng màu thương hiệu (nâu/be/kem/đen) | ✅ Lấy từ chính bảng màu sản phẩm |
| Logo dạng chữ ANIVELE thay "Demostore" | ✅ Tạm, tới khi có file logo thật |
| Swatch màu tròn, nút size ≥44px cho mobile | ✅ |
| Size hết hàng gạch chéo, không ẩn | ✅ |
| Việt hoá nhãn còn sót ("Home", "Pattern") | ✅ |
| Bo góc vuông, typography thanh | ✅ |
| Tỷ lệ ảnh 2:3 dọc (lookbook) | ✅ Hiện trọn outfit thay vì bị cắt |
| Ảnh thứ 2 khi rê chuột ở danh mục | ✅ Xem mặt trước/sau không cần bấm vào |
| Sticky header + sticky buybox | ✅ Nút mua luôn trong tầm mắt |
| Danh sách yêu thích (Wishlist) | ✅ Bật tính năng có sẵn của core |
| Ô số lượng ngay ở trang danh mục | ✅ |
| Bảng size chart | ✅ Popup theo danh mục, sửa số đo trong Admin |
| Ảnh WebP/AVIF | ⏳ Chưa làm |
| Lookbook, đánh giá kèm ảnh | ⏳ Giai đoạn sau |

**Giai đoạn 2 — hệ chữ theo hướng riani.com** ✅ (06/09/2026)

| Việc | Trạng thái |
|---|---|
| Chữ in hoa giãn 0.06em cho tên SP, nút, điều hướng, chân trang | ✅ Lấy đúng con số từ CSS thật của Riani |
| Serif hiển thị (Cormorant Garamond) cho tiêu đề trang + tên SP | ✅ Tự host, đã kiểm đủ dấu tiếng Việt bằng fontTools |
| Bổ sung Inter 300 — core chỉ có 400–700 | ✅ Tự host, 4 file chỉ 58 KB |
| Góc vuông tuyệt đối (`$border-radius: 0`) | ✅ |
| Thẻ sản phẩm bỏ mô tả, chỉ còn ảnh → tên → giá | ✅ Gỡ 6 khai báo chiều cao cứng của core |
| Vùng bấm thẻ sản phẩm phủ đúng khung ảnh 2:3 | ✅ Sửa lỗi do giai đoạn 1 gây ra |
| Việt hoá ô sắp xếp + tiêu đề trang chủ | ✅ Migration `1788690517` |
| Ảnh trang chi tiết cao gấp rưỡi (287×430 → 455×680) | ✅ `height` thắng `min-height` inline, không cần `!important` |
| Trang chủ dạng editorial (hero, lookbook) | ⏳ Cần dựng bố cục CMS, chưa làm |

> ⚠️ **Cần file logo thật.** Hiện dùng chữ ANIVELE. Tải logo tại Administration → Themes → Giao diện Acme → Media, template tự chuyển sang dùng ảnh.
>
> ⚠️ **Số đo trong bảng size là SỐ THAM KHẢO** theo thang phổ biến của thị trường VN, không phải số đo thật của Anivele. Phải đo trên sản phẩm thật rồi thay lại tại Administration → Danh mục → tab Tuỳ chỉnh → Bảng size, **trước khi go-live**. Số đo sai là nguyên nhân đổi trả hàng đầu với thời trang online.

**Bộ chọn biến thể:** kiểm tra thực tế cho thấy core 6.7 **đã render sẵn** swatch màu (`displayType = color`) và nút size — không phải dropdown như [02-theme.md](02-theme.md) lo ngại. Theme chỉ tinh chỉnh thị giác.

Chi tiết: [02-theme.md](02-theme.md), thao tác tại [runbook/02-tao-theme.md](runbook/02-tao-theme.md)

> 🧭 **Với định hướng đa thương hiệu**, `AcmeTheme` trở thành giao diện **của nhà bán lẻ**, không phải của một brand. Bản sắc từng brand chuyển sang landing page CMS — xem [12-da-thuong-hieu.md](12-da-thuong-hieu.md) mục 5.4.

---

## Phase MB — Đa thương hiệu 🔄 **ĐANG TRIỂN KHAI** (từ 12/10/2026)

> **Rà soát các plugin còn lại 14/10/2026:** VietQR dùng handler `PrePayment` của core (`AcmePayment` 1.1.0, bỏ handler tự viết); bảng size đọc danh mục chính `seoCategory` core đã nạp (bỏ subscriber + struct); demo dùng `Cocur\Slugify` của core. Bảng kết quả: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 4.
>
> **Rà soát 14/10/2026 — tận dụng thứ Shopware đã có** (AcmeBrand 1.2.0): meta SEO chuyển sang custom field dịch được của nhà sản xuất (bỏ bảng dịch riêng, 3 class, 3 ô nhập JS); SEO URL brand chuyển sang `EntityIndexer` (bỏ subscriber + lệnh `acme:brand:seo-urls`). 31 test đạt; Admin chụp ảnh kiểm chứng thẻ custom field native. Chi tiết: [ADR-003](decisions/ADR-003-mo-hinh-thuong-hieu.md) mục *Rà soát*, quy tắc mới: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 4.

**Mục tiêu:** nhiều thương hiệu bán chung một website, một catalog, một giỏ hàng; mỗi brand có trang riêng tại `/brands/{slug}`.

**Kiến trúc:** brand = `product_manufacturer` của core + plugin `AcmeBrand` lưu phần core không có (active, banner, layout, SEO meta). Một sales channel. Bộ sưu tập = category + product stream. Chi tiết: [12-da-thuong-hieu.md](12-da-thuong-hieu.md).

### MB0 — Chốt quyết định (không code) 🔄

| Việc | Trạng thái |
|---|---|
| Duyệt [ADR-003](decisions/ADR-003-mo-hinh-thuong-hieu.md) | ✅ 12/10/2026 — phương án A: brand = `product_manufacturer` + plugin `AcmeBrand` |
| Dựng lại DB local khớp [00-hien-trang.md](00-hien-trang.md) | ✅ 12/10/2026 — 5 plugin `Acme*` active, 76 sản phẩm (12 cha + 64 biến thể), `AcmeTheme` gán cho `anivele`, storefront HTTP 200 |
| Câu hỏi mở cho kinh doanh | ✅ 12/10/2026 — Anivele là một brand; brand demo giả định; brand tắt chỉ ẩn trang brand, sản phẩm vẫn bán; SKU mã brand 3 ký tự. Chi tiết: [12-da-thuong-hieu.md](12-da-thuong-hieu.md) mục 8 |
| Tên nền tảng / tên miền | ⏳ Chưa chốt — không chặn MB1–MB3 |
| `SwagPayPal` vẫn *Installed* (inactive) dù tài liệu ghi đã gỡ hẳn | ✅ 14/10/2026: `plugin:uninstall` + `composer remove swag/paypal`, xoá thư mục `custom/plugins/SwagPayPal` (composer không tự xoá) và 4 bảng `swag_paypal_*` rỗng còn sót |

> Dựng lại DB mới kiểm bằng số liệu DB và HTTP status — **chưa chụp ảnh storefront**. Chụp lại trước khi bắt đầu MB3 để có ảnh nền so sánh.

### MB1 — Dữ liệu thương hiệu (plugin `AcmeBrand`) ✅ (14/10/2026)

| Việc | Trạng thái |
|---|---|
| Migration bảng `acme_brand` | ✅ `Migration1791823368CreateBrandTables` (bảng dịch đi kèm đã bỏ ở rà soát 14/10 — meta SEO thành custom field của nhà sản xuất) |
| `EntityExtension` trên `product_manufacturer` → `manufacturer.extensions.acmeBrand` | ✅ Autoload, xoá manufacturer kéo theo xoá brand |
| Mã brand 3 ký tự (`code`) — duy nhất, đúng định dạng `[A-Z0-9]{3}` | ✅ Kiểm ở tầng ghi DAL, trùng mã trả 400 `ACME_BRAND__DUPLICATE_CODE` |
| Administration: thẻ "Thương hiệu" trong trang nhà sản xuất (hiển thị, nổi bật, mã, thứ tự, banner, layout, SEO meta) | ✅ JS viết tay, có snippet vi-VN / en-GB |
| ACL theo quyền `product_manufacturer` | ✅ Mapping JS cho role mới + `Migration1791823565AddBrandPrivileges` cho role đã có |
| Đổi nhãn menu "Các nhà sản xuất" → "Thương hiệu" | ✅ 14/10/2026 — xem MB3 |

**Nghiệm thu đã chạy:**

- `vendor/bin/phpunit -c custom/plugins/AcmeBrand` → **16 test / 41 assertion đạt**, không deprecation: ghi/đọc qua DAL, mặc định *không hoạt động*, xoá manufacturer → xoá brand, xoá banner → brand còn, mã sai định dạng bị chặn, trùng mã → lỗi 400, migration chạy lại vô hại, quyền ACL thêm đúng và không nhân đôi.
- Administration điều khiển bằng Chrome DevTools Protocol, user tạm (đã xoá): bật công tắc + gõ `mbt` + bấm Lưu → DB `code = MBT`, `active = 1`, meta title lưu theo ngôn ngữ nội dung đang chọn. Chụp ảnh giao diện en-GB và vi-VN. Phát hiện và sửa 2 lỗi hiển thị (ô thứ tự trống, gõ chữ thường khi mã không đổi vẫn hiện chữ thường).
- Xoá nhà sản xuất qua Admin API → 0 dòng `acme_brand` còn lại.

> **Không có Jest** (quyết định 14/10/2026): Administration không có `node_modules`, `npm install` ~1 GB và mất mỗi lần `composer update`. Component admin được kiểm bằng thao tác thật qua CDP + ảnh chụp thay cho Jest.
>
> Meta title/description dịch theo **ngôn ngữ nội dung** (ô chọn "English" ở đầu trang) — muốn nhập bản tiếng Việt phải đổi ô đó sang Tiếng Việt, giống mọi trường dịch được của core.

### MB2 — Gán brand cho sản phẩm ✅ (14/10/2026)

| Việc | Trạng thái |
|---|---|
| Gán manufacturer cho sản phẩm — **native**, form sản phẩm + Import/Export | ✅ Không cần code |
| `AcmeDemoData` sinh 3 brand: Anivele (`ANV`) + Mây Atelier (`MAY`), Linh Lan (`LLN`) giả định — 4 mẫu mỗi brand | ✅ Có `AcmeBrand` thì ghi kèm mã brand + bật trang brand; không có vẫn chạy |
| Bộ lọc brand ở listing danh mục | ✅ **Đã bật sẵn** — layout listing không cấu hình `filters` thì core bật mọi bộ lọc (`ProductListingCmsElementResolver::restrictFilters`). Chỉ cần sản phẩm có brand |
| Nhãn bộ lọc "Nhà sản xuất" → **"Thương hiệu"** | ✅ Snippet `listing.filterManufacturerDisplayName` trong `AcmeBrand` |
| SKU mã brand 3 ký tự áp dụng cho dữ liệu demo | ✅ `MAY-1005`, biến thể `MAY-1005-01`… |

**Nghiệm thu đã chạy:**

- Seed trên dữ liệu cũ (`ANV-*` toàn bộ) → vẫn **76 sản phẩm (12 + 64 biến thể), 47 ảnh, 12 visibility** — đổi mã sản phẩm có sẵn, không tạo bản trùng. Chạy lại lần hai không đổi gì. Đổi brand giữa hai brand không phải Anivele (`MAY-1008` → `LLN-1008`) cũng đúng; 0 biến thể sai tiền tố.
- Storefront: `?manufacturer=<id>` trả đúng số mẫu từng brand — Đầm: Anivele 3, Linh Lan 3, Mây Atelier 1; Áo: Linh Lan 1, Mây Atelier 1. Chụp ảnh desktop + mobile, bộ lọc "Thương hiệu" chỉ liệt kê brand có hàng trong danh mục.
- Store API: lọc `manufacturerId` của Mây Atelier → 32 sản phẩm = 4 cha + 28 biến thể — **biến thể kế thừa brand** của cha. `extensions.acmeBrand` có trong aggregation listing (dùng được cho MB3).
- Unit test `DemoProductCatalog` (6 test): tiền tố SKU = mã brand, mã brand đúng định dạng, SKU không trùng, khoá id giữ dãy `ANV-1001…`, danh mục có ≥2 mẫu thì có ≥2 brand. Test cuối bắt được lỗi phân bổ ban đầu (Áo chỉ có 1 brand).

> **Id demo suy ra từ khoá cố định, không từ SKU** (`key` = `ANV-1005`, `number` = `MAY-1005`). Không được đổi khoá — xem [runbook/05-demo-data.md](runbook/05-demo-data.md).

### MB3 — Storefront thương hiệu ✅ (14/10/2026)

| Việc | Trạng thái |
|---|---|
| **Spike:** listing có phân trang/sắp xếp/bộ lọc trên trang không phải category | ✅ Đạt — `ProductListingRoute` trên **category gốc điều hướng** + bộ lọc cứng `manufacturerId`. Ghi trong [ADR-003](decisions/ADR-003-mo-hinh-thuong-hieu.md) |
| Trang `/brands` — danh sách brand đang hoạt động (nổi bật → thứ tự → tên) | ✅ |
| Trang `/brands/{slug}` — banner, logo, mô tả, layout CMS (kiểu landing page), sản phẩm của brand | ✅ Bộ lọc thuộc tính/giá + sắp xếp + phân trang của core, lọc/sắp xếp AJAX |
| SEO URL `brands/{tên}` (template sửa được ở Settings → SEO) + canonical + meta | ✅ Đổi tên → slug mới, URL cũ 301 (native) |
| Sitemap cho brand | ✅ |
| Brand ngừng hoạt động → 404, rời `/brands` và sitemap; **sản phẩm vẫn bán** | ✅ |
| Logo/tên brand ở trang sản phẩm dẫn về trang brand | ✅ Ghi đè link của phần tử CMS `manufacturer-logo` (core dẫn ra website ngoài) |
| Đổi nhãn Admin "Các nhà sản xuất" → "Thương hiệu" | ✅ 14/10/2026 — file snippet Admin native (`Resources/app/administration/src/snippet/*.json`): menu, danh sách, nút, thẻ chi tiết, ô tìm kiếm, điều kiện Rule Builder "Sản phẩm thuộc thương hiệu". Chụp ảnh Admin kiểm chứng |
| Menu chính tô "Trang chủ" khi đang ở trang brand | ✅ 14/10/2026 — template meta của trang brand gọi `parent()` rồi xoá trạng thái active; chụp ảnh kiểm chứng |

**Nghiệm thu đã chạy:**

- **Spike** qua Store API trên category gốc + `manufacturerId` của Mây Atelier: phân trang (2/trang → trang 1, 2), sắp xếp giá, lọc màu Nâu → 1, Kem → 2. Mọi sản phẩm cha đều nằm trong cây điều hướng.
- **Test:** 7 integration test mới (`BrandPageLoaderTest`) — chỉ sản phẩm của brand, ẩn bộ lọc brand, phân trang, brand tắt/không có phần mở rộng → 404, thứ tự `/brands`, SEO URL theo tên + đổi tên. Tổng **23 test đạt**, chạy 3 lần thứ tự ngẫu nhiên.
- **HTTP:** `/brands`, `/brands/may-atelier`, `?order=`, `?p=2`, route AJAX → 200; slug lạ, id lạ → 404. Canonical của mọi biến thể URL trỏ về `/brands/may-atelier`. Meta title/description/keywords lấy từ brand.
- **Thao tác thật qua Admin API:** sửa meta title → trang đổi ngay (cache tag của core tự xoá cache); tắt brand → 404 + rời `/brands`, sản phẩm vẫn ở danh mục; bật lại → 200; đổi tên → `brands/linh-lan-studio`, URL cũ 301. Đã khôi phục dữ liệu.
- **Trình duyệt thật (CDP):** tick màu "Kem" trên trang brand → 4 → 2 mẫu, gọi đúng `/widgets/brand/…/listing`, URL cập nhật, không lỗi JS. Chụp ảnh `/brands`, trang brand (desktop + mobile), trang sản phẩm. Ảnh phát hiện và đã sửa 3 lỗi: lưới `/brands` dính lề trái, tên brand bị lặp ở trang sản phẩm, link brand thành `https://http://…` + mở tab mới.
- `sitemap:generate` → có đủ 3 URL `/brands/…`.

> 🔴 **`cache:clear` KHÔNG xoá HTTP cache** ở Shopware 6.7. Sửa template/code mà storefront vẫn hiện bản cũ → `bin/console cache:clear:http`. Đã mất công hai lần trong MB3 vì bẫy này.
>
> Sau khi cài/cập nhật `AcmeBrand` hoặc sửa template SEO: `bin/console dal:refresh:index --only=acme_brand.indexer` (từ 1.2.0 SEO URL brand đi qua cơ chế indexing của core).

### MB4 — Bộ sưu tập ✅ (14/10/2026)

| Việc | Trạng thái |
|---|---|
| Bộ sưu tập = category + product stream của core | ✅ Không code — demo có "Hàng mới về" (tạo trong 30 ngày, `since P30D`) và "Thu Đông 2026" (theo tag) |
| Trang tổng `/collections/` thay cho `/bo-suu-tap/` | ✅ 14/10/2026 — ô *"Là trang tổng bộ sưu tập"* (`acme_collection_root`) + một nhánh trong template SEO (`AcmeCatalog` 1.3.0). `/bo-suu-tap/` → **301** về `/collections/`; menu, breadcrumb, sitemap đều dùng `/collections/`. 3 integration test |
| SEO URL `/collections/{tên}` | ✅ Kinh doanh chốt dạng `/collections/…` (14/10/2026). `AcmeCatalog` thêm ô **"Là bộ sưu tập"** trên danh mục + template SEO navigation có điều kiện; danh mục thường giữ `Dam/`, `Ao/` |
| Bộ sưu tập theo brand | ✅ Native: bộ lọc "Thương hiệu" trong trang bộ sưu tập (`/collections/thu-dong-2026?manufacturer=…`). Muốn khối riêng trên trang brand: thêm CMS *product slider* nguồn product stream "brand + tag" vào layout landing page của brand — chưa dựng mẫu |
| Bộ sưu tập "Sale" | ✅ 14/10/2026 — product stream native "Giá rẻ nhất → Phần trăm > 0"; demo gán giá niêm yết cho 3 mẫu (3 brand). `/collections/sale` → 3 mẫu, huy hiệu % và giá gạch native |

**Nghiệm thu đã chạy:**

- `plugin:update AcmeCatalog` + `dal:refresh:index` → URL `collections/hang-moi-ve`, `collections/thu-dong-2026`; các danh mục khác không đổi (`Ao/`, `Dam/`, `Chan-vay/`, `Bo-suu-tap/`).
- Storefront: Hàng mới về 12 mẫu, Thu Đông 2026 5 mẫu từ 3 brand; lọc Thu Đông × Linh Lan → 2. Canonical đúng, menu chính có link 2 bộ sưu tập. Chụp ảnh desktop + mobile.
- Test: `AcmeCatalog` 4 integration test (đổi template khi còn mặc định, **không ghi đè template người vận hành đã sửa**, chạy lại không nhân đôi field, URL đúng cho danh mục có/không đánh dấu). `AcmeDemoData` thêm 2 unit test. Tổng 3 plugin: 23 + 4 + 8 test đạt.

> Sau khi cập nhật `AcmeCatalog` trên môi trường khác: `bin/console dal:refresh:index` để sinh lại URL danh mục. Cách tạo bộ sưu tập mới: [05-catalog-fashion.md](05-catalog-fashion.md) mục *Bộ sưu tập*.

### MB5 — Nghiệm thu commerce hợp nhất ✅ (14/10/2026)

| Việc | Trạng thái |
|---|---|
| Một đơn chứa sản phẩm của ≥2 brand: thanh toán **COD**, trừ tồn kho đúng | ✅ Native, không code |
| Khuyến mãi theo brand bằng `LineItemOfManufacturerRule` | ✅ Native — Rule Builder + *Khuyến mãi* → *Giảm giá* → "Áp dụng cho sản phẩm khớp rule" |
| Brand có trong payload `order_line_item` để báo cáo | ✅ `payload.manufacturerId` |

**Nghiệm thu đã chạy:**

- **Đơn COD thật trên DB dev qua Store API** (khách vãng lai, địa chỉ Hải Châu – Đà Nẵng): `MAY-1002-01` (Mây Atelier) + `LLN-1003-01` (Linh Lan) → đơn 10001, **2.140.000 ₫** gồm VAT 10% (194.545 ₫), phí ship 0 ₫ (đơn từ 500k), thanh toán COD trạng thái `open`. Mỗi dòng đơn lưu đúng `manufacturerId`. Tồn kho từng biến thể −1.
- **Huỷ đơn qua Admin API** → tồn kho hoàn về đúng. Sau đó xoá đơn + khách thử; DB trở về 1 đơn, 1 khách có sẵn từ trước. Bộ đếm số đơn đã tăng — đơn thật tiếp theo là 10002.
- **3 integration test** (`MultiBrandCheckoutTest`): giỏ 2 brand giữ brand trong payload và vào dòng đơn; đơn trừ tồn kho cả hai brand; khuyến mãi 10% gắn rule "nhà sản xuất = Brand A" chỉ giảm −10 trên sản phẩm Brand A (không giới hạn đúng sẽ là −30).

> Không dòng code nào của plugin chạy trong luồng giỏ hàng / checkout / đơn hàng — đúng nguyên tắc 3 của [12-da-thuong-hieu.md](12-da-thuong-hieu.md). Test giữ giả định đó cho các bản nâng Shopware sau.
>
> **Mọi brand dùng chung một kho** (chốt 14/10/2026, câu hỏi mở số 5): một đơn nhiều brand là một kiện, một bảng phí ship chung — đúng cấu hình hiện tại, không cần tách kiện hay đa kho.

### MB6 — Sẵn sàng mở rộng

> 📋 **Báo cáo khảo sát 14/10/2026:** [14-mb6-khao-sat.md](14-mb6-khao-sat.md) — chờ quyết định ở mục 5 của báo cáo.

| Việc | Ghi chú |
|---|---|
| Đồng bộ ERP / PIM | Sync API + Integration quyền tối thiểu; App nếu cần webhook. Thiếu duy nhất: FK resolver `acme_brand.code` trong `AcmeBrand` |
| Đa kho | **Không cần** — mọi brand chung một kho (14/10/2026). Chỉ xem lại nếu mở kho thứ hai: decorate `AbstractStockStorage` hoặc Shopware Commercial |
| Bảng size theo brand × danh mục | Tra cứu 3 tầng sản phẩm → brand → danh mục bằng custom field (`AcmeCatalog` định nghĩa, `AcmeTheme` hiển thị) — chờ brand thật |

> **Không đánh dấu ✅** khi chưa có automated test và ảnh chụp storefront kiểm chứng.

---

## Phase 4 — Marketing & Vận hành

| Việc | Ghi chú |
|---|---|
| SEO (URL tiếng Việt không dấu, meta, sitemap) | 🔄 Phần kỹ thuật ✅ 14/10/2026 — xem *SEO* bên dưới. Còn: meta trang chủ (chờ tên nền tảng), nội dung meta cho hàng thật |
| Tích hợp Zalo OA / Facebook Messenger | Kênh CSKH chủ đạo tại VN |
| Email transactional (xác nhận đơn, vận chuyển) | Cần SMTP thật, hiện chưa cấu hình |
| Analytics (GA4, Facebook Pixel) | Lưu ý tuân thủ quy định dữ liệu cá nhân |
| Chương trình khuyến mãi, mã giảm giá | Shopware có sẵn Rule Builder |

---

### SEO — đã làm (14/10/2026), toàn bộ bằng trường native

| Việc | Kết quả |
|---|---|
| **Canonical biến thể** — trường `canonicalProductId` của sản phẩm cha (biến thể kế thừa) trỏ về biến thể đầu | Trang `MAY-1002-03` → canonical `MAY-1002-01`. Sitemap: **64 → 12** URL sản phẩm (core tự lọc theo canonical). Lệnh `acme:catalog:variants` tự đặt khi sinh biến thể mới; seed demo đặt cho dữ liệu cũ (chỉ khi trống) |
| **Meta description** danh mục, bộ sưu tập (trường `metaDescription`) | Demo có sẵn; trang Đầm, Sale có mô tả |
| **Meta description sản phẩm** | Demo sinh từ câu đầu mô tả, cắt ở ranh giới từ ≤155 ký tự (trước: 248 ký tự, core cắt cứng ở 255) |
| Canonical, `index,follow`, OG title/image | Đã đúng sẵn trên mọi loại trang (đo bằng script khảo sát) |
| **URL chữ thường** cho sản phẩm và danh mục — đồng nhất với `/brands/…`, `/collections/…` | ✅ Chốt 14/10/2026. Chỉ thêm bộ lọc `lower` vào template SEO của core (migration `AcmeCatalog` 1.2.0, không ghi đè template đã sửa tay). `/dam/`, `/ao-polo-det-kim-tay-ngan/may-1002-01`; menu, listing, sitemap (23 URL) đều chữ thường. URL hoa cũ vẫn trả 200 (DB so khớp không phân biệt hoa thường) nhưng canonical trỏ về bản chữ thường → không trùng lặp. 3 integration test |

Không làm, có lý do:

| Việc | Vì sao |
|---|---|
| Gắn tên shop vào sau tiêu đề trang | Core không có cấu hình này — phải sửa template; chờ chốt tên nền tảng rồi quyết |
| JSON-LD (`JSON_LD_DATA`) | Là feature flag của bản **6.8** (major). Hiện storefront đã có microdata schema.org (Google đọc được). Bật khi nâng 6.8 |
| `hreflang` | Storefront chỉ có một domain tiếng Việt |

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
