<script setup lang="ts">
import type { Schemas } from "#shopware";

defineOptions({
  name: "AccountOrderDetailAddresses",
});

defineProps<{
  shippingAddress?: Schemas["OrderAddress"];
  billingAddress?: Schemas["OrderAddress"];
}>();
</script>

<template>
  <!-- Dùng lại khối địa chỉ của trang cảm ơn: tên "Họ Tên", tỉnh/thành, SĐT (đơn VN) -->
  <div class="mt-8 flex flex-col sm:flex-row justify-between gap-6">
    <CheckoutOrderAddress
      v-if="shippingAddress"
      class="sm:w-1/3"
      :address="shippingAddress"
      :label="$t('account.orderDetails.shippingAddress')"
    />
    <CheckoutOrderAddress
      v-if="billingAddress"
      class="sm:w-1/3"
      :address="billingAddress"
      :label="$t('account.orderDetails.billingAddress')"
    />

    <slot />
  </div>
</template>
