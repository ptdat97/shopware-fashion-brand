<?php declare(strict_types=1);

namespace Acme\Payment\Migration;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Phương thức thanh toán cho channel headless (type API) — channel bán hàng duy nhất từ ADR-005.
 *
 * Migration1786162033VietnamesePaymentAndShipping chỉ gắn phương thức vào channel
 * Storefront. Channel API do bản cài Shopware tạo sẵn đang mang bộ mặc định của core
 * (ghi nợ, hoá đơn, COD, chuyển khoản) — ghi nợ và hoá đơn không dùng ở VN.
 *
 * Kết quả: COD + chuyển khoản trước, COD là mặc định. VietQR **không** gắn — đang tạm gác
 * tới khi có số tài khoản thật (CLAUDE.md, 14/10/2026).
 *
 * Không ghi đè cấu hình người vận hành (MIG-03): chỉ gỡ đúng hai phương thức mặc định
 * của core, chỉ đổi phương thức mặc định khi nó đang là một trong hai phương thức bị gỡ.
 *
 * @internal
 */
class Migration1792336158HeadlessPaymentMethods extends MigrationStep
{
    private const KEPT = ['payment_cashpayment', 'payment_prepayment'];

    private const CORE_DEFAULTS_NOT_USED_IN_VN = ['payment_debitpayment', 'payment_invoicepayment'];

    private const DEFAULT_METHOD = 'payment_cashpayment';

    public function getCreationTimestamp(): int
    {
        return 1792336158;
    }

    public function update(Connection $connection): void
    {
        $headlessIds = $connection->fetchFirstColumn(
            'SELECT `id` FROM `sales_channel` WHERE `type_id` = :typeId',
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)]
        );

        if ($headlessIds === []) {
            return;
        }

        $kept = $this->methodIds($connection, self::KEPT);
        $removed = $this->methodIds($connection, self::CORE_DEFAULTS_NOT_USED_IN_VN);
        $default = $this->methodIds($connection, [self::DEFAULT_METHOD])[0] ?? null;

        foreach ($headlessIds as $salesChannelId) {
            foreach ($kept as $methodId) {
                $connection->executeStatement(
                    'INSERT IGNORE INTO `sales_channel_payment_method`
                        (`sales_channel_id`, `payment_method_id`) VALUES (:sc, :pm)',
                    ['sc' => $salesChannelId, 'pm' => $methodId]
                );
            }

            if ($removed === []) {
                continue;
            }

            if ($default !== null) {
                // Phương thức mặc định phải nằm trong danh sách gắn với channel — đổi trước khi gỡ.
                $connection->executeStatement(
                    'UPDATE `sales_channel` SET `payment_method_id` = :default, `updated_at` = :now
                     WHERE `id` = :sc AND `payment_method_id` IN (:removed)',
                    ['default' => $default, 'now' => $this->now(), 'sc' => $salesChannelId, 'removed' => $removed],
                    ['removed' => ArrayParameterType::BINARY]
                );
            }

            $connection->executeStatement(
                'DELETE FROM `sales_channel_payment_method`
                 WHERE `sales_channel_id` = :sc AND `payment_method_id` IN (:removed)',
                ['sc' => $salesChannelId, 'removed' => $removed],
                ['removed' => ArrayParameterType::BINARY]
            );
        }
    }

    /**
     * @param list<string> $technicalNames
     *
     * @return list<string>
     */
    private function methodIds(Connection $connection, array $technicalNames): array
    {
        return $connection->fetchFirstColumn(
            'SELECT `id` FROM `payment_method` WHERE `technical_name` IN (:names)',
            ['names' => $technicalNames],
            ['names' => ArrayParameterType::STRING]
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
