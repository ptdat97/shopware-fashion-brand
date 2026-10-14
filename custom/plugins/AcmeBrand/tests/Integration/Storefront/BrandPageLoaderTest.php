<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Integration\Storefront;

use Acme\Brand\BrandCustomFields;
use Acme\Brand\BrandException;
use Acme\Brand\Seo\BrandPageSeoUrlRoute;
use Acme\Brand\Storefront\Page\Brand\BrandPageLoader;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trang brand dùng route listing của core trên category gốc + bộ lọc manufacturer.
 * Các test này giữ đúng những gì spike MB3 đã chứng minh.
 *
 * @internal
 */
class BrandPageLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private BrandPageLoader $loader;

    private SalesChannelContext $context;

    /** Kênh storefront thật — kênh TestDefaults::SALES_CHANNEL là headless, không có SEO URL storefront */
    private string $salesChannelId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->salesChannelId = (string) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM sales_channel WHERE type_id = :type LIMIT 1',
            ['type' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
        );
        $this->loader = static::getContainer()->get(BrandPageLoader::class);
        $this->createCatalog();

        // Tạo context SAU khi có dữ liệu: ProductBuilder tạo thuế mới, context tạo trước
        // sẽ không biết thuế đó và tính giá lỗi.
        $this->context = $this->createContext();
    }

    private function createContext(): SalesChannelContext
    {
        return static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->salesChannelId);
    }

    public function testListingContainsOnlyProductsOfTheBrand(): void
    {
        $listing = $this->loader->loadListing($this->ids->get('alpha'), new Request(), $this->context);

        static::assertSame(2, $listing->getTotal());
        static::assertEqualsCanonicalizing(
            [$this->ids->get('alpha-1'), $this->ids->get('alpha-2')],
            array_values($listing->getEntities()->getIds())
        );
    }

    public function testBrandFilterIsHiddenOnItsOwnPage(): void
    {
        $listing = $this->loader->loadListing($this->ids->get('alpha'), new Request(), $this->context);

        static::assertNull($listing->getAggregations()->get('manufacturer'));
        static::assertNotNull($listing->getAggregations()->get('price'), 'Các bộ lọc khác của core vẫn phải còn');
    }

    public function testListingIsPaginatedByCore(): void
    {
        $listing = $this->loader->loadListing($this->ids->get('alpha'), new Request(['limit' => 1, 'p' => 2]), $this->context);

        static::assertSame(2, $listing->getTotal());
        static::assertCount(1, $listing->getEntities());
        static::assertSame(2, $listing->getPage());
    }

    public function testInactiveBrandIsNotFound(): void
    {
        $this->expectExceptionObject(BrandException::notFound($this->ids->get('gamma')));

        $this->loader->loadActiveBrand($this->ids->get('gamma'), $this->context);
    }

    public function testManufacturerWithoutBrandDataIsNotFound(): void
    {
        $this->expectExceptionObject(BrandException::notFound($this->ids->get('plain')));

        $this->loader->loadActiveBrand($this->ids->get('plain'), $this->context);
    }

    public function testBrandListShowsActiveBrandsByPosition(): void
    {
        $page = $this->loader->loadList(new Request(), $this->context);

        $ours = array_values(array_intersect(
            $page->getManufacturers()->getIds(),
            [$this->ids->get('alpha'), $this->ids->get('beta'), $this->ids->get('gamma'), $this->ids->get('plain')]
        ));

        static::assertSame([$this->ids->get('beta'), $this->ids->get('alpha')], $ours);
    }

    public function testSeoUrlFollowsBrandNameAndActivation(): void
    {
        static::assertSame('brands/brand-alpha', $this->canonicalSeoPath($this->ids->get('alpha')));
        static::assertNull($this->canonicalSeoPath($this->ids->get('gamma')), 'Brand đang tắt không được sinh URL');

        static::getContainer()->get('product_manufacturer.repository')->update([
            ['id' => $this->ids->get('alpha'), 'name' => 'Alpha Studio'],
        ], $this->context->getContext());

        static::assertSame('brands/alpha-studio', $this->canonicalSeoPath($this->ids->get('alpha')));
    }

    public function testPageMetaComesFromManufacturerCustomFieldsWithFallbacks(): void
    {
        static::getContainer()->get('product_manufacturer.repository')->update([[
            'id' => $this->ids->get('alpha'),
            'description' => '<p>Mô tả Alpha</p>',
            'customFields' => [BrandCustomFields::META_TITLE => 'Alpha chính hãng'],
        ]], Context::createDefaultContext());

        $meta = $this->loader->load($this->ids->get('alpha'), new Request(), $this->context)->getMetaInformation();

        static::assertNotNull($meta);
        static::assertSame('Alpha chính hãng', $meta->getMetaTitle());
        static::assertSame('Mô tả Alpha', $meta->getMetaDescription(), 'Thiếu meta description thì lấy từ mô tả nhà sản xuất');
        static::assertSame('Brand Alpha', $meta->getMetaKeywords(), 'Thiếu keywords thì lấy tên');
    }

    private function canonicalSeoPath(string $manufacturerId): ?string
    {
        $path = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT seo_path_info FROM seo_url
              WHERE route_name = :route AND foreign_key = :id AND is_canonical = 1 AND is_deleted = 0
                AND sales_channel_id = :salesChannel
              LIMIT 1',
            [
                'route' => BrandPageSeoUrlRoute::ROUTE_NAME,
                'id' => Uuid::fromHexToBytes($manufacturerId),
                'salesChannel' => Uuid::fromHexToBytes($this->salesChannelId),
            ]
        );

        return $path === false ? null : (string) $path;
    }

    private function createCatalog(): void
    {
        $context = Context::createDefaultContext();

        static::getContainer()->get('product_manufacturer.repository')->create([
            ['id' => $this->ids->get('alpha'), 'name' => 'Brand Alpha', 'acmeBrand' => ['active' => true, 'position' => 2]],
            ['id' => $this->ids->get('beta'), 'name' => 'Brand Beta', 'acmeBrand' => ['active' => true, 'position' => 1]],
            ['id' => $this->ids->get('gamma'), 'name' => 'Brand Gamma', 'acmeBrand' => ['active' => false]],
            ['id' => $this->ids->get('plain'), 'name' => 'Chỉ là nhà sản xuất'],
        ], $context);

        // Sản phẩm phải nằm trong cây danh mục của sales channel — trang brand liệt kê
        // qua category gốc điều hướng.
        static::getContainer()->get('category.repository')->create([[
            'id' => $this->ids->get('category'),
            'name' => 'Brand test',
            'parentId' => $this->createContext()->getSalesChannel()->getNavigationCategoryId(),
        ]], $context);

        $products = [];
        foreach (['alpha-1' => 'alpha', 'alpha-2' => 'alpha', 'beta-1' => 'beta', 'gamma-1' => 'gamma'] as $number => $brand) {
            $products[] = (new ProductBuilder($this->ids, $number))
                ->price(100)
                ->visibility($this->salesChannelId)
                ->add('manufacturerId', $this->ids->get($brand))
                ->add('categories', [['id' => $this->ids->get('category')]])
                ->build();
        }

        static::getContainer()->get('product.repository')->create($products, $context);
    }
}
