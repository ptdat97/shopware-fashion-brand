<?php declare(strict_types=1);

namespace Acme\Catalog\Tests\Integration\Migration;

use Acme\Catalog\Migration\Migration1791978755ProductFashionAttributes as FashionAttributes;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1791978755ProductFashionAttributesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->removeExisting();
    }

    public function testCreatesNativeMaterialGroupAndModelSizeField(): void
    {
        (new FashionAttributes())->update($this->connection);

        $group = $this->connection->fetchAssociative(
            'SELECT pg.display_type, pg.filterable, pg.visible_on_product_detail_page FROM property_group pg
               JOIN property_group_translation t ON t.property_group_id = pg.id WHERE t.name = :name LIMIT 1',
            ['name' => FashionAttributes::MATERIAL_GROUP_NAME]
        );
        static::assertSame(
            ['display_type' => 'text', 'filterable' => 1, 'visible_on_product_detail_page' => 1],
            array_map(static fn ($v) => is_numeric($v) ? (int) $v : $v, (array) $group),
            'Lọc được ở danh mục, hiện trong bảng thông số trang sản phẩm'
        );

        static::assertSame('product', $this->connection->fetchOne(
            'SELECT r.entity_name FROM custom_field f JOIN custom_field_set_relation r ON r.set_id = f.set_id WHERE f.name = :name',
            ['name' => FashionAttributes::FIELD_MODEL_SIZE]
        ));
    }

    public function testRerunCreatesNothingTwice(): void
    {
        $migration = new FashionAttributes();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(1, $this->materialGroupCount());
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM custom_field WHERE name = :name',
            ['name' => FashionAttributes::FIELD_MODEL_SIZE]
        ));
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM custom_field_set WHERE name = :name',
            ['name' => FashionAttributes::CUSTOM_FIELD_SET]
        ));
    }

    public function testExistingMaterialGroupCreatedByMerchantIsReused(): void
    {
        static::getContainer()->get('property_group.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => FashionAttributes::MATERIAL_GROUP_NAME,
            'displayType' => 'select',
        ]], Context::createDefaultContext());

        (new FashionAttributes())->update($this->connection);

        static::assertSame(1, $this->materialGroupCount(), 'Không tạo nhóm "Chất liệu" thứ hai');
        static::assertSame('select', $this->connection->fetchOne(
            'SELECT pg.display_type FROM property_group pg JOIN property_group_translation t ON t.property_group_id = pg.id WHERE t.name = :name',
            ['name' => FashionAttributes::MATERIAL_GROUP_NAME]
        ), 'Cấu hình người vận hành giữ nguyên');
    }

    private function materialGroupCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT property_group_id) FROM property_group_translation WHERE name = :name',
            ['name' => FashionAttributes::MATERIAL_GROUP_NAME]
        );
    }

    /**
     * DB test đã cài AcmeCatalog nên migration đã chạy một lần — xoá đi để kiểm từ đầu
     * (trong transaction của test, rollback sau đó).
     */
    private function removeExisting(): void
    {
        $this->connection->executeStatement(
            'DELETE pg FROM property_group pg JOIN property_group_translation t ON t.property_group_id = pg.id WHERE t.name = :name',
            ['name' => FashionAttributes::MATERIAL_GROUP_NAME]
        );
        $this->connection->executeStatement('DELETE FROM custom_field_set WHERE name = :name', ['name' => FashionAttributes::CUSTOM_FIELD_SET]);
        $this->connection->executeStatement('DELETE FROM custom_field WHERE name = :name', ['name' => FashionAttributes::FIELD_MODEL_SIZE]);
    }
}
