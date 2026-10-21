<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\SalesChannel;

use Acme\Brand\BrandCustomFields;
use Acme\Brand\Core\Content\Brand\BrandEntity;
use Acme\Brand\Extension\ProductManufacturerBrandExtension;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET|POST /store-api/acme/brand/{manufacturerId} — brand đang bật, kèm trang CMS và meta.
 * Brand tắt / không có → 404 ACME_BRAND__NOT_FOUND.
 *
 * Tên route `store-api.acme.brand.detail` cũng là tên route SEO headless
 * (BrandStoreApiUrlRoute): seo_url.pathInfo của channel API trỏ về route này.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BrandRoute extends AbstractBrandRoute
{
    final public const ROUTE_NAME = 'store-api.acme.brand.detail';

    private const DESCRIPTION_LENGTH = 160;

    public function __construct(
        private readonly ActiveBrandLoader $activeBrandLoader,
        private readonly SalesChannelCmsPageLoaderInterface $cmsPageLoader,
    ) {
    }

    public function getDecorated(): AbstractBrandRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/acme/brand/{manufacturerId}', name: self::ROUTE_NAME, methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function load(string $manufacturerId, Request $request, SalesChannelContext $context): BrandRouteResponse
    {
        $manufacturer = $this->activeBrandLoader->load($manufacturerId, $context->getContext());
        $brand = $manufacturer->getExtension(ProductManufacturerBrandExtension::PROPERTY_NAME);
        \assert($brand instanceof BrandEntity);

        $name = (string) $manufacturer->getTranslation('name');
        $custom = $manufacturer->getTranslation('customFields') ?? [];

        return new BrandRouteResponse(new BrandDetail(
            $manufacturer,
            $this->loadCmsPage($brand, $request, $context),
            (string) (($custom[BrandCustomFields::META_TITLE] ?? '') ?: $name),
            $this->metaDescription($manufacturer, $custom),
            (string) (($custom[BrandCustomFields::KEYWORDS] ?? '') ?: $name),
        ));
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
     * Meta SEO nằm trong custom field dịch được của nhà sản xuất (ADR-003). Thiếu thì lấy
     * đoạn đầu của mô tả.
     *
     * @param array<string, mixed> $custom
     */
    private function metaDescription(ProductManufacturerEntity $manufacturer, array $custom): string
    {
        $description = ($custom[BrandCustomFields::META_DESCRIPTION] ?? '')
            ?: mb_substr(trim(strip_tags((string) $manufacturer->getTranslation('description'))), 0, self::DESCRIPTION_LENGTH);

        return (string) $description;
    }
}
