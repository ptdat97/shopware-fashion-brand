import type { APIRequestContext, BrowserContext } from "@playwright/test";

/**
 * Gọi Store API của backend local như frontend (header sw-access-key). Dùng để chuẩn bị
 * dữ liệu (tìm biến thể còn hàng, dựng giỏ) — phần cần kiểm chứng vẫn đi qua giao diện.
 */
/** URL Store API — NUXT_PUBLIC_SHOPWARE_ENDPOINT đã gồm "/store-api/" */
export function storeApiUrl(path: string): string {
  const endpoint = process.env.NUXT_PUBLIC_SHOPWARE_ENDPOINT;
  if (!endpoint)
    throw new Error("Thiếu NUXT_PUBLIC_SHOPWARE_ENDPOINT (frontend/.env)");
  return `${endpoint.replace(/\/$/, "")}/${path.replace(/^\//, "")}`;
}

export const headers = (contextToken?: string) => ({
  "sw-access-key": process.env.NUXT_PUBLIC_SHOPWARE_ACCESS_TOKEN ?? "",
  "sw-include-seo-urls": "true",
  accept: "application/json",
  ...(contextToken ? { "sw-context-token": contextToken } : {}),
});

/** /store-api/shop-settings — gồm extensions.acmeHeadless (logo, robots) khi plugin bật */
export async function getShopSettings(request: APIRequestContext) {
  const response = await request.get(storeApiUrl("shop-settings"), {
    headers: headers(),
  });
  if (!response.ok())
    throw new Error(`Store API /shop-settings: ${response.status()}`);
  return response.json();
}

export type InStockVariant = {
  id: string;
  productNumber: string;
  manufacturerId: string;
  price: number;
  /** Đường dẫn SEO canonical, dạng "/dam-…/anv-1001-02" */
  path: string;
};

/**
 * Biến thể đang bán, còn ít nhất `minStock` — chọn lúc chạy test thay vì gán cứng SKU
 * (tồn kho giảm sau mỗi lần đặt đơn thử).
 */
export async function findInStockVariants(
  request: APIRequestContext,
  minStock = 3,
): Promise<InStockVariant[]> {
  const response = await request.post(storeApiUrl("product"), {
    headers: headers(),
    data: {
      limit: 50,
      filter: [
        {
          type: "range",
          field: "availableStock",
          parameters: { gte: minStock },
        },
        {
          type: "not",
          queries: [{ type: "equals", field: "parentId", value: null }],
        },
      ],
      sort: [{ field: "productNumber", order: "ASC" }],
    },
  });
  if (!response.ok())
    throw new Error(`Store API /product: ${response.status()}`);

  const { elements } = (await response.json()) as {
    elements: Array<{
      id: string;
      productNumber: string;
      manufacturerId: string;
      calculatedPrice: { totalPrice: number };
      seoUrls?: Array<{ seoPathInfo: string; isCanonical: boolean }>;
    }>;
  };

  return elements.flatMap((product) => {
    const seo =
      product.seoUrls?.find((url) => url.isCanonical) ?? product.seoUrls?.[0];
    return seo
      ? [
          {
            id: product.id,
            productNumber: product.productNumber,
            manufacturerId: product.manufacturerId,
            price: product.calculatedPrice.totalPrice,
            path: `/${seo.seoPathInfo}`,
          },
        ]
      : [];
  });
}

/** Mỗi brand một biến thể (đơn nhiều brand — MB5) */
export function onePerBrand(
  variants: InStockVariant[],
  count: number,
): InStockVariant[] {
  const seen = new Set<string>();
  const picked = variants.filter((variant) => {
    if (seen.has(variant.manufacturerId)) return false;
    seen.add(variant.manufacturerId);
    return true;
  });
  if (picked.length < count)
    throw new Error(`Cần ${count} brand còn hàng, chỉ có ${picked.length}`);
  return picked.slice(0, count);
}

/**
 * Giỏ hàng mới với các biến thể đã chọn, gắn vào trình duyệt qua cookie `sw-context-token`
 * (cookie @shopware/nuxt-module đọc) — trang /checkout mở ra là có hàng.
 */
export async function prepareCart(
  request: APIRequestContext,
  context: BrowserContext,
  baseURL: string,
  variants: InStockVariant[],
): Promise<string> {
  const contextResponse = await request.get(storeApiUrl("context"), {
    headers: headers(),
  });
  const token = (await contextResponse.json()).token as string;

  const cart = await request.post(storeApiUrl("checkout/cart/line-item"), {
    headers: headers(token),
    data: {
      items: variants.map((variant) => ({
        id: variant.id,
        referencedId: variant.id,
        type: "product",
        quantity: 1,
      })),
    },
  });
  if (!cart.ok()) throw new Error(`Không thêm được vào giỏ: ${cart.status()}`);

  await context.addCookies([
    { name: "sw-context-token", value: token, url: baseURL },
  ]);
  return token;
}
