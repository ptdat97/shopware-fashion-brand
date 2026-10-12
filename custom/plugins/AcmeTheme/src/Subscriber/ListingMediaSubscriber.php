<?php declare(strict_types=1);

namespace AcmeTheme\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Nạp thêm association `media` cho trang danh mục.
 *
 * Mặc định listing chỉ nạp `cover` (đã kiểm chứng qua Store API: trường
 * `media` không có trong response), nên template không thể lấy ảnh thứ hai
 * để làm hiệu ứng hover.
 *
 * Đặc thù thời trang: khách xem được mặt trước/mặt sau ngay ở danh mục,
 * giảm số lần phải bấm vào từng sản phẩm.
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
        // Phải nạp cả association lồng `media.media`: `product.media` chỉ là
        // bảng nối ProductMedia, entity Media thật nằm bên trong.
        // Nạp thiếu thì template thấy `product.media` có phần tử nhưng
        // `productMedia.media` là null — không báo lỗi, chỉ âm thầm không hiện.
        $event->getCriteria()->addAssociation('media.media');
    }
}
