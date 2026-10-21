/**
 * File sitemap (.xml.gz) — proxy route `store-api.sitemap.file` của core. Core tự kiểm
 * file thuộc đúng sales channel; ở đây chỉ chặn đường dẫn lạ trước khi gọi.
 */
export default defineEventHandler(async (event) => {
  const path = getRouterParam(event, "path") ?? "";
  if (!/^salesChannel-[0-9a-f-]+\/[\w.-]+\.xml\.gz$/.test(path)) {
    throw createError({ statusCode: 404 });
  }

  const response = await storeApiRequest(event, `sitemap/${path}`);
  // Core trả 204 khi file không thuộc channel / không tồn tại
  if (response.status !== 200 || !response.body) {
    throw createError({ statusCode: 404 });
  }

  setResponseHeaders(event, {
    "content-type": "application/gzip",
    "cache-control": "public, max-age=3600",
  });

  return sendStream(event, response.body);
});
