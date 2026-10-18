<script setup lang="ts">
import { getLanguageName } from "@shopware/helpers";

const { loadNavigationElements } = useNavigation();
const { data } = useAsyncData("mainNavigation", () => {
  return loadNavigationElements({ depth: 2 });
});

const { languages, changeLanguage, replaceToDevStorefront } =
  useInternationalization();
const { currentLanguageId } = useSessionContext();
const {
  currenciesList,
  currentCurrencyId,
  changingCurrencyId,
  changeCurrency,
} = useCurrencySwitcher();
provide("swNavigation-main-navigation", data);

const { availableLocales } = useI18n();

// Chỉ hiện ngôn ngữ frontend có bản dịch — channel có thể gán thêm ngôn ngữ
// (English mặc định của bản cài) mà frontend không phục vụ.
const languagesList = computed(
  () =>
    languages.value
      ?.filter((language) =>
        (availableLocales as string[]).includes(
          language.translationCode?.code ?? "",
        ),
      )
      .map((language) => {
        return {
          id: language.id,
          label: getLanguageName(language),
        };
      }) ?? [],
);

async function onChangeHandler(id: string) {
  const data = await changeLanguage(id);

  if (data.redirectUrl) {
    window.location.replace(replaceToDevStorefront(data.redirectUrl));
  } else {
    window.location.reload();
  }
}

async function onCurrencyChangeHandler(id: string) {
  await changeCurrency(id);
}

const { loadNavigationElements: loadFooterNavigationElements } = useNavigation({
  type: "footer-navigation",
});
const { data: footerData } = useAsyncData("mainFooterNavigation", () => {
  return loadFooterNavigationElements({ depth: 1 });
});
provide("swNavigation-footer-navigation", footerData);
</script>
<template>
  <div class="flex flex-col min-h-screen">
    <header>
      <LayoutMetaNavigation
        v-if="
          (languagesList.length > 1 || currenciesList.length > 1) &&
          currentLanguageId
        "
        class="px-6"
        :current-language-id="currentLanguageId"
        :languages="languagesList"
        :current-currency-id="currentCurrencyId"
        :currencies="currenciesList"
        :changing-currency-id="changingCurrencyId"
        @onLanguageChangeHandler="onChangeHandler"
        @onCurrencyChangeHandler="onCurrencyChangeHandler"
      />
      <LayoutHeader />
    </header>
    <main class="flex-1" :aria-label="$t('layout.ariaLabels.mainContent')">
      <LayoutNotifications />
      <slot />
    </main>
    <LayoutFooter />
  </div>
</template>
