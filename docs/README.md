# Tài liệu kỹ thuật — Nền tảng thời trang đa thương hiệu trên Shopware 6.7

Nền tảng bán lẻ thời trang **nhiều thương hiệu trên một website** cho thị trường Việt Nam: một catalog, một giỏ hàng, một checkout; mỗi thương hiệu có trang riêng. Toàn bộ số liệu trong bộ tài liệu này lấy trực tiếp từ hệ thống đang chạy tại `/Users/dat/Ecommerce/shopware-fashion-brand/backend`.

> 🧭 **Định hướng hiện tại:** headless — [15-headless.md](15-headless.md) (frontend Nuxt, công nghệ, việc cần chuyển), quyết định [ADR-005](decisions/ADR-005-headless-monorepo.md). Kiến trúc đa thương hiệu không đổi: [12-da-thuong-hieu.md](12-da-thuong-hieu.md).
>
> Repo là monorepo: Shopware ở `backend/`, Nuxt ở `frontend/`. Mọi lệnh `bin/console`/`composer`/`vendor/bin/*` trong tài liệu chạy trong `backend/`.

## Đọc trước khi code

| | Tài liệu | Nội dung |
|---|---|---|
| ⚠️ | **[08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md)** | **Được sửa ở đâu** — không can thiệp core, không `composer require` plugin tự viết |
| ⚠️ | **[09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md)** | **Sửa như thế nào** — phạm vi thay đổi, kiến trúc PHP, migration, test |
| 📐 | **[13-chuan-phat-trien-plugin.md](13-chuan-phat-trien-plugin.md)** | **Luật chi tiết** — quy tắc có mã (`DATA-04`…) theo chuẩn Shopware, Definition of Done, hiện trạng tuân thủ |
| 🔧 | **[runbook/06-tao-plugin.md](runbook/06-tao-plugin.md)** | **Bắt tay vào làm** — quy trình tạo plugin mới đúng chuẩn |

## Tra cứu nhanh

| Cần gì | Tài liệu |
|---|---|
| Hệ thống đang ở trạng thái nào | [00-hien-trang.md](00-hien-trang.md) |
| Lệnh Symfony/Shopware | [07-lenh-thuong-dung.md](07-lenh-thuong-dung.md) |
| Còn bao nhiêu việc tới go-live | [01-roadmap.md](01-roadmap.md) |
| **Kiến trúc đa thương hiệu** | **[12-da-thuong-hieu.md](12-da-thuong-hieu.md)** |
| MB6 — khảo sát ERP/PIM, đa kho, bảng size theo brand | [14-mb6-khao-sat.md](14-mb6-khao-sat.md) |
| **Headless — chạy frontend, công nghệ, việc cần chuyển** | **[15-headless.md](15-headless.md)** |
| Khảo sát dự án nguồn mở để đẩy nhanh phát triển | [16-khao-sat-nguon-mo.md](16-khao-sat-nguon-mo.md) |

## Theo chủ đề

**Cấu hình & vận hành**

| Chủ đề | Tài liệu |
|---|---|
| Thị trường VN — VND, thuế, địa chỉ | [04-thi-truong-vn.md](04-thi-truong-vn.md) |
| Plugin / app cần cài | [03-plugin-app.md](03-plugin-app.md) |
| MCP server & công cụ AI cho dev | [11-mcp-va-ai-tooling.md](11-mcp-va-ai-tooling.md) |
| Chuẩn bị go-live | [06-golive.md](06-golive.md) |

**Xây dựng shop**

| Chủ đề | Tài liệu |
|---|---|
| **Đa thương hiệu — brand, trang brand, bộ sưu tập** | **[12-da-thuong-hieu.md](12-da-thuong-hieu.md)** |
| Theme của nền tảng | [02-theme.md](02-theme.md) |
| Catalog thời trang — size, màu, variant | [05-catalog-fashion.md](05-catalog-fashion.md) |
| **Phase 2 — đánh giá catalog & kế hoạch variant** | **[10-phase2-catalog.md](10-phase2-catalog.md)** |

**Quy trình thao tác** — [runbook/](runbook/)

