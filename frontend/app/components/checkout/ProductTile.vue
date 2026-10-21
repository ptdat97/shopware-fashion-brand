<script lang="ts" setup>
import { getSmallestThumbnailUrl } from "@shopware/helpers";

import type { Schemas } from "#shopware";

const { item } = defineProps<{
  item: Schemas["LineItem"];
}>();

const quantity = ref(item.quantity ?? 0);

const emit = defineEmits<{
  remove: [id: string];
  updateQuantity: [id: string, quantity: number];
}>();

const cover = computed(() =>
  item.cover ? getSmallestThumbnailUrl(item.cover) : "",
);
</script>
<template>
  <div
    class="flex gap-4"
    data-testid="checkout-product-tile-item"
    :data-product-id="item.referencedId"
  >
    <div class="w-37.5 h-37.5 shrink-0 flex items-center justify-center">
      <!-- Dòng khuyến mãi không có ảnh — template vẽ ảnh hỏng, chữ alt tràn ra ngoài -->
      <NuxtImg
        v-if="cover"
        data-testid="checkout-product-tile-image"
        :src="cover"
        :alt="item.label || item.payload?.name || ''"
        class="object-cover object-center"
      />
      <span
        v-else-if="item.type === 'promotion'"
        class="i-carbon-tag size-8 text-surface-on-surface-variant"
        aria-hidden="true"
      />
    </div>
    <div class="grid grid-cols-2 justify-between py-2.5 grow">
      <div class="text-surface-on-surface">
        <div class="line-clamp-2">{{ item.label }}</div>
      </div>

      <SharedPrice
        class="text-right justify-start text-surface-on-surface ml-auto"
        :value="item.price?.totalPrice"
      />

      <div class="content-end">
        <!-- Dòng khuyến mãi không có số lượng (template vẽ như sản phẩm); giữ ô trống cho thẳng hàng -->
        <FormQuantitySelect
          v-if="item.type !== 'promotion'"
          v-model="quantity"
          size="small"
          @update:modelValue="emit('updateQuantity', item.id, quantity)"
        />
      </div>
      <div class="content-end text-right">
        <FormLinkButton
          class="text-sm border-b-1 border-solid border-b-brand-primary hover:border-none"
          :label="$t('cart.remove')"
          data-testid="checkout-product-tile-remove-button"
          @click="emit('remove', item.id)"
        />
      </div>
    </div>
  </div>
</template>
