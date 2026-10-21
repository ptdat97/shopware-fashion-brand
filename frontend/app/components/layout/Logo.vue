<script setup lang="ts">
// Logo do backend cung cấp (quy tắc cứng số 8): ảnh trong Media chọn ở cấu hình plugin
// AcmeHeadless → shop-settings → extensions.acmeHeadless.logo. Chưa có ảnh (hoặc plugin
// tắt) thì hiện TÊN SHOP dạng chữ (general.shopName), in hoa bằng CSS — kiểu chữ theo
// theme Twig AcmeTheme (.header-logo-wordmark).
defineProps<{
  inverse?: boolean;
}>();

const { shopName, logo } = useShopSettings();
</script>

<template>
  <img
    v-if="logo?.url"
    :src="logo.url"
    :alt="logo.translated?.alt || shopName"
    class="block h-10 w-auto max-w-48 object-contain max-sm:h-8"
    :class="{ 'brightness-0 invert': inverse }"
    data-testid="header-logo-image"
  />
  <span
    v-else
    class="inline-block font-light uppercase leading-tight tracking-[0.22em] text-[1.75rem] max-sm:text-xl"
    :class="inverse ? 'text-white' : 'text-brand-primary'"
  >
    {{ shopName }}
  </span>
</template>
