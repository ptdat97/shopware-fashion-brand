<script setup lang="ts">
// Override SwFilterDropdown của @shopware/cms-base-layer: layer truyền thẳng mã
// aggregation (manufacturer, price, rating, shipping-free) làm nhãn. Dịch qua
// listing.filterLabels; nhóm thuộc tính (Màu sắc, Kích cỡ…) đã có tên dịch từ backend.
import { onClickOutside } from "@vueuse/core";
import { computed, ref, useTemplateRef } from "vue";

const props = defineProps<{
  label: string;
  isActive?: boolean;
}>();

const { t, te } = useI18n();
const displayLabel = computed(() => {
  const key = `listing.filterLabels.${props.label}`;
  return te(key) ? t(key) : props.label;
});

const isOpen = ref(false);
const dropdownElement = useTemplateRef<HTMLDivElement>("dropdownElement");

onClickOutside(dropdownElement, () => {
  isOpen.value = false;
});

function toggle() {
  isOpen.value = !isOpen.value;
}
</script>

<template>
  <div ref="dropdownElement" class="relative">
    <button
      type="button"
      class="bg-brand-tertiary rounded-full px-4 py-1.5 inline-flex items-center hover:bg-brand-tertiary-hover transition-colors"
      :class="{ 'ring-2 ring-brand-primary': isActive }"
      @click="toggle"
      :aria-expanded="isOpen"
      aria-haspopup="true"
    >
      <div class="py-1 inline-flex items-center gap-1">
        <span class="text-brand-on-tertiary text-base font-normal leading-6">
          {{ displayLabel }}
        </span>
        <SwChevronIcon
          :direction="isOpen ? 'up' : 'down'"
          :size="24"
          class="text-brand-on-tertiary"
        />
      </div>
    </button>

    <div
      v-if="isOpen"
      class="absolute top-full left-0 mt-2 min-w-64 bg-surface-surface rounded-lg shadow-lg ring-1 ring-outline-outline-variant z-50 p-4"
      role="menu"
    >
      <slot />
    </div>
  </div>
</template>
