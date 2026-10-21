<?php declare(strict_types=1);

namespace Acme\Catalog\Tests\Integration\Migration;

use Acme\Catalog\Migration\Migration1792576523SyncOptionPositionsAcrossLanguages as SyncPositions;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class Migration1792576523SyncOptionPositionsAcrossLanguagesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    private string $germanId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        // Ngôn ngữ thứ hai có sẵn trong mọi bản cài core (DB test không có vi-VN)
        $this->germanId = (string) $this->connection->fetchOne(
            'SELECT LOWER(HEX(l.id)) FROM language l JOIN locale loc ON loc.id = l.locale_id WHERE loc.code = "de-DE"'
        );
    }

    public function testDefaultPositionsOfOtherLanguagesFollowSystemLanguage(): void
    {
        // Như dữ liệu demo cũ: position chỉ có ở ngôn ngữ hệ thống, bản dịch kia mặc định 1
        $this->createSizeGroup(['S' => 1, 'M' => 2, 'L' => 3, 'XL' => 4], ['S' => 1, 'M' => 1, 'L' => 1, 'XL' => 1]);

        $migration = new SyncPositions();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(['S' => 1, 'M' => 2, 'L' => 3, 'XL' => 4], $this->positions($this->germanId));
    }

    public function testPositionsSetByMerchantAreKept(): void
    {
        $this->createSizeGroup(['S' => 1, 'M' => 2, 'L' => 3, 'XL' => 4], ['S' => 4, 'M' => 3, 'L' => 2, 'XL' => 1]);

        (new SyncPositions())->update($this->connection);

        static::assertSame(['S' => 4, 'M' => 3, 'L' => 2, 'XL' => 1], $this->positions($this->germanId), 'Bản dịch đã có thứ tự riêng giữ nguyên (XL = 1 cũng không bị đổi vì là thứ tự cố ý)');
    }

    /**
     * @param array<string, int> $system
     * @param array<string, int> $german
     */
    private function createSizeGroup(array $system, array $german): void
    {
        $options = [];
        foreach ($system as $name => $position) {
            $options[] = [
                'id' => $this->ids->get($name),
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => $name, 'position' => $position],
                    $this->germanId => ['name' => $name, 'position' => $german[$name]],
                ],
            ];
        }

        static::getContainer()->get('property_group.repository')->create([[
            'id' => $this->ids->get('group'),
            'name' => 'Size test',
            'sortingType' => 'position',
            'displayType' => 'text',
            'options' => $options,
        ]], Context::createDefaultContext());
    }

    /**
     * @return array<string, int>
     */
    private function positions(string $languageId): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT t.name, t.position FROM property_group_option_translation t
               JOIN property_group_option o ON o.id = t.property_group_option_id
              WHERE o.property_group_id = :group AND t.language_id = :lang
              ORDER BY FIELD(t.name, "S", "M", "L", "XL")',
            ['group' => Uuid::fromHexToBytes($this->ids->get('group')), 'lang' => Uuid::fromHexToBytes($languageId)]
        );

        return array_map('intval', $rows);
    }
}
