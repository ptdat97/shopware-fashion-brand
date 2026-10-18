<?php declare(strict_types=1);

namespace Acme\Brand\Extension;

use Acme\Brand\Core\Content\Brand\BrandDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * `manufacturer.extensions.acmeBrand`
 *
 * Autoload bật có chủ ý: bảng 1–1 nhỏ, và trang chi tiết nhà sản xuất của
 * Administration tải manufacturer bằng `repository.get(id)` không kèm criteria —
 * không autoload thì phải override cả hàm tải dữ liệu của core.
 *
 * @internal
 */
class ProductManufacturerBrandExtension extends EntityExtension
{
    public const PROPERTY_NAME = 'acmeBrand';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(self::PROPERTY_NAME, 'id', 'product_manufacturer_id', BrandDefinition::class, true))
                ->addFlags(new ApiAware(), new CascadeDelete())
        );
    }

    public function getEntityName(): string
    {
        return ProductManufacturerDefinition::ENTITY_NAME;
    }
}
