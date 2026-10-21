<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<ProductManufacturerCollection>>
 */
class BrandListRouteResponse extends StoreApiResponse
{
    public function getManufacturers(): ProductManufacturerCollection
    {
        return $this->object->getEntities();
    }
}
