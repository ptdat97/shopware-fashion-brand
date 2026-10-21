/**
 * Kiểu cho route Store API tự viết trong plugin backend — ghép vào kiểu của Shopware qua
 * WithApiOverrides (shopware.d.ts). Hợp đồng HTTP (API-04) của plugin, giữ khớp với:
 *   backend/custom/plugins/AcmeBrand/src/Core/Content/Brand/SalesChannel/*Route.php
 *   backend/custom/plugins/AcmePayment/src/Core/Checkout/Payment/SalesChannel/BankTransferRoute.php
 *   backend/custom/plugins/AcmeHeadless/src/System/SalesChannel/HeadlessShopSettings.php
 */
import type {
  components,
  operations as CoreOperations,
} from "@shopware/api-client/store-api-types";

type CoreSchemas = components["schemas"];

type CoreShopSettingsOperation =
  CoreOperations["getShopSettings get /shop-settings"];

/** `extensions.acmeHeadless` của /shop-settings (AcmeHeadless) — thiếu khi plugin tắt */
type AcmeHeadlessShopSettings = {
  apiAlias: "acme_headless_shop_settings";
  /** Logo trong Media; null → hiện tên shop dạng chữ */
  logo: CoreSchemas["Media"] | null;
  /** Quy tắc robots.txt của channel, nguyên văn */
  robotsRules: string;
  robotsDisableDefaults: boolean;
  staging: boolean;
};

/** Thông tin chuyển khoản của một đơn (AcmePayment) */
type AcmeBankTransfer = {
  apiAlias: "acme_payment_bank_transfer";
  /** false: đơn không trả bằng chuyển khoản (COD…) */
  applicable: boolean;
  /** false: chưa nhập tài khoản nhận — chỉ hiện hướng dẫn */
  configured: boolean;
  paymentMethodTechnicalName: string | null;
  bankBin: string;
  bankName: string;
  accountNumber: string;
  accountHolder: string;
  amount: number;
  /** Nội dung chuyển khoản = tiền tố + mã đơn */
  reference: string;
  instruction: string;
  /** Chỉ có với VietQR */
  qrDataUri: string | null;
};

/** Phần mở rộng `acme_brand` của nhà sản xuất (extensions.acmeBrand) */
type AcmeBrand = {
  id: string;
  productManufacturerId: string;
  code?: string | null;
  active: boolean;
  featured: boolean;
  position: number;
  bannerMediaId?: string | null;
  bannerMedia?: CoreSchemas["Media"] | null;
  cmsPageId?: string | null;
};

type AcmeBrandManufacturer = CoreSchemas["ProductManufacturer"] & {
  extensions?: {
    acmeBrand?: AcmeBrand;
  };
};

type AcmeBrandDetail = {
  apiAlias: "acme_brand_detail";
  manufacturer: AcmeBrandManufacturer;
  cmsPage: CoreSchemas["CmsPage"] | null;
  /** Đã áp quy tắc dự phòng ở backend (custom field → tên / mô tả) */
  metaTitle: string;
  metaDescription: string;
  metaKeywords: string;
};

export type Schemas = {
  AcmeBrand: AcmeBrand;
  AcmeBrandManufacturer: AcmeBrandManufacturer;
  AcmeBrandDetail: AcmeBrandDetail;
  AcmeHeadlessShopSettings: AcmeHeadlessShopSettings;
  AcmeBankTransfer: AcmeBankTransfer;
};

type BrandPathParams = {
  /** Id nhà sản xuất (thương hiệu = product_manufacturer, ADR-003) */
  manufacturerId: string;
};

export type operations = {
  /** Thương hiệu đang bật — nổi bật, vị trí, tên */
  "readAcmeBrandList post /acme/brand": {
    contentType?: "application/json";
    accept?: "application/json";
    response: {
      elements: AcmeBrandManufacturer[];
      total: number;
    };
    responseCode: 200;
  };
  /** Chi tiết thương hiệu; tắt / không có → 404 ACME_BRAND__NOT_FOUND */
  "readAcmeBrand post /acme/brand/{manufacturerId}": {
    contentType?: "application/json";
    accept?: "application/json";
    pathParams: BrandPathParams;
    response: AcmeBrandDetail;
    responseCode: 200;
  };
  /** Sản phẩm của thương hiệu — cùng tham số và định dạng với /product-listing */
  "readAcmeBrandListing post /acme/brand/{manufacturerId}/listing": {
    contentType?: "application/json";
    accept?: "application/json";
    headers?: {
      "sw-include-seo-urls"?: boolean;
    };
    pathParams: BrandPathParams;
    body: CoreSchemas["ProductListingCriteria"];
    response: CoreSchemas["ProductListingResult"];
    responseCode: 200;
  };
  /** Cấu hình shop của core + logo, robots (AcmeHeadless) */
  "getShopSettings get /shop-settings": Omit<
    CoreShopSettingsOperation,
    "response"
  > & {
    response: CoreShopSettingsOperation["response"] & {
      extensions?: {
        acmeHeadless?: AcmeHeadlessShopSettings;
      };
    };
  };
  /** Thông tin chuyển khoản của đơn của khách đang đăng nhập; không có → 404 ACME_PAYMENT__ORDER_NOT_FOUND */
  "readAcmeBankTransfer get /acme/payment/bank-transfer/{orderId}": {
    contentType?: "application/json";
    accept?: "application/json";
    pathParams: {
      orderId: string;
    };
    response: AcmeBankTransfer;
    responseCode: 200;
  };
};
