import {
  getCategoryImageUrl,
  getSmallestThumbnailUrl,
  getTranslatedProperty,
  isLandingPage,
  isProduct,
} from "@shopware/helpers";

import type { Schemas } from "#shopware";

export type UseCmsHeadReturn = ReturnType<typeof useCmsHead>;

type CmsPageEntity =
  | Schemas["Category"]
  | Schemas["LandingPage"]
  | Schemas["Product"];

type MetaEntry = {
  name: string;
  content: string;
};

function getMetaContent(meta: MetaEntry[], name: string) {
  return meta.find((entry) => entry.name === name)?.content;
}

export function useCmsHead(
  entity: Ref<CmsPageEntity> | ComputedRef<CmsPageEntity>,
  options?: {
    mainShopTitle?: string;
    /** Đường dẫn canonical khác trang hiện tại — vd. biến thể trỏ về biến thể canonical */
    canonicalPath?: Ref<string | null | undefined>;
  },
): void {
  const unrefEntity = unref(entity);
  const route = useRoute();
  const requestUrl = useRequestURL();
  const mainShopTitle = options?.mainShopTitle ?? useAppConfig().siteName;
  // Không giữ query: mỗi cách sắp xếp / lọc / UTM không phải một trang chuẩn riêng
  const canonicalUrl = computed(
    () =>
      new URL(options?.canonicalPath?.value ?? route.path, requestUrl.origin)
        .href,
  );
  // get title and meta tags available in the Shopware instance
  const { title: metaTitle, meta } = useCmsMeta(unrefEntity);

  const title = computed(() => {
    const title = metaTitle.value;
    if (mainShopTitle) {
      return `${title} | ${mainShopTitle}`;
    }
    return title;
  });

  const description = computed(() => getMetaContent(meta.value, "description"));

  const ogTitle = computed(() =>
    isProduct(unrefEntity)
      ? getTranslatedProperty(unrefEntity, "ogTitle") ||
        getMetaContent(meta.value, "title") ||
        title.value
      : getMetaContent(meta.value, "title"),
  );

  const ogDescription = computed(() =>
    isProduct(unrefEntity)
      ? getTranslatedProperty(unrefEntity, "ogDescription") || description.value
      : description.value,
  );

  // access to image varies depending on the type of the entity
  const ogImage = computed(() => {
    if (isLandingPage(unrefEntity)) {
      return;
    }

    return isProduct(unrefEntity)
      ? getSmallestThumbnailUrl(unrefEntity.openGraphMedia) ||
          getSmallestThumbnailUrl(unrefEntity.cover?.media)
      : getCategoryImageUrl(unrefEntity as Schemas["Category"]);
  });

  const additionalMeta = computed(() =>
    meta.value.filter(
      (entry: MetaEntry) => !["title", "description"].includes(entry.name),
    ),
  );

  useSeoMeta({
    title,
    description,
    ogTitle,
    ogDescription,
    ogImage,
    ogType: "website",
    ogSiteName: title,
  });

  useHead({
    meta: additionalMeta,
  });

  useHead(() => ({
    link: [
      {
        rel: "canonical",
        href: canonicalUrl.value,
      },
    ],
  }));
}
