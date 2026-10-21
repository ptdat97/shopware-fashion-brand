/**
 * Đường dẫn SEO canonical của sản phẩm (route headless `store-api.product.detail`).
 * Gọi lúc setup (cần ngữ cảnh Shopware), dùng hàm trả về trong useAsyncData.
 * Hàm trả null khi sản phẩm chưa có URL (chưa hiển thị trên channel, chưa lập chỉ mục).
 */
export function useProductSeoPath() {
  const { apiClient } = useShopwareContext();

  async function findProductSeoPath(productId: string): Promise<string | null> {
    const { data } = await apiClient.invoke("readSeoUrl post /seo-url", {
      body: {
        limit: 1,
        filter: [
          {
            type: "equals",
            field: "routeName",
            value: "store-api.product.detail",
          },
          { type: "equals", field: "foreignKey", value: productId },
          { type: "equals", field: "isCanonical", value: true },
        ],
      },
    });

    const seoUrl = data.elements?.[0];
    return seoUrl ? `/${seoUrl.seoPathInfo}` : null;
  }

  return { findProductSeoPath };
}
