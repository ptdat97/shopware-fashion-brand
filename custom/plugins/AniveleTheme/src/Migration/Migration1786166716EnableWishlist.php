<?php declare(strict_types=1);

namespace AniveleTheme\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Bật danh sách yêu thích (Wishlist).
 *
 * Core có sẵn tính năng nhưng mặc định tắt (`core.cart.wishlistEnabled`).
 * Khách mua thời trang thường lưu vài mẫu để cân nhắc rồi mới quyết định,
 * nên đây là tính năng đáng bật cho shop thời trang.
 *
 * @internal
 */
class Migration1786166716EnableWishlist extends MigrationStep
{
    private const CONFIG_KEY = 'core.cart.wishlistEnabled';

    public function getCreationTimestamp(): int
    {
        return 1786166716;
    }

    public function update(Connection $connection): void
    {
        $encoded = json_encode(['_value' => true], \JSON_THROW_ON_ERROR);

        $existing = $connection->fetchOne(
            'SELECT `id` FROM `system_config`
             WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL LIMIT 1',
            ['key' => self::CONFIG_KEY]
        );

        if ($existing === false) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => self::CONFIG_KEY,
                'configuration_value' => $encoded,
                'sales_channel_id' => null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]);

            return;
        }

        $connection->executeStatement(
            'UPDATE `system_config` SET `configuration_value` = :value, `updated_at` = :now WHERE `id` = :id',
            [
                'value' => $encoded,
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                'id' => $existing,
            ]
        );
    }
}
