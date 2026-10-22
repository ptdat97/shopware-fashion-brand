<script setup lang="ts">
import { isTimeoutError } from "@shopware/api-client";

definePageMeta({
  layout: "checkout",
});

const {
  shippingMethods,
  getShippingMethods,
  paymentMethods,
  getPaymentMethods,
  setShippingMethod,
  setPaymentMethod,
  createOrder,
} = useCheckout();

const {
  selectedPaymentMethod: sessionSelectedPaymentMethod,
  selectedShippingMethod: sessionSelectedShippingMethod,
} = useSessionContext();

const { changeProductQuantity, removeItemById, isEmpty, refreshCart, cart } =
  useCart();

const {
  register,
  refreshUser,
  updatePersonalInfo,
  user,
  isLoggedIn,
  isGuestSession,
  userDefaultBillingAddress,
} = useUser();
const { updateCustomerAddress } = useAddress();
const { handleApiError: handleRegistrationError } = useApiErrorsResolver(
  "checkout_registration_form",
);
const { handleApiError: handlePlaceOrderError } = useApiErrorsResolver(
  "checkout_place_order",
);
const { pushError } = useNotifications();

const {
  selectedShippingMethod,
  selectedPaymentMethod,
  canPlaceOrder,
  $vBaseInfo,
  $vBillingAddress,
  customerBaseInfo,
  billingAddress,
  createAccount,
  countryHasStates,
} = useTemplateCheckout();

const isUserSession = computed(() => isLoggedIn.value || isGuestSession.value);

// Các PATCH /context đang chạy (chọn thanh toán / vận chuyển, tỉnh). Đăng ký khách vãng lai
// sao chép ngữ cảnh sang token mới ngay khi bắt đầu — PATCH chưa xong thì lựa chọn mất: khách
// chọn "Chuyển khoản trước", bấm đặt hàng ngay, đơn thành COD (Playwright bắt được 22/10/2026,
// khoảng 1/8 lần chạy; mạng di động chậm còn dễ gặp hơn).
const pendingContextUpdates = new Set<Promise<unknown>>();
function trackContextUpdate<T>(update: Promise<T>): Promise<T> {
  pendingContextUpdates.add(update);
  update.finally(() => pendingContextUpdates.delete(update)).catch(() => {});
  return update;
}
async function settleContextUpdates() {
  await Promise.allSettled([...pendingContextUpdates]);
}

// Khách vãng lai chưa có địa chỉ trong ngữ cảnh → backend tính phí ship theo ngữ cảnh mặc định
// (chưa có tỉnh): khách Hà Nội thấy 35.000 ₫ trong khi đơn tính 25.000 ₫ (H8). Báo quốc gia /
// tỉnh vừa chọn lên ngữ cảnh rồi tải lại giỏ — phí do backend tính lại (quy tắc cứng số 8).
const { apiClient } = useShopwareContext();
watch(
  () => [billingAddress.value.countryId, billingAddress.value.countryStateId],
  async ([countryId, countryStateId]) => {
    if (isUserSession.value || !countryId) return;
    try {
      await trackContextUpdate(
        apiClient.invoke("updateContext patch /context", {
          body: {
            countryId,
            ...(countryStateId ? { countryStateId } : {}),
          },
        }),
      );
      await refreshCart();
    } catch (error) {
      console.error(
        "[checkout] cập nhật tỉnh/thành vào ngữ cảnh thất bại",
        error,
      );
    }
  },
);
const registeredDuringCheckout = ref(false);
const showCustomerForm = computed(
  () => !isUserSession.value || registeredDuringCheckout.value,
);
const localePath = useLocalePath();
const { formatLink } = useInternationalization(localePath);
const { push } = useRouter();
const { t } = useI18n();

function handleRemoveItem(id: string) {
  removeItemById(id);
}

function handleUpdateQuantity(id: string, quantity: number) {
  changeProductQuantity({ id, quantity });
}

function persistentError(message: string) {
  pushError(message, { persistent: true });
}

function restoreFocus(trigger: Element | null) {
  if (trigger instanceof HTMLElement) {
    nextTick(() => trigger.focus());
  }
}

function focusFirstInvalid() {
  nextTick(() => {
    document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
  });
}

const isPlacingOrder = ref(false);

