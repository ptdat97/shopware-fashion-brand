<?php declare(strict_types=1);

namespace Acme\Shipping\Tests\Integration\Migration;

use Acme\Shipping\Migration\Migration1791973174VietnamZoneShipping;
use Acme\Shipping\Rule\InnerCityStateSync;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * DB test chỉ cài AcmeShipping — không có AcmeVietnam, nên tỉnh/thành VN do test tự thêm.
 * Đúng tình huống "plugin chạy một mình" của Quy tắc số 5.
 *
 * @internal
 */
class Migration1791973174VietnamZoneShippingTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testStandardShippingGetsThreeZonePrices(): void
    {
        (new Migration1791973174VietnamZoneShipping())->update($this->connection);

        $fees = $this->connection->fetchFirstColumn(
            'SELECT CAST(JSON_EXTRACT(p.currency_price, :path) AS UNSIGNED)
               FROM shipping_method_price p JOIN shipping_method m ON m.id = p.shipping_method_id
              WHERE m.technical_name = "shipping_standard" ORDER BY 1',
            ['path' => $this->grossPath()]
        );

        static::assertSame([0, 25000, 35000], array_map('intval', $fees));
    }

    public function testPricesEditedInAdministrationAreNotOverwrittenOnRerun(): void
    {
        $migration = new Migration1791973174VietnamZoneShipping();
        $migration->update($this->connection);

        // Người vận hành đổi phí tỉnh 35k → 40k
        $this->connection->executeStatement(
            'UPDATE shipping_method_price p JOIN shipping_method m ON m.id = p.shipping_method_id
                SET p.currency_price = JSON_SET(p.currency_price, :path, 40000)
              WHERE m.technical_name = "shipping_standard" AND p.rule_id IS NULL',
            ['path' => $this->grossPath()]
        );

        $migration->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM shipping_method_price p JOIN shipping_method m ON m.id = p.shipping_method_id
              WHERE m.technical_name = "shipping_standard" AND p.rule_id IS NULL'
        ), 'Không tạo thêm dòng giá');
        static::assertStringContainsString('40000', (string) $this->connection->fetchOne(
            'SELECT p.currency_price FROM shipping_method_price p JOIN shipping_method m ON m.id = p.shipping_method_id
              WHERE m.technical_name = "shipping_standard" AND p.rule_id IS NULL'
        ));
    }

    public function testInnerCityRuleIsEmptyWithoutProvincesAndFilledOnceTheyExist(): void
    {
        (new Migration1791973174VietnamZoneShipping())->update($this->connection);
        $this->connection->executeStatement(
            'DELETE cs FROM country_state cs JOIN country c ON c.id = cs.country_id
              WHERE c.iso = "VN" AND cs.short_code IN ("VN-HN", "VN-HCM")'
        );

        static::assertSame(0, InnerCityStateSync::sync($this->connection), 'Chưa có tỉnh: rule rỗng, không lỗi');

        $vietnamId = $this->connection->fetchOne('SELECT id FROM country WHERE iso = "VN"');
        foreach (InnerCityStateSync::STATE_CODES as $position => $code) {
            $this->connection->insert('country_state', [
                'id' => Uuid::randomBytes(),
                'country_id' => $vietnamId,
                'short_code' => $code,
                'position' => $position,
                'active' => 1,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]);
        }

        static::assertSame(2, InnerCityStateSync::sync($this->connection), 'Có tỉnh rồi thì đồng bộ vào rule');
        static::assertNull($this->connection->fetchOne(
            'SELECT payload FROM rule WHERE name = :n',
            ['n' => InnerCityStateSync::RULE_NAME]
        ), 'Rule phải được biên dịch lại');
    }

    private function grossPath(): string
    {
        return '$.c' . Defaults::CURRENCY . '.gross';
    }
}
