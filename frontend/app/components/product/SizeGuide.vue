<script setup lang="ts">
/**
 * "Người mẫu mặc size…" + nút mở bảng size — cạnh nút mua (H5, như theme Twig AcmeTheme).
 * Số đo sai là nguyên nhân đổi trả hàng đầu với thời trang online.
 *
 *  - Người mẫu: custom field sản phẩm `acme_model_size` (AcmeCatalog), biến thể kế thừa
 *  - Bảng size: custom field của DANH MỤC CHÍNH (`seoCategory`, chọn trong Administration →
 *    sản phẩm → "Danh mục chính") — `acme_size_chart_table` (HTML), `acme_size_chart_note`
 *
 * Không có dữ liệu (AcmeCatalog tắt, chưa nhập) → không hiện gì.
 */
import { getTranslatedProperty } from "@shopware/helpers";
import xss from "xss";

import type { Schemas } from "#shopware";

const { product } = defineProps<{
  product: Schemas["Product"];
}>();

type CustomFields = Record<string, unknown> | undefined;
// Store API trả translated.customFields — kiểu schema của core chưa khai báo
const translatedCustomFields = (entity: { translated?: unknown } | undefined) =>
  (entity?.translated as { customFields?: CustomFields } | undefined)
    ?.customFields;

const modelSize = computed(() => {
  const value = translatedCustomFields(product)?.acme_model_size;
  return typeof value === "string" ? value.trim() : "";
});

const sizeChart = computed(() => {
  const category = product.seoCategory;
  const fields = translatedCustomFields(category);
  const table = fields?.acme_size_chart_table;
  if (typeof table !== "string" || table.trim() === "") return null;

  const note = fields?.acme_size_chart_note;
  return {
    // HTML do người vận hành nhập trong Administration — vẫn lọc như mô tả sản phẩm của layer
    table: xss(table),
    note: typeof note === "string" ? note : "",
    categoryName: category
      ? (getTranslatedProperty(category, "name") ?? "")
      : "",
  };
});

const dialog = useTemplateRef<HTMLDialogElement>("dialog");
</script>

<template>
  <div
    v-if="modelSize || sizeChart"
    class="mt-4 flex flex-col gap-2 text-sm text-surface-on-surface-variant"
  >
    <p v-if="modelSize" data-testid="acme-model-size">{{ modelSize }}</p>

    <template v-if="sizeChart">
      <button
        type="button"
        class="self-start underline underline-offset-4 text-surface-on-surface hover:text-brand-primary-hover"
        data-testid="acme-size-chart-open"
        @click="dialog?.showModal()"
      >
        {{ $t("product.sizeGuide.open") }}
      </button>

      <dialog
        ref="dialog"
        class="m-auto w-[min(640px,calc(100vw-2rem))] p-0 bg-surface-surface text-surface-on-surface backdrop:bg-black/50"
        aria-labelledby="acme-size-chart-title"
        closedby="any"
      >
        <div
          class="flex items-center justify-between gap-4 px-5 py-4 border-b border-outline-outline-variant"
        >
          <h2 id="acme-size-chart-title" class="text-lg">
            {{
              $t("product.sizeGuide.title", {
                category: sizeChart.categoryName,
              })
            }}
          </h2>
          <button
            type="button"
            class="i-carbon-close size-6"
            :aria-label="$t('close')"
            @click="dialog?.close()"
          />
        </div>
        <div class="p-5 overflow-x-auto">
          <!-- eslint-disable-next-line vue/no-v-html -- đã lọc bằng xss -->
          <div
            class="text-sm [&_table]:w-full [&_table]:border-collapse [&_th]:text-left [&_th]:font-medium [&_th]:px-3 [&_th]:py-2 [&_td]:px-3 [&_td]:py-2 [&_tr]:border-b [&_tr]:border-outline-outline-variant [&_thead]:bg-surface-surface-container"
            v-html="sizeChart.table"
          />
          <p v-if="sizeChart.note" class="mt-4 text-surface-on-surface-variant">
            {{ sizeChart.note }}
          </p>
        </div>
      </dialog>
    </template>
  </div>
</template>