| Quy trình | Tài liệu |
|---|---|
| Backup & khôi phục | [runbook/00-backup.md](runbook/00-backup.md) |
| Cấu hình VND | [runbook/01-cau-hinh-vnd.md](runbook/01-cau-hinh-vnd.md) |
| Tạo theme | [runbook/02-tao-theme.md](runbook/02-tao-theme.md) |
| Xoá demo data | [runbook/03-xoa-demo-data.md](runbook/03-xoa-demo-data.md) |
| Tái lập Phase 0 trên môi trường khác | [runbook/04-phase0-da-thuc-hien.md](runbook/04-phase0-da-thuc-hien.md) |
| Dữ liệu demo thời trang | [runbook/05-demo-data.md](runbook/05-demo-data.md) |
| **Tạo plugin mới** | [runbook/06-tao-plugin.md](runbook/06-tao-plugin.md) |

**Quyết định kỹ thuật** — [decisions/](decisions/)

| Quyết định | Tài liệu |
|---|---|
| Chọn MySQL 8.0.33 | [decisions/ADR-001-mysql.md](decisions/ADR-001-mysql.md) |
| Hướng đi cho thanh toán | [decisions/ADR-002-thanh-toan.md](decisions/ADR-002-thanh-toan.md) |
| Mô hình dữ liệu thương hiệu ✅ đã duyệt | [decisions/ADR-003-mo-hinh-thuong-hieu.md](decisions/ADR-003-mo-hinh-thuong-hieu.md) |
| Đổi namespace `AcmeTheme\` → `Acme\Theme\` ✅ | [decisions/ADR-004-namespace-acme-theme.md](decisions/ADR-004-namespace-acme-theme.md) |

## Quy tắc quan trọng nhất

> **1. Không can thiệp core Shopware.** Mọi thay đổi phải viết qua Plugin, App hoặc Theme — không sửa `vendor/`.
>
> Lý do: `composer update` ghi đè toàn bộ `vendor/`, git không track thư mục này, và sửa core khoá chặt khả năng nâng cấp Shopware về sau.
>
> **2. Không `composer require` plugin tự viết.** Shopware tự quét `custom/plugins/`. Thêm vào `composer.json` sẽ khoá nút Xoá plugin trong Administration và gây xung đột merge.
>
> **3. Đọc kiến trúc Shopware trước khi viết. Không bịa API.** Shopware xoá interface giữa các bản major — code chép từ tutorial cũ sẽ không chạy. Xác nhận mọi tên class trong `vendor/` bằng lệnh ở [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md#kiểm-tra-tự-động).
>
> **4. Brand là `product_manufacturer`, mọi brand chung một sales channel.** Không tạo entity brand song song, không tạo sales channel/theme theo brand, không chèn logic brand vào cart/checkout — xem [12-da-thuong-hieu.md](12-da-thuong-hieu.md) mục 6.
>
> **5. Không tạo lại thứ Shopware đã có.** Trường sẵn có → cấu hình native → custom field → điểm mở rộng; bảng/entity riêng là lựa chọn cuối, phải ghi lý do — [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) Quy tắc số 4.
>
> Chi tiết và cách làm đúng cho từng nhu cầu: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md)

## Quy ước của bộ tài liệu

1. **Mô tả trạng thái hiện tại, không kể lịch sử thay đổi.** Ngoại lệ duy nhất là tiến trình dự án ([01-roadmap.md](01-roadmap.md)) và các ADR — nơi bối cảnh dẫn tới quyết định là phần nội dung chính.
2. **Mọi con số đều kiểm chứng trên hệ thống thật**, không copy từ tutorial. Phần chưa kiểm chứng được ghi rõ `[CHƯA KIỂM CHỨNG]`.
3. **Nói rõ cái gì KHÔNG có sẵn.** Thị trường VN thiếu nhiều plugin mà tài liệu Shopware quốc tế mặc định là có — xem [03-plugin-app.md](03-plugin-app.md).
4. **Ưu tiên thứ tự phụ thuộc.** Không làm theme trước khi chốt currency, vì giá hiển thị sai sẽ phải sửa lại toàn bộ template.
