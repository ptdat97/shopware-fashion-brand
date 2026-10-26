# Runbook: Build và deploy (backend + frontend)

> Lập 26/10/2026. Monorepo hai phần deploy riêng: `backend/` (Shopware, PHP) và `frontend/` (Nuxt, Node). Chưa có server thật — các bước dưới đã chạy thử trên máy local, phần hạ tầng (domain, HTTPS, server) còn ở [06-golive.md](../06-golive.md).

## 1. Backend — build (máy CI hoặc máy build, không phải server)

```bash
cd backend
composer install --no-dev --optimize-autoloader      # chỉ gói production
# Admin của plugin cài bằng composer (FroshTools, Mail Archive) không kèm bản build sẵn:
SHOPWARE_ADMIN_BUILD_ONLY_EXTENSIONS=1 bin/build-administration.sh
bin/console assets:install
```

- Cách chuẩn của Shopware là `shopware-cli project ci .` — làm cả ba việc trên và dọn `node_modules`. **Chưa cài trên máy local:** Homebrew đòi tin cậy (trust) tap `shopware/tap` — quyết định của chủ máy / người dựng CI. Dùng script của core như trên cho tới khi có.
- Plugin tự viết (`custom/plugins/Acme*`) đã commit sẵn bản build Admin (`src/Resources/public/administration`), không cần build lại.
- `bin/build-administration.sh` dùng `npm --prefer-offline`: cache npm cũ có thể báo "No matching version" cho gói vẫn tồn tại (gặp 26/10 với `@tiptap/pm`) → `npm view <gói>@<bản>` để làm mới cache rồi chạy lại.
- Không chạy `theme:compile`: không còn channel Storefront (ADR-005, ADR-006).

## 2. Backend — deploy (trên server)

```bash
cd backend
vendor/bin/shopware-deployment-helper run --skip-theme-compile --skip-assets-install
```

Cấu hình ở `backend/.config/shopware-project.yml`:

| Mục | Làm gì |
|---|---|
| `extension-management` | Cài / cập nhật mọi plugin theo version trong `composer.json` của plugin — thay các bước "Triển khai: `plugin:update X`" ghi trong commit (REL-02). **`AcmeDemoData` bỏ qua** — dữ liệu demo không lên staging/production |
| `one-time-tasks` | `dal:refresh:index` một lần sau lần cài đầu. Plugin cần đánh chỉ mục lại khi cập nhật (ví dụ `AcmeBrand`, `AcmeVietnam` tìm kiếm) → thêm một task với **id mới** |
| `cache.always_clear` | Xoá HTTP cache + object cache sau mỗi lần deploy (`cache:clear` không xoá HTTP cache) |

Lần đầu trên server trống: helper tự `system:install`, đọc `INSTALL_ADMIN_USERNAME`, `INSTALL_ADMIN_PASSWORD`, `INSTALL_ADMIN_EMAIL`, `INSTALL_LOCALE`, `INSTALL_CURRENCY`, `SALES_CHANNEL_URL` từ môi trường. Dự án cần **vi-VN / VND** — nhưng channel bán hàng `Headless`, VND, thuế, tỉnh/thành đều do migration plugin tạo (`AcmeVietnam`, `AcmePayment`, `AcmeShipping`), nên chạy đúng thứ tự: cài core → helper cài plugin → migration chạy.

Biến môi trường bắt buộc trên server (không commit — `backend/.env.local` hoặc biến của hệ thống):

| Biến | Ghi chú |
|---|---|
| `APP_ENV=prod`, `APP_SECRET` | `APP_SECRET` mới — giá trị local coi như đã lộ (06-golive) |
| `DATABASE_URL` | User riêng có mật khẩu, **MySQL 8.4 LTS** ([ADR-001](../decisions/ADR-001-mysql.md) — 8.0 đã hết hỗ trợ 30/04/2026) |
| `APP_URL` | URL backend (Admin + Store API) |
| `ACME_FRONTEND_URL` | Domain frontend — migration `AcmeVietnam` tạo domain external storefront cho channel `Headless` |
| `MAILER_DSN` | SMTP thật. Local dùng Mailpit (`smtp://127.0.0.1:1025`) |

Chạy nền (bắt buộc — sitemap, email, chỉ mục chạy qua hàng đợi):

```bash
bin/console messenger:consume async low_priority --time-limit=300   # supervisor / systemd, tự khởi động lại
bin/console scheduled-task:run --time-limit=300                     # sitemap mỗi giờ, dọn Mail Archive…
```

## 3. Frontend — build và chạy

```bash
pnpm install --frozen-lockfile        # gốc repo
pnpm build                            # → frontend/.output
node frontend/.output/server/index.mjs
```

Biến môi trường (không commit `frontend/.env`):

| Biến | Ghi chú |
|---|---|
| `NUXT_PUBLIC_SHOPWARE_ENDPOINT` | URL Store API công khai, có `/store-api/` ở cuối |
| `NUXT_PUBLIC_SHOPWARE_ACCESS_TOKEN` | Access key của channel `Headless` |
| `NUXT_SHOPWARE_ENDPOINT` | (tuỳ chọn) Store API nội bộ cho server Nitro — sitemap / robots gọi qua đây |

## 4. Sau mỗi lần deploy

```bash
pnpm test:e2e     # E2E_BASE_URL=https://<domain> — CHỈ trên staging: test đặt đơn thật
```

Trên production chỉ kiểm tay: trang chủ, một trang sản phẩm, `/robots.txt`, `/sitemap.xml`; trong Administration: màn hình của FroshTools (sức khoẻ hệ thống, hàng đợi, scheduled task) và Mail Archive (email vừa gửi).

## Liên quan

[06-golive.md](../06-golive.md) · [00-backup.md](00-backup.md) · [15-headless.md](../15-headless.md) mục 7, 9 · [16-khao-sat-nguon-mo.md](../16-khao-sat-nguon-mo.md)
