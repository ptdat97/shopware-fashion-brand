<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand;

use Acme\Brand\Core\Content\Brand\Aggregate\BrandTranslation\BrandTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Phần mở rộng 1–1 của `product_manufacturer`: những thuộc tính thương hiệu core không có.
 */
class BrandDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'acme_brand';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BrandCollection::class;
    }

    public function getEntityClass(): string
    {
        return BrandEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'active' => false,
            'featured' => false,
            'position' => 0,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new FkField('product_manufacturer_id', 'productManufacturerId', ProductManufacturerDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(ProductManufacturerDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new StringField('code', 'code', 3))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new BoolField('featured', 'featured'))->addFlags(new ApiAware()),
            (new IntField('position', 'position'))->addFlags(new ApiAware()),

            (new FkField('banner_media_id', 'bannerMediaId', MediaDefinition::class))->addFlags(new ApiAware()),
            (new FkField('cms_page_id', 'cmsPageId', CmsPageDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(CmsPageDefinition::class))->addFlags(new ApiAware()),

            (new TranslatedField('metaTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('metaDescription'))->addFlags(new ApiAware()),
            (new TranslatedField('keywords'))->addFlags(new ApiAware()),

            new OneToOneAssociationField('productManufacturer', 'product_manufacturer_id', 'id', ProductManufacturerDefinition::class, false),
            (new ManyToOneAssociationField('bannerMedia', 'banner_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('cmsPage', 'cms_page_id', CmsPageDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(BrandTranslationDefinition::class, 'acme_brand_id'))->addFlags(new ApiAware()),
        ]);
    }
}
