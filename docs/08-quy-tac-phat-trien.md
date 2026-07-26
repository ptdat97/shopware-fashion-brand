# Quy tắc phát triển

> **Đây là quy tắc bắt buộc của dự án.** Áp dụng cho mọi người tham gia phát triển, mọi giai đoạn.

## Quy tắc số 1 — Không can thiệp core Shopware

**Mọi thay đổi phải viết qua Plugin, App hoặc Theme. Tuyệt đối không sửa mã nguồn core.**

### Cụ thể là không được đụng vào

| Đường dẫn | Nội dung |
|---|---|
| `vendor/shopware/core/` | Nhân hệ thống |
| `vendor/shopware/storefront/` | Giao diện mặc định |
| `vendor/shopware/administration/` | Giao diện quản trị |
| `vendor/` (toàn bộ) | Mọi thư viện bên thứ ba |
| `public/bundles/` | File build tự sinh |
| `public/theme/` | Theme đã biên dịch |

### Chỉ được viết vào

| Đường dẫn | Dùng cho |
|---|---|
| `custom/plugins/` | Plugin tự viết hoặc cài thủ công |
| `custom/static-plugins/` | Plugin quản lý cùng repo dự án |
| `custom/apps/` | App tích hợp dịch vụ ngoài |
| `config/` | Cấu hình cấp dự án |
| `docs/` | Tài liệu |

---

## Vì sao quy tắc này quan trọng

### 1. Mọi thay đổi trong `vendor/` sẽ bị xoá sạch

`composer install` và `composer update` **ghi đè toàn bộ `vendor/`**. Công sức sửa core biến mất không dấu vết, thường vào đúng lúc nâng cấp — thời điểm tệ nhất để phát hiện.

### 2. Git không hề theo dõi `vendor/`

Đã kiểm chứng trên chính dự án này:

```
vendor/shopware/core/Defaults.php                       → IGNORED (không track)
vendor/shopware/storefront/.../base.html.twig           → IGNORED (không track)
public/bundles/storefront/app.js                        → IGNORED (không track)
custom/plugins/AniveleDemoData/src/AniveleDemoData.php  → tracked ✅
```

Sửa core nghĩa là: **không có lịch sử, không review được, không rollback được, đồng đội không nhận được thay đổi.** Máy bạn chạy được nhưng máy người khác thì không — loại lỗi cực kỳ tốn thời gian truy vết.

### 3. Khoá chặt khả năng nâng cấp

Shopware ra bản vá bảo mật và bản nâng cấp thường xuyên. Nếu core đã bị sửa, mỗi lần nâng cấp buộc phải chọn:

- Nâng cấp và mất hết tuỳ chỉnh, hoặc
- Không nâng cấp và ôm luôn lỗ hổng bảo mật

Cả hai đều tệ. Dự án dần rơi vào tình trạng **không dám nâng cấp** — rủi ro lớn cho một shop TMĐT xử lý thanh toán và dữ liệu khách hàng.

---

## Cách làm đúng cho từng nhu cầu

### Muốn đổi giao diện

❌ **Sai:** sửa `vendor/shopware/storefront/Resources/views/...`

