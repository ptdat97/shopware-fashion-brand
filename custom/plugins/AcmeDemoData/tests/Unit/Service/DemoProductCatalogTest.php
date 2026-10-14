<?php declare(strict_types=1);

namespace Acme\DemoData\Tests\Unit\Service;

use Acme\DemoData\Service\DemoProductCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DemoProductCatalog::class)]
class DemoProductCatalogTest extends TestCase
{
    public function testSkuPrefixIsTheBrandCode(): void
    {
        foreach (DemoProductCatalog::definitions() as $definition) {
            static::assertStringStartsWith($definition['brand'] . '-', $definition['number'], $definition['name']);
        }
    }

    public function testEveryProductBelongsToADeclaredBrand(): void
    {
        $brands = array_keys(DemoProductCatalog::brands());

        foreach (DemoProductCatalog::definitions() as $definition) {
            static::assertContains($definition['brand'], $brands, $definition['name']);
        }
    }

    public function testBrandCodesFollowTheSkuPrefixRule(): void
    {
        foreach (array_keys(DemoProductCatalog::brands()) as $code) {
            static::assertMatchesRegularExpression('/^[A-Z0-9]{3}$/', $code);
        }
    }

    public function testSkuIsUniqueAcrossBrands(): void
    {
        $numbers = array_column(DemoProductCatalog::definitions(), 'number');

        static::assertSame($numbers, array_values(array_unique($numbers)));
    }

    /**
     * Id sản phẩm suy ra từ khoá. Khoá đổi = seed tạo sản phẩm trùng thay vì cập nhật
     * dữ liệu đã có — nên khoá phải giữ nguyên dãy ANV-1001… kể cả khi SKU đổi brand.
     */
    public function testIdKeysStayOnTheOriginalSequence(): void
    {
        $keys = array_column(DemoProductCatalog::definitions(), 'key');

        static::assertSame(
            array_map(static fn (int $i): string => 'ANV-' . (1001 + $i), array_keys($keys)),
            $keys
        );
    }

    public function testCategoriesWithSeveralProductsOfferSeveralBrands(): void
    {
        $products = [];
        $brands = [];
        foreach (DemoProductCatalog::definitions() as $definition) {
            $products[$definition['category']] = ($products[$definition['category']] ?? 0) + 1;
            $brands[$definition['category']][$definition['brand']] = true;
        }

        foreach ($products as $category => $count) {
            if ($count < 2) {
                continue;
            }

            static::assertGreaterThanOrEqual(2, \count($brands[$category]), 'Danh mục ' . $category . ' cần ≥2 brand để thử bộ lọc thương hiệu');
        }
    }

    public function testAutumnWinterCollectionSpansSeveralBrands(): void
    {
        $brands = [];
        foreach (DemoProductCatalog::definitions() as $definition) {
            if (\in_array(DemoProductCatalog::TAG_AUTUMN_WINTER, $definition['tags'] ?? [], true)) {
                $brands[$definition['brand']] = true;
            }
        }

        static::assertGreaterThanOrEqual(2, \count($brands), 'Cần ≥2 brand để thử lọc thương hiệu trong một bộ sưu tập');
    }

    public function testEveryCollectionHasAtLeastOneCondition(): void
    {
        foreach (DemoProductCatalog::collections() as $name => $conditions) {
            static::assertNotEmpty($conditions, $name);
            foreach ($conditions as $condition) {
                static::assertArrayHasKey('type', $condition, $name);
                static::assertArrayHasKey('field', $condition, $name);
            }
        }
    }

    public function testSaleProductsAreCheaperThanListPriceAndSpanBrands(): void
    {
        $brands = [];
        foreach (DemoProductCatalog::definitions() as $definition) {
            if (!isset($definition['listPrice'])) {
                continue;
            }

            static::assertGreaterThan($definition['price'], $definition['listPrice'], $definition['name'] . ': giá niêm yết phải cao hơn giá bán');
            $brands[$definition['brand']] = true;
        }

        static::assertGreaterThanOrEqual(2, \count($brands), 'Bộ sưu tập Sale cần ≥2 brand');
    }
}
