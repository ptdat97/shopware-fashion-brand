/**
 * Cấu hình shop do backend cung cấp (`/store-api/shop-settings`) — một lần, dùng chung.
 * Quy tắc cứng số 8: tên shop, số sản phẩm/trang, quy tắc form… lấy ở đây, không gán
 * cứng trong frontend. app.vue chờ dữ liệu này khi render SSR, các chỗ khác đọc lại cache.
 */
export function useShopSettings() {
  const { apiClient } = useShopwareContext();

  const result = useAsyncData("shopSettings", async () => {
    const { data } = await apiClient.invoke(
      "getShopSettings get /shop-settings",
    );
    return data;
  });

  const settings = result.data;

  return {
    settings,
    /** Administration → Settings → Basic information → Tên shop */
    shopName: computed(() => settings.value?.general?.shopName ?? ""),
    /** Administration → Settings → Products → Số sản phẩm mỗi trang */
    productsPerPage: computed(
      () => settings.value?.listing?.productsPerPage || undefined,
    ),
    /** Administration → Extensions → Acme Frontend headless → Logo (null: chưa có / plugin tắt) */
    logo: computed(
      () => settings.value?.extensions?.acmeHeadless?.logo ?? null,
    ),
    /** Settings → Login & registration → Độ dài mật khẩu tối thiểu (0: chưa tải — không kiểm) */
    passwordMinLength: computed(
      () => settings.value?.loginRegistration?.passwordMinLength ?? 0,
    ),
    /** Settings → Cart → Bật danh sách yêu thích (chưa tải → ẩn, tránh hiện rồi biến mất) */
    wishlistEnabled: computed(
      () => settings.value?.cart?.wishlistEnabled ?? false,
    ),
    ready: result,
  };
}
