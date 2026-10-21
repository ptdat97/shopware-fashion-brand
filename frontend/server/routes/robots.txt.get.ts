/**
 * robots.txt ở domain frontend (H7). Quy tắc do backend quyết (quy tắc cứng số 8, L4):
 * Administration → Settings → Basic information → "Rules for robots.txt" của channel
 * Headless, đọc qua /store-api/shop-settings → extensions.acmeHeadless (plugin AcmeHeadless).
 *
 * Frontend chỉ ghép như template robots.txt.twig của storefront: "User-agent: *" + "Allow: /"
 * (trừ khi bật "Disable default robots.txt rules"), staging → chặn tất cả, rồi quy tắc của
 * channel, cuối cùng dòng Sitemap — URL tuyệt đối theo domain đang phục vụ.
 */
type HeadlessSettings = {
  robotsRules?: string;
  robotsDisableDefaults?: boolean;
  staging?: boolean;
};

export default defineEventHandler(async (event) => {
  const origin = getRequestURL(event).origin;

  const response = await storeApiRequest(event, "shop-settings");
  if (!response.ok) {
    // Không đọc được cấu hình → không trả robots.txt "cho phép tất cả" thay cho quy tắc thật
    throw createError({
      statusCode: 502,
      statusMessage: "Robots rules unavailable",
    });
  }

  const settings = (await response.json()) as {
    extensions?: { acmeHeadless?: HeadlessSettings };
  };
  const headless = settings.extensions?.acmeHeadless ?? {};

  const lines: string[] = [];
  if (!headless.robotsDisableDefaults) {
    lines.push("User-agent: *", "Allow: /");
  }
  if (headless.staging) {
    lines.push("Disallow: /");
  } else {
    if (headless.robotsRules)
      lines.push(...headless.robotsRules.split(/\r?\n/));
    lines.push("", `Sitemap: ${origin}/sitemap.xml`);
  }

  setResponseHeaders(event, {
    "content-type": "text/plain; charset=utf-8",
    "cache-control": "public, max-age=3600",
  });

  return lines.join("\n") + "\n";
});
