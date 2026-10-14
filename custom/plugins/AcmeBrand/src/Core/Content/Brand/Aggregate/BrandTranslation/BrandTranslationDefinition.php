<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\Aggregate\BrandTranslation;

use Acme\Brand\Core\Content\Brand\BrandDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BrandTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'acme_brand_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BrandTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return BrandTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return BrandDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('meta_title', 'metaTitle'))->addFlags(new ApiAware()),
            (new LongTextField('meta_description', 'metaDescription'))->addFlags(new ApiAware()),
            (new LongTextField('keywords', 'keywords'))->addFlags(new ApiAware()),
        ]);
    }
}
