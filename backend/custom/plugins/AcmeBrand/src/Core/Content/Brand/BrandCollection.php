<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BrandEntity>
 */
class BrandCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BrandEntity::class;
    }
}
