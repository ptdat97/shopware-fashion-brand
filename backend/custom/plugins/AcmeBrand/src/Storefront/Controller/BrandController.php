<?php declare(strict_types=1);

namespace Acme\Brand\Storefront\Controller;

use Acme\Brand\Storefront\Page\Brand\BrandPageLoader;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Các trang này vẫn qua HTTP cache của storefront. Cache tag của core tự gắn theo
 * entity đã đọc, nên sửa brand (kể cả phần mở rộng acme_brand) qua DAL/Admin là trang
 * cập nhật ngay — đã kiểm ở MB3. Riêng khi đổi TEMPLATE/code phải chạy
 * `bin/console cache:clear:http`: `cache:clear` không xoá HTTP cache.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class BrandController extends StorefrontController
{
    public function __construct(private readonly BrandPageLoader $pageLoader)
    {
    }

    #[Route(path: '/brands', name: 'frontend.acme.brand.list', methods: [Request::METHOD_GET])]
    public function list(Request $request, SalesChannelContext $context): Response
    {
        return $this->renderStorefront('@AcmeBrand/storefront/page/brand/list.html.twig', [
            'page' => $this->pageLoader->loadList($request, $context),
        ]);
    }

    /**
     * URL thật là `/brands/{slug}` — bản ghi seo_url ánh xạ về đường dẫn kỹ thuật này.
     */
    #[Route(path: '/brand/{manufacturerId}', name: 'frontend.acme.brand.page', methods: [Request::METHOD_GET])]
    public function detail(string $manufacturerId, Request $request, SalesChannelContext $context): Response
    {
        return $this->renderStorefront('@AcmeBrand/storefront/page/brand/detail.html.twig', [
            'page' => $this->pageLoader->load($manufacturerId, $request, $context),
        ]);
    }

    /**
     * Listing JS của Storefront gọi route này khi lọc / sắp xếp / sang trang, rồi thay
     * khối `.cms-element-product-listing` bằng HTML trả về.
     */
    #[Route(
        path: '/widgets/brand/{manufacturerId}/listing',
        name: 'frontend.acme.brand.listing',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function listing(string $manufacturerId, Request $request, SalesChannelContext $context): Response
    {
        // Brand tắt → 404 do BrandListingRoute kiểm
        $response = $this->renderStorefront('@AcmeBrand/storefront/page/brand/listing.html.twig', [
            'manufacturerId' => $manufacturerId,
            'searchResult' => $this->pageLoader->loadListing($manufacturerId, $request, $context),
        ]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }
}
