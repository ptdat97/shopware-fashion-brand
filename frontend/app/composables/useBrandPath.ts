/**
 * Đường dẫn trang thương hiệu (`/brands/{tên}`) của một nhà sản xuất — SEO URL native
 * của route headless `store-api.acme.brand.detail` (plugin AcmeBrand). Không tự ghép
 * slug từ tên: tên đổi / brand tắt thì core tự cập nhật / gỡ URL.
 *
 * Trả null khi không có URL (brand tắt, chưa lập chỉ mục) — chỗ gọi tự quyết hiển thị.
 */
export function useBrandPath(manufacturerId: string) {
  const { apiClient } = useShopwareContext();

  return useAsyncData(`acmeBrandPath-${manufacturerId}`, async () => {
    const { data } = await apiClient.invoke("readSeoUrl post /seo-url", {
      body: {
        limit: 1,
        filter: [
          {
            type: "equals",
            field: "routeName",
            value: "store-api.acme.brand.detail",
          },
          { type: "equals", field: "foreignKey", value: manufacturerId },
          { type: "equals", field: "isCanonical", value: true },
        ],
      },
    });

    const seoUrl = data.elements?.[0];
    return seoUrl ? `/${seoUrl.seoPathInfo}` : null;
  });
}
