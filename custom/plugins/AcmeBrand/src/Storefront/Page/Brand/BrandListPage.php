<?php declare(strict_types=1);

namespace Acme\Brand\Storefront\Page\Brand;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Storefront\Page\Page;

/**
 * Trang `/brands`: mọi thương hiệu đang hoạt động.
 */
class BrandListPage extends Page
{
    protected ProductManufacturerCollection $manufacturers;

    public function getManufacturers(): ProductManufacturerCollection
    {
        return $this->manufacturers;
    }

    public function setManufacturers(ProductManufacturerCollection $manufacturers): void
    {
        $this->manufacturers = $manufacturers;
    }
}
