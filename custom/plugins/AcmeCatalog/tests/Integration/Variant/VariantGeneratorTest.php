<?php declare(strict_types=1);

namespace Acme\Catalog\Tests\Integration\Variant;

use Acme\Catalog\CatalogException;
use Acme\Catalog\Variant\VariantGenerator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Nhóm thuộc tính và sản phẩm do test tự dựng — không dựa vào dữ liệu demo (TEST-03).
 *
 * @internal
 */
class VariantGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private VariantGenerator $generator;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var array{colour: list<string>, size: list<string>}
     */
    private array $options;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->generator = static::getContainer()->get(VariantGenerator::class);
        $this->productRepository = static::getContainer()->get('product.repository');
    }

    public function testGeneratesColourTimesSizeVariantsWithSharedCanonical(): void
    {
        $this->createVariantGroups();
        $parentId = $this->createProduct('TST-1001', stock: 60, colours: 2, sizes: 3);

        $result = $this->generator->generate($this->context, 'TST-1001');

        static::assertSame([['productNumber' => 'TST-1001', 'colours' => 2, 'sizes' => 3, 'variants' => 6]], $result['generated']);

        $children = $this->children($parentId);
        static::assertSame(
            ['TST-1001-01', 'TST-1001-02', 'TST-1001-03', 'TST-1001-04', 'TST-1001-05', 'TST-1001-06'],
            array_values(array_map(static fn (ProductEntity $p) => $p->getProductNumber(), $children))
        );
        static::assertSame([10], array_values(array_unique(array_map(static fn (ProductEntity $p) => $p->getStock(), $children))), 'Tồn kho chia đều 60 / 6');

        $first = $children[0];
        static::assertEqualsCanonicalizing([$this->options['colour'][0], $this->options['size'][0]], $first->getOptionIds());

        $parent = $this->productRepository->search(new Criteria([$parentId]), $this->context)->getEntities()->first();
        static::assertNotNull($parent);
        static::assertSame($first->getId(), $parent->getCanonicalProductId(), 'Canonical = biến thể đầu tiên');
        static::assertFalse($parent->getVariantListingConfig()?->getDisplayParent());
    }

    public function testRunningTwiceDoesNotDuplicateVariants(): void
    {
        $this->createVariantGroups();
        $parentId = $this->createProduct('TST-1002', stock: 8, colours: 2, sizes: 2);

        $this->generator->generate($this->context, 'TST-1002');
        $second = $this->generator->generate($this->context, 'TST-1002');

        static::assertSame([], $second['generated'], 'Sản phẩm đã có biến thể thì bỏ qua');
        static::assertCount(4, $this->children($parentId));
    }

    public function testDryRunWritesNothing(): void
    {
        $this->createVariantGroups();
        $parentId = $this->createProduct('TST-1003', stock: 4, colours: 1, sizes: 2);

        $result = $this->generator->generate($this->context, 'TST-1003', dryRun: true);

        static::assertSame(2, $result['generated'][0]['variants']);
        static::assertSame([], $this->children($parentId));
    }

    public function testProductWithoutSizeIsSkipped(): void
    {
        $this->createVariantGroups();
        $this->createProduct('TST-1004', stock: 4, colours: 2, sizes: 0);

        $result = $this->generator->generate($this->context, 'TST-1004');

        static::assertSame([], $result['generated']);
        static::assertSame([['productNumber' => 'TST-1004', 'colours' => 2, 'sizes' => 0]], $result['skipped']);
    }

    public function testMissingPropertyGroupsIsAnExplicitError(): void
    {
        $this->expectExceptionObject(CatalogException::variantGroupsMissing(VariantGenerator::COLOUR_GROUP, VariantGenerator::SIZE_GROUP));

        $this->generator->generate($this->context);
    }

    private function createVariantGroups(): void
    {
        $this->options = ['colour' => [], 'size' => []];
        $groups = [];

        foreach (['colour' => [VariantGenerator::COLOUR_GROUP, ['Đen', 'Kem']], 'size' => [VariantGenerator::SIZE_GROUP, ['S', 'M', 'L']]] as $key => [$name, $values]) {
            $options = [];
            foreach ($values as $value) {
                $id = Uuid::randomHex();
                $this->options[$key][] = $id;
                $options[] = ['id' => $id, 'name' => $value];
            }
            $groups[] = ['id' => Uuid::randomHex(), 'name' => $name, 'options' => $options];
        }

        static::getContainer()->get('property_group.repository')->create($groups, $this->context);
    }

    private function createProduct(string $number, int $stock, int $colours, int $sizes): string
    {
        $id = Uuid::randomHex();
        $properties = array_map(
            static fn (string $optionId) => ['id' => $optionId],
            [...\array_slice($this->options['colour'], 0, $colours), ...\array_slice($this->options['size'], 0, $sizes)]
        );

        $this->productRepository->create([[
            'id' => $id,
            'productNumber' => $number,
            'name' => 'Váy thử ' . $number,
            'stock' => $stock,
            'tax' => ['name' => 'VAT 10%', 'taxRate' => 10],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 500000, 'net' => 454545, 'linked' => true]],
            'properties' => $properties,
        ]], $this->context);

        return $id;
    }

    /**
     * @return list<ProductEntity>
     */
    private function children(string $parentId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addSorting(new FieldSorting('productNumber'));

        return array_values($this->productRepository->search($criteria, $this->context)->getEntities()->getElements());
    }
}
