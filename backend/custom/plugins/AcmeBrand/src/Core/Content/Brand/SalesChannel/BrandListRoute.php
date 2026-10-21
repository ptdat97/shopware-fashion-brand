<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET|POST /store-api/acme/brand — mọi thương hiệu đang bật, nổi bật trước, rồi theo
 * vị trí, rồi theo tên (thứ tự người vận hành đặt trong thẻ "Thương hiệu" ở Admin).
 * Kèm logo (`media`) và phần mở rộng `extensions.acmeBrand`. Số brand nhỏ → trả hết.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BrandListRoute extends AbstractBrandListRoute
{
    /**
     * @param EntityRepository<ProductManufacturerCollection> $manufacturerRepository
     */
    public function __construct(private readonly EntityRepository $manufacturerRepository)
    {
    }

    public function getDecorated(): AbstractBrandListRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/acme/brand', name: 'store-api.acme.brand.list', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function load(Request $request, SalesChannelContext $context): BrandListRouteResponse
    {
        $criteria = new Criteria();
        $criteria->setTitle('acme-brand::list');
        $criteria->addFilter(new EqualsFilter('acmeBrand.active', true));
        $criteria->addAssociation('media');
        $criteria->addAssociation('acmeBrand');
        $criteria->addSorting(
            new FieldSorting('acmeBrand.featured', FieldSorting::DESCENDING),
            new FieldSorting('acmeBrand.position', FieldSorting::ASCENDING),
            new FieldSorting('name', FieldSorting::ASCENDING),
        );

        return new BrandListRouteResponse($this->manufacturerRepository->search($criteria, $context->getContext()));
    }
}
