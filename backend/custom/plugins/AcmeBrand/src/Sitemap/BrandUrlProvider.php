<?php declare(strict_types=1);

namespace Acme\Brand\Sitemap;

use Acme\Brand\Seo\BrandPageSeoUrlRoute;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Đưa trang `/brands/{slug}` của brand đang hoạt động vào sitemap.
 *
 * Brand chưa có SEO URL thì bỏ qua thay vì ghi đường dẫn kỹ thuật `/brand/{id}` —
 * chạy `bin/console dal:refresh:index --only=acme_brand.indexer` để sinh.
 *
 * @internal
 */
class BrandUrlProvider extends AbstractUrlProvider
{
    final public const CHANGE_FREQ = 'weekly';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'acme_brand';
    }

    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        /** @var list<array{id: string, created_at: string, updated_at: ?string}> $brands */
        $brands = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(product_manufacturer_id)) AS id, created_at, updated_at
               FROM acme_brand
              WHERE active = 1
              ORDER BY position, id
              LIMIT ' . $limit . ' OFFSET ' . (int) $offset
        );

        if ($brands === []) {
            return new UrlResult([], null);
        }

        /** @var array<string, array{seo_path_info: string}> $seoUrls */
        $seoUrls = FetchModeHelper::groupUnique(
            $this->getSeoUrls(array_column($brands, 'id'), BrandPageSeoUrlRoute::ROUTE_NAME, $context, $this->connection)
        );

        $urls = [];
        foreach ($brands as $brand) {
            if (!isset($seoUrls[$brand['id']])) {
                continue;
            }

            $url = new Url();
            $url->setLoc($seoUrls[$brand['id']]['seo_path_info']);
            $url->setLastmod(new \DateTime($brand['updated_at'] ?: $brand['created_at']));
            $url->setChangefreq(self::CHANGE_FREQ);
            $url->setResource(ProductManufacturerEntity::class);
            $url->setIdentifier($brand['id']);

            $urls[] = $url;
        }

        return new UrlResult($urls, \count($brands) === $limit ? (int) $offset + $limit : null);
    }
}
