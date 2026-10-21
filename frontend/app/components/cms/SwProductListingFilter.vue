<script setup lang="ts" generic="ListingFilter extends { code: string }">
import { computed } from "vue";
import type { Component } from "vue";

// Override SwProductListingFilter của @shopware/cms-base-layer (bộ lọc dạng thanh bên):
// dịch nhãn bộ lọc trước khi chuyển xuống 4 component con — chặn ở một chỗ thay vì
// override cả 4. Phần còn lại giữ nguyên bản gốc.
import SwFilterCategoriesVue from "#cms-base-layer/app/components/listing-filters/SwFilterCategories.vue";
import SwFilterPriceVue from "#cms-base-layer/app/components/listing-filters/SwFilterPrice.vue";
import SwFilterPropertiesVue from "#cms-base-layer/app/components/listing-filters/SwFilterProperties.vue";
import SwFilterRatingVue from "#cms-base-layer/app/components/listing-filters/SwFilterRating.vue";
import SwFilterShippingFreeVue from "#cms-base-layer/app/components/listing-filters/SwFilterShippingFree.vue";

const {
  filter,
  selectedManufacturer,
  selectedProperties,
  selectedCategories = new Set<string>(),
  selectedMinPrice,
  selectedMaxPrice,
  selectedRating,
  selectedShippingFree,
  displayMode = "accordion",
} = defineProps<{
  filter: ListingFilter;
  selectedManufacturer: Set<string>;
  selectedProperties: Set<string>;
  selectedCategories?: Set<string>;
  selectedMinPrice: number | undefined;
  selectedMaxPrice: number | undefined;
  selectedRating: number | undefined;
  selectedShippingFree: boolean | undefined;
  displayMode?: "accordion" | "dropdown";
}>();

const emit = defineEmits<{
  "filter-change": [{ code: string; value: string | number | boolean }];
}>();

const filterLabel = useFilterLabel();
const labelledFilter = computed(() =>
  "label" in filter && typeof filter.label === "string"
    ? { ...filter, label: filterLabel(filter.label) }
    : filter,
);

const transformedFilters = computed(() => ({
  price: {
    min: selectedMinPrice,
    max: selectedMaxPrice,
  },
  rating: selectedRating,
  "shipping-free": selectedShippingFree,
  manufacturer: [...selectedManufacturer],
  properties: [...selectedProperties],
  categories: [...selectedCategories],
}));

const filterComponent = computed<Component | undefined>(() => {
  const componentMap: Record<string, Component> = {
    manufacturer: SwFilterPropertiesVue,
    categories: SwFilterCategoriesVue,
    price: SwFilterPriceVue,
    rating: SwFilterRatingVue,
    "shipping-free": SwFilterShippingFreeVue,
  };

  return (
    componentMap[filter.code] ||
    ("options" in filter ? SwFilterPropertiesVue : undefined)
  );
});

const handleSelectValue = ({
  code,
  value,
}: {
  code: string;
  value: string | number | boolean;
}) => {
  emit("filter-change", { code, value });
};
</script>
<template>
  <div :data-testid="`listing-filter-${filter.code}`">
    <component
      :is="filterComponent"
      :filter="labelledFilter"
      :selected-filters="transformedFilters"
      :display-mode="displayMode"
      @select-value="handleSelectValue"
    />
  </div>
</template>
