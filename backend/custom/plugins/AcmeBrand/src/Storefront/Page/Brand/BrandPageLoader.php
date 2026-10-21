<?php declare(strict_types=1);

namespace Acme\Brand\Storefront\Page\Brand;

use Acme\Brand\Core\Content\Brand\BrandEntity;
use Acme\Brand\Core\Content\Brand\SalesChannel\AbstractBrandListingRoute;
use Acme\Brand\Core\Content\Brand\SalesChannel\AbstractBrandListRoute;
use Acme\Brand\Core\Content\Brand\SalesChannel\AbstractBrandRoute;
use Acme\Brand\Core\Content\Brand\SalesChannel\ActiveBrandLoader;
use Acme\Brand\Extension\ProductManufacturerBrandExtension;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Trang thương hiệu Twig — mỏng: dữ liệu lấy từ các route Store API của plugin
 * (BrandListRoute, BrandRoute, BrandListingRoute), giống CategoryPageLoader của core gọi
 * CategoryRoute. Frontend headless gọi đúng các route đó, nên quy tắc brand (đang bật,
 * listing trên category gốc + lọc manufacturer, meta dự phòng) chỉ nằm một chỗ.
 *
 * @internal
 */
class BrandPageLoader
{
    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly AbstractBrandListRoute $brandListRoute,
        private readonly AbstractBrandRoute $brandRoute,
        private readonly AbstractBrandListingRoute $brandListingRoute,
        private readonly ActiveBrandLoader $activeBrandLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function loadList(Request $request, SalesChannelContext $context): BrandListPage
    {
        $page = BrandListPage::createFrom($this->genericPageLoader->load($request, $context));
        $page->setManufacturers($this->brandListRoute->load($request, $context)->getManufacturers());

        $meta = $page->getMetaInformation() ?? new MetaInformation();
        $meta->setMetaTitle($this->translator->trans('acme-brand.list.metaTitle'));
        $meta->setMetaDescription($this->translator->trans('acme-brand.list.metaDescription'));
        $page->setMetaInformation($meta);

        $this->eventDispatcher->dispatch(new BrandListPageLoadedEvent($page, $context, $request));

        return $page;
    }

    public function load(string $manufacturerId, Request $request, SalesChannelContext $context): BrandPage
    {
        $detail = $this->brandRoute->load($manufacturerId, $request, $context)->getBrandDetail();
        $manufacturer = $detail->getManufacturer();
        $brand = $manufacturer->getExtension(ProductManufacturerBrandExtension::PROPERTY_NAME);
        \assert($brand instanceof BrandEntity);

        $page = BrandPage::createFrom($this->genericPageLoader->load($request, $context));
        $page->setManufacturer($manufacturer);
        $page->setBrand($brand);
        $page->setCmsPage($detail->getCmsPage());
        $page->setListing($this->loadListing($manufacturerId, $request, $context));

        $meta = $page->getMetaInformation() ?? new MetaInformation();
        $meta->setMetaTitle($detail->getMetaTitle());
        $meta->setMetaDescription($detail->getMetaDescription());
        $meta->setMetaKeywords($detail->getMetaKeywords());
        $page->setMetaInformation($meta);

        $this->eventDispatcher->dispatch(new BrandPageLoadedEvent($page, $context, $request));

        return $page;
    }

    /**
     * Dùng chung cho trang brand và route AJAX (lọc / phân trang / sắp xếp).
     */
    public function loadListing(string $manufacturerId, Request $request, SalesChannelContext $context): ProductListingResult
    {
        return $this->brandListingRoute->load($manufacturerId, $request, $context)->getResult();
    }

    /**
     * Brand không tồn tại, chưa có phần mở rộng, hoặc đang tắt → cùng một lỗi 404.
     */
    public function loadActiveBrand(string $manufacturerId, SalesChannelContext $context): ProductManufacturerEntity
    {
        return $this->activeBrandLoader->load($manufacturerId, $context->getContext());
    }
}
