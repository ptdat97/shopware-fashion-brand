<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Việt hoá tên trạng thái đơn hàng / thanh toán / giao hàng của core — khách thấy ở trang
 * cảm ơn và lịch sử đơn ("Open" → "Đang chờ xử lý"). Gói ngôn ngữ vi-VN không phủ bảng
 * state_machine_state_translation (phát hiện 21/10/2026 khi đặt đơn thử qua frontend).
 *
 * Nhận diện theo (state machine, technical_name) — không đổi giữa các môi trường (MIG-02).
 * Chỉ thêm bản dịch khi chưa có — không ghi đè chữ người vận hành đã sửa (MIG-03).
 *
 * @internal
 */
class Migration1792575782VietnameseOrderStates extends MigrationStep
{
    /** state machine → technical_name → tên tiếng Việt */
    public const NAMES = [
        'order.state' => [
            'open' => 'Đang chờ xử lý',
            'in_progress' => 'Đang xử lý',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã huỷ',
        ],
        'order_transaction.state' => [
            'open' => 'Chưa thanh toán',
            'unconfirmed' => 'Chờ xác nhận',
            'in_progress' => 'Đang thanh toán',
            'authorized' => 'Đã cấp phép',
            'paid' => 'Đã thanh toán',
            'paid_partially' => 'Đã thanh toán một phần',
            'reminded' => 'Đã nhắc thanh toán',
            'failed' => 'Thanh toán thất bại',
            'cancelled' => 'Đã huỷ',
            'refunded' => 'Đã hoàn tiền',
            'refunded_partially' => 'Đã hoàn tiền một phần',
            'chargeback' => 'Bị bồi hoàn',
        ],
        'order_delivery.state' => [
            'open' => 'Chưa giao',
            'shipped' => 'Đã giao cho đơn vị vận chuyển',
            'shipped_partially' => 'Đã giao một phần',
            'returned' => 'Đã hoàn trả',
            'returned_partially' => 'Đã hoàn trả một phần',
            'cancelled' => 'Đã huỷ',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1792575782;
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

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

        foreach (self::NAMES as $machine => $states) {
            foreach ($states as $technicalName => $name) {
                $connection->executeStatement(
                    'INSERT IGNORE INTO `state_machine_state_translation`
                        (`state_machine_state_id`, `language_id`, `name`, `created_at`)
                     SELECT s.`id`, :lang, :name, :now
                       FROM `state_machine_state` s
                       JOIN `state_machine` m ON m.`id` = s.`state_machine_id`
                      WHERE m.`technical_name` = :machine AND s.`technical_name` = :state',
                    ['lang' => $vietnameseId, 'name' => $name, 'now' => $now, 'machine' => $machine, 'state' => $technicalName]
                );
            }
        }
    }
}
