<?php declare(strict_types=1);

namespace AniveleTheme\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Việt hoá hai chỗ còn sót trên trang danh mục.
 *
 * Cùng loại lỗi với Migration1786164190VietnameseStorefrontLabels và cũng chỉ
 * lộ ra khi CHỤP ẢNH storefront: nhãn nằm trong DATABASE chứ không trong code
 * nên grep template không bao giờ thấy.
 *
 *   1. Tiêu đề trang chủ hiện "Home" — bản dịch vi-VN của danh mục gốc là NULL
 *      nên storefront rơi về ngôn ngữ hệ thống (en-US). Migration trước đã xử
 *      lý việc này nhưng database sau đó được dựng lại, mà migration đã chạy
 *      thì không chạy lại. Viết lại thành bước mới thay vì sửa bước cũ.
 *
 *   2. Ô sắp xếp hiện "Name A-Z", "Price ascending" — bảng product_sorting chỉ
 *      có bản dịch en-US. Gói ngôn ngữ vi-VN không kèm dữ liệu này vì nó là
 *      dữ liệu cửa hàng, không phải snippet.
 *
 * @internal
 */
class Migration1788690517VietnameseListingLabels extends MigrationStep
{
    /** Nhãn sắp xếp, khoá là url_key trong bảng product_sorting */
    private const SORTING_LABELS = [
        'name-asc' => 'Tên A-Z',
        'name-desc' => 'Tên Z-A',
        'price-asc' => 'Giá tăng dần',
        'price-desc' => 'Giá giảm dần',
        'score' => 'Liên quan nhất',
        'topseller' => 'Bán chạy nhất',
    ];

    public function getCreationTimestamp(): int
    {
        return 1788690517;
    }

    public function update(Connection $connection): void
    {
        $vietnameseId = $this->findVietnameseLanguageId($connection);

        if ($vietnameseId === null) {
            // Chưa cài gói ngôn ngữ vi-VN — không có gì để dịch, cũng không phải lỗi.
            return;
        }

        $this->translateRootCategory($connection, $vietnameseId);
        $this->translateProductSortings($connection, $vietnameseId);
    }

    private function findVietnameseLanguageId(Connection $connection): ?string
    {
        $id = $connection->fetchOne(
            'SELECT l.`id` FROM `language` l
             JOIN `locale` loc ON loc.`id` = l.`locale_id`
             WHERE loc.`code` = :code LIMIT 1',
            ['code' => 'vi-VN']
        );

        return $id === false ? null : $id;
    }

    /**
     * Chỉ ghi khi ô tên đang trống hoặc còn nguyên chữ "Home" — người vận hành
     * đã tự đặt tên khác thì giữ nguyên ý họ.
     */
    private function translateRootCategory(Connection $connection, string $vietnameseId): void
    {
        $connection->executeStatement(
            'UPDATE `category_translation` ct
             JOIN `category` c ON c.`id` = ct.`category_id`
             SET ct.`name` = :name, ct.`updated_at` = :now
             WHERE c.`parent_id` IS NULL
               AND ct.`language_id` = :lang
               AND (ct.`name` IS NULL OR ct.`name` = :oldName)',
            [
                'name' => 'Trang chủ',
                'now' => $this->now(),
                'lang' => $vietnameseId,
                'oldName' => 'Home',
            ]
        );
    }

    /**
     * INSERT IGNORE: nếu đã có dòng vi-VN thì không đụng tới, tránh ghi đè nhãn
     * mà người vận hành tự sửa trong Administration.
     */
    private function translateProductSortings(Connection $connection, string $vietnameseId): void
    {
        foreach (self::SORTING_LABELS as $urlKey => $label) {
            $sortingId = $connection->fetchOne(
                'SELECT `id` FROM `product_sorting` WHERE `url_key` = :urlKey LIMIT 1',
                ['urlKey' => $urlKey]
            );

            if ($sortingId === false) {
                continue;
            }

            $connection->executeStatement(
                'INSERT IGNORE INTO `product_sorting_translation`
                    (`product_sorting_id`, `language_id`, `label`, `created_at`)
                 VALUES (:sortingId, :lang, :label, :now)',
                [
                    'sortingId' => $sortingId,
                    'lang' => $vietnameseId,
                    'label' => $label,
                    'now' => $this->now(),
                ]
            );
        }
    }

    /**
     * Dấu thời gian cho cột created_at/updated_at.
     *
     * Trước đây MigrationStep có sẵn `now()`, nhưng core đã bỏ phương thức này
     * (kiểm trên Shopware 6.7.13.1) — gọi nó là lỗi fatal khi migration chạy
     * trên môi trường mới. Core hiện tự format tại chỗ, xem
     * vendor/shopware/core/Migration/V6_7/Migration1733136208AddH1ToCmsCategoryListing.php
     */
    private function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
