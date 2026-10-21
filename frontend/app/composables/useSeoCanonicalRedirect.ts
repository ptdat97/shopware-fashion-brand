import type { Schemas } from "#shopware";

type ResolvedSeoUrl = Partial<
  Pick<Schemas["SeoUrl"], "seoPathInfo" | "pathInfo" | "foreignKey">
> | null;

/**
 * Đích chuyển hướng 301 về URL canonical — tương đương `core.seo.redirectToCanonicalUrl`
 * mà storefront Twig có sẵn, frontend headless phải tự làm:
 *
 *  - URL đúng trang nhưng khác chữ hoa/thường (`/Dam/` → `/dam/`): MySQL so khớp không
 *    phân biệt hoa thường nên resolver vẫn tra ra — chuyển về đúng bản canonical.
 *  - URL cũ không còn canonical (`/Bo-suu-tap/Sale/` → `/collections/sale`): core giữ bản
 *    ghi cũ với is_canonical = NULL. Route seo-url của Store API mặc định chỉ trả URL
 *    canonical, trừ khi request tự đặt điều kiện isCanonical (SalesChannelSeoUrlDefinition).
 *
 * Trả null khi không cần chuyển hướng (hoặc không tìm thấy — chỗ gọi trả 404 như cũ).
 */
export function useSeoCanonicalRedirect() {
  const { apiClient } = useShopwareContext();

  async function findSeoUrl(filter: Record<string, unknown>[]) {
    const { data } = await apiClient.invoke("readSeoUrl post /seo-url", {
      body: {
        limit: 1,
        filter: filter as Schemas["Criteria"]["filter"],
      },
    });
    return data.elements?.[0] ?? null;
  }

  async function resolveRedirect(
    routePath: string,
    resolved: ResolvedSeoUrl,
  ): Promise<string | null> {
    const requested = routePath.replace(/^\//, "");

    if (resolved?.seoPathInfo) {
      return resolved.seoPathInfo === requested
        ? null
        : `/${resolved.seoPathInfo}`;
    }

    // Đã có trang (từ history.state khi điều hướng phía client) — không tra thêm
    if (resolved?.foreignKey) return null;

    const previous = await findSeoUrl([
      { type: "equals", field: "seoPathInfo", value: requested },
      { type: "equals", field: "isCanonical", value: null },
    ]);
    if (!previous?.pathInfo) return null;

    const canonical = await findSeoUrl([
      { type: "equals", field: "pathInfo", value: previous.pathInfo },
      { type: "equals", field: "isCanonical", value: true },
    ]);
    return canonical ? `/${canonical.seoPathInfo}` : null;
  }

  return { resolveRedirect };
}
