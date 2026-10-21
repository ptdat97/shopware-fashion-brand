<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sản phẩm của một thương hiệu (phân trang, lọc, sắp xếp của core). Decorate được (API-02).
 */
abstract class AbstractBrandListingRoute
{
    abstract public function getDecorated(): AbstractBrandListingRoute;

    abstract public function load(string $manufacturerId, Request $request, SalesChannelContext $context): ProductListingRouteResponse;
}
