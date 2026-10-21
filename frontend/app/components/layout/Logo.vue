<script setup lang="ts">
// Logo dạng chữ — dùng tạm khi chưa có file logo thật (CLAUDE.md: "Chưa có file logo thật").
// Chữ là TÊN SHOP do backend cung cấp (quy tắc cứng số 8, shop-settings → general.shopName),
// in hoa bằng CSS. Kiểu chữ theo theme Twig AcmeTheme (.header-logo-wordmark).
// Có logo thật thì thay nội dung bằng ảnh (L5 trong docs/15 mục 8), giữ nguyên chỗ dùng.
defineProps<{
  inverse?: boolean;
}>();

const { shopName } = useShopSettings();
</script>

<template>
  <span
    class="inline-block font-light uppercase leading-tight tracking-[0.22em] text-[1.75rem] max-sm:text-xl"
    :class="inverse ? 'text-white' : 'text-brand-primary'"
  >
    {{ shopName }}
  </span>
</template>
