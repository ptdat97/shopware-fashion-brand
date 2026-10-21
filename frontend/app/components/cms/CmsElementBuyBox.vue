<script setup lang="ts">
/**
 * Bọc CmsElementBuyBox của @shopware/cms-base-layer: vẽ nguyên bản gốc rồi thêm hướng dẫn
 * size bên dưới (H5) — như `{{ parent() }}` + nội dung thêm trong template Twig, không chép
 * lại 166 dòng của layer.
 */
import type { CmsElementBuyBox } from "@shopware/composables";

import LayerCmsElementBuyBox from "#cms-base-layer/app/components/public/cms/element/CmsElementBuyBox.vue";

defineProps<{
  content: CmsElementBuyBox;
}>();
</script>

<template>
  <div>
    <LayerCmsElementBuyBox :content="content" />
    <ProductSizeGuide
      v-if="content.data?.product"
      :product="content.data.product"
    />
  </div>
</template>
