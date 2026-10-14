<?php declare(strict_types=1);

namespace Acme\Brand\Storefront\Page\Brand;

use Acme\Brand\Core\Content\Brand\BrandEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Storefront\Page\Page;

/**
 * Trang `/brands/{slug}`: thông tin brand + layout CMS (nếu có) + sản phẩm của brand.
 */
class BrandPage extends Page
{
    protected ProductManufacturerEntity $manufacturer;

    protected BrandEntity $brand;

    protected ProductListingResult $listing;

    protected ?CmsPageEntity $cmsPage = null;

    public function getManufacturer(): ProductManufacturerEntity
    {
        return $this->manufacturer;
    }

    public function setManufacturer(ProductManufacturerEntity $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getBrand(): BrandEntity
    {
        return $this->brand;
    }

    public function setBrand(BrandEntity $brand): void
    {
        $this->brand = $brand;
    }

    public function getListing(): ProductListingResult
    {
        return $this->listing;
    }

    public function setListing(ProductListingResult $listing): void
    {
        $this->listing = $listing;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(?CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }
}
