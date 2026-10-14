# ADR-004: Giữ namespace `AcmeTheme\` — ngoại lệ của STR-01

**Trạng thái:** ✅ Đã quyết — 14/10/2026
**Quyết định:** Plugin `AcmeTheme` giữ namespace PHP `AcmeTheme\` thay vì `Acme\Theme\` như [STR-01](../13-chuan-phat-trien-plugin.md). Plugin mới **không** được lấy đây làm tiền lệ.

## Bối cảnh

STR-01 yêu cầu namespace `Acme\<Nghiệp vụ>\`. Sáu plugin đã đúng; `AcmeTheme` được tạo trước khi có chuẩn, dùng `AcmeTheme\`.

Shopware nhận diện plugin đã cài theo **`plugin.base_class`**, không theo tên thư mục (`PluginService::refreshPlugins()` tìm bản ghi bằng `filterByProperty('baseClass', …)`). Bảng `plugin` có `UNIQUE(name)` và `UNIQUE(base_class)` — kiểm trên DB local 14/10/2026.

## Đổi namespace thì điều gì xảy ra

1. `plugin:refresh` không tìm thấy bản ghi theo base class mới → thử tạo bản ghi `AcmeTheme` mới → vướng `UNIQUE(name)` → lỗi; bản ghi cũ bị xoá vì "không còn trên đĩa".
2. Lần refresh sau mới tạo được bản ghi, ở trạng thái **chưa cài** → bundle không nạp → storefront dùng theme của một plugin không chạy cho tới khi cài + bật lại + `theme:compile`.
3. Migration của plugin được nhận diện theo tên class đầy đủ → chạy lại dưới namespace mới.

Mọi môi trường đã cài đều phải trải qua chuỗi trên. Thứ nhận được chỉ là tên namespace đồng bộ — không có thay đổi hành vi nào.

## Quyết định

Giữ `AcmeTheme\`. Mọi quy tắc khác của chuẩn vẫn áp dụng đầy đủ cho `AcmeTheme` (docblock STR-06, snippet `acme-theme.*` STR-02, test TEST-01).

## Khi nào xem lại

- Có lý do khác buộc phải cài lại theme trên mọi môi trường (ví dụ tách theme thành nhiều theme) → đổi namespace cùng lúc.
- Một công cụ kiểm tra tự động (PHPStan rule, CI) yêu cầu namespace thống nhất và không cho phép loại trừ.
