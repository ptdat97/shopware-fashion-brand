<?php declare(strict_types=1);

namespace Acme\Brand\Seo;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\StorefrontFrameworkException;

/**
 * Slug của thương hiệu chính là bản ghi `seo_url` này — không lưu cột slug riêng (ADR-003).
 *
 * Chỉ sinh URL cho brand đang hoạt động. Brand bị tắt giữ URL cũ nhưng trang trả 404.
 *
 * @internal
 */
class BrandPageSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'frontend.acme.brand.page';

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
            throw StorefrontFrameworkException::invalidArgument('SEO URL Mapping expects argument to be a ProductManufacturerEntity');
        }

        return new SeoUrlMapping(
            $manufacturer,
            $this->getConfig()->getPrimaryKeyParameter($manufacturer->getId()),
            ['manufacturer' => $manufacturer->jsonSerialize()]
        );
    }
}
