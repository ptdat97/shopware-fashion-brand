<script setup lang="ts">
/**
 * Thông tin chuyển khoản của một đơn (H6, "Chuyển khoản trước" / VietQR) — trang cảm ơn
 * và chi tiết đơn trong tài khoản.
 *
 * Mọi thứ do backend quyết (quy tắc cứng số 8, AcmePayment): đơn có cần chuyển khoản
 * không, tài khoản nhận, số tiền, nội dung chuyển khoản, câu hướng dẫn, mã QR. Đơn không
 * phải chuyển khoản (COD…) → không hiện gì.
 */
const props = defineProps<{
  orderId: string;
}>();

const { apiClient } = useShopwareContext();
const { pushSuccess } = useNotifications();
const { t } = useI18n();

const { data: info } = useAsyncData(
  `acmeBankTransfer-${props.orderId}`,
  async () => {
    const { data } = await apiClient.invoke(
      "readAcmeBankTransfer get /acme/payment/bank-transfer/{orderId}",
      { pathParams: { orderId: props.orderId } },
    );
    return data;
  },
  { server: false },
);

async function copy(value: string, label: string) {
  try {
    await navigator.clipboard.writeText(value);
    pushSuccess(t("checkout.bankTransfer.copied", { label }));
  } catch (error) {
    console.error("[bank-transfer] không sao chép được", error);
  }
}
</script>

<template>
  <section
    v-if="info?.applicable"
    class="border border-outline-outline p-6"
    data-testid="bank-transfer"
  >
    <h2 class="font-serif text-2xl text-surface-on-surface mb-3">
      {{ $t("checkout.bankTransfer.title") }}
    </h2>

    <p
      v-if="info.instruction"
      class="text-sm text-surface-on-surface-variant leading-normal whitespace-pre-line mb-5"
    >
      {{ info.instruction }}
    </p>

    <p
      v-if="!info.configured"
      class="text-sm text-surface-on-surface"
      role="status"
    >
      {{ $t("checkout.bankTransfer.notConfigured") }}
    </p>

    <div v-else class="flex flex-col sm:flex-row gap-6">
      <img
        v-if="info.qrDataUri"
        :src="info.qrDataUri"
        :alt="$t('checkout.bankTransfer.qrAlt')"
        width="200"
        height="200"
        class="w-50 h-50 shrink-0 self-center sm:self-start"
      />

      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm w-full">
        <dt class="text-surface-on-surface-variant">
          {{ $t("checkout.bankTransfer.bank") }}
        </dt>
        <dd class="text-surface-on-surface">{{ info.bankName }}</dd>

        <dt class="text-surface-on-surface-variant">
          {{ $t("checkout.bankTransfer.accountHolder") }}
        </dt>
        <dd class="text-surface-on-surface">{{ info.accountHolder }}</dd>

        <template
          v-for="row in [
            {
              key: 'accountNumber',
              value: info.accountNumber,
              testid: 'bank-transfer-account-number',
            },
            {
              key: 'reference',
              value: info.reference,
              testid: 'bank-transfer-reference',
            },
          ]"
          :key="row.key"
        >
          <dt class="text-surface-on-surface-variant self-center">
            {{ $t(`checkout.bankTransfer.${row.key}`) }}
          </dt>
          <dd class="flex items-center gap-2 text-surface-on-surface">
            <span class="font-medium tracking-wide" :data-testid="row.testid">
              {{ row.value }}
            </span>
            <button
              type="button"
              class="inline-flex items-center justify-center min-w-11 min-h-11 -my-2 text-brand-primary hover:text-brand-primary-hover"
              :aria-label="`${$t('checkout.bankTransfer.copy')} ${$t(`checkout.bankTransfer.${row.key}`)}`"
              @click="copy(row.value, $t(`checkout.bankTransfer.${row.key}`))"
            >
              <span class="w-5 h-5 i-carbon-copy" aria-hidden="true" />
            </button>
          </dd>
        </template>

        <dt class="text-surface-on-surface-variant">
          {{ $t("checkout.bankTransfer.amount") }}
        </dt>
        <dd>
          <SharedPrice
            :value="info.amount"
            class="text-surface-on-surface font-medium"
            data-testid="bank-transfer-amount"
          />
        </dd>
      </dl>
    </div>
  </section>
</template>
