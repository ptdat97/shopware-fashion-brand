<?php declare(strict_types=1);

namespace Acme\Shipping\Rule;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Đồng bộ danh sách tỉnh của rule "Giao nội thành HN/HCM" với tỉnh/thành đang có trong DB.
 *
 * Tỉnh/thành do AcmeVietnam nạp. Cài AcmeShipping trước thì điều kiện rule rỗng → không
 * khớp địa chỉ nào → mọi đơn tính phí tỉnh (checkout vẫn chạy, chỉ lệch phí). Gọi lại
 * sau khi có tỉnh — `AcmeShipping::activate()` làm việc này.
 *
 * @internal
 */
final class InnerCityStateSync
{
    public const RULE_NAME = 'Giao nội thành HN/HCM';

    public const STATE_CODES = ['VN-HN', 'VN-HCM'];

    /**
     * @return int số tỉnh đang gắn vào rule sau khi đồng bộ
     */
    public static function sync(Connection $connection): int
    {
        $conditionId = $connection->fetchOne(
            'SELECT rc.`id` FROM `rule_condition` rc
               JOIN `rule` r ON r.`id` = rc.`rule_id`
              WHERE r.`name` = :name AND rc.`type` = "customerShippingState"
              LIMIT 1',
            ['name' => self::RULE_NAME]
        );

        if ($conditionId === false) {
            return 0;
        }

        $stateIds = $connection->fetchFirstColumn(
            'SELECT LOWER(HEX(cs.`id`)) FROM `country_state` cs
               JOIN `country` c ON c.`id` = cs.`country_id`
              WHERE c.`iso` = "VN" AND cs.`short_code` IN (:codes)
              ORDER BY cs.`short_code`',
            ['codes' => self::STATE_CODES],
            ['codes' => ArrayParameterType::STRING]
        );

        $connection->update(
            'rule_condition',
            ['value' => json_encode(['operator' => '=', 'stateIds' => $stateIds], \JSON_THROW_ON_ERROR)],
            ['id' => $conditionId]
        );

        // Rule đã biên dịch (payload) phải tính lại, nếu không core vẫn dùng bản cũ
        $connection->executeStatement(
            'UPDATE `rule` SET `payload` = NULL, `invalid` = 0 WHERE `name` = :name',
            ['name' => self::RULE_NAME]
        );

        return \count($stateIds);
    }
}
