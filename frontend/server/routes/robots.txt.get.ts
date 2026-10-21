/**
 * robots.txt ở domain frontend (H7). Sinh động vì dòng Sitemap cần URL tuyệt đối mà
 * mỗi môi trường một domain.
 *
 * Chặn trang không có giá trị tìm kiếm và trang riêng của khách: tài khoản, giỏ/thanh
 * toán, yêu thích, kết quả tìm kiếm nội bộ (tốn ngân sách thu thập, nội dung trùng).
 */
export default defineEventHandler((event) => {
  const origin = getRequestURL(event).origin;

  setResponseHeaders(event, {
    "content-type": "text/plain; charset=utf-8",
    "cache-control": "public, max-age=86400",
  });

  return [
    "User-agent: *",
    "Disallow: /account",
    "Disallow: /checkout",
    "Disallow: /wishlist",
    "Disallow: /search",
    "",
    `Sitemap: ${origin}/sitemap.xml`,
    "",
  ].join("\n");
});
