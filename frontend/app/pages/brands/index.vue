<script setup lang="ts">
/**
 * Trang tổng `/brands` (H2): mọi thương hiệu đang bật, theo thứ tự người vận hành đặt.
 *
 * Đường dẫn tới từng brand là SEO URL native của core (route `store-api.acme.brand.detail`)
 * — tra một lần qua /store-api/seo-url, không tự ghép slug từ tên.
 */
import { getTranslatedProperty } from "@shopware/helpers";

const { apiClient } = useShopwareContext();
const { t } = useI18n();
const localePath = useLocalePath();
const { formatLink } = useInternationalization(localePath);

const { data } = await useAsyncData("acmeBrandList", async () => {
  const { data: brands } = await apiClient.invoke(
    "readAcmeBrandList post /acme/brand",
  );

  const ids = brands.elements.map((brand) => brand.id);
  if (ids.length === 0) {
    return { brands: brands.elements, paths: {} as Record<string, string> };
  }

  const { data: seoUrls } = await apiClient.invoke("readSeoUrl post /seo-url", {
    body: {
      filter: [
        {
          type: "equals",
          field: "routeName",
          value: "store-api.acme.brand.detail",
        },
        { type: "equals", field: "isCanonical", value: true },
        // Store API nhận equalsAny dạng chuỗi nối bằng "|"
        { type: "equalsAny", field: "foreignKey", value: ids.join("|") },
      ],
    },
  });

  const paths: Record<string, string> = {};
  for (const seoUrl of seoUrls.elements ?? []) {
    paths[seoUrl.foreignKey] = `/${seoUrl.seoPathInfo}`;
  }

  return { brands: brands.elements, paths };
});

const brands = computed(() => data.value?.brands ?? []);

useBreadcrumbs([{ name: t("brand.list.title"), path: "/brands" }]);

useHead({
  title: `${t("brand.list.title")} | ${useAppConfig().siteName}`,
  meta: [{ name: "description", content: t("brand.list.metaDescription") }],
});
</script>

<template>
  <div>
    <LayoutBreadcrumbs />

    <h1 class="font-serif text-4xl md:text-5xl text-surface-on-surface mb-10">
      {{ t("brand.list.title") }}
    </h1>

    <p
      v-if="brands.length === 0"
      class="text-surface-on-surface-variant py-16 text-center"
    >
      {{ t("brand.list.empty") }}
    </p>

    <ul
      v-else
      class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6"
    >
      <li v-for="brand in brands" :key="brand.id">
        <NuxtLink
          :to="formatLink(data?.paths[brand.id] ?? '/brands')"
          class="group flex flex-col items-center justify-center gap-3 aspect-[4/3] p-6 bg-surface-surface-container border border-outline-outline-variant hover:border-brand-primary transition-colors"
        >
          <img
            v-if="brand.media"
            :src="brand.media.url"
            :alt="getTranslatedProperty(brand, 'name') ?? ''"
            class="max-h-16 w-auto object-contain"
          />
          <span
            class="font-light uppercase tracking-[0.15em] text-center text-surface-on-surface group-hover:text-brand-primary-hover"
          >
            {{ getTranslatedProperty(brand, "name") }}
          </span>
          <span
            v-if="brand.extensions?.acmeBrand?.featured"
            class="text-xs text-surface-on-surface-variant"
          >
            {{ t("brand.list.featured") }}
          </span>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
