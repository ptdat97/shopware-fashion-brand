import type { Page } from "@playwright/test";

/**
 * Mở trang và chờ Vue hydrate xong. HTML SSR hiện ra trước khi gắn sự kiện — bấm sớm thì
 * nút "Thêm vào giỏ" không phản ứng. Vue đặt `__vue_app__` lên `#__nuxt` khi mount xong,
 * nhưng component async của trang CMS (buy box) còn hydrate sau đó — chờ thêm tới khi hết
 * request (đo 22/10/2026: chỉ chờ `__vue_app__` thì bấm không gửi request nào).
 */
export async function gotoHydrated(page: Page, path: string): Promise<void> {
  await page.goto(path);
  await page.waitForFunction(() =>
    Boolean(
      (document.querySelector("#__nuxt") as { __vue_app__?: unknown } | null)
        ?.__vue_app__,
    ),
  );
  await page.waitForLoadState("networkidle");
}
