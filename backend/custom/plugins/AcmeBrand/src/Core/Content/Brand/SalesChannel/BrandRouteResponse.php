<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<BrandDetail>
 */
class BrandRouteResponse extends StoreApiResponse
{
    public function getBrandDetail(): BrandDetail
    {
        return $this->object;
    }
}
