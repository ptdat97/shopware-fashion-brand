<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\Aggregate\BrandTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BrandTranslationEntity>
 */
class BrandTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BrandTranslationEntity::class;
    }
}
