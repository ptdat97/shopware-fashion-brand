import { type Ref, computed } from "vue";

import { customValidators } from "#imports";

import type { AddressFieldFlags } from "./addressFormRules";

export function registrationFormRules(
  accountType: Ref<string>,
  countryId: Ref<string>,
  countryHasStates: Ref<boolean>,
  fields: AddressFieldFlags,
) {
  const { required, minLength, email, requiredIf } = customValidators();

  return computed(() => ({
    accountType: {
      required,
    },
    // Chỉ bắt buộc — họ Việt 2 ký tự (Lê, Lý, Hồ…) phải đăng ký được
    firstName: {
      required,
    },
    lastName: {
      required,
    },
    email: {
      required,
      email,
    },
    password: {
      required,
      minLength: minLength(8),
    },
    billingAddress: {
      company: {
        required: requiredIf(() => unref(accountType) === "business"),
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
          return !!unref(countryId) && countryHasStates.value;
        }),
      },
      phoneNumber: {
        required: requiredIf(() => fields.phoneNumberRequired.value),
      },
    },
  }));
}
