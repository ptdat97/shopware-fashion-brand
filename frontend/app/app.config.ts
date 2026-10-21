export default defineAppConfig({
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
