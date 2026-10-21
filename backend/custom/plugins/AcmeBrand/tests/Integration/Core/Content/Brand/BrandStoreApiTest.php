<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Integration\Core\Content\Brand;

use Acme\Brand\BrandCustomFields;
use Acme\Brand\BrandException;
use Acme\Brand\Seo\BrandStoreApiUrlRoute;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Store API thương hiệu cho frontend headless (H2) — gọi qua HTTP kernel như frontend Nuxt
 * (header sw-access-key), trên một channel type API có domain external storefront (cấu
 * hình thật, ADR-005).
 *
 * Gọi thẳng HttpKernel::handle() thay vì KernelBrowser: dự án không cài symfony/browser-kit
 * (composer.json chỉ đổi khi cài plugin bên thứ ba — CLAUDE.md).
 *
 * @internal
 */
class BrandStoreApiTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const FRONTEND_URL = 'http://brand-frontend.acme.test';

    private IdsCollection $ids;

    private string $accessKey;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $channel = $this->createSalesChannel([
            'id' => $this->ids->get('channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [['url' => self::FRONTEND_URL, 'isExternalStorefront' => true]],
        ]);
        $this->accessKey = (string) $channel['accessKey'];
        $this->createCatalog();
    }

    public function testListReturnsActiveBrandsInOperatorOrder(): void
    {
        $body = $this->request('GET', '/store-api/acme/brand');

        $ours = array_values(array_intersect(
            array_column($body['elements'], 'id'),
            [$this->ids->get('alpha'), $this->ids->get('beta'), $this->ids->get('gamma'), $this->ids->get('plain')]
        ));

        static::assertSame([$this->ids->get('beta'), $this->ids->get('alpha')], $ours, 'Chỉ brand đang bật, theo vị trí');
        static::assertArrayHasKey('acmeBrand', $body['elements'][0]['extensions'], 'Frontend cần dữ liệu brand (mã, banner…)');
    }

    public function testDetailReturnsBrandWithMetaFallbacks(): void
    {
        static::getContainer()->get('product_manufacturer.repository')->update([[
            'id' => $this->ids->get('alpha'),
            'description' => '<p>Mô tả Alpha</p>',
            'customFields' => [BrandCustomFields::META_TITLE => 'Alpha chính hãng'],
        ]], Context::createDefaultContext());

        $body = $this->request('GET', '/store-api/acme/brand/' . $this->ids->get('alpha'));

        static::assertSame('acme_brand_detail', $body['apiAlias']);
        static::assertSame($this->ids->get('alpha'), $body['manufacturer']['id']);
        static::assertTrue($body['manufacturer']['extensions']['acmeBrand']['active']);
        static::assertSame('Alpha chính hãng', $body['metaTitle']);
        static::assertSame('Mô tả Alpha', $body['metaDescription'], 'Thiếu meta description thì lấy từ mô tả');
        static::assertSame('Brand Alpha', $body['metaKeywords'], 'Thiếu keywords thì lấy tên');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unavailableBrands(): iterable
    {
        yield 'brand đang tắt' => ['gamma'];
        yield 'nhà sản xuất chưa có dữ liệu brand' => ['plain'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unavailableBrands')]
    public function testUnavailableBrandIsNotFoundOnDetailAndListing(string $key): void
    {
        foreach (['', '/listing'] as $suffix) {
            $body = $this->request('GET', '/store-api/acme/brand/' . $this->ids->get($key) . $suffix, 404);
            static::assertSame(BrandException::NOT_FOUND, $body['errors'][0]['code']);
        }
    }

    public function testMalformedIdIsNotFoundNotBadRequest(): void
    {
        $body = $this->request('GET', '/store-api/acme/brand/khong-phai-uuid', 404);

        static::assertSame(BrandException::NOT_FOUND, $body['errors'][0]['code']);
    }

    public function testListingContainsOnlyBrandProductsWithCoreListingFeatures(): void
    {
        $body = $this->request('POST', '/store-api/acme/brand/' . $this->ids->get('alpha') . '/listing', 200, ['limit' => 1, 'p' => 2]);

        static::assertSame('product_listing', $body['apiAlias'], 'Cùng định dạng /store-api/product-listing');
        static::assertSame(2, $body['total']);
        static::assertSame(2, $body['page']);
        static::assertCount(1, $body['elements']);
        static::assertSame($this->ids->get('alpha'), $body['elements'][0]['manufacturerId']);
        static::assertArrayNotHasKey('manufacturer', $body['aggregations'], 'Lọc theo brand trên trang của chính brand là vô nghĩa');
    }

    public function testHeadlessSeoUrlPointsToStoreApiRoute(): void
    {
        $row = static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT seo_path_info, path_info FROM seo_url
              WHERE route_name = :route AND foreign_key = :id AND sales_channel_id = :channel
                AND is_canonical = 1 AND is_deleted = 0',
            [
                'route' => BrandStoreApiUrlRoute::ROUTE_NAME,
                'id' => Uuid::fromHexToBytes($this->ids->get('alpha')),
                'channel' => Uuid::fromHexToBytes($this->ids->get('channel')),
            ]
        );

        static::assertSame([
            'seo_path_info' => 'brands/brand-alpha',
            'path_info' => '/store-api/acme/brand/' . $this->ids->get('alpha'),
        ], $row);

        $resolved = $this->request('POST', '/store-api/seo-url', 200, [
            'filter' => [['type' => 'equals', 'field' => 'seoPathInfo', 'value' => 'brands/brand-alpha']],
        ]);
        static::assertSame(BrandStoreApiUrlRoute::ROUTE_NAME, $resolved['elements'][0]['routeName'], 'Frontend tra SEO path ra route brand');
        static::assertSame($this->ids->get('alpha'), $resolved['elements'][0]['foreignKey']);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, int $expectedStatus = 200, array $payload = []): array
    {
        $request = Request::create($uri, $method, $payload, [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_' . PlatformRequest::HEADER_ACCESS_KEY => $this->accessKey,
            'HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN => Uuid::randomHex(),
        ]);
        $response = static::getKernel()->handle($request);

        static::assertSame($expectedStatus, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function createCatalog(): void
    {
        $context = Context::createDefaultContext();

        // Tạo sau channel: BrandIndexer sinh SEO URL headless cho channel vừa có domain external
        static::getContainer()->get('product_manufacturer.repository')->create([
            ['id' => $this->ids->get('alpha'), 'name' => 'Brand Alpha', 'acmeBrand' => ['active' => true, 'position' => 2]],
            ['id' => $this->ids->get('beta'), 'name' => 'Brand Beta', 'acmeBrand' => ['active' => true, 'position' => 1]],
            ['id' => $this->ids->get('gamma'), 'name' => 'Brand Gamma', 'acmeBrand' => ['active' => false]],
            ['id' => $this->ids->get('plain'), 'name' => 'Chỉ là nhà sản xuất'],
        ], $context);

        $navigationCategoryId = (string) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('channel'))]
        );
        static::getContainer()->get('category.repository')->create([[
            'id' => $this->ids->get('category'),
            'name' => 'Brand test',
            'parentId' => $navigationCategoryId,
        ]], $context);

        $products = [];
        foreach (['alpha-1' => 'alpha', 'alpha-2' => 'alpha', 'beta-1' => 'beta', 'gamma-1' => 'gamma'] as $number => $brand) {
            $products[] = (new ProductBuilder($this->ids, $number))
                ->price(100)
                ->visibility($this->ids->get('channel'))
                ->add('manufacturerId', $this->ids->get($brand))
                ->add('categories', [['id' => $this->ids->get('category')]])
                ->build();
        }

        static::getContainer()->get('product.repository')->create($products, $context);
    }
}
