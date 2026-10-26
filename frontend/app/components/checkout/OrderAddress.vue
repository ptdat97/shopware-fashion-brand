<script setup lang="ts">
import { getTranslatedProperty } from "@shopware/helpers";

import type { Schemas } from "#shopware";

defineOptions({
  name: "CheckoutOrderAddress",
});

const { address } = defineProps<{
  address: Schemas["OrderAddress"];
  label: string;
}>();

const countryName = computed(() =>
  address.country ? getTranslatedProperty(address.country, "name") : "",
);
// Tỉnh/thành và SĐT — khách tự kiểm thông tin giao hàng (shipper VN giao theo tỉnh, gọi SĐT)
const stateName = computed(() =>
  address.countryState
    ? getTranslatedProperty(address.countryState, "name")
    : "",
);
const cityLine = computed(() =>
  [address.zipcode, [address.city, stateName.value].filter(Boolean).join(", ")]
    .filter(Boolean)
    .join(" "),
);
</script>
<template>
  <div>
    <h3 class="text-surface-on-surface font-bold leading-normal mb-3">
      {{ label }}
    </h3>
    <address
      class="not-italic p-4 bg-brand-secondary flex flex-col justify-start items-start gap-0.5"
    >
      <div class="text-surface-on-surface text-base leading-normal">
        {{ address.lastName }} {{ address.firstName }}
      </div>
      <div class="text-surface-on-surface text-base leading-normal">
        {{ address.street }}
      </div>
      <div class="text-surface-on-surface text-base leading-normal">
        {{ cityLine }}
      </div>
      <div
        v-if="countryName"
        class="text-surface-on-surface text-base leading-normal"
      >
        {{ countryName }}
      </div>
      <div
        v-if="address.phoneNumber"
        class="text-surface-on-surface text-base leading-normal"
      >
        {{ address.phoneNumber }}
      </div>
    </address>
  </div>
</template>
