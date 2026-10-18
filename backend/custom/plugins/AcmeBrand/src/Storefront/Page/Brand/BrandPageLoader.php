<?php declare(strict_types=1);

namespace Acme\Brand\Storefront\Page\Brand;

use Acme\Brand\BrandCustomFields;
use Acme\Brand\BrandException;
use Acme\Brand\Core\Content\Brand\BrandEntity;
use Acme\Brand\Extension\ProductManufacturerBrandExtension;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Danh sách sản phẩm của brand đi qua đúng route listing của core
 * (`ProductListingRoute`), trên category gốc điều hướng của sales channel, kèm bộ lọc
 * cứng theo manufacturer. Nhờ vậy phân trang, sắp xếp, bộ lọc thuộc tính/giá, sự kiện
 * `ProductListingCriteriaEvent` đều là của core — không tự viết lại.
 *
 * Hệ quả: sản phẩm KHÔNG nằm trong cây danh mục của sales channel sẽ không hiện ở
 * trang brand. Đã kiểm chứng ở spike MB3 (ADR-003).
 *
 * @internal
 */
class BrandPageLoader
{
    private const DESCRIPTION_LENGTH = 160;

    /**
     * @param EntityRepository<ProductManufacturerCollection> $manufacturerRepository
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly EntityRepository $manufacturerRepository,
        private readonly AbstractProductListingRoute $listingRoute,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function loadList(Request $request, SalesChannelContext $context): BrandListPage
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('acmeBrand.active', true));
        $criteria->addAssociation('media');
        $criteria->addSorting(
            new FieldSorting('acmeBrand.featured', FieldSorting::DESCENDING),
            new FieldSorting('acmeBrand.position', FieldSorting::ASCENDING),
            new FieldSorting('name', FieldSorting::ASCENDING),
        );

        $page = BrandListPage::createFrom($this->genericPageLoader->load($request, $context));
        $page->setManufacturers($this->manufacturerRepository->search($criteria, $context->getContext())->getEntities());

        $meta = $page->getMetaInformation() ?? new MetaInformation();
        $meta->setMetaTitle($this->translator->trans('acme-brand.list.metaTitle'));
        $meta->setMetaDescription($this->translator->trans('acme-brand.list.metaDescription'));
        $page->setMetaInformation($meta);

        $this->eventDispatcher->dispatch(new BrandListPageLoadedEvent($page, $context, $request));

        return $page;
    }

    public function load(string $manufacturerId, Request $request, SalesChannelContext $context): BrandPage
    {
        $manufacturer = $this->loadActiveBrand($manufacturerId, $context);
        $brand = $manufacturer->getExtension(ProductManufacturerBrandExtension::PROPERTY_NAME);
        \assert($brand instanceof BrandEntity);

        $page = BrandPage::createFrom($this->genericPageLoader->load($request, $context));
        $page->setManufacturer($manufacturer);
        $page->setBrand($brand);
        $page->setCmsPage($this->loadCmsPage($brand, $request, $context));
        $page->setListing($this->loadListing($manufacturerId, $request, $context));
        $page->setMetaInformation($this->buildMeta($page->getMetaInformation(), $manufacturer));

        $this->eventDispatcher->dispatch(new BrandPageLoadedEvent($page, $context, $request));

        return $page;
    }

    /**
     * Dùng chung cho trang brand và route AJAX (lọc / phân trang / sắp xếp).
     */
    public function loadListing(string $manufacturerId, Request $request, SalesChannelContext $context): ProductListingResult
    {
        // Lọc theo brand trên trang của chính brand là vô nghĩa
        $request->request->set(ManufacturerListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM, false);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('manufacturerId', $manufacturerId));

        $rootCategoryId = $context->getSalesChannel()->getNavigationCategoryId();

        return $this->listingRoute->load($rootCategoryId, $request, $context, $criteria)->getResult();
    }

    /**
     * Brand không tồn tại, chưa có phần mở rộng, hoặc đang tắt → cùng một lỗi 404.
     */
    public function loadActiveBrand(string $manufacturerId, SalesChannelContext $context): ProductManufacturerEntity
    {
        $criteria = new Criteria([$manufacturerId]);
        $criteria->addFilter(new EqualsFilter('acmeBrand.active', true));
        $criteria->addAssociation('media');
        $criteria->addAssociation('acmeBrand.bannerMedia');

        $manufacturer = $this->manufacturerRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$manufacturer instanceof ProductManufacturerEntity) {
            throw BrandException::notFound($manufacturerId);
        }

        return $manufacturer;
    }

    private function loadCmsPage(BrandEntity $brand, Request $request, SalesChannelContext $context): ?CmsPageEntity
    {
        $cmsPageId = $brand->getCmsPageId();
        if ($cmsPageId === null) {
            return null;
        }

        return $this->cmsPageLoader->load($request, new Criteria([$cmsPageId]), $context)->getEntities()->first();
    }

    /**
     * Meta SEO nằm trong custom field dịch được của nhà sản xuất — cơ chế dịch và ô nhập
     * trong Administration đều là của core. Thiếu thì lấy từ tên / mô tả.
     */
    private function buildMeta(?MetaInformation $meta, ProductManufacturerEntity $manufacturer): MetaInformation
    {
        $meta ??= new MetaInformation();
        $name = (string) $manufacturer->getTranslation('name');
        $custom = $manufacturer->getTranslation('customFields') ?? [];

        $meta->setMetaTitle((string) (($custom[BrandCustomFields::META_TITLE] ?? '') ?: $name));

        $description = ($custom[BrandCustomFields::META_DESCRIPTION] ?? '')
            ?: mb_substr(trim(strip_tags((string) $manufacturer->getTranslation('description'))), 0, self::DESCRIPTION_LENGTH);
        $meta->setMetaDescription((string) $description);

        $meta->setMetaKeywords((string) (($custom[BrandCustomFields::KEYWORDS] ?? '') ?: $name));

        return $meta;
    }
}
