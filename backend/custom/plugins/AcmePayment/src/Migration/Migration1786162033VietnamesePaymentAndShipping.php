<?php declare(strict_types=1);

namespace Acme\Payment\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Dọn phương thức thanh toán/vận chuyển cho đúng thị trường VN.
 *
 * Trước migration này storefront chỉ gắn đúng MỘT phương thức (COD) và còn
 * 17 phương thức PayPal mồ côi trỏ tới class đã bị gỡ.
 *
 * @internal
 */
class Migration1786162033VietnamesePaymentAndShipping extends MigrationStep
{
    private const VIETQR_TECHNICAL_NAME = 'acme_vietqr';

    /** Phương thức của core dùng được cho thị trường VN */
    private const KEPT_CORE_METHODS = [
        'payment_cashpayment',  // COD — chiếm tỷ trọng lớn nhất ở VN
        'payment_prepayment',   // Chuyển khoản trước
    ];

    public function getCreationTimestamp(): int
    {
        return 1786162033;
    }

    public function update(Connection $connection): void
    {
        $this->removeOrphanedPayPalMethods($connection);
        $this->translatePaymentMethods($connection);
        $this->attachPaymentMethodsToStorefront($connection);
    }

    /**
     * 17 phương thức PayPal trỏ tới class không còn tồn tại sau khi gỡ plugin.
     * Để lại thì Administration hiển thị phương thức chết, và có nguy cơ lỗi
     * nếu ai đó bật nhầm.
     *
     * Chỉ xoá khi chưa có đơn hàng nào dùng — đơn cũ tham chiếu tới payment
     * method, xoá đi sẽ làm hỏng lịch sử đơn.
     */
    private function removeOrphanedPayPalMethods(Connection $connection): void
    {
        $orphanIds = $connection->fetchFirstColumn(
            "SELECT pm.`id` FROM `payment_method` pm
             WHERE pm.`handler_identifier` LIKE 'Swag\\\\\\\\PayPal%'
               AND NOT EXISTS (
                   SELECT 1 FROM `order_transaction` ot WHERE ot.`payment_method_id` = pm.`id`
               )"
        );

        if ($orphanIds === []) {
            return;
        }

        $connection->executeStatement(
            'DELETE FROM `sales_channel_payment_method` WHERE `payment_method_id` IN (:ids)',
            ['ids' => $orphanIds],
            ['ids' => ArrayParameterType::BINARY]
        );

        $connection->executeStatement(
            'DELETE FROM `payment_method` WHERE `id` IN (:ids)',
            ['ids' => $orphanIds],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * Phương thức của core chỉ có tên tiếng Anh/Đức. Storefront chạy vi-VN
     * nên khách đang thấy "Cash on delivery" thay vì "Thanh toán khi nhận hàng".
     */
    private function translatePaymentMethods(Connection $connection): void
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

        $translations = [
            'payment_cashpayment' => [
                'Thanh toán khi nhận hàng (COD)',
                'Trả tiền mặt cho nhân viên giao hàng khi nhận được đơn.',
            ],
            'payment_prepayment' => [
                'Chuyển khoản trước',
                'Chuyển khoản tới tài khoản của cửa hàng. Đơn hàng được giao sau khi cửa hàng nhận được tiền.',
            ],
            'payment_invoicepayment' => [
                'Thanh toán bằng hoá đơn',
                'Nhận hàng trước, thanh toán theo hoá đơn sau.',
            ],
        ];

        foreach ($translations as $technicalName => [$name, $description]) {
            $paymentMethodId = $connection->fetchOne(
                'SELECT `id` FROM `payment_method` WHERE `technical_name` = :name LIMIT 1',
                ['name' => $technicalName]
            );

            if ($paymentMethodId === false) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO `payment_method_translation`
                    (`payment_method_id`, `language_id`, `name`, `description`, `created_at`)
                 VALUES (:id, :lang, :name, :description, :now)
                 ON DUPLICATE KEY UPDATE `name` = :name, `description` = :description, `updated_at` = :now',
                [
                    'id' => $paymentMethodId,
                    'lang' => $vietnameseId,
                    'name' => $name,
                    'description' => $description,
                    'now' => $this->now(),
                ]
            );
        }
    }

    /**
     * Gắn COD + chuyển khoản + VietQR vào storefront.
     *
     * Đây là bộ tối thiểu để go-live theo khuyến nghị của ADR-002: COD chiếm
     * tỷ trọng lớn nhất trong TMĐT Việt Nam, VietQR chi phí thấp và đang tăng nhanh.
     */
    private function attachPaymentMethodsToStorefront(Connection $connection): void
    {
        $storefrontIds = $connection->fetchFirstColumn(
            'SELECT `id` FROM `sales_channel` WHERE `type_id` = :typeId',
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_STOREFRONT)]
        );

        if ($storefrontIds === []) {
            return;
        }

        $wanted = [...self::KEPT_CORE_METHODS, self::VIETQR_TECHNICAL_NAME];

        $methodIds = $connection->fetchFirstColumn(
            'SELECT `id` FROM `payment_method` WHERE `technical_name` IN (:names)',
            ['names' => $wanted],
            ['names' => ArrayParameterType::STRING]
        );

        foreach ($storefrontIds as $salesChannelId) {
            foreach ($methodIds as $methodId) {
                $connection->executeStatement(
                    'INSERT IGNORE INTO `sales_channel_payment_method`
                        (`sales_channel_id`, `payment_method_id`) VALUES (:sc, :pm)',
                    ['sc' => $salesChannelId, 'pm' => $methodId]
                );
            }

            $connection->executeStatement(
                'UPDATE `payment_method` SET `active` = 1, `updated_at` = :now WHERE `id` IN (:ids)',
                ['now' => $this->now(), 'ids' => $methodIds],
                ['ids' => ArrayParameterType::BINARY]
            );
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
