import type { LocationQuery } from "vue-router";

import type { operations } from "#shopware";

type ListingCriteria =
  operations["readProductListing post /product-listing/{categoryId}"]["body"];

/**
 * Tham số listing (trang, số lượng, sắp xếp, bộ lọc) từ query URL — cùng quy ước với
 * CmsElementProductListing của cms-base-layer, để lần tải đầu (SSR) khớp với những gì
 * component đó tải lại khi khách đổi bộ lọc. Không gán thứ tự mặc định: Store API dùng
 * cấu hình sắp xếp của Administration.
 */
export function buildListingCriteria(
  query: LocationQuery,
  defaultLimit = 15,
): ListingCriteria {
  const criteria: Record<string, unknown> = {
    limit: toNumber(firstQueryValue(query.limit)) ?? defaultLimit,
    p: toNumber(firstQueryValue(query.p)) ?? 1,
  };

  const order = firstQueryValue(query.order);
  if (order) criteria.order = order;
  const properties = firstQueryValue(query.properties);
  if (properties) criteria.properties = properties;
  const minPrice = toNumber(firstQueryValue(query["min-price"]));
  if (minPrice !== undefined) criteria["min-price"] = minPrice;
  const maxPrice = toNumber(firstQueryValue(query["max-price"]));
  if (maxPrice !== undefined) criteria["max-price"] = maxPrice;
  const rating = toNumber(firstQueryValue(query.rating));
  if (rating !== undefined) criteria.rating = rating;
  if (query["shipping-free"])
    criteria["shipping-free"] =
      firstQueryValue(query["shipping-free"]) === "true";

  return criteria as ListingCriteria;
}
