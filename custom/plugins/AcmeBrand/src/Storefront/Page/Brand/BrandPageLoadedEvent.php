<?php declare(strict_types=1);

namespace Acme\Brand\Storefront\Page\Brand;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Điểm mở rộng cho plugin khác (vd. thêm khối nội dung vào trang brand).
 */
class BrandPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected BrandPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): BrandPage
    {
        return $this->page;
    }
}
