# ADR-006: Gỡ storefront Twig (`shopware/storefront`)

**Trạng thái:** 🟡 Đề xuất — 21/10/2026, chờ chủ dự án duyệt
**Quyết định đề xuất:** gỡ gói `shopware/storefront` khỏi `backend/`, cùng mọi code Twig/SCSS/controller storefront trong plugin. Làm theo **5 bước ở mục "Kế hoạch"**, mỗi bước một commit và kiểm được riêng; bước cuối mới gỡ gói. Frontend Nuxt (ADR-005) đã thay toàn bộ giao diện khách hàng.

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

| Bước | Việc | Kiểm |
|---|---|---|
| 1 | **Email:** migration (trong `AcmeHeadless`) thay `rawUrl('frontend.account.order.single.page', …)` bằng `salesChannel.domains\|first.url ~ '/account/order/' ~ order.deepLinkCode` trong các mẫu của core. Chỉ thay mẫu còn đúng nguyên văn của core; mẫu người vận hành đã sửa thì ghi log, không ghi đè (MIG-03) | Gửi thử email xác nhận đơn (Admin → mẫu email → gửi thử); link mở đúng trang đơn trên frontend |
| 2 | **Chuyển phần `AcmeTheme` mà frontend còn dùng:** `ListingMediaSubscriber` và cấu hình wishlist sang `AcmeHeadless` (dữ liệu hiển thị cho frontend) | Ảnh rê chuột trên thẻ sản phẩm vẫn có, chụp ảnh trước/sau; wishlist vẫn bật |
| 3 | **`AcmeBrand` 2.0.0, `AcmePayment` 2.0.0:** xoá `src/Storefront/`, Twig, SCSS, snippet `storefront.*`, `BrandPageSeoUrlRoute` (SEO `frontend.*`), `CheckoutFinishSubscriber`, `VietQrData`. Major vì bỏ bề mặt công khai (REL-01) | Test plugin đạt; Store API brand và chuyển khoản không đổi (so JSON trước/sau) |
| 4 | **Gỡ `AcmeTheme`:** `plugin:uninstall` rồi xoá thư mục | Quy tắc 7: xem hàng, giỏ, COD vẫn chạy |
| 5 | **Gỡ gói:** backup → `composer remove shopware/storefront` → `cache:clear` → chạy toàn bộ test plugin, e2e COD + chuyển khoản, đăng ký tài khoản, quên mật khẩu, đăng ký nhận tin; kiểm `seo_url`/sitemap channel Headless không đổi | Lệnh "class ma" (docs/08) sạch với 0 tham chiếu `Shopware\Storefront` |

Có thể dừng sau bước bất kỳ mà hệ thống vẫn chạy. Bước 1–2 có giá trị ngay cả khi không gỡ gói: email không còn phụ thuộc storefront, và tính năng frontend không còn nằm trong một plugin theme.

## Hệ quả

- `backend/` chỉ còn core + administration + elasticsearch. `bin/console theme:compile` không còn cần trong deploy, deploy nhanh hơn.
- Plugin chỉ còn **một** đường hiển thị (Store API). Bản đồ trách nhiệm ở [08](../08-quy-tac-phat-trien.md) Quy tắc số 5 bỏ dòng `AcmeTheme`. Quy tắc "markup plugin nào thì CSS plugin đó" chỉ còn áp cho frontend.
- **Không quay lại được rẻ:** muốn có storefront Twig lại thì phải cài gói và viết lại theme. Chấp nhận được vì mục tiêu dự án đã là headless (ADR-005).

## Phương án đã loại

| Phương án | Lý do loại |
|---|---|
| Giữ gói vô thời hạn "phòng khi cần" | Code chết vẫn phải bảo trì; rủi ro email (mục 1) vẫn còn đó, chỉ chưa lộ ra. |
| Gỡ gói ngay, sửa lỗi sau | Email xác nhận đơn hỏng ngay lập tức; ảnh rê chuột mất mà không báo lỗi. |
| Tự viết lại route `frontend.account.order.single.page` trong plugin | Giữ một route storefront giả chỉ để `rawUrl` chạy — vá triệu chứng, khó hiểu cho người sau. Sửa mẫu email là đúng gốc. |

## Liên quan

[ADR-005](ADR-005-headless-monorepo.md) · [15-headless.md](../15-headless.md) · [08-quy-tac-phat-trien.md](../08-quy-tac-phat-trien.md) Quy tắc số 5 · [13-chuan-phat-trien-plugin.md](../13-chuan-phat-trien-plugin.md) (MIG-03, REL-01)
