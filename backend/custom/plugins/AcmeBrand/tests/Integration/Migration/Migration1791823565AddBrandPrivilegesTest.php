<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Integration\Migration;

use Acme\Brand\Migration\Migration1791823565AddBrandPrivileges;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1791823565AddBrandPrivilegesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testManufacturerEditorGainsBrandPrivileges(): void
    {
        $roleId = $this->createRole(['product_manufacturer.viewer', 'product_manufacturer.editor', 'product_manufacturer:read', 'product_manufacturer:update']);

        (new Migration1791823565AddBrandPrivileges())->update($this->connection);

        $privileges = $this->privilegesOf($roleId);
        static::assertContains('acme_brand:read', $privileges);
        static::assertContains('acme_brand:create', $privileges);
        static::assertContains('acme_brand_translation:update', $privileges);
        static::assertNotContains('acme_brand:delete', $privileges, 'Không có quyền xoá nhà sản xuất thì không được xoá brand');
    }

    public function testRoleWithoutManufacturerAccessIsUntouched(): void
    {
        $roleId = $this->createRole(['order.viewer', 'order:read']);

        (new Migration1791823565AddBrandPrivileges())->update($this->connection);

        static::assertSame(['order.viewer', 'order:read'], $this->privilegesOf($roleId));
    }

    public function testRunningTwiceDoesNotDuplicatePrivileges(): void
    {
        $roleId = $this->createRole(['product_manufacturer.viewer', 'product_manufacturer:read']);
        $migration = new Migration1791823565AddBrandPrivileges();

        $migration->update($this->connection);
        $afterFirstRun = $this->privilegesOf($roleId);
        $migration->update($this->connection);

        static::assertSame($afterFirstRun, $this->privilegesOf($roleId));
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(array $privileges): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert('acl_role', [
            'id' => $id,
            'name' => 'Test ' . Uuid::randomHex(),
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    /**
     * @return list<string>
     */
    private function privilegesOf(string $roleId): array
    {
        $json = (string) $this->connection->fetchOne('SELECT privileges FROM acl_role WHERE id = :id', ['id' => $roleId]);

        return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }
}
