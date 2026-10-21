<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Dữ liệu trang thương hiệu, dùng chung cho Store API (frontend headless) và trang Twig.
 * Meta đã áp quy tắc dự phòng (custom field → tên / mô tả) — frontend không tự tính.
 */
class BrandDetail extends Struct
{
    public function __construct(
        protected ProductManufacturerEntity $manufacturer,
        protected ?CmsPageEntity $cmsPage,
        protected string $metaTitle,
        protected string $metaDescription,
        protected string $metaKeywords,
    ) {
    }

    public function getManufacturer(): ProductManufacturerEntity
    {
        return $this->manufacturer;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function getMetaTitle(): string
    {
        return $this->metaTitle;
    }

    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    public function getMetaKeywords(): string
    {
        return $this->metaKeywords;
    }

    public function getApiAlias(): string
    {
        return 'acme_brand_detail';
    }
}
