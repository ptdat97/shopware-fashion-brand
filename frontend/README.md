# frontend — storefront headless (Nuxt 4)

Dựng từ [Vue Starter Template](https://developer.shopware.com/frontends/introduction/templates/vue-starter-template.html) của Shopware Frontends, gọi Store API của `../backend`.

- Chạy, access key, công nghệ, việc còn lại: [docs/15-headless.md](../docs/15-headless.md)
- Quyết định kiến trúc: [ADR-005](../docs/decisions/ADR-005-headless-monorepo.md)

```bash
# ở gốc repo
pnpm install
cp frontend/.env.template frontend/.env   # điền access key channel Headless
pnpm dev                                  # http://localhost:3000
pnpm lint && pnpm typecheck && pnpm build
```

| Thư mục                       | Nội dung                                                   |
| ----------------------------- | ---------------------------------------------------------- |
| `app/assets/css/tailwind.css` | Toàn bộ cấu hình Tailwind v4: token màu, tương thích layer |
| `tailwind/carbon-icons.mjs`   | Plugin icon `i-carbon-*`                                   |
| `app/components/cms/`         | Override component của `@shopware/cms-base-layer`          |
| `app/components/global/`      | Component trang theo SEO URL (`FrontendDetailPage`…)       |
| `i18n/vi-VN/`                 | Bản dịch — ngôn ngữ duy nhất                               |
