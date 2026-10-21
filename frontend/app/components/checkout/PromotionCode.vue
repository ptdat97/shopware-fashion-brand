<script setup lang="ts">
/**
 * Ô nhập mã khuyến mãi — giỏ hàng và trang thanh toán (H8). Template không có ô này nên khách
 * không dùng được mã giảm giá nào; storefront Twig có sẵn ở giỏ hàng.
 *
 * Quy tắc cứng số 8: frontend chỉ gửi mã khách nhập. Mã có hợp lệ không, giảm bao nhiêu, áp cho
 * sản phẩm nào (vd. chỉ một thương hiệu — Rule Builder) đều do backend quyết; frontend hiển thị
 * kết quả và dịch lỗi backend trả theo `messageKey` (errors.*).
 */
const { addPromotionCode, appliedPromotionCodes, removeItem } = useCart();
const { getErrorsCodes } = useCartNotification();
const { pushError, pushSuccess } = useNotifications();
const { t } = useI18n();

const code = ref("");
const isSubmitting = ref(false);

async function submit() {
  const value = code.value.trim();
  if (!value || isSubmitting.value) return;

  isSubmitting.value = true;
  try {
    await addPromotionCode(value);
    const errors = getErrorsCodes();
    for (const error of errors) {
      // Câu đã dịch do backend trả (translatedMessage) — bản dịch frontend chỉ là dự phòng
      const translated = (error as { translatedMessage?: string })
        .translatedMessage;
      pushError(translated || t(`errors.${error.messageKey}`, { ...error }));
    }
    const applied = appliedPromotionCodes.value.some(
      (item) =>
        String(item.referencedId ?? "").toLowerCase() === value.toLowerCase(),
    );
    if (applied) {
      pushSuccess(t("checkout.promotionCode.added", { code: value }));
      code.value = "";
    }
  } catch {
    pushError(t("messages.error"));
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<template>
  <div class="flex flex-col gap-3" data-testid="promotion-code">
    <form class="flex gap-2" @submit.prevent="submit">
      <label for="promotion-code-input" class="sr-only">
        {{ $t("checkout.promotionCode.label") }}
      </label>
      <input
        id="promotion-code-input"
        v-model="code"
        type="text"
        autocomplete="off"
        class="flex-1 min-w-0 px-3 py-2 border border-outline-outline-variant bg-white text-sm focus:outline-none focus:border-brand-primary"
        :placeholder="$t('form.promoCodePlaceholder')"
        data-testid="promotion-code-input"
      />
      <button
        type="submit"
        class="px-4 py-2 text-sm font-medium border border-brand-primary text-brand-primary hover:bg-brand-primary hover:text-brand-on-primary disabled:opacity-50 transition-colors"
        :disabled="isSubmitting || !code.trim()"
        data-testid="promotion-code-submit"
      >
        {{ $t("checkout.promotionCode.apply") }}
      </button>
    </form>

    <ul v-if="appliedPromotionCodes.length" class="flex flex-col gap-1">
      <li
        v-for="promotion in appliedPromotionCodes"
        :key="promotion.id"
        class="flex items-center justify-between gap-2 text-sm"
        data-testid="promotion-code-applied"
      >
        <span class="text-surface-on-surface">
          {{ promotion.label }}
          <span class="text-surface-on-surface-variant">
            ({{ promotion.referencedId }})
          </span>
        </span>
        <button
          type="button"
          class="underline underline-offset-4 text-surface-on-surface-variant hover:text-brand-primary-hover"
          @click="removeItem(promotion)"
        >
          {{ $t("cart.remove") }}
        </button>
      </li>
    </ul>
  </div>
</template>
