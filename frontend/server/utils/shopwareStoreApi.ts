import type { H3Event } from "h3";

/**
 * Gọi Store API từ server Nitro. Dùng endpoint nội bộ (NUXT_SHOPWARE_ENDPOINT) nếu có —
 * server và backend thường chung mạng — không thì endpoint public.
 */
export function storeApiRequest(event: H3Event, path: string) {
  const config = useRuntimeConfig(event);
  const endpoint = config.shopware?.endpoint || config.public.shopware.endpoint;

  return fetch(new URL(path, endpoint), {
    headers: {
      "sw-access-key": config.public.shopware.accessToken,
      accept: "application/json",
    },
  });
}
