<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Việt hoá dữ liệu mặc định của core mà gói ngôn ngữ vi-VN không phủ:
 *   - tên danh mục gốc "Home" → "Trang chủ"
 *   - nhãn ô sắp xếp (bảng product_sorting chỉ có bản en-US)
 *
 * Chuyển từ AcmeTheme ngày 14/10/2026 (gộp Migration1786164190VietnameseStorefrontLabels
 * và Migration1788690517VietnameseListingLabels): đây là bản địa hoá thị trường, không
 * phải giao diện — tắt theme thì nhãn vẫn phải là tiếng Việt.
 *
 * Chỉ ghi khi đang trống / còn nguyên giá trị mặc định → chạy lại trên môi trường đã có
 * bản cũ không ghi đè chữ người vận hành đã sửa.
 *
 * @internal
 */
class Migration1791973286VietnameseCoreLabels extends MigrationStep
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
        return 1791973286;
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
