<script setup lang="ts">
// Override SwVariantConfigurator của @shopware/cms-base-layer (H5) — script giữ nguyên bản
// gốc, chỉ thay giao diện theo theme Twig AcmeTheme:
//  - nhóm displayType "color": ô màu tròn tô đúng colorHexCode (layer bỏ qua, hiện chữ)
//  - size: vùng chạm tối thiểu 48×44px
//  - tổ hợp hết hàng (option.combinable = false từ Store API): KHÔNG ẩn — docs/02 coi ẩn
//    size hết hàng là lỗi UX; làm mờ + gạch (chữ: gạch ngang, ô màu: gạch chéo)
import { useCmsTranslations } from "@shopware/composables";
import {
  buildUrlPrefix,
  getProductRoute,
  getTranslatedProperty,
} from "@shopware/helpers";
import { defu } from "defu";
import { computed, ref, unref } from "vue";
import type { ComputedRef } from "vue";

import { useProductConfigurator, useRouter, useUrlResolver } from "#imports";
import type { Schemas } from "#shopware";

const { getUrlPrefix } = useUrlResolver();

const prefix = getUrlPrefix();

const { allowRedirect = true } = defineProps<{
  allowRedirect?: boolean;
}>();

type Translations = {
  product: {
    chooseA: string;
  };
};

let translations: Translations = {
  product: {
    chooseA: "Choose a",
  },
};

translations = defu(useCmsTranslations(), translations) as Translations;

const emit =
  defineEmits<
    (e: "change", selected: Partial<Schemas["Product"]> | undefined) => void
  >();
const isLoading = ref<boolean>();
const router = useRouter();
const {
  handleChange,
  getOptionGroups,
  getSelectedOptions,
  findVariantForSelectedOptions,
} = useProductConfigurator();

const selectedOptions: ComputedRef = computed(() =>
  Object.values(unref(getSelectedOptions)),
);
const isOptionSelected = (optionId: string) =>
  Object.values(getSelectedOptions.value).includes(optionId);

const isColorGroup = (group: Schemas["PropertyGroup"]) =>
  group.displayType === "color" &&
  !!group.options?.some((option) => option.colorHexCode);

// Ô màu không có chữ — hiện tên màu đang chọn cạnh nhãn nhóm
const selectedOptionName = (group: Schemas["PropertyGroup"]) =>
  group.options?.find((option) => isOptionSelected(option.id))?.translated
    ?.name;

const { apiClient } = useShopwareContext();
const { product } = useProduct();

/**
 * Khách đổi một nhóm (vd. màu) sang giá trị không ghép được với lựa chọn hiện tại (size L
 * không có màu Be): bản gốc của layer tìm đúng tổ hợp đó, không thấy → không làm gì.
 * Hỏi core như storefront Twig — `find-variant` với `switchedGroup` giữ giá trị vừa bấm và
 * tự chọn lại nhóm khác để ra biến thể còn bán (ProductCombinationFinder).
 */
async function resolveSwitchedCombination(switchedGroup?: string) {
  const parentId = product.value?.parentId;
  if (!parentId || !switchedGroup) return undefined;

  try {
    const { data } = await apiClient.invoke(
      "searchProductVariantIds post /product/{productId}/find-variant",
      {
        pathParams: { productId: parentId },
        body: {
          options: Object.values(getSelectedOptions.value),
          switchedGroup,
        },
      },
    );
    const options = data.foundCombination?.options;
    return options?.length
      ? Object.fromEntries(options.map((id, index) => [String(index), id]))
      : undefined;
  } catch {
    // Không tìm được → rơi về cách tìm của layer
    return undefined;
  }
}

