<script setup lang="ts">
/**
 * Trang thương hiệu `/brands/{tên}` (H2).
 *
 * Resolver `[...all].vue` tra SEO path ra routeName `store-api.acme.brand.detail` (route
 * SEO headless của plugin AcmeBrand) → tên component `StoreApiAcmeBrandDetail`, foreignKey
 * là id nhà sản xuất. Dữ liệu từ Store API của AcmeBrand; frontend không tự áp quy tắc
 * brand (đang bật, meta dự phòng…).
 *
 * Listing dùng lại component của cms-base-layer: trang provide khoá `categoryListing` bằng
 * một listing composable gọi endpoint brand, nên bộ lọc, sắp xếp, phân trang, thẻ sản
 * phẩm đều là của layer.
 */
import { ApiClientError } from "@shopware/api-client";
import type {
  CmsElementProductListing,
  CmsElementSidebarFilter,
} from "@shopware/composables";
import { createListingComposable } from "@shopware/composables";
import { getSrcSetForMedia, getTranslatedProperty } from "@shopware/helpers";
import xss from "xss";

import type { operations } from "#shopware";

const props = defineProps<{
  navigationId: string;
}>();

type ListingCriteria =
  operations["readAcmeBrandListing post /acme/brand/{manufacturerId}/listing"]["body"];

const { apiClient } = useShopwareContext();
const route = useRoute();
const requestUrl = useRequestURL();
const { t } = useI18n();

const { data: detail } = await useAsyncData(
  `acmeBrand-${props.navigationId}`,
  async () => {
    try {
      const { data } = await apiClient.invoke(
        "readAcmeBrand post /acme/brand/{manufacturerId}",
        { pathParams: { manufacturerId: props.navigationId } },
      );
      return data;
    } catch (error) {
      // Brand tắt / không có: backend trả 404 ACME_BRAND__NOT_FOUND
      if (error instanceof ApiClientError && error.status === 404) {
        throw createError({
          statusCode: 404,
          statusMessage: t("brand.page.notFound"),
        });
      }
      throw error;
    }
  },
);

if (!detail.value) {
  throw createError({
    statusCode: 404,
    statusMessage: t("brand.page.notFound"),
  });
}

const searchBrandListing = async (criteria: ListingCriteria) => {
  const { data } = await apiClient.invoke(
    "readAcmeBrandListing post /acme/brand/{manufacturerId}/listing",
    {
      headers: { "sw-include-seo-urls": true },
      pathParams: { manufacturerId: props.navigationId },
      body: criteria,
    },
  );
  return data;
};

const { data: initialListing } = await useAsyncData(
  `acmeBrandListing-${props.navigationId}`,
  () => searchBrandListing(buildListingCriteria(route.query)),
);

provide(
  "categoryListing",
  createListingComposable({
    listingKey: "acmeBrandListing",
    searchMethod: searchBrandListing,
    searchDefaults: {} as operations["searchPage post /search"]["body"],
    initialListing: initialListing.value,
  }),
);

const manufacturer = computed(() => detail.value!.manufacturer);
const brandName = computed(
  () => getTranslatedProperty(manufacturer.value, "name") ?? "",
);
const description = computed(() =>
  xss(getTranslatedProperty(manufacturer.value, "description") ?? ""),
);
const banner = computed(
  () => manufacturer.value.extensions?.acmeBrand?.bannerMedia ?? null,
);
const logo = computed(() => manufacturer.value.media ?? null);

// Phần tử listing của layer đọc cấu hình kiểu phần tử CMS — dựng tối giản
const listingContent = computed(
  () =>
    ({
      type: "product-listing",
      config: { boxLayout: { value: "standard", source: "static" } },
      data: { listing: initialListing.value },
    }) as unknown as CmsElementProductListing,
);
const filterContent = computed(
  () =>
    ({
      type: "sidebar-filter",
      config: {},
    }) as unknown as CmsElementSidebarFilter,
);

useBreadcrumbs([
  { name: t("brand.list.title"), path: "/brands" },
  { name: brandName.value, path: route.path },
]);

useHead({
  title: `${detail.value.metaTitle} | ANIVELE`,
  meta: [
    { name: "description", content: detail.value.metaDescription },
    { name: "keywords", content: detail.value.metaKeywords },
  ],
  link: [
    { rel: "canonical", href: new URL(route.path, requestUrl.origin).href },
  ],
});
</script>

<template>
  <div>
    <LayoutBreadcrumbs />

    <div v-if="banner" class="mb-8">
      <img
        :src="banner.url"
        :srcset="getSrcSetForMedia(banner)"
        sizes="100vw"
        :alt="brandName"
        class="w-full max-h-[480px] object-cover"
        fetchpriority="high"
      />
    </div>

    <header class="flex flex-col items-start gap-4 mb-10 max-w-3xl">
      <img
        v-if="logo"
        :src="logo.url"
        :alt="brandName"
        class="h-16 w-auto object-contain"
      />
      <h1 class="font-serif text-4xl md:text-5xl text-surface-on-surface">
        {{ brandName }}
      </h1>
      <!-- eslint-disable-next-line vue/no-v-html -- đã lọc bằng xss như mô tả sản phẩm của layer -->
      <div
        v-if="description"
        class="text-surface-on-surface-variant leading-relaxed"
        v-html="description"
      />
    </header>

    <CmsPage v-if="detail?.cmsPage" :content="detail.cmsPage" />

    <section aria-labelledby="acme-brand-products-title">
      <h2
        id="acme-brand-products-title"
        class="text-xl uppercase tracking-wide font-light mb-6"
      >
        {{ t("brand.page.productsTitle", { brand: brandName }) }}
      </h2>
      <SwProductListingFiltersHorizontal
        :content="filterContent"
        class="mb-8"
      />
      <CmsElementProductListing :content="listingContent" />
    </section>
  </div>
</template>
