import { customValidators } from "#imports";
import type { operations } from "#shopware";

export function changePasswordFormRules(
  state: Ref<
    operations["changePassword post /account/change-password"]["body"]
  >,
  /** loginRegistration.passwordMinLength của backend */
  passwordMinLength: Ref<number>,
) {
  const { required, minLength, sameAs } = customValidators();
  // computed: luật đổi theo cấu hình backend khi shop-settings tải xong
  return computed(() => ({
    newPassword: {
      required,
      minLength: minLength(unref(passwordMinLength)),
    },
    newPasswordConfirm: {
      required,
      sameAs: sameAs(computed(() => state.value.newPassword)),
    },
    password: {
      required,
    },
  }));
}
