# MCP & công cụ AI cho phát triển

Tài liệu này nói về hai thứ khác nhau, đừng nhầm lẫn:

| | Dùng để làm gì | Chạy ở đâu |
|---|---|---|
| **MCP server của Shopware** | Cho AI đọc/ghi **dữ liệu shop** (sản phẩm, đơn hàng, cấu hình) | Trong chính Shopware, endpoint `/api/_mcp` |
| **Plugin Claude Code của Shopware** | Cho AI dùng **công cụ dev** (PHPStan, PHPUnit, ECS) | Trên máy lập trình viên |

---

## Phần 1 — MCP server của Shopware

### Có sẵn trong core, không cần cài gì

Đã kiểm chứng trên chính dự án này (Shopware 6.7.12.2):

```
vendor/shopware/core/Framework/Mcp/                    ← code MCP server
vendor/shopware/core/Framework/Mcp/Controller/McpServerController.php:72   → /api/_mcp
vendor/shopware/core/Framework/Mcp/Controller/StoreApiMcpServerController.php:59 → /store-api/_mcp
```

> ⚠️ **Đang là tính năng thử nghiệm.** Feature flag `MCP_SERVER`, mặc định **tắt**, đánh dấu `@experimental stableVersion:v6.8.0` trong core. Nghĩa là API còn có thể đổi trước khi ổn định.

### Bật lên

**Bước 1** — bật feature flag trong `.env.local`:

```bash
MCP_SERVER=1
```

Không bật thì endpoint trả **404**, không phải lỗi cấu hình (xem `McpServerController::handle()` — kiểm tra `Feature::isActive('MCP_SERVER')` đầu tiên).

**Bước 2** — tạo integration lấy khoá:

```bash
php bin/console integration:create "Claude Code" --admin
```

Lệnh in ra `SHOPWARE_ACCESS_KEY_ID` (dạng `SWIA...`) và `SHOPWARE_SECRET_ACCESS_KEY`. **Secret chỉ hiện một lần.**

**Bước 3** — tạo `.mcp.json` ở gốc dự án:

```json
{
  "mcpServers": {
    "shopware": {
      "type": "http",
      "url": "http://shopware-fashion-brand.test/api/_mcp",
      "headers": {
        "sw-access-key": "SWIA...",
        "sw-secret-access-key": "..."
      }
    }
  }
}
```

> Claude Code cần `"type": "http"` — **không phải** `"streamable-http"` như một số client khác.

**Bước 4** — khởi động lại Claude Code.

### Kiểm tra

```bash
# Xem MCP đăng ký được những gì
php bin/console debug:mcp
```

### 🔴 Bảo mật — đọc kỹ trước khi bật

| Rủi ro | Xử lý |
|---|---|
| **`--admin` là toàn quyền** | Integration này đọc/sửa/xoá được mọi thứ kể cả đơn hàng và khách hàng. Chỉ tạo trên máy dev |
| **Secret lộ qua git** | `.mcp.json` chứa secret dạng plaintext → **bắt buộc** thêm vào `.gitignore` |
| **Bật nhầm trên production** | Tính năng còn experimental. **Không bật `MCP_SERVER=1` trên production** cho tới khi Shopware đánh dấu stable (dự kiến 6.8.0) |

Thêm vào `.gitignore` trước khi tạo file:

```
.mcp.json
```

Nếu lỡ commit secret: `php bin/console integration:create` lại để sinh khoá mới, và xoá integration cũ trong Administration.

---

## Phần 2 — Plugin Claude Code chính thức của Shopware

Shopware phát hành marketplace plugin cho Claude Code tại [shopwareLabs/ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools).

### Cài

```
/plugin marketplace add shopwareLabs/ai-coding-tools
/plugin install dev-tooling@shopware-ai-coding-tools
```

Khởi động lại Claude Code sau khi cài.

### Vì sao dự án này cần

Dự án **chưa có công cụ test/lint nào** — đã kiểm tra, không có PHPUnit, PHPStan hay CS-Fixer (`vendor/phpstan` và `vendor/friendsofphp` chỉ là phụ thuộc gián tiếp của Symfony, không phải công cụ dùng được).

Trong khi đó [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md) dành cả một mục quy định cách viết test. **Quy tắc không chạy được là quy tắc chết.**

`dev-tooling` gói sẵn PHPStan, ECS, PHPUnit, Rector mà **không cần đụng `composer.json` của dự án** — phù hợp với [Quy tắc số 2](08-quy-tac-phat-trien.md).

### Các plugin khác trong marketplace

| Plugin | Dùng khi |
|---|---|
| `dev-tooling` | PHPStan, ECS, PHPUnit, Rector — **nên cài trước tiên** |
| `test-writing` | Sinh và kiểm tra PHPUnit test cho Shopware 6 |
| `contributor-writing` | Viết ADR, commit message, PR description |
| `plugin-setup` | Trợ lý cấu hình các plugin còn lại |

> Marketplace này do Shopware Labs phát hành, hướng tới người đóng góp cho core. Một số plugin (`ci-failure-interpretation`, `code-contribution-analysis`) gắn với quy trình CI của repo `shopware/shopware` — **không dùng được cho dự án riêng như dự án này**.

---

## Liên quan

- Quy tắc viết code: [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md), [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md)
- Tạo plugin mới: [runbook/06-tao-plugin.md](runbook/06-tao-plugin.md)
- Tài liệu chính thức: [MCP Server — Shopware Documentation](https://developer.shopware.com/docs/products/tools/mcp-server/)
