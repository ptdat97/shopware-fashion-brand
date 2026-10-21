<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Chi tiết một thương hiệu: nhà sản xuất + trang CMS + meta SEO. Decorate được (API-02).
 */
abstract class AbstractBrandRoute
{
    abstract public function getDecorated(): AbstractBrandRoute;

    abstract public function load(string $manufacturerId, Request $request, SalesChannelContext $context): BrandRouteResponse;
}