const onHandleChange = async (switchedGroup?: string) => {
  isLoading.value = true;
  const variantFound = await findVariantForSelectedOptions(
    await resolveSwitchedCombination(switchedGroup),
  );
  const selectedOptionsVariantPath = buildUrlPrefix(
    getProductRoute(variantFound),
    prefix,
  );
  if (allowRedirect && selectedOptionsVariantPath) {
    try {
      router.push(selectedOptionsVariantPath);
    } catch {
      console.error("incorrect URL", selectedOptionsVariantPath);
    }
  } else {
    emit("change", variantFound);
  }
  isLoading.value = false;
};
</script>

<template>
  <div class="relative flex flex-col">
    <div
      v-if="isLoading"
      class="absolute inset-0 flex items-center justify-center z-10 bg-white/75"
    >
      <div
        data-testid="loading"
        class="size-8 i-carbon-progress-bar-round animate-spin text-gray-500"
      />
    </div>
    <div
      v-for="optionGroup in getOptionGroups"
      :key="optionGroup.id"
      class="mt-6"
    >
      <div class="text-sm text-surface-on-surface font-medium">
        {{ getTranslatedProperty(optionGroup, "name")
        }}<span
          v-if="isColorGroup(optionGroup) && selectedOptionName(optionGroup)"
          class="font-normal text-surface-on-surface-variant"
          >: {{ selectedOptionName(optionGroup) }}</span
        >
      </div>
      <fieldset class="mt-3 flex-1">
        <legend class="sr-only">
          {{ translations.product.chooseA }}
          {{ getTranslatedProperty(optionGroup, "name") }}
        </legend>

        <!-- Ô màu tròn -->
        <div v-if="isColorGroup(optionGroup)" class="flex flex-wrap gap-3">
          <button
            v-for="option in optionGroup.options"
            :key="option.id"
            type="button"
            data-testid="product-variant"
            :data-selected="isOptionSelected(option.id)"
            :data-combinable="option.combinable !== false"
            :aria-pressed="isOptionSelected(option.id)"
            :aria-label="option.translated.name"
            :title="option.translated.name"
            class="relative size-10 rounded-full border border-black/15 overflow-hidden transition-shadow hover:border-brand-primary"
            :class="{
              'ring-2 ring-brand-primary ring-offset-2': isOptionSelected(
                option.id,
              ),
              'opacity-45': option.combinable === false,
            }"
            :style="{ backgroundColor: option.colorHexCode ?? undefined }"
            @click="
              handleChange(optionGroup.translated.name, option.id, () =>
                onHandleChange(optionGroup.id),
              )
            "
          >
            <span
              v-if="option.combinable === false"
              aria-hidden="true"
              class="absolute inset-0 bg-[linear-gradient(to_top_left,transparent_calc(50%-1px),rgba(0,0,0,0.55)_calc(50%-1px),rgba(0,0,0,0.55)_calc(50%+1px),transparent_calc(50%+1px))]"
            />
          </button>
        </div>

        <!-- Nút chữ (size…) -->
        <div v-else class="flex flex-wrap gap-2">
          <button
            v-for="option in optionGroup.options"
            :key="option.id"
            type="button"
            data-testid="product-variant"
            :data-selected="isOptionSelected(option.id)"
            :data-combinable="option.combinable !== false"
            :aria-pressed="isOptionSelected(option.id)"
            class="min-w-12 min-h-11 px-3 border text-sm font-medium uppercase bg-white text-surface-on-surface hover:border-brand-primary transition-colors"
            :class="{
              'border-2 border-brand-primary': isOptionSelected(option.id),
              'border-outline-outline-variant': !isOptionSelected(option.id),
              'opacity-45 line-through bg-black/[0.03]':
                option.combinable === false,
            }"
            @click="
              handleChange(optionGroup.translated.name, option.id, () =>
                onHandleChange(optionGroup.id),
              )
            "
          >
            <span
              :id="`${option.id}-choice-label`"
              data-testid="product-variant-text"
            >
              {{ option.translated.name }}
            </span>
          </button>
        </div>
      </fieldset>
    </div>
  </div>
</template>