✅ **Đúng:** tạo theme riêng, dùng `sw_extends` và chỉ ghi đè block cần đổi:

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_name %}
    {# nội dung tuỳ biến #}
{% endblock %}
```

Xem [02-theme.md](02-theme.md) và [runbook/02-tao-theme.md](runbook/02-tao-theme.md).

> Kể cả trong theme cũng **không copy nguyên file template rồi sửa vài dòng**. Khi Shopware cập nhật template gốc, bản copy bị kẹt ở phiên bản cũ và sinh lỗi khó truy vết.

### Muốn đổi hành vi nghiệp vụ

❌ **Sai:** sửa service class trong `vendor/`

✅ **Đúng:** dùng một trong các cơ chế mở rộng của Shopware:

| Cơ chế | Dùng khi |
|---|---|
| **Event subscriber** | Phản ứng khi có sự kiện (đơn hàng tạo, khách đăng ký...) |
| **Service decorator** | Bọc và thay đổi hành vi service có sẵn |
| **Custom entity / field** | Thêm dữ liệu riêng |
| **Store API route** | Thêm endpoint cho frontend |
| **Scheduled task** | Chạy tác vụ định kỳ |

Shopware có sẵn lệnh sinh khung:

```bash
php bin/console make:plugin:event-subscriber
php bin/console make:plugin:entity
php bin/console make:plugin:storefront-controller
php bin/console make:plugin:store-api-route
php bin/console make:plugin:scheduled-task
```

Xem đầy đủ: `php bin/console list make`

### Muốn thêm cổng thanh toán / vận chuyển

✅ Viết plugin implement handler tương ứng. Xem [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md).

### Muốn tích hợp hệ thống ngoài (CRM, ERP, chatbot)

✅ Cân nhắc **App** thay vì Plugin — chạy trên server riêng, giao tiếp qua HTTP. So sánh Plugin vs App tại [03-plugin-app.md](03-plugin-app.md).

---

## Trường hợp ngoại lệ: cấu hình dữ liệu

Quy tắc trên nói về **mã nguồn**. Có những thay đổi thuộc về **cấu hình/dữ liệu**, làm qua Administration hoặc SQL là hợp lệ:

- Cấu hình tiền tệ, thuế, quốc gia (Phase 0 đã làm theo cách này)
- Cấu hình sales channel, ngôn ngữ, domain
- Dữ liệu sản phẩm, danh mục, khách hàng

Nguyên tắc phân biệt:

> **Sửa file trong `vendor/` → cấm.**
> **Sửa dữ liệu trong database qua API/Admin/SQL → được, nhưng phải backup trước và ghi lại vào [runbook/](runbook/).**

Với thay đổi cấu hình có tính lặp lại (cần tái lập trên staging/production), nên viết thành **migration trong plugin** thay vì SQL thủ công, để có thể chạy lại một cách nhất quán.

---

## Đọc core thì sao?

**Đọc thoải mái — rất khuyến khích.** Đọc core là cách tốt nhất để hiểu Shopware hoạt động ra sao và tìm đúng điểm mở rộng.

Ví dụ thực tế trong dự án này: khi cấu hình VND, việc đọc `vendor/shopware/core/Defaults.php` giúp phát hiện `Defaults::CURRENCY` là hằng số hardcode, từ đó chọn cách chuyển đổi tại chỗ thay vì tạo currency mới — tránh được lỗi nghiêm trọng. Xem [runbook/04-phase0-da-thuc-hien.md](runbook/04-phase0-da-thuc-hien.md).

**Đọc để hiểu ✅ — Sửa để dùng ❌**

---

## Kiểm tra trước khi commit

```bash
# Không được có thay đổi nào trong vendor/
git status --short vendor/ 2>/dev/null

# Xác nhận file mình sửa có được git track không
git check-ignore -v <đường-dẫn-file>
```

Nếu `git check-ignore` báo file bị ignore mà bạn vừa sửa nó để thay đổi hành vi hệ thống → **đang vi phạm quy tắc, phải làm lại qua plugin/theme.**

---

## Khi thấy core có lỗi thật sự

Đôi khi lỗi nằm ở chính Shopware. Thứ tự xử lý:

1. **Kiểm tra bản mới** — lỗi có thể đã được vá: `composer show -a shopware/core`
2. **Tìm cách vòng qua** bằng decorator hoặc event subscriber
3. **Báo lỗi lên** [github.com/shopware/shopware](https://github.com/shopware/shopware)
4. **Nếu buộc phải vá gấp:** dùng `composer patches` (cnp/composer-patches) để vá có kiểm soát — patch được lưu trong repo, áp lại tự động sau mỗi `composer install`, và ghi rõ lý do vào [decisions/](decisions/)

Tuyệt đối không sửa tay trực tiếp rồi để đó.

---

## Tóm tắt

| | |
|---|---|
| ✅ Viết code trong | `custom/plugins/`, `custom/static-plugins/`, `custom/apps/` |
| ✅ Mở rộng bằng | Theme kế thừa, event subscriber, decorator, custom entity |
| ✅ Đọc core để | Hiểu cơ chế, tìm điểm mở rộng đúng |
| ❌ Không sửa | `vendor/`, `public/bundles/`, `public/theme/` |
| ❌ Không copy | Nguyên file template rồi sửa vài dòng |

**Câu hỏi tự kiểm tra trước mỗi thay đổi:**
*"Sau khi chạy `composer update`, thay đổi này còn không?"*

Nếu câu trả lời là **không** → đang làm sai chỗ.
