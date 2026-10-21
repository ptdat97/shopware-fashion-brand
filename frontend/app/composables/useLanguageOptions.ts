import { getLanguageName } from "@shopware/helpers";

/**
 * Ngôn ngữ cho bộ chọn ở thanh trên cùng — chỉ những ngôn ngữ frontend có bản dịch.
 * Channel có thể gán thêm ngôn ngữ (English mặc định của bản cài) mà frontend không phục
 * vụ; với một ngôn ngữ duy nhất (vi-VN) bộ chọn tự ẩn. Dùng chung cho layout default và
 * checkout.
 */
export function useLanguageOptions() {
  const { languages } = useInternationalization();
  const { availableLocales } = useI18n();

  return computed(
    () =>
      languages.value
        ?.filter((language) =>
          (availableLocales as string[]).includes(
            language.translationCode?.code ?? "",
          ),
        )
        .map((language) => ({
          id: language.id,
          label: getLanguageName(language),
        })) ?? [],
  );
}
