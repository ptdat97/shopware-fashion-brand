<?php declare(strict_types=1);

namespace Acme\Brand\Storefront\Page\Brand;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class BrandListPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected BrandListPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): BrandListPage
    {
        return $this->page;
    }
}
