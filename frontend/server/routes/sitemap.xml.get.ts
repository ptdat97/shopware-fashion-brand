/**
 * Chỉ mục sitemap ở domain frontend (H7). Shopware sinh file sitemap cho channel headless
 * (type API, domain external storefront) — `bin/console sitemap:generate` hoặc scheduled
 * task — với URL trang theo domain frontend; file chỉ lấy được qua Store API.
 * Ở đây liệt kê các file đó và đổi đường dẫn sang /sitemap/… của frontend.
 */
type SitemapFile = { filename: string; created?: string };

export default defineEventHandler(async (event) => {
  const response = await storeApiRequest(event, "sitemap");
  if (!response.ok) {
    throw createError({
      statusCode: 502,
      statusMessage: "Sitemap unavailable",
    });
  }

  const files = (await response.json()) as SitemapFile[];
  const origin = getRequestURL(event).origin;

  const entries = files
    .map((file) => {
      // filename: http://<backend>/sitemap/salesChannel-…/….xml.gz
      const relative = file.filename.split("/sitemap/")[1];
      if (!relative) return "";
      const lastmod = file.created ? `<lastmod>${file.created}</lastmod>` : "";
      return `<sitemap><loc>${origin}/sitemap/${relative}</loc>${lastmod}</sitemap>`;
    })
    .filter(Boolean)
    .join("");

  setResponseHeaders(event, {
    "content-type": "application/xml; charset=utf-8",
    "cache-control": "public, max-age=3600",
  });

  return `<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${entries}</sitemapindex>`;
});
