import { useRegle } from "@regle/core";
import type { Regle } from "@regle/core";

import type { Schemas } from "#shopware";

import { customValidators } from "../../i18n/utils/i18n-validators";

type CheckoutBillingAddress = Omit<
  Schemas["CustomerAddress"],
  "id" | "customerId"
>;

type CheckoutBaseInfo = {
  email: string;
  password: string;
};

interface UseTemplateCheckoutReturn {
  selectedShippingMethod: Ref<string | null>;
  selectedPaymentMethod: Ref<string | null>;
  createAccount: Ref<boolean>;
  countryHasStates: Ref<boolean>;
  billingAddress: Ref<CheckoutBillingAddress>;
  canPlaceOrder: ComputedRef<boolean>;
  $vBillingAddress: Regle<CheckoutBillingAddress>["r$"];
  $vBaseInfo: Regle<CheckoutBaseInfo>["r$"];
  customerBaseInfo: Ref<CheckoutBaseInfo>;
}

export function useTemplateCheckout(): UseTemplateCheckoutReturn {
  const { required, minLength, email, requiredIf } = customValidators();

  const selectedShippingMethod = ref<string | null>(null);
  const selectedPaymentMethod = ref<string | null>(null);

  const createAccount = ref(false);
  const countryHasStates = ref(false);

  const billingAddress = ref<CheckoutBillingAddress>({
    firstName: "",
    lastName: "",
    street: "",
    zipcode: "",
    city: "",
    countryId: "",
    countryStateId: "",
    phoneNumber: "",
  });

  const { passwordMinLength } = useShopSettings();
  const { phoneNumberRequired, postalCodeRequired } = useAddressFieldSettings(
    () => billingAddress.value.countryId,
  );

  const customerBaseInfo = ref<CheckoutBaseInfo>({
    email: "",
    password: "",
  });

  const canPlaceOrder = computed(
    () => !!(selectedShippingMethod.value && selectedPaymentMethod.value),
  );

  // Tên/họ chỉ bắt buộc, không giới hạn độ dài tối thiểu như template: họ Việt 2 ký tự
  // (Lê, Lý, Hồ, Võ, Vũ, Đỗ…) và tên như "An" phải đặt hàng được. Backend cũng chỉ NotBlank.
  const { r$: $vBillingAddress } = useRegle(billingAddress, () => ({
    firstName: {
      required,
    },
    lastName: {
      required,
    },
    // Chỉ bắt buộc — backend không đặt độ dài tối thiểu cho địa chỉ (quy tắc cứng số 8)
    street: {
      required,
    },
    zipcode: {
      required: requiredIf(() => postalCodeRequired.value),
    },
    city: {
      required,
    },
    countryId: {
      required,
    },
    countryStateId: {
      required: requiredIf(() => countryHasStates.value),
    },
    phoneNumber: {
      required: requiredIf(() => phoneNumberRequired.value),
    },
  }));

  const { r$: $vBaseInfo } = useRegle(customerBaseInfo, () => ({
    email: {
      required,
      email,
    },
    password: createAccount.value
      ? {
          required,
          // Độ dài tối thiểu do backend cấu hình (Settings → Login & registration)
          minLength: minLength(passwordMinLength.value),
        }
      : {},
  }));

  watch(createAccount, (shouldCreateAccount) => {
    if (shouldCreateAccount) return;
    customerBaseInfo.value.password = "";
    $vBaseInfo.password.$reset();
  });

  return {
    selectedShippingMethod,
    selectedPaymentMethod,
    createAccount,
    countryHasStates,
    billingAddress,
    canPlaceOrder,
    $vBillingAddress,
    $vBaseInfo,
    customerBaseInfo,
  };
}
