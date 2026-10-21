<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Acme\Brand\BrandException;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Quy tắc "brand nào được hiển thị": có phần mở rộng acme_brand và đang bật.
 * Không tồn tại, chưa có phần mở rộng, hoặc đang tắt → cùng một lỗi 404 (ADR-003:
 * brand tắt chỉ ẩn trang brand, sản phẩm vẫn bán).
 *
 * Một chỗ duy nhất cho quy tắc này — route chi tiết, route listing và trang Twig dùng chung.
 *
 * @internal
 */
class ActiveBrandLoader
{
    /**
     * @param EntityRepository<ProductManufacturerCollection> $manufacturerRepository
     */
    public function __construct(private readonly EntityRepository $manufacturerRepository)
    {
    }

    /**
     * Nhà sản xuất kèm logo, phần mở rộng brand và banner — đủ để vẽ trang brand.
     */
    public function load(string $manufacturerId, Context $context): ProductManufacturerEntity
    {
        $criteria = $this->activeCriteria($manufacturerId);
        $criteria->setTitle('acme-brand::detail');
        $criteria->addAssociation('media');
        $criteria->addAssociation('acmeBrand.bannerMedia');

        $manufacturer = $this->manufacturerRepository->search($criteria, $context)->getEntities()->first();
        if (!$manufacturer instanceof ProductManufacturerEntity) {
            throw BrandException::notFound($manufacturerId);
        }

        return $manufacturer;
    }

    /**
     * Chỉ kiểm tra, không tải dữ liệu (route listing không cần logo/banner — PERF-01).
     */
    public function assertActive(string $manufacturerId, Context $context): void
    {
        $criteria = $this->activeCriteria($manufacturerId);
        $criteria->setTitle('acme-brand::assert-active');

        if ($this->manufacturerRepository->searchIds($criteria, $context)->getTotal() === 0) {
            throw BrandException::notFound($manufacturerId);
        }
    }

    private function activeCriteria(string $manufacturerId): Criteria
    {
        // Id sai định dạng (gõ tay URL) cũng là "không có brand này" — 404, không phải 400
        if (!Uuid::isValid($manufacturerId)) {
            throw BrandException::notFound($manufacturerId);
        }

        $criteria = new Criteria([$manufacturerId]);
        $criteria->addFilter(new EqualsFilter('acmeBrand.active', true));

        return $criteria;
    }
}
