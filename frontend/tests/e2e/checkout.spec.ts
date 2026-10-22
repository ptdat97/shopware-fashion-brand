import { expect, test, type Page } from "@playwright/test";

import { gotoHydrated } from "./support/page";
import {
  findInStockVariants,
  onePerBrand,
  prepareCart,
} from "./support/storeApi";

/**
 * Đặt đơn thật với tư cách khách vãng lai (H6, H8, ADR-006). Mỗi lần chạy tạo đơn trong DB
 * local và trừ tồn kho — email dạng e2e-…@example.com để lọc ra khi cần dọn.
 */
const digits = (text: string) => text.replace(/\D/g, "");

async function fillGuestForm(page: Page, province: string) {
  await page.locator("#email").fill(`e2e-${Date.now()}@example.com`);
  await page.locator("#first-name").fill("Bình");
  await page.locator("#last-name").fill("Trần");
  await page.locator("#phone-number").fill("0987654321");
  await page.locator("#street").fill("45 Ngõ Thử, Phường Thử");
  await page.locator("#city").fill("Quận Thử");
  await page.locator("#state").selectOption({ label: province });
}

async function choosePayment(page: Page, name: string) {
  await page.locator("label").filter({ hasText: name }).first().click();
  await expect(
    page
      .locator("label")
      .filter({ hasText: name })
      .first()
      .locator("input[type=radio]"),
  ).toBeChecked();
}

async function placeOrder(page: Page): Promise<string> {
  await page.getByRole("button", { name: "Xác nhận và đặt hàng" }).click();
  await page.waitForURL(/\/checkout\/success\//, { timeout: 30_000 });
  const success = page.getByTestId("checkout-success-page");
  await expect(success).toBeVisible();
  const orderNumber = (await success.innerText()).match(/#(\d+)/)?.[1];
  expect(orderNumber, "trang cảm ơn phải có mã đơn").toBeTruthy();
  return orderNumber!;
}

test.describe("Thanh toán", () => {
  test("COD, giỏ hai thương hiệu, Hà Nội — không có khối chuyển khoản", async ({
    page,
    context,
    request,
    baseURL,
  }) => {
    const variants = onePerBrand(await findInStockVariants(request), 2);
    await prepareCart(request, context, baseURL!, variants);

    await gotoHydrated(page, "/checkout");
    await fillGuestForm(page, "Hà Nội");
    await choosePayment(page, "Thanh toán khi nhận hàng");
    const totalBefore = digits(
      await page.getByTestId("cart-total").innerText(),
    );

    await placeOrder(page);

    expect(digits(await page.getByTestId("order-total").innerText())).toBe(
      totalBefore,
    );
    await expect(page.getByTestId("checkout-success-page")).toContainText(
      "0987654321",
    );
    await expect(page.getByTestId("checkout-success-page")).toContainText(
      "Thanh toán khi nhận hàng",
    );
    await expect(page.getByTestId("bank-transfer")).toHaveCount(0);
  });

  test("Chuyển khoản trước — thông tin chuyển khoản từ backend @mobile", async ({
    page,
    context,
    request,
    baseURL,
  }) => {
    const [variant] = await findInStockVariants(request);
    await prepareCart(request, context, baseURL!, [variant!]);

    await gotoHydrated(page, "/checkout");
    await fillGuestForm(page, "Hà Nội");
    await choosePayment(page, "Chuyển khoản trước");
    const orderNumber = await placeOrder(page);

    await expect(page.getByTestId("checkout-success-page")).toContainText(
      "Chuyển khoản trước",
    );
    const bank = page.getByTestId("bank-transfer");
    await expect(bank).toBeVisible();
    await expect(bank).toContainText("Thông tin chuyển khoản");

    // Chưa nhập tài khoản nhận trong Admin → chỉ hướng dẫn; đã nhập → số TK, nội dung CK, số tiền
    if (await page.getByTestId("bank-transfer-reference").count()) {
      await expect(page.getByTestId("bank-transfer-reference")).toHaveText(
        new RegExp(`${orderNumber}$`),
      );
      expect(
        digits(await page.getByTestId("bank-transfer-amount").innerText()),
      ).toBe(digits(await page.getByTestId("order-total").innerText()));
    } else {
      await expect(bank).toContainText("Cửa hàng sẽ liên hệ");
    }

    // Khổ điện thoại: không tràn ngang
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(overflow).toBeLessThanOrEqual(0);
  });

  test("chọn thanh toán rồi đặt hàng ngay khi mạng chậm — đơn giữ đúng phương thức", async ({
    page,
    context,
    request,
    baseURL,
  }) => {
    // Hồi quy 22/10/2026: PATCH /context chọn phương thức chưa xong mà đăng ký khách đã chạy
    // → ngữ cảnh mới mang COD, đơn thành COD. Làm chậm PATCH đó 1,5 giây để tái hiện chắc chắn.
    const [variant] = await findInStockVariants(request);
    await prepareCart(request, context, baseURL!, [variant!]);
    await page.route("**/store-api/context", async (route) => {
      if (
        route.request().method() === "PATCH" &&
        route.request().postData()?.includes("paymentMethodId")
      ) {
        await new Promise((resolve) => setTimeout(resolve, 1_500));
      }
      await route.continue();
    });

    await gotoHydrated(page, "/checkout");
    await fillGuestForm(page, "Hà Nội");
    await page
      .locator("label")
      .filter({ hasText: "Chuyển khoản trước" })
      .first()
      .click();
    await placeOrder(page);

    await expect(page.getByTestId("checkout-success-page")).toContainText(
      "Chuyển khoản trước",
    );
    await expect(page.getByTestId("bank-transfer")).toBeVisible();
  });

  test("phí ship theo tỉnh trước khi đặt (H8)", async ({
    page,
    context,
    request,
    baseURL,
  }) => {
    const cheap = (await findInStockVariants(request)).find(
      (variant) => variant.price < 500_000,
    );
    test.skip(
      !cheap,
      "Không có sản phẩm dưới ngưỡng miễn ship 500.000 ₫ — mọi đơn đều miễn ship",
    );
    await prepareCart(request, context, baseURL!, [cheap!]);

    await gotoHydrated(page, "/checkout");
    await fillGuestForm(page, "Hà Nội");
    await expect(page.getByTestId("cart-total")).toHaveText(
      new RegExp(`${(cheap!.price + 25_000).toLocaleString("vi-VN")}`),
    );

    await page.locator("#state").selectOption({ label: "Đà Nẵng" });
    await expect(page.getByTestId("cart-total")).toHaveText(
      new RegExp(`${(cheap!.price + 35_000).toLocaleString("vi-VN")}`),
    );
  });

  test("mã khuyến mãi sai — câu báo lỗi của backend", async ({
    page,
    context,
    request,
    baseURL,
  }) => {
    const [variant] = await findInStockVariants(request);
    await prepareCart(request, context, baseURL!, [variant!]);

    await gotoHydrated(page, "/checkout/cart");
    await page.getByTestId("promotion-code-input").fill("SAI123");
    await page.getByTestId("promotion-code-submit").click();
    await expect(
      page.getByTestId("notification-element-message").first(),
    ).toContainText("SAI123");
  });
});
