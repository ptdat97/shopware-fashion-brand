<?php declare(strict_types=1);

namespace Acme\Headless\Tests\Integration;

use Acme\Headless\Migration\Migration1792666426EnableWishlist;
use Acme\Headless\Subscriber\ListingMediaSubscriber;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Hai phần chuyển từ AcmeTheme sang (ADR-006 bước 2).
 *
 * @internal
 */
class ListingMediaAndWishlistTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelFunctionalTestBehaviour;

    public function testListingLoadsProductMediaForHoverImage(): void
    {
        $criteria = new Criteria();
        $event = new ProductListingCriteriaEvent(new Request(), $criteria, $this->createSalesChannelContext());

        static::assertContains(
            [ListingMediaSubscriber::class, 'addMediaAssociation'],
            array_map(
                static fn (callable $listener): mixed => \is_array($listener) ? [$listener[0]::class, $listener[1]] : null,
                static::getContainer()->get('event_dispatcher')->getListeners(ProductListingCriteriaEvent::class)
            ),
            'Subscriber phải được đăng ký'
        );

        (new ListingMediaSubscriber())->addMediaAssociation($event);

        static::assertTrue($criteria->hasAssociation('media'));
        static::assertTrue($criteria->getAssociation('media')->hasAssociation('media'), 'Thiếu media.media thì ảnh null');
    }

    public function testWishlistEnabledOnlyWhenOperatorHasNotDecided(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $config = static::getContainer()->get(SystemConfigService::class);
        $migration = new Migration1792666426EnableWishlist();

        $config->delete(Migration1792666426EnableWishlist::CONFIG_KEY);
        $migration->update($connection);
        $config->reset();
        static::assertTrue($config->getBool(Migration1792666426EnableWishlist::CONFIG_KEY));

        $config->set(Migration1792666426EnableWishlist::CONFIG_KEY, false);
        $migration->update($connection);
        $config->reset();
        static::assertFalse($config->getBool(Migration1792666426EnableWishlist::CONFIG_KEY), 'Người vận hành tắt thì giữ tắt');
    }
}
