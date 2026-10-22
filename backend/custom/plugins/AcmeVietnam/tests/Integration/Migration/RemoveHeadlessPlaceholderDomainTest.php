<?php declare(strict_types=1);

namespace Acme\Vietnam\Tests\Integration\Migration;

use Acme\Vietnam\Migration\Migration1792666986RemoveHeadlessPlaceholderDomain as Migration;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Link trong email lấy `salesChannel.domains|first.url` — domain giữ chỗ phải biến mất khi
 * đã có domain frontend thật.
 *
 * @internal
 */
class RemoveHeadlessPlaceholderDomainTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testPlaceholderRemovedOnlyWhenFrontendDomainExists(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $domain = static fn (string $url, bool $external = false): array => [
            'url' => $url,
            'isExternalStorefront' => $external,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => (string) $connection->fetchOne('SELECT LOWER(HEX(id)) FROM snippet_set WHERE iso = "en-GB" LIMIT 1'),
        ];

        $withFrontend = $this->createSalesChannel([
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [
                $domain('default.headless71'),
                $domain('http://frontend-71.acme.test', true),
            ],
        ]);
        $onlyPlaceholder = $this->createSalesChannel([
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [$domain('default.headless72')],
        ]);

        $migration = new Migration();
        $migration->update($connection);
        $migration->update($connection);

        $urls = static fn (string $channelId): array => $connection->fetchFirstColumn(
            'SELECT url FROM sales_channel_domain WHERE sales_channel_id = :id ORDER BY url',
            ['id' => Uuid::fromHexToBytes($channelId)]
        );

        static::assertSame(['http://frontend-71.acme.test'], $urls($withFrontend['id']), 'Email trỏ về domain frontend');
        static::assertSame(['default.headless72'], $urls($onlyPlaceholder['id']), 'Chưa có domain frontend thì giữ — channel cần ít nhất một domain');
    }
}