async function validateCustomerForm() {
  const [baseInfoResult, billingAddressResult] = await Promise.allSettled([
    $vBaseInfo.$validate(),
    $vBillingAddress.$validate(),
  ]);

  const isBaseInfoValid =
    baseInfoResult.status === "fulfilled" && baseInfoResult.value.valid;
  const isBillingAddressValid =
    billingAddressResult.status === "fulfilled" &&
    billingAddressResult.value.valid;

  if (!isBaseInfoValid || !isBillingAddressValid) {
    focusFirstInvalid();
    return false;
  }

  return true;
}

function getBillingAddressFields() {
  return {
    firstName: billingAddress.value.firstName,
    lastName: billingAddress.value.lastName,
    street: billingAddress.value.street,
    zipcode: billingAddress.value.zipcode,
    city: billingAddress.value.city,
    countryId: billingAddress.value.countryId,
    countryStateId: billingAddress.value.countryStateId || undefined,
    phoneNumber: billingAddress.value.phoneNumber || undefined,
  };
}

async function updateCustomerDetails() {
  const existingAddress = userDefaultBillingAddress.value;
  if (existingAddress) {
    await updateCustomerAddress({
      ...existingAddress,
      ...getBillingAddressFields(),
    });
  }

  await updatePersonalInfo({
    firstName: billingAddress.value.firstName,
    lastName: billingAddress.value.lastName,
    ...(user.value?.salutationId
      ? { salutationId: user.value.salutationId }
      : {}),
  });

  await refreshUser();
}

async function saveCustomerDetails() {
  // Lựa chọn của khách trên màn hình — nguồn đúng, kể cả khi PATCH trước đó chưa kịp ghi
  const chosenPaymentMethod = selectedPaymentMethod.value;
  const chosenShippingMethod = selectedShippingMethod.value;
  await settleContextUpdates();

  if (isUserSession.value) {
    await updateCustomerDetails();
  } else {
    await register({
      accountType: "private",
      firstName: billingAddress.value.firstName,
      lastName: billingAddress.value.lastName,
      email: customerBaseInfo.value.email,
      password: createAccount.value ? customerBaseInfo.value.password : "",
      guest: !createAccount.value,
      billingAddress: {
        customerId: "",
        id: "",
        ...getBillingAddressFields(),
      },
      acceptedDataProtection: true,
    });
    registeredDuringCheckout.value = true;
  }

  await Promise.allSettled([
    getShippingMethods({ forceReload: true }),
    getPaymentMethods({ forceReload: true }),
  ]);

  // Ngữ cảnh mới sau đăng ký có thể mang phương thức mặc định (COD) — áp lại lựa chọn của
  // khách nếu vẫn hợp lệ. Backend từ chối (phương thức không còn khả dụng) thì giữ của ngữ cảnh.
  if (
    chosenPaymentMethod &&
    sessionSelectedPaymentMethod.value?.id !== chosenPaymentMethod
  ) {
    await setPaymentMethod({ id: chosenPaymentMethod }).catch(() => {});
  }
  if (
    chosenShippingMethod &&
    sessionSelectedShippingMethod.value?.id !== chosenShippingMethod
  ) {
    await setShippingMethod({ id: chosenShippingMethod }).catch(() => {});
  }

  if (sessionSelectedPaymentMethod.value) {
    selectedPaymentMethod.value = sessionSelectedPaymentMethod.value.id;
  }
  if (sessionSelectedShippingMethod.value) {
    selectedShippingMethod.value = sessionSelectedShippingMethod.value.id;
  }
}

async function handlePlaceOrder() {
  if (isPlacingOrder.value) return;

  if (showCustomerForm.value) {
    const isValid = await validateCustomerForm();
    if (!isValid) return;
  }

  if (!canPlaceOrder.value) return;

  isPlacingOrder.value = true;
  const trigger = document.activeElement;
  let savingCustomerDetails = false;
  try {
    if (showCustomerForm.value) {
      savingCustomerDetails = true;
      await saveCustomerDetails();
      savingCustomerDetails = false;
    }

    if (!canPlaceOrder.value) return;

    const order = await createOrder();
    await push(formatLink(`/checkout/success/${order.id}`));
    await refreshCart();
  } catch (error) {
    if (isTimeoutError(error)) {
      persistentError(t("errors.order-timeout"));
    } else if (savingCustomerDetails) {
      handleRegistrationError(error, persistentError);
    } else {
      handlePlaceOrderError(error, persistentError);
    }
    restoreFocus(trigger);
  } finally {
    isPlacingOrder.value = false;
  }
}

function handleChangeShippingMethod(id: string) {
  trackContextUpdate(setShippingMethod({ id }));
}

function handleChangePaymentMethod(id: string) {
  trackContextUpdate(setPaymentMethod({ id }));
}

