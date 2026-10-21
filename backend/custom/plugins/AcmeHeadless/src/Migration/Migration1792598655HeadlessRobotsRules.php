<?php declare(strict_types=1);

namespace Acme\Headless\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Quy tắc robots.txt cho channel headless (type API) — ô "Domain specific robots.txt rules"
 * của Settings → Basic information, đặt riêng cho channel.
 *
 * Giá trị chung mà core cài sẵn (Migration1728119898AddRobotsTxt) viết cho storefront Twig
 * (`/widgets/`, `/account/`…). Frontend Nuxt có đường dẫn khác: chặn trang riêng của khách
 * (tài khoản, giỏ/thanh toán, yêu thích) và kết quả tìm kiếm nội bộ (nội dung trùng, tốn
 * ngân sách thu thập) — trước đây gán cứng trong frontend (L4, docs/15 mục 8).
 *
 * Không ghi đè cấu hình người vận hành (MIG-03): channel đã có giá trị riêng thì bỏ qua.
 *
 * @internal
 */
class Migration1792598655HeadlessRobotsRules extends MigrationStep
{
    final public const CONFIG_KEY = 'core.basicInformation.robotsRules';

    final public const RULES = "Disallow: /account\nDisallow: /checkout\nDisallow: /wishlist\nDisallow: /search";

    public function getCreationTimestamp(): int
    {
        return 1792598655;
    }

    public function update(Connection $connection): void
    {
        $channelIds = $connection->fetchFirstColumn(
            'SELECT `id` FROM `sales_channel` WHERE `type_id` = :typeId',
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)]
        );

        foreach ($channelIds as $channelId) {
            $exists = $connection->fetchOne(
                'SELECT 1 FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` = :sc',
                ['key' => self::CONFIG_KEY, 'sc' => $channelId]
            );

            if ($exists !== false) {
                continue;
            }

            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => self::CONFIG_KEY,
                'configuration_value' => json_encode(['_value' => self::RULES], \JSON_THROW_ON_ERROR),
                'sales_channel_id' => $channelId,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }
}
