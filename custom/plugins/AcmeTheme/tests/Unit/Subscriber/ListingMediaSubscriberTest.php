<?php declare(strict_types=1);

namespace Acme\Theme\Tests\Unit\Subscriber;

use Acme\Theme\Subscriber\ListingMediaSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ListingMediaSubscriberTest extends TestCase
{
    public function testListensToProductListingCriteria(): void
    {
        static::assertArrayHasKey(ProductListingCriteriaEvent::class, ListingMediaSubscriber::getSubscribedEvents());
    }

    public function testLoadsNestedMediaSoTheHoverImageIsNotNull(): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent(new Request(), $criteria, $this->createStub(SalesChannelContext::class));

        (new ListingMediaSubscriber())->addMediaAssociation($event);

        // Chỉ `media` thì ProductMedia có nhưng `productMedia.media` null — ảnh hover âm thầm không hiện
        static::assertTrue($criteria->hasAssociation('media'));
        static::assertTrue($criteria->getAssociation('media')->hasAssociation('media'));
    }
}
