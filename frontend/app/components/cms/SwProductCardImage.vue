<script setup lang="ts">
// Override SwProductCardImage của @shopware/cms-base-layer (H5), theo theme Twig AcmeTheme:
//  - khung 2:3 nền kem, ảnh căn mép trên (layer: vuông 400×400 → ảnh người mẫu dọc bị cắt)
//  - ảnh thứ hai khi rê chuột — chỉ thiết bị có chuột; điện thoại không tải (display:none
//    + loading="lazy"), tiết kiệm dữ liệu di động
//  - badge: layer luôn ghi "Bán chạy" kể cả khi chỉ giảm giá → tách "-x%" và "Bán chạy"
// Phần còn lại của script giữ nguyên bản gốc.
import {
  type UrlRouteOutput,
  getSmallestThumbnailUrl,
  getTranslatedProperty,
  isProductOnSale,
  isProductTopSeller,
} from "@shopware/helpers";
import { computed, inject } from "vue";

import { useUser } from "#imports";
import type { Schemas } from "#shopware";

type Translations = {
  product: {
    badges: {
      topseller: string;
    };
    addToWishlist: string;
    removeFromWishlist: string;
  };
};

const props = defineProps<{
  product: Schemas["Product"];
  translations: Translations;
  isInWishlist: boolean;
  isLoading: boolean;
  toggleWishlist: () => Promise<void>;
  productLink: UrlRouteOutput;
}>();

const coverSrcPath = computed(() => {
  return (
    props.product?.cover?.media?.url ||
    getSmallestThumbnailUrl(props.product?.cover?.media)
  );
});

const coverAlt = computed(() => {
  return (
    getTranslatedProperty(props.product?.cover?.media, "alt") ||
    props.product?.translated?.name
  );
});

const isOnSale = computed(() => isProductOnSale(props.product));

// Ảnh thứ hai (khác ảnh bìa) — association media do AcmeHeadless nạp vào listing
const hoverMedia = computed(() => {
  const coverMediaId = props.product?.cover?.mediaId;
  return (
    [...(props.product?.media ?? [])]
      .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
      .find((item) => item.mediaId !== coverMediaId && item.media?.url)
      ?.media ?? null
  );
});

const discountPercent = computed(() => {
  const percentage =
    props.product?.calculatedPrice?.listPrice?.percentage ??
    props.product?.calculatedCheapestPrice?.listPrice?.percentage;
  return percentage ? Math.round(percentage) : null;
});
const isTopseller = computed(() => isProductTopSeller(props.product));

const { isLoggedIn } = useUser();
const loginModal = inject<{
  open: (options?: { onSuccess?: () => void | Promise<void> }) => void;
} | null>("loginModal", null);

const { wishlistEnabled } = useShopSettings();

function handleWishlistClick() {
  if (isLoggedIn.value || !loginModal) {
    props.toggleWishlist();
    return;
  }
  loginModal.open({ onSuccess: props.toggleWishlist });
}
</script>

<template>
  <div
    class="group self-stretch relative flex flex-col justify-start items-start overflow-hidden aspect-[2/3] bg-brand-secondary"
  >
    <RouterLink
      :to="productLink"
      class="self-stretch h-full relative overflow-hidden"
    >
      <NuxtImg
        preset="productCard"
        class="w-full h-full absolute top-0 left-0 object-cover object-top"
        :src="coverSrcPath"
        :alt="coverAlt"
        width="400"
        height="600"
        densities="1x"
        loading="lazy"
        data-testid="product-box-img"
      />
      <NuxtImg
        v-if="hoverMedia"
        preset="productCard"
        class="hidden pointer-fine:block w-full h-full absolute top-0 left-0 object-cover object-top opacity-0 group-hover:opacity-100 transition-opacity duration-300"
        :src="hoverMedia.url"
        :alt="getTranslatedProperty(hoverMedia, 'alt') || coverAlt"
        width="400"
        height="600"
        densities="1x"
        loading="lazy"
        aria-hidden="true"
        data-testid="product-box-img-hover"
      />
    </RouterLink>

    <div
      v-if="isOnSale || isTopseller"
      class="left-2 bottom-2 absolute flex gap-1"
    >
      <div
        v-if="isOnSale && discountPercent"
        class="px-1.5 py-1 bg-other-sale rounded text-states-on-error text-xs font-bold leading-none"
        data-testid="product-box-badge-sale"
      >
        -{{ discountPercent }}%
      </div>
      <div
        v-if="isTopseller"
        class="px-1.5 py-1 bg-brand-primary rounded text-brand-on-primary text-xs font-bold leading-none"
        data-testid="product-box-badge-topseller"
      >
        {{ translations.product.badges.topseller }}
      </div>
    </div>

    <!-- Ẩn khi người vận hành tắt wishlist (Settings → Cart) — quy tắc cứng số 8 -->
    <client-only v-if="wishlistEnabled">
      <SwIconButton
        type="secondary"
        :aria-label="
          isInWishlist
            ? translations.product.removeFromWishlist
            : translations.product.addToWishlist
        "
        :disabled="isLoading"
        class="w-10 h-10 right-4 top-4 absolute bg-brand-secondary rounded-full flex items-center justify-center"
        data-testid="product-box-toggle-wishlist-button"
        :data-in-wishlist="isInWishlist"
        @click="handleWishlistClick"
      >
        <SwWishlistIcon :filled="isInWishlist" />
      </SwIconButton>
    </client-only>
  </div>
</template>
