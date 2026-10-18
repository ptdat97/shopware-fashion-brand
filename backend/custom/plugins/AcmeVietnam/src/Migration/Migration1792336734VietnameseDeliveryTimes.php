<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Việt hoá tên thời gian giao hàng mặc định của core ("1-3 days" → "1-3 ngày").
 *
 * Gói ngôn ngữ vi-VN không phủ bảng delivery_time, nên khách thấy chữ tiếng Anh ở
 * trang sản phẩm và bước chọn vận chuyển. Storefront Twig không hiện tên này ở trang
 * sản phẩm nên trước đây chưa lộ; frontend headless thì có (ADR-005).
 *
 * Nhận diện bản ghi theo (min, max, unit) — không đổi giữa các môi trường (MIG-02).
 * Chỉ thêm bản dịch vi-VN khi chưa có — không ghi đè chữ người vận hành đã sửa (MIG-03).
 *
 * @internal
 */
class Migration1792336734VietnameseDeliveryTimes extends MigrationStep
{
    /** [min, max, unit, tên tiếng Việt] */
    private const NAMES = [
        [1, 3, 'day', '1-3 ngày'],
        [2, 5, 'day', '2-5 ngày'],
        [1, 2, 'week', '1-2 tuần'],
        [3, 4, 'week', '3-4 tuần'],
        [0, 0, 'hour', 'Tải về ngay'],
    ];

    public function getCreationTimestamp(): int
    {
        return 1792336734;
    }

    public function update(Connection $connection): void
    {
        $vietnameseId = $connection->fetchOne(
            'SELECT l.`id` FROM `language` l
             JOIN `locale` loc ON loc.`id` = l.`locale_id`
             WHERE loc.`code` = :code LIMIT 1',
            ['code' => 'vi-VN']
        );

        if ($vietnameseId === false) {
            return;
        }

        foreach (self::NAMES as [$min, $max, $unit, $name]) {
            $connection->executeStatement(
                'INSERT IGNORE INTO `delivery_time_translation` (`delivery_time_id`, `language_id`, `name`, `created_at`)
                 SELECT d.`id`, :lang, :name, :now FROM `delivery_time` d
                  WHERE d.`min` = :min AND d.`max` = :max AND d.`unit` = :unit',
                [
                    'lang' => $vietnameseId,
                    'name' => $name,
                    'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                    'min' => $min,
                    'max' => $max,
                    'unit' => $unit,
                ]
            );
        }
    }
}
