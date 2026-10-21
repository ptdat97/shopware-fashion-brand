export default defineAppConfig({
  // Tên shop trong tiêu đề trang ("<trang> | <siteName>"). Tên nền tảng chưa chốt
  // (CLAUDE.md) — tạm ANIVELE như storefront Twig cũ; chốt tên thì chỉ sửa ở đây.
  siteName: "ANIVELE",

  // Default cache lifetime (in milliseconds) for CSR (Client-Side Rendering) data (24 hours)
  defaultCSRCacheLifetime: 86400000,

  // Image placeholder configuration (cms-base-layer uses this setting)
  imagePlaceholder: {
    color: "#f0e6d2", // kem — nền chờ ảnh sản phẩm, như theme Twig
  },
  backgroundImage: {
    format: "webp",
    quality: 80,
  },
});
