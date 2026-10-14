<?php declare(strict_types=1);

namespace Acme\Brand\Tests\Integration\Migration;

use Acme\Brand\Migration\Migration1791823368CreateBrandTables;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class Migration1791823368CreateBrandTablesTest extends TestCase
{
    use KernelTestBehaviour;

    public function testRunningAgainOnInstalledPluginIsHarmless(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $columnsBefore = $connection->createSchemaManager()->listTableColumns('acme_brand');

        (new Migration1791823368CreateBrandTables())->update($connection);

        static::assertEquals($columnsBefore, $connection->createSchemaManager()->listTableColumns('acme_brand'));
        static::assertTrue($connection->createSchemaManager()->tablesExist(['acme_brand_translation']));
    }
}
