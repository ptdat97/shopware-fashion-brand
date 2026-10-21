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
  const { required, minLength, requiredIf } = customValidators();

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
    street: {
      required,
      minLength: minLength(3),
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
