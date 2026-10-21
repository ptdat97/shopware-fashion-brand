/**
 * Nhãn bộ lọc listing. @shopware/helpers lấy nhãn từ tên aggregation, nên các bộ lọc
 * không phải thuộc tính hiện mã thô (manufacturer, price, rating, shipping-free).
 * Dịch qua listing.filterLabels; nhóm thuộc tính (Màu sắc…) đã có tên dịch từ backend.
 */
export function useFilterLabel() {
  const { t, te } = useI18n();

  return (label: string): string => {
    const key = `listing.filterLabels.${label}`;
    return te(key) ? t(key) : label;
  };
}
