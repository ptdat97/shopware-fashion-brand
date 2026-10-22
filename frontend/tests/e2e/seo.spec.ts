import { expect, test } from "@playwright/test";

import { getShopSettings } from "./support/storeApi";

/** robots.txt và sitemap ở domain frontend (H7, L4) — nội dung do backend quyết. */
test.describe("robots.txt và sitemap", () => {
  test("robots.txt ghép đúng quy tắc của channel từ backend", async ({
    request,
    baseURL,
  }) => {
    const settings = await getShopSettings(request);
    const headless = settings.extensions?.acmeHeadless;
    test.skip(!headless, "Plugin AcmeHeadless đang tắt");

    const robots = await (await request.get("/robots.txt")).text();

    if (!headless.robotsDisableDefaults) {
      expect(robots).toMatch(/^User-agent: \*\nAllow: \//);
    }
    for (const rule of String(headless.robotsRules)
      .split(/\r?\n/)
      .filter(Boolean)) {
      expect(robots).toContain(rule);
    }
    expect(robots).toContain(`Sitemap: ${baseURL}/sitemap.xml`);
  });

  test("sitemap.xml liệt kê file sitemap theo domain frontend", async ({
    request,
    baseURL,
  }) => {
    const response = await request.get("/sitemap.xml");
    expect(response.status()).toBe(200);
    expect(await response.text()).toContain(`<loc>${baseURL}/sitemap/`);
  });
});
