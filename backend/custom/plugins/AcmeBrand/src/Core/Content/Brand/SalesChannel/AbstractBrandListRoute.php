<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Danh sách thương hiệu đang bật. Decorate được (API-02).
 */
abstract class AbstractBrandListRoute
{
    abstract public function getDecorated(): AbstractBrandListRoute;

    abstract public function load(Request $request, SalesChannelContext $context): BrandListRouteResponse;
}
