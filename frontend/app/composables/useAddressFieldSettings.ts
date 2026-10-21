import type { MaybeRefOrGetter } from "vue";

/**
 * Trường địa chỉ nào hiện / bắt buộc — theo CẤU HÌNH của Shopware, không gán cứng trong
 * form (template gán cứng theo thói quen châu Âu):
 *
 *  - Số điện thoại: Administration → Settings → Login & registration
 *    (`/store-api/shop-settings` → loginRegistration). Shipper VN cần SĐT để giao hàng.
 *  - Mã bưu chính: theo quốc gia (`country.postalCodeRequired`) — VN không bắt buộc
 *    (Phase 0), khách VN hầu như không biết mã bưu chính của mình.
 *
 * Backend vẫn tự kiểm (AddressValidationFactory) — đây chỉ để form báo lỗi sớm, đúng luật.
 */
export function useAddressFieldSettings(
  countryId: MaybeRefOrGetter<string | null | undefined>,
) {
  const { getCountries } = useCountries();
  // Cùng một nguồn với tên shop, số sản phẩm/trang… (useShopSettings) — một request, một cache
  const { settings: shopSettings } = useShopSettings();
  const settings = computed(() => shopSettings.value?.loginRegistration);

  // Mặc định hiện ô SĐT trong lúc chờ cấu hình — tránh form nhảy bố cục
  const showPhoneNumber = computed(
    () => settings.value?.showPhoneNumberField ?? true,
  );
  const phoneNumberRequired = computed(
    () =>
      showPhoneNumber.value &&
      (settings.value?.phoneNumberFieldRequired ?? false),
  );
  const postalCodeRequired = computed(
    () =>
      getCountries.value.find((country) => country.id === toValue(countryId))
        ?.postalCodeRequired ?? false,
  );

  return { showPhoneNumber, phoneNumberRequired, postalCodeRequired };
}
