<?php declare(strict_types=1);

namespace Acme\Catalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Thứ tự giá trị thuộc tính (size S → M → L → XL, màu…) đúng ở MỌI ngôn ngữ.
 *
 * `position` của giá trị thuộc tính là field dịch được của core, và bộ chọn biến thể
 * sắp theo position của ngôn ngữ đang xem (ProductConfiguratorLoader). Dữ liệu demo
 * (AcmeDemoData ≤ 1.2.0) chỉ ghi position cho ngôn ngữ hệ thống → bản dịch vi-VN đều = 1
 * → từ khi channel chạy vi-VN (ADR-005) size hiện "L, XL, S, M". Phát hiện 21/10/2026 (H5).
 *
 * Chép position từ ngôn ngữ hệ thống sang ngôn ngữ khác CHỈ cho nhóm mà mọi giá trị ở ngôn
 * ngữ đó còn mặc định 1 — thứ tự người vận hành đã đặt trong Administration giữ nguyên (MIG-03).
 *
 * @internal
 */
class Migration1792576523SyncOptionPositionsAcrossLanguages extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792576523;
    }

    public function update(Connection $connection): void
    {
        $system = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        // (nhóm, ngôn ngữ) chưa ai đặt thứ tự: MỌI giá trị của nhóm trong ngôn ngữ đó = 1.
        // Xét cả nhóm, không từng dòng — người vận hành cố ý để một giá trị ở vị trí 1 thì
        // các giá trị khác đã khác 1 (MIG-03).
        $untouched = $connection->fetchAllAssociative(
            'SELECT o.`property_group_id` AS group_id, t.`language_id` AS language_id
               FROM `property_group_option_translation` t
               JOIN `property_group_option` o ON o.`id` = t.`property_group_option_id`
              WHERE t.`language_id` <> :system
              GROUP BY o.`property_group_id`, t.`language_id`
             HAVING COUNT(*) > 1 AND MAX(t.`position`) = 1 AND MIN(t.`position`) = 1',
            ['system' => $system]
        );

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($untouched as $pair) {
            $connection->executeStatement(
                'UPDATE `property_group_option_translation` t
                   JOIN `property_group_option` o ON o.`id` = t.`property_group_option_id`
                   JOIN `property_group_option_translation` src
                     ON src.`property_group_option_id` = t.`property_group_option_id`
                    AND src.`language_id` = :system
                    SET t.`position` = src.`position`, t.`updated_at` = :now
                  WHERE o.`property_group_id` = :groupId AND t.`language_id` = :languageId',
                ['system' => $system, 'now' => $now, 'groupId' => $pair['group_id'], 'languageId' => $pair['language_id']]
            );
        }
    }
}
