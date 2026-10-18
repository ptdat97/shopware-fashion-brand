<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Integration\Core\Content\Brand;

use Acme\Brand\BrandException;
use Acme\Brand\Core\Content\Brand\BrandEntity;
use Acme\Brand\Validation\BrandCodeValidator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class BrandRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $manufacturerRepository;

    private EntityRepository $brandRepository;

    private EntityRepository $mediaRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->manufacturerRepository = static::getContainer()->get('product_manufacturer.repository');
        $this->brandRepository = static::getContainer()->get('acme_brand.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testBrandIsWrittenTogetherWithManufacturerAndAutoloadedOnRead(): void
    {
        $manufacturerId = Uuid::randomHex();

        $this->manufacturerRepository->create([[
            'id' => $manufacturerId,
            'name' => 'Nike',
            'acmeBrand' => [
                'code' => 'NKE',
                'active' => true,
                'featured' => true,
                'position' => 5,
            ],
        ]], $this->context);

        $manufacturer = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $this->context)->first();
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);

        $brand = $manufacturer->getExtension('acmeBrand');
        static::assertInstanceOf(BrandEntity::class, $brand);
        static::assertSame($manufacturerId, $brand->getProductManufacturerId());
        static::assertSame('NKE', $brand->getCode());
        static::assertTrue($brand->isActive());
        static::assertTrue($brand->isFeatured());
        static::assertSame(5, $brand->getPosition());
    }

    public function testNewBrandIsInactiveUntilEnabled(): void
    {
        $manufacturerId = Uuid::randomHex();

        $this->manufacturerRepository->create([[
            'id' => $manufacturerId,
            'name' => 'Adidas',
            'acmeBrand' => ['code' => 'ADS'],
        ]], $this->context);

        $brand = $this->loadBrandOf($manufacturerId);

        static::assertFalse($brand->isActive());
        static::assertFalse($brand->isFeatured());
        static::assertSame(0, $brand->getPosition());
    }

    public function testManufacturerWithoutBrandDataHasNoExtension(): void
    {
        $manufacturerId = Uuid::randomHex();

        $this->manufacturerRepository->create([['id' => $manufacturerId, 'name' => 'Không phải brand']], $this->context);

        $manufacturer = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $this->context)->first();
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);
        static::assertNull($manufacturer->getExtension('acmeBrand'));
    }

    public function testDeletingManufacturerDeletesItsBrandData(): void
    {
        $manufacturerId = Uuid::randomHex();
        $this->manufacturerRepository->create([[
            'id' => $manufacturerId,
            'name' => 'Puma',
            'acmeBrand' => ['code' => 'PUM'],
        ]], $this->context);
        $brandId = $this->loadBrandOf($manufacturerId)->getId();

        $this->manufacturerRepository->delete([['id' => $manufacturerId]], $this->context);

        static::assertSame(0, $this->brandRepository->searchIds(new Criteria([$brandId]), $this->context)->getTotal());
    }

    public function testDeletingBannerMediaKeepsBrand(): void
    {
        $manufacturerId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([['id' => $mediaId]], $this->context);
        $this->manufacturerRepository->create([[
            'id' => $manufacturerId,
            'name' => 'Converse',
            'acmeBrand' => ['code' => 'CVS', 'active' => true, 'bannerMediaId' => $mediaId],
        ]], $this->context);

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        $brand = $this->loadBrandOf($manufacturerId);
        static::assertNull($brand->getBannerMediaId());
        static::assertTrue($brand->isActive());
    }

    public function testBrandCodeMustBeThreeUppercaseCharacters(): void
    {
        try {
            $this->manufacturerRepository->create([[
                'name' => 'Sai mã',
                'acmeBrand' => ['code' => 'nk'],
            ]], $this->context);
            static::fail('Mã brand sai định dạng phải bị chặn');
        } catch (WriteException $exception) {
            static::assertSame([BrandCodeValidator::ERROR_CODE], $this->violationCodes($exception));
        }
    }

    public function testBrandCodeIsUniqueAcrossBrands(): void
    {
        $this->manufacturerRepository->create([[
            'name' => 'Nike',
            'acmeBrand' => ['code' => 'NKE'],
        ]], $this->context);

        $this->expectExceptionObject(BrandException::duplicateCode('NKE'));

        $this->manufacturerRepository->create([[
            'name' => 'Nike nhái',
            'acmeBrand' => ['code' => 'NKE'],
        ]], $this->context);
    }

    public function testBrandsWithoutCodeDoNotCollide(): void
    {
        $this->manufacturerRepository->create([
            ['name' => 'Chưa có mã 1', 'acmeBrand' => ['active' => false]],
            ['name' => 'Chưa có mã 2', 'acmeBrand' => ['active' => false]],
        ], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', null));

        static::assertGreaterThanOrEqual(2, $this->brandRepository->searchIds($criteria, $this->context)->getTotal());
    }

    private function loadBrandOf(string $manufacturerId): BrandEntity
    {
        $manufacturer = $this->manufacturerRepository->search(new Criteria([$manufacturerId]), $this->context)->first();
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);

        $brand = $manufacturer->getExtension('acmeBrand');
        static::assertInstanceOf(BrandEntity::class, $brand);

        return $brand;
    }

    /**
     * @return list<string>
     */
    private function violationCodes(WriteException $exception): array
    {
        $codes = [];
        foreach ($exception->getExceptions() as $inner) {
            if (!$inner instanceof WriteConstraintViolationException) {
                continue;
            }
            foreach ($inner->getViolations() as $violation) {
                $codes[] = (string) $violation->getCode();
            }
        }

        return $codes;
    }
}
