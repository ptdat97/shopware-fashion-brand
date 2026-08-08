<?php declare(strict_types=1);

namespace Anivele\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Đặt vi-VN làm ngôn ngữ mặc định của storefront.
 *
 * Bản cài mới dùng en-US cho mọi sales channel và domain. Shop bán tại VN
 * cần storefront tiếng Việt ngay từ đầu, nếu không khách thấy giao diện tiếng Anh.
 *
 * Migration chạy sau Migration1786157044VietnamMarketBaseline và phụ thuộc
 * SwagLanguagePack đã tạo ngôn ngữ vi-VN cùng snippet set tương ứng.
 * Nếu chưa có vi-VN, migration bỏ qua không lỗi — cài language pack rồi chạy lại.
 *
 * @internal
 */
class Migration1786157045VietnameseStorefront extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786157045;
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
            // SwagLanguagePack chưa cài — không phải lỗi, chỉ chưa tới lúc.
            return;
        }

        $storefrontIds = $connection->fetchFirstColumn(
            'SELECT sc.`id` FROM `sales_channel` sc WHERE sc.`type_id` = :typeId',
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
        );

        if ($storefrontIds === []) {
            return;
        }

        $snippetSetId = $connection->fetchOne(
            'SELECT `id` FROM `snippet_set` WHERE `iso` = :iso ORDER BY `name` LIMIT 1',
            ['iso' => 'vi-VN']
        );

        foreach ($storefrontIds as $salesChannelId) {
            // Ngôn ngữ phải được gán cho channel trước khi đặt làm mặc định.
            $connection->executeStatement(
                'INSERT IGNORE INTO `sales_channel_language` (`sales_channel_id`, `language_id`)
                 VALUES (:salesChannelId, :languageId)',
                ['salesChannelId' => $salesChannelId, 'languageId' => $vietnameseId]
            );

            $connection->executeStatement(
                'UPDATE `sales_channel` SET `language_id` = :languageId, `updated_at` = :now WHERE `id` = :id',
                ['languageId' => $vietnameseId, 'now' => $this->now(), 'id' => $salesChannelId]
            );

            $connection->executeStatement(
                'UPDATE `sales_channel_domain`
                 SET `language_id` = :languageId, `updated_at` = :now
                 WHERE `sales_channel_id` = :salesChannelId',
                ['languageId' => $vietnameseId, 'now' => $this->now(), 'salesChannelId' => $salesChannelId]
            );

            if ($snippetSetId !== false) {
                $connection->executeStatement(
                    'UPDATE `sales_channel_domain`
                     SET `snippet_set_id` = :snippetSetId, `updated_at` = :now
                     WHERE `sales_channel_id` = :salesChannelId',
                    ['snippetSetId' => $snippetSetId, 'now' => $this->now(), 'salesChannelId' => $salesChannelId]
                );
            }
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
