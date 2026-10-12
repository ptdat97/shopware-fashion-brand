<?php declare(strict_types=1);

namespace AcmeTheme\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Việt hoá các nhãn còn sót trên storefront.
 *
 * Phát hiện bằng cách CHỤP ẢNH MÀN HÌNH storefront thật, không phải đọc code —
 * những chỗ này không lộ ra khi grep HTML hay xem CSS.
 *
 * @internal
 */
class Migration1786164190VietnameseStorefrontLabels extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786164190;
    }

    public function update(Connection $connection): void
    {
        $this->translateRootCategory($connection);
    }

    /**
     * Danh mục gốc chưa có bản dịch vi-VN nên trang chủ hiện tiêu đề "Home"
     * tiếng Anh giữa một storefront tiếng Việt.
     */
    private function translateRootCategory(Connection $connection): void
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

        $rootIds = $connection->fetchFirstColumn(
            'SELECT `id` FROM `category` WHERE `parent_id` IS NULL'
        );

        foreach ($rootIds as $categoryId) {
            $connection->executeStatement(
                'UPDATE `category_translation`
                 SET `name` = :name, `updated_at` = :now
                 WHERE `category_id` = :id
                   AND `language_id` = :lang
                   AND (`name` IS NULL OR `name` = :oldName)',
                [
                    'name' => 'Trang chủ',
                    'now' => $this->now(),
                    'id' => $categoryId,
                    'lang' => $vietnameseId,
                    'oldName' => 'Home',
                ]
            );
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
