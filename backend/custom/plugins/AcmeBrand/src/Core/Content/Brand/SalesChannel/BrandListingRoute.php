<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET|POST /store-api/acme/brand/{manufacturerId}/listing — nhận cùng tham số với
 * /store-api/product-listing (p, limit, order, properties, min-price…) và trả cùng
 * định dạng, nên component listing của frontend dùng lại được.
 *
 * Đi qua đúng route listing của core trên category gốc điều hướng của sales channel, kèm
 * bộ lọc cứng theo manufacturer — phân trang, sắp xếp, bộ lọc, ProductListingCriteriaEvent
 * đều là của core. Hệ quả (spike MB3, ADR-003): sản phẩm không nằm trong cây danh mục của
 * channel không hiện ở trang brand.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BrandListingRoute extends AbstractBrandListingRoute
{
    public function __construct(
        private readonly ActiveBrandLoader $activeBrandLoader,
        private readonly AbstractProductListingRoute $listingRoute,
    ) {
    }

    public function getDecorated(): AbstractBrandListingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/acme/brand/{manufacturerId}/listing', name: 'store-api.acme.brand.listing', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function load(string $manufacturerId, Request $request, SalesChannelContext $context): ProductListingRouteResponse
    {
        $this->activeBrandLoader->assertActive($manufacturerId, $context->getContext());

        // Lọc theo brand trên trang của chính brand là vô nghĩa
        $request->request->set(ManufacturerListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM, false);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('manufacturerId', $manufacturerId));

        return $this->listingRoute->load($context->getSalesChannel()->getNavigationCategoryId(), $request, $context, $criteria);
    }
}
