import { customValidators } from "#imports";

export function loginFormRules() {
  const { required, email } = customValidators();
  return {
    username: {
      required,
      email,
    },
    // Đăng nhập chỉ cần nhập — đúng/sai do backend kiểm (không tự đặt độ dài tối thiểu)
    password: {
      required,
    },
  };
}
