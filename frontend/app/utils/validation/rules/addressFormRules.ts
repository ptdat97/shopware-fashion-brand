import { type Ref, computed } from "vue";

import { customValidators } from "#imports";

/** Trường theo cấu hình Shopware — xem useAddressFieldSettings */
export type AddressFieldFlags = {
  phoneNumberRequired: Ref<boolean>;
  postalCodeRequired: Ref<boolean>;
};

export function addressFormRules(
  countryHasStates: Ref<boolean>,
  fields: AddressFieldFlags,
) {
  const { required, requiredIf } = customValidators();

  return computed(() => ({
    salutationId: {
      required,
    },
    // Chỉ bắt buộc — tên/họ Việt có thể rất ngắn (backend cũng chỉ NotBlank)
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
      required: requiredIf(() => fields.postalCodeRequired.value),
    },
    city: {
      required,
    },
    countryId: {
      required,
    },
    countryStateId: {
      required: requiredIf(() => {
        return countryHasStates.value;
      }),
    },
    phoneNumber: {
      required: requiredIf(() => fields.phoneNumberRequired.value),
    },
  }));
}
