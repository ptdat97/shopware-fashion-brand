# ADR-006: Gỡ storefront Twig (`shopware/storefront`)

**Trạng thái:** ✅ Đã duyệt — 22/10/2026 (đề xuất 21/10). Chủ dự án thêm: phần còn cần thiết có thể chuyển sang plugin khác.
**Quyết định:** gỡ gói `shopware/storefront` khỏi `backend/`, cùng mọi code Twig/SCSS/controller storefront trong plugin. Làm theo **5 bước ở mục "Kế hoạch"**, mỗi bước một commit và kiểm được riêng; bước cuối mới gỡ gói. Frontend Nuxt (ADR-005) đã thay toàn bộ giao diện khách hàng.

## Bối cảnh

- Từ 18/10/2026 không channel nào dùng storefront Twig (channel `anivele` đã xoá, ADR-005 mục 3–4). ADR-005 hoãn việc gỡ gói tới khi xong H1–H8.
- **H1–H8 đã xong (21/10/2026)**, kèm e2e COD và "Chuyển khoản trước" qua frontend (đơn #10011–#10014: VAT 10%, SĐT + tỉnh lưu vào đơn, `manufacturerId` trong dòng đơn, tồn kho trừ đúng — [15-headless.md](../15-headless.md) mục 4).
- Giữ gói tức là giữ code chết nhưng vẫn phải bảo trì. Mỗi lần nâng cấp Shopware phải kiểm cả Twig override không ai xem. Plugin còn hai đường hiển thị (Twig + Store API) cho cùng một nghiệp vụ, dễ lệch nhau.

## Hiện trạng phụ thuộc (kiểm 21/10/2026)

| Plugin | PHP dùng `Shopware\Storefront` | Twig | Asset storefront | Thứ frontend headless **đang dùng** |
|---|---|---|---|---|
| `AcmeBrand` | 8 file: controller `/brands`, page loader, `BrandPageSeoUrlRoute` (`frontend.*`) | 6 | 1 | Store API brand, `BrandStoreApiUrlRoute`, sitemap |
| `AcmePayment` | `CheckoutFinishSubscriber` (trang cảm ơn Twig) | 1 | 1 | `BankTransferRoute` (đã có QR VietQR), `BankTransferInfoProvider` |
| `AcmeTheme` | `AcmeTheme` (ThemeInterface) | 4 | 11 | ⚠️ **`ListingMediaSubscriber`** — ảnh thứ hai khi rê chuột trên thẻ sản phẩm (H5); ⚠️ migration bật wishlist |
| Còn lại (`AcmeVietnam`, `AcmeCatalog`, `AcmeShipping`, `AcmeHeadless`, `AcmeDemoData`) | 0 | 0 | 0 | — |

Phụ thuộc **ngoài code plugin**, dễ bị bỏ sót:

1. ⚠️ **Mẫu email của core gọi route storefront.** 20 mẫu (xác nhận đơn, đổi trạng thái đơn/giao hàng/thanh toán, hoá đơn…) có `rawUrl('frontend.account.order.single.page', { deepLinkCode }, salesChannel.domains|first.url)`. Hiện link vẫn đúng: route tạo ra `/account/order/{deepLinkCode}` trên domain frontend, và Nuxt có trang `pages/account/order/[deepCode].vue`. **Gỡ storefront thì route này không còn**, `rawUrl` ném lỗi và **email xác nhận đơn không gửi được**.
2. Menu **Themes** trong Administration thuộc gói storefront, sẽ biến mất. Logo đã chuyển sang cấu hình `AcmeHeadless` (L5), bảng màu nằm trong `frontend/app/assets/css/tailwind.css`, nên không mất gì.
3. Các URL cấu hình sẵn trong core (`core.loginRegistration.confirmationUrl`, `core.newsletter.subscribeUrl`, khôi phục mật khẩu) là đường dẫn tương đối ghép vào domain channel, không phụ thuộc gói storefront. Chỉ cần frontend có trang tương ứng (kiểm ở bước 5).
4. `backend/composer.json` có `"shopware/storefront": "*"`. Gỡ là thay đổi gói core, đúng ngoại lệ của quy tắc cứng số 2.

## Kế hoạch (mỗi bước một commit)

| Bước | Việc | Kiểm | Tiến độ |
|---|---|---|---|
| 1 | **Email:** migration (trong `AcmeHeadless`) thay các lời gọi `rawUrl('frontend.account.order.single.*', …)` bằng phép nối chuỗi trên `salesChannel.domains\|first.url`. Chỉ thay đúng đoạn `rawUrl(...)` nguyên văn của core; phần người vận hành đã sửa giữ nguyên (MIG-03) | Gửi thử email xác nhận đơn (Admin → mẫu email → gửi thử); link mở đúng trang đơn trên frontend | ✅ 22/10 — `AcmeHeadless` 1.1.0 `Migration1792666425MailTemplateFrontendUrls`: thay cả 3 route storefront trong mẫu email (trang đơn, tài liệu dễ đọc, tải sản phẩm số) bằng phép nối chuỗi cho ra **đúng y URL cũ** (test so thẳng với `rawUrl` khi còn storefront; mọi mẫu biên dịch được). Local: 0 mẫu còn `rawUrl` |
| 2 | **Chuyển phần `AcmeTheme` mà frontend còn dùng:** `ListingMediaSubscriber` và cấu hình wishlist sang `AcmeHeadless` (dữ liệu hiển thị cho frontend) | Ảnh rê chuột trên thẻ sản phẩm vẫn có, chụp ảnh trước/sau; wishlist vẫn bật |  |
| 3 | **`AcmeBrand` 2.0.0, `AcmePayment` 2.0.0:** xoá `src/Storefront/`, Twig, SCSS, snippet `storefront.*`, `BrandPageSeoUrlRoute` (SEO `frontend.*`), `CheckoutFinishSubscriber`, `VietQrData`. Major vì bỏ bề mặt công khai (REL-01) | Test plugin đạt; Store API brand và chuyển khoản không đổi (so JSON trước/sau) |  |
| 4 | **Gỡ `AcmeTheme`:** `plugin:uninstall` rồi xoá thư mục | Quy tắc 7: xem hàng, giỏ, COD vẫn chạy | ✅ 22/10 — `plugin:uninstall AcmeTheme` + xoá thư mục. Ảnh rê chuột: 7/7 thẻ ở `/dam/` vẫn có, ảnh thẻ đang rê **trùng từng pixel** trước/sau; wishlist vẫn bật |
| 5 | **Gỡ gói:** backup → `composer remove shopware/storefront` → `cache:clear` → chạy toàn bộ test plugin, e2e COD + chuyển khoản, đăng ký tài khoản, quên mật khẩu, đăng ký nhận tin; kiểm `seo_url`/sitemap channel Headless không đổi | Lệnh "class ma" (docs/08) sạch với 0 tham chiếu `Shopware\Storefront` | ⏸ **Tạm dừng 22/10** — xem mục "Bước 5 bị chặn" |

### Bước 5 bị chặn (22/10/2026)

`composer why shopware/storefront`: ngoài `composer.json` gốc còn **`swag/language-pack`** (`dev-master`) khai báo `shopware/storefront ~6.7`. Gỡ storefront thì composer buộc gỡ luôn language pack. Mà language pack là nguồn **duy nhất** của:

- Administration tiếng Việt (tài khoản quản trị đang dùng vi-VN) — `Resources/app/administration/src/snippet/vi-VN.json`
- Câu báo lỗi giỏ hàng / Store API tiếng Việt (`translatedMessage`, ví dụ "Không tìm thấy mã khuyến mãi…" ở H8) — `snippet/core/messages.vi-VN.base.json`

Gói storefront vẫn cài nhưng **không còn code dự án nào dùng** (0 tham chiếu `Shopware\Storefront` trong `custom/plugins`, không channel Storefront). Mở lại bước 5 khi: language pack có bản không phụ thuộc storefront, **hoặc** chủ dự án chấp nhận mất hai phần trên / tự cung cấp bản dịch (snippet file trong plugin — phần lỗi giỏ hàng nhỏ, phần Administration rất lớn).

Có thể dừng sau bước bất kỳ mà hệ thống vẫn chạy. Bước 1–2 có giá trị ngay cả khi không gỡ gói: email không còn phụ thuộc storefront, và tính năng frontend không còn nằm trong một plugin theme.

## Hệ quả

- `backend/` chỉ còn core + administration + elasticsearch. `bin/console theme:compile` không còn cần trong deploy, deploy nhanh hơn.
- Plugin chỉ còn **một** đường hiển thị (Store API). Bản đồ trách nhiệm ở [08](../08-quy-tac-phat-trien.md) Quy tắc số 5 bỏ dòng `AcmeTheme`. Quy tắc "markup plugin nào thì CSS plugin đó" chỉ còn áp cho frontend.
- **Không quay lại được rẻ:** muốn có storefront Twig lại thì phải cài gói và viết lại theme. Chấp nhận được vì mục tiêu dự án đã là headless (ADR-005).

## Phát hiện khi làm (22/10/2026)

- ✅ **Link trong email trỏ domain giữ chỗ.** Channel Headless có hai domain; domain đầu là `default.headless0` do core tạo (Migration1620820321…). Mẫu email lấy `salesChannel.domains|first.url` → link "xem đơn" là `default.headless0/account/order/…` — hỏng từ trước bước 1 (bước 1 giữ nguyên URL nên giữ luôn lỗi). Sửa: `AcmeVietnam` 1.5.0 xoá domain giữ chỗ khi channel đã có domain frontend thật. Kiểm: email xác nhận đơn #10017 (log `mail.sent`) chứa `http://localhost:3000/account/order/…`.
- ⚠️ **Khách vãng lai không mở được đơn từ email.** Trang `/account/order/{deepLinkCode}` bắt xác minh email **+ mã bưu chính**; core (`OrderRoute::checkGuestAuth`) từ chối khi đơn không có mã bưu chính — mà đơn VN không có (Phase 0, H6). Có từ thời storefront Twig. Chưa sửa — cần quyết định yếu tố xác minh thứ hai (đề xuất: SĐT, đã bắt buộc).
- ✅ (26/10, `AcmeVietnam` 1.6.0 — [04](../04-thi-truong-vn.md) mục 8) ~~**Email đơn hàng còn tiếng Anh**~~ ("Order confirmation"): channel vi-VN nhưng mẫu email chưa có bản vi-VN. Chưa sửa.

## Phương án đã loại

| Phương án | Lý do loại |
|---|---|
| Giữ gói vô thời hạn "phòng khi cần" | Code chết vẫn phải bảo trì; rủi ro email (mục 1) vẫn còn đó, chỉ chưa lộ ra. |
| Gỡ gói ngay, sửa lỗi sau | Email xác nhận đơn hỏng ngay lập tức; ảnh rê chuột mất mà không báo lỗi. |
| Tự viết lại route `frontend.account.order.single.page` trong plugin | Giữ một route storefront giả chỉ để `rawUrl` chạy — vá triệu chứng, khó hiểu cho người sau. Sửa mẫu email là đúng gốc. |

## Liên quan

[ADR-005](ADR-005-headless-monorepo.md) · [15-headless.md](../15-headless.md) · [08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md) Quy tắc số 5 · [13-chuan-phat-trien-plugin.md](../13-chuan-phat-trien-plugin.md) (MIG-03, REL-01)
