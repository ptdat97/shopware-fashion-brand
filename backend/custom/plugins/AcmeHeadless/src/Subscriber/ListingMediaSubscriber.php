<?php declare(strict_types=1);

namespace Acme\Headless\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Nạp thêm association `media` cho listing (danh mục, trang brand, tìm kiếm — tìm kiếm
 * dùng ProductSearchCriteriaEvent, lớp con của event này).
 *
 * Mặc định listing chỉ nạp `cover`, nên thẻ sản phẩm của frontend không có ảnh thứ hai để
 * đổi khi rê chuột (H5, `SwProductCardImage.vue`). Đặc thù thời trang: khách xem được mặt
 * trước/mặt sau ngay ở danh mục.
 *
 * Chuyển từ AcmeTheme sang đây ngày 22/10/2026 (ADR-006 bước 2) — AcmeTheme là theme
 * storefront Twig, sẽ bị gỡ.
 *
 * @internal
 */
class ListingMediaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => 'addMediaAssociation',
        ];
    }

    public function addMediaAssociation(ProductListingCriteriaEvent $event): void
    {
        // Phải nạp cả `media.media`: `product.media` chỉ là bảng nối ProductMedia, entity
        // Media thật nằm bên trong. Thiếu thì có phần tử nhưng `media` null — âm thầm không hiện.
        $event->getCriteria()->addAssociation('media.media');
    }
}
