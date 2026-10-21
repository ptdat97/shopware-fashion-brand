<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Số điện thoại BẮT BUỘC ở địa chỉ khách (đăng ký, thanh toán, sổ địa chỉ) — shipper VN
 * gọi điện trước khi giao, đơn không có SĐT gần như không giao được.
 *
 * Sửa lỗi của Migration1786157044VietnamMarketBaseline: bản đó đặt
 * `core.basicInformation.phoneNumberFieldRequired` — khoá này chỉ áp cho FORM LIÊN HỆ
 * (ContactFormValidationFactory). Kiểm địa chỉ đọc khoá `core.loginRegistration.
 * phoneNumberFieldRequired` (AddressValidationFactory), chưa từng được đặt → từ Phase 0
 * khách đặt được đơn không có SĐT. Phát hiện 21/10/2026 khi thử đặt đơn qua frontend
 * headless. Không sửa migration cũ (MIG-04).
 *
 * Chỉ ghi khi chưa có giá trị — người vận hành đã chọn trong Administration → Settings →
 * Login & registration thì giữ nguyên (MIG-03).
 *
 * @internal
 */
class Migration1792575582RequirePhoneNumberOnAddress extends MigrationStep
{
    public const CONFIG_KEY = 'core.loginRegistration.phoneNumberFieldRequired';

    public function getCreationTimestamp(): int
    {
        return 1792575582;
    }

    public function update(Connection $connection): void
    {
        $existing = $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => self::CONFIG_KEY]
        );
        if ($existing !== false) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => true], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }
}
