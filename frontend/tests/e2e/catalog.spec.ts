import { expect, test } from "@playwright/test";

import { gotoHydrated } from "./support/page";
import { findInStockVariants, getShopSettings } from "./support/storeApi";

test.describe("Xem hàng", () => {
  test("logo lấy từ backend (ảnh trong Media hoặc tên shop)", async ({
    page,
    request,
  }) => {
    const settings = await getShopSettings(request);

    await gotoHydrated(page, "/");
    const logo = settings.extensions?.acmeHeadless?.logo;
    if (logo?.url) {
      const image = page.locator("header [data-testid=header-logo-image]");
      await expect(image).toBeVisible();
      expect(
        await image.evaluate((img: HTMLImageElement) => img.naturalWidth),
      ).toBeGreaterThan(0);
    } else {
      await expect(page.locator("header")).toContainText(
        settings.general.shopName,
        { ignoreCase: true },
      );
    }
  });

  test("danh mục có thẻ sản phẩm và ảnh thứ hai khi rê chuột (H5, AcmeHeadless)", async ({
    page,
  }) => {
    await gotoHydrated(page, "/dam/");
    const cards = page.locator("[data-testid=product-box-img]");
    await expect(cards.first()).toBeVisible();

    const hover = page.locator("[data-testid=product-box-img-hover]");
    expect(await hover.count(), "listing phải nạp media.media").toBeGreaterThan(
      0,
    );

    await cards.first().hover();
    await expect(hover.first()).toHaveCSS("opacity", "1");
  });

  test("trang thương hiệu: danh sách → chi tiết có sản phẩm (H2)", async ({
    page,
  }) => {
    await gotoHydrated(page, "/brands");
    const firstBrand = page.locator("main a[href*='/brands/']").first();
    const name = (await firstBrand.innerText()).split("\n")[0]!.trim();

    await firstBrand.click();
    await expect(page).toHaveURL(/\/brands\/[a-z0-9-]+$/);
    await expect(page.locator("h1")).toContainText(name, { ignoreCase: true });
    await expect(
      page.locator("[data-testid=product-box-img]").first(),
    ).toBeVisible();
  });

  test("thêm biến thể còn hàng vào giỏ từ trang sản phẩm", async ({
    page,
    request,
  }) => {
    const [variant] = await findInStockVariants(request);
    test.skip(!variant, "Không còn biến thể nào đủ tồn kho");

    await gotoHydrated(page, variant!.path);
    await page.getByTestId("add-to-cart-button").click();
    await expect(
      page.getByTestId("notification-element-message").first(),
    ).toContainText("đã được thêm vào giỏ hàng");

    await gotoHydrated(page, "/checkout/cart");
    await expect(
      page.getByTestId("checkout-product-tile-item").first(),
    ).toBeVisible();
  });
});
