<?php declare(strict_types=1);

namespace Acme\Headless\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Bật danh sách yêu thích (`core.cart.wishlistEnabled`, mặc định tắt) — frontend đọc cờ này
 * qua shop-settings. Khách thời trang thường lưu vài mẫu để cân nhắc rồi mới mua.
 *
 * Chuyển từ AcmeTheme (Migration1786166716EnableWishlist) ngày 22/10/2026, ADR-006 bước 2.
 * Khác bản cũ: không ghi đè khi đã có giá trị (MIG-03) — người vận hành tắt thì giữ tắt.
 *
 * @internal
 */
class Migration1792666426EnableWishlist extends MigrationStep
{
    final public const CONFIG_KEY = 'core.cart.wishlistEnabled';

    public function getCreationTimestamp(): int
    {
        return 1792666426;
    }

    public function update(Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => self::CONFIG_KEY]
        );

        if ($exists !== false) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => true], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => null,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
