<script setup lang="ts">
// Logo dạng chữ — dùng tạm khi chưa có file logo thật (CLAUDE.md: "Chưa có file logo thật").
// Kiểu chữ theo theme Twig AcmeTheme (.header-logo-wordmark): mảnh, in hoa, giãn chữ.
// Có logo thật thì thay nội dung bằng <NuxtImg>, giữ nguyên chỗ dùng.
defineProps<{
  inverse?: boolean;
}>();
</script>

<template>
  <span
    class="inline-block font-light uppercase leading-tight tracking-[0.22em] text-[1.75rem] max-sm:text-xl"
    :class="inverse ? 'text-white' : 'text-brand-primary'"
  >
    Anivele
  </span>
</template>
