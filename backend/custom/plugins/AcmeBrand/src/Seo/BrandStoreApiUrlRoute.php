<?php declare(strict_types=1);

namespace Acme\Brand\Seo;

use Acme\Brand\Core\Content\Brand\SalesChannel\BrandRoute;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * SEO URL thương hiệu `/brands/{slug}` cho channel headless (type API), theo cùng cơ chế
 * core dùng cho sản phẩm/danh mục (ProductStoreApiUrlRoute…): tag
 * `shopware.entity.seo_url.route`, template `seo_url_template.is_headless = 1`, URL chỉ
 * sinh cho domain external storefront.
 *
 * Slug của thương hiệu chính là bản ghi `seo_url` này — không lưu cột slug riêng (ADR-003).
 * Chỉ sinh URL cho brand đang hoạt động.
 *
 * seo_url.pathInfo trỏ về route Store API BrandRoute (`/store-api/acme/brand/{id}`);
 * frontend tra SEO path → routeName `store-api.acme.brand.detail` + foreignKey.
 *
 * Cài thêm SeoUrlRouteInterface để template dùng biến `manufacturer` (mặc định core đặt tên
 * biến theo entity: `productManufacturer`). Bản storefront Twig (`frontend.acme.brand.page`)
 * đã bỏ ở 2.0.0 — ADR-006.
 *
 * @internal
 */
class BrandStoreApiUrlRoute implements EntitySeoUrlRouteInterface, SeoUrlRouteInterface
{
    final public const ROUTE_NAME = BrandRoute::ROUTE_NAME;

    final public const DEFAULT_TEMPLATE = 'brands/{{ manufacturer.translated.name|lower }}';

    public function __construct(private readonly ProductManufacturerDefinition $manufacturerDefinition)
    {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->manufacturerDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true,
            'manufacturerId'
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addFilter(new EqualsFilter('acmeBrand.active', true));
    }

    public function getMapping(Entity $manufacturer, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$manufacturer instanceof ProductManufacturerEntity) {
            // Exception của core, không của storefront: phần headless không phụ thuộc gói storefront (ADR-005)
            throw SeoException::unexpectedType($manufacturer, ProductManufacturerEntity::class);
        }

        return new SeoUrlMapping(
            $manufacturer,
            $this->getConfig()->getPrimaryKeyParameter($manufacturer->getId()),
            ['manufacturer' => $manufacturer->jsonSerialize()]
        );
    }
}
