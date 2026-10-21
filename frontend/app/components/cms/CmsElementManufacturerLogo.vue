<script setup lang="ts">
/**
 * Override CmsElementManufacturerLogo của @shopware/cms-base-layer (H3) — cùng hành vi
 * với template Twig của AcmeBrand: logo nhà sản xuất ở trang sản phẩm dẫn về TRANG
 * THƯƠNG HIỆU trong shop thay vì website ngoài của hãng, cùng tab.
 *
 * Chỉ khi brand đang bật và người vận hành chưa tự đặt link cho phần tử CMS; còn lại
 * giữ nguyên bản gốc của layer (CmsElementImage). Chưa có logo → hiện tên brand.
 */
import type { CmsElementManufacturerLogo } from "@shopware/composables";
import { getTranslatedProperty } from "@shopware/helpers";

import CmsElementImage from "#cms-base-layer/app/components/public/cms/element/CmsElementImage.vue";
import type { Schemas } from "#shopware";

const props = defineProps<{
  content: CmsElementManufacturerLogo;
}>();

const localePath = useLocalePath();
const { formatLink } = useInternationalization(localePath);

// Store API trả kèm data.manufacturer (có extensions.acmeBrand) — kiểu của layer chưa khai báo
const manufacturer = (
  props.content.data as { manufacturer?: Schemas["AcmeBrandManufacturer"] }
)?.manufacturer;

const linksToBrandPage =
  !!manufacturer?.extensions?.acmeBrand?.active &&
  !props.content.config?.url?.value;

const { data: brandPath } = linksToBrandPage
  ? await useBrandPath(manufacturer!.id)
  : { data: ref<string | null>(null) };

const brandName = manufacturer
  ? (getTranslatedProperty(manufacturer, "name") ?? "")
  : "";
</script>

<template>
  <NuxtLink
    v-if="brandPath"
    :to="formatLink(brandPath)"
    :title="brandName"
    class="inline-flex items-center"
    data-testid="acme-brand-manufacturer-link"
  >
    <img
      v-if="content.data?.media?.url"
      :src="content.data.media.url"
      :alt="brandName"
      class="max-h-12 w-auto object-contain"
    />
    <span
      v-else
      class="text-sm uppercase tracking-[0.15em] whitespace-nowrap text-surface-on-surface-variant hover:text-brand-primary-hover"
    >
      {{ brandName }}
    </span>
  </NuxtLink>
  <CmsElementImage v-else :content="content" />
</template>
