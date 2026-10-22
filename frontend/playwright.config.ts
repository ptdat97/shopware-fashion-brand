import { defineConfig, devices } from "@playwright/test";

/**
 * Test e2e cho frontend headless, chạy trên dữ liệu thật của backend local (Store API +
 * DB `shopware`). Hướng dẫn: docs/15-headless.md mục 9.
 *
 *   pnpm test:e2e              (gốc repo — tự bật `pnpm dev` nếu chưa chạy)
 *   pnpm test:e2e --ui         chế độ xem từng bước
 *
 * Mặc định dùng Chrome cài sẵn trên máy (không tải trình duyệt). CI: đặt
 * PLAYWRIGHT_CHANNEL="" và chạy `pnpm exec playwright install chromium` trước.
 *
 * `nuxi typecheck` không quét file này và `tests/` — kiểm kiểu riêng: `pnpm typecheck:e2e`
 * (trong frontend/, cấu hình ở tests/e2e/tsconfig.json).
 */
try {
  // Access key + endpoint Store API (file không vào git)
  process.loadEnvFile(".env");
} catch {
  // CI truyền qua biến môi trường
}

const baseURL = process.env.E2E_BASE_URL ?? "http://localhost:3000";
const channel = process.env.PLAYWRIGHT_CHANNEL ?? "chrome";

export default defineConfig({
  testDir: "tests/e2e",
  // Đặt đơn thật, trừ tồn kho thật — chạy tuần tự để dễ đọc log và không tranh tồn kho
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [["list"], ["html", { open: "never" }]],
  use: {
    baseURL,
    locale: "vi-VN",
    timezoneId: "Asia/Ho_Chi_Minh",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "desktop",
      use: { ...devices["Desktop Chrome"], channel: channel || undefined },
    },
    {
      // Khổ điện thoại thật của khách VN — chỉ chạy test gắn @mobile
      name: "mobile",
      grep: /@mobile/,
      use: {
        ...devices["Desktop Chrome"],
        channel: channel || undefined,
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
      },
    },
  ],
  webServer: {
    command: "pnpm dev",
    url: baseURL,
    reuseExistingServer: true,
    timeout: 180_000,
  },
});
