import { type Ref, computed } from "vue";

import { customValidators } from "#imports";

import type { AddressFieldFlags } from "./addressFormRules";

export function registrationFormRules(
  accountType: Ref<string>,
  countryId: Ref<string>,
  countryHasStates: Ref<boolean>,
  fields: AddressFieldFlags,
  /** loginRegistration.passwordMinLength của backend */
  passwordMinLength: Ref<number>,
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
      minLength: minLength(unref(passwordMinLength)),
    },
    billingAddress: {
      company: {
        required: requiredIf(() => unref(accountType) === "business"),
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
          return !!unref(countryId) && countryHasStates.value;
        }),
      },
      phoneNumber: {
        required: requiredIf(() => fields.phoneNumberRequired.value),
      },
    },
  }));
}