onMounted(() => {
  getShippingMethods();
  getPaymentMethods();

  if (userDefaultBillingAddress.value) {
    billingAddress.value = userDefaultBillingAddress.value;
  }

  if (sessionSelectedPaymentMethod.value) {
    selectedPaymentMethod.value = sessionSelectedPaymentMethod.value.id;
  }

  if (sessionSelectedShippingMethod.value) {
    selectedShippingMethod.value = sessionSelectedShippingMethod.value.id;
  }
});
</script>
<template>
  <div class="container mx-auto">
    <h1 v-if="!isEmpty" class="text-[2.5rem] my-20 font-serif">
      {{ $t("checkout.title") }}
    </h1>

    <div v-if="isEmpty" class="flex flex-col items-center justify-center py-20">
      <p class="text-surface-on-surface text-lg mb-6">
        {{ $t("cart.emptyCartLabel") }}
      </p>
      <NuxtLink
        :to="formatLink('/')"
        class="bg-brand-primary text-brand-on-primary text-center font-bold leading-6 py-3 px-4 rounded-md"
      >
        {{ $t("cart.continueShopping") }}
      </NuxtLink>
    </div>

    <!-- Mobile-first: xếp dọc trên điện thoại (Tóm tắt lên đầu để thấy tổng tiền trước khi
         điền form), hai cột từ lg. Template gốc cố định hai cột w-1/2 → form bị ép ~200px. -->
    <div
      v-else
      class="flex flex-col lg:flex-row gap-10 lg:gap-20 justify-between"
    >
      <div class="w-full lg:w-1/2 relative">
        <div
          v-if="isPlacingOrder"
          class="absolute inset-0 z-10 flex items-center justify-center bg-surface-surface/70 backdrop-blur-[1px] cursor-wait"
          role="status"
          :aria-label="$t('checkout.placingOrder')"
        >
          <div class="flex flex-col items-center gap-3">
            <span
              class="w-8 h-8 border-2 border-brand-primary border-t-transparent rounded-full animate-spin"
              aria-hidden="true"
            />
            <span class="text-sm font-bold text-surface-on-surface">
              {{ $t("checkout.placingOrder") }}
            </span>
          </div>
        </div>
        <CheckoutStepHeader
          :step="1"
          :label="$t('checkout.shippingAddressLabel')"
        >
          <div v-if="showCustomerForm" class="flex flex-col">
            <CheckoutCustomerBaseInfo
              class="mb-4"
              v-model:email="customerBaseInfo.email"
              v-model:password="customerBaseInfo.password"
              v-model:createAccount="createAccount"
              :validation="$vBaseInfo"
            />
            <CheckoutCustomerAddress
              class="mb-4"
              v-model="billingAddress"
              :validation="$vBillingAddress"
              @states-change="countryHasStates = $event.length > 0"
            />
          </div>

          <CheckoutCustomerAddressChosen v-else :address="billingAddress" />
        </CheckoutStepHeader>
        <CheckoutStepHeader
          :step="2"
          :label="$t('checkout.shippingMethodLabel')"
        >
          <CheckoutShippingMethods
            :shippingMethods="shippingMethods"
            v-model:selectedShippingMethod="selectedShippingMethod"
            @change="handleChangeShippingMethod"
          />
        </CheckoutStepHeader>
        <CheckoutStepHeader
          :step="3"
          :label="$t('checkout.paymentMethodLabel')"
        >
          <CheckoutPaymentMethods
            :paymentMethods="paymentMethods"
            v-model:selectedPaymentMethod="selectedPaymentMethod"
            @change="handleChangePaymentMethod"
          />
        </CheckoutStepHeader>
        <FormBaseButton
          type="button"
          data-testid="checkout-place-order-button"
          :label="
            isPlacingOrder
              ? $t('checkout.placingOrder')
              : $t('checkout.placeOrderButton')
          "
          :loading="isPlacingOrder"
          :disabled="isUserSession && !canPlaceOrder"
          @click="handlePlaceOrder"
        />
      </div>
      <div class="w-full lg:w-1/2 relative order-first lg:order-none">
        <div
          v-if="isPlacingOrder"
          class="absolute inset-0 z-10 bg-surface-surface/70 backdrop-blur-[1px] cursor-wait"
          aria-hidden="true"
        />
        <CheckoutSummaryBox
          v-if="cart"
          :cart="cart"
          @remove="handleRemoveItem"
          @updateQuantity="handleUpdateQuantity"
        />
      </div>
    </div>
  </div>
</template>
