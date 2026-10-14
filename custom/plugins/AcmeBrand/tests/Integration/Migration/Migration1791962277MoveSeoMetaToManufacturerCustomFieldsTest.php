<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Integration\Migration;

use Acme\Brand\BrandCustomFields;
use Acme\Brand\Migration\Migration1791962277MoveSeoMetaToManufacturerCustomFields;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Không test updateDestructive(): chỉ là DROP TABLE, và DDL trong test sẽ phá transaction
 * rollback của IntegrationTestBehaviour.
 *
 * @internal
 */
class Migration1791962277MoveSeoMetaToManufacturerCustomFieldsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private string $manufacturerId;

    private string $brandId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->manufacturerId = Uuid::randomHex();
        $this->brandId = Uuid::randomHex();

        static::getContainer()->get('product_manufacturer.repository')->create([[
            'id' => $this->manufacturerId,
            'name' => 'Brand cũ',
            'acmeBrand' => ['id' => $this->brandId, 'active' => true],
        ]], Context::createDefaultContext());
    }

    public function testOldMetaIsCopiedIntoManufacturerCustomFieldsOfTheSameLanguage(): void
    {
        $this->insertOldTranslation(['meta_title' => 'Tiêu đề cũ', 'keywords' => 'đầm, váy']);

        (new Migration1791962277MoveSeoMetaToManufacturerCustomFields())->update($this->connection);

        // assertEquals: cột JSON của MySQL tự sắp lại thứ tự khoá
        static::assertEquals(
            [BrandCustomFields::META_TITLE => 'Tiêu đề cũ', BrandCustomFields::KEYWORDS => 'đầm, váy'],
            $this->customFields()
        );
    }

    public function testValueAlreadyEnteredInCustomFieldWins(): void
    {
        static::getContainer()->get('product_manufacturer.repository')->update([[
            'id' => $this->manufacturerId,
            'customFields' => [BrandCustomFields::META_TITLE => 'Đã nhập mới'],
        ]], Context::createDefaultContext());
        $this->insertOldTranslation(['meta_title' => 'Tiêu đề cũ']);

        (new Migration1791962277MoveSeoMetaToManufacturerCustomFields())->update($this->connection);

        static::assertSame([BrandCustomFields::META_TITLE => 'Đã nhập mới'], $this->customFields());
    }

    public function testOldTranslationPrivilegesAreRemovedFromRoles(): void
    {
        $roleId = Uuid::randomBytes();
        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'Test ' . Uuid::randomHex(),
            'privileges' => json_encode(['acme_brand:read', 'acme_brand_translation:read', 'acme_brand_translation:update']),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        (new Migration1791962277MoveSeoMetaToManufacturerCustomFields())->update($this->connection);

        static::assertSame(['acme_brand:read'], json_decode((string) $this->connection->fetchOne(
            'SELECT privileges FROM acl_role WHERE id = :id',
            ['id' => $roleId]
        ), true));
    }

    public function testCustomFieldsAreAttachedToManufacturerOnlyOnce(): void
    {
        $migration = new Migration1791962277MoveSeoMetaToManufacturerCustomFields();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(3, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM custom_field f JOIN custom_field_set s ON s.id = f.set_id
               JOIN custom_field_set_relation r ON r.set_id = s.id
              WHERE s.name = :set AND r.entity_name = "product_manufacturer"',
            ['set' => BrandCustomFields::SET_NAME]
        ));
    }

    /**
     * @param array<string, string> $values
     */
    private function insertOldTranslation(array $values): void
    {
        $this->connection->insert('acme_brand_translation', $values + [
            'acme_brand_id' => Uuid::fromHexToBytes($this->brandId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function customFields(): array
    {
        $json = $this->connection->fetchOne(
            'SELECT custom_fields FROM product_manufacturer_translation
              WHERE product_manufacturer_id = :id AND language_id = :language',
            ['id' => Uuid::fromHexToBytes($this->manufacturerId), 'language' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        return $json ? json_decode((string) $json, true) : [];
    }
}
