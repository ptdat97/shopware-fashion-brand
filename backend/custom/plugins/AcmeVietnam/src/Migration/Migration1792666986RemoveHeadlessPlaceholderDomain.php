<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Xoá domain giữ chỗ `default.headless{n}` của channel type API khi channel đã có domain
 * frontend thật (external storefront — Migration1792336157VietnameseHeadlessChannel).
 *
 * Core (Migration1620820321AddDefaultDomainForHeadlessSaleschannel) tạo domain này vì một
 * channel cần ít nhất một domain. Nó không phải URL thật, nhưng mẫu email của core lấy
 * `salesChannel.domains|first.url` — trúng domain giữ chỗ → link "xem đơn hàng" trong email
 * xác nhận là `default.headless0/account/order/…`, không mở được (phát hiện 22/10/2026).
 *
 * Chỉ xoá khi: channel có domain external storefront khác, và domain giữ chỗ không bị
 * product export / hreflang tham chiếu. Chạy lại vô hại.
 *
 * @internal
 */
class Migration1792666986RemoveHeadlessPlaceholderDomain extends MigrationStep
{
    final public const PLACEHOLDER_PREFIX = 'default.headless';

    public function getCreationTimestamp(): int
    {
        return 1792666986;
    }

    public function update(Connection $connection): void
    {
        // Chọn trước rồi mới xoá: MySQL không cho DELETE đọc lại chính bảng trong subquery
        $ids = $connection->fetchFirstColumn(
            'SELECT placeholder.`id` FROM `sales_channel_domain` placeholder
               JOIN `sales_channel` channel ON channel.`id` = placeholder.`sales_channel_id`
              WHERE channel.`type_id` = :apiType
                AND placeholder.`url` LIKE :prefix
                AND EXISTS (SELECT 1 FROM `sales_channel_domain` frontend
                             WHERE frontend.`sales_channel_id` = placeholder.`sales_channel_id`
                               AND frontend.`is_external_storefront` = 1
                               AND frontend.`id` <> placeholder.`id`)
                AND NOT EXISTS (SELECT 1 FROM `product_export` pe WHERE pe.`sales_channel_domain_id` = placeholder.`id`)
                AND (channel.`hreflang_default_domain_id` IS NULL OR channel.`hreflang_default_domain_id` <> placeholder.`id`)',
            [
                'apiType' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API),
                'prefix' => self::PLACEHOLDER_PREFIX . '%',
            ]
        );

        foreach ($ids as $id) {
            $connection->delete('sales_channel_domain', ['id' => $id]);
        }
    }
}
