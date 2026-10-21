<?php declare(strict_types=1);

namespace Acme\Headless\Tests\Integration;

use Acme\Headless\Migration\Migration1792598655HeadlessRobotsRules;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * /store-api/shop-settings trên channel type API như frontend Nuxt gọi (header sw-access-key).
 *
 * Gọi thẳng HttpKernel::handle() thay vì KernelBrowser: dự án không cài symfony/browser-kit.
 *
 * @internal
 */
class HeadlessShopSettingsTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private string $accessKey;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $channel = $this->createSalesChannel([
            'id' => $this->ids->get('channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [['url' => 'http://headless-frontend.acme.test', 'isExternalStorefront' => true]],
        ]);
        $this->accessKey = (string) $channel['accessKey'];
    }

    public function testWithoutLogoFrontendFallsBackToShopName(): void
    {
        $settings = $this->shopSettings();

        static::assertArrayHasKey('shopName', $settings['general'], 'Dữ liệu core vẫn nguyên');
        static::assertNull($settings['extensions']['acmeHeadless']['logo']);
    }

    public function testConfiguredLogoComesWithUrlAndAlt(): void
    {
        static::getContainer()->get('media.repository')->create([[
            'id' => $this->ids->get('logo'),
            'fileName' => 'logo-acme',
            'fileExtension' => 'svg',
            'mimeType' => 'image/svg+xml',
            'path' => 'media/ac/me/logo-acme.svg',
            'alt' => 'Logo Acme',
        ]], Context::createDefaultContext());
        $this->config()->set('AcmeHeadless.config.logo', $this->ids->get('logo'), $this->ids->get('channel'));

        $logo = $this->shopSettings()['extensions']['acmeHeadless']['logo'];

        static::assertStringContainsString('/media/ac/me/logo-acme.svg', $logo['url']);
        static::assertSame('Logo Acme', $logo['translated']['alt']);
    }

    public function testDeletedLogoMediaMeansNoLogo(): void
    {
        $this->config()->set('AcmeHeadless.config.logo', Uuid::randomHex(), $this->ids->get('channel'));

        static::assertNull($this->shopSettings()['extensions']['acmeHeadless']['logo']);
    }

    public function testRobotsRulesOfTheChannel(): void
    {
        $this->config()->set('core.basicInformation.robotsRules', "Disallow: /account\n", $this->ids->get('channel'));
        $this->config()->set('core.basicInformation.robotsDisableDefaults', true, $this->ids->get('channel'));

        $headless = $this->shopSettings()['extensions']['acmeHeadless'];

        static::assertSame('Disallow: /account', $headless['robotsRules']);
        static::assertTrue($headless['robotsDisableDefaults']);
        static::assertFalse($headless['staging']);
    }

    public function testMigrationSetsFrontendRobotsRulesWithoutOverwritingOperator(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $operatorChannel = $this->createSalesChannel([
            'id' => $this->ids->get('operator-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [['url' => 'http://operator.acme.test', 'isExternalStorefront' => true]],
        ]);
        $this->config()->set(Migration1792598655HeadlessRobotsRules::CONFIG_KEY, 'Disallow: /tu-dat', $operatorChannel['id']);

        $migration = new Migration1792598655HeadlessRobotsRules();
        $migration->update($connection);
        $migration->update($connection);

        static::assertSame(Migration1792598655HeadlessRobotsRules::RULES, $this->rawRules($this->ids->get('channel')));
        static::assertSame('Disallow: /tu-dat', $this->rawRules($operatorChannel['id']), 'Giá trị người vận hành đặt giữ nguyên');
        static::assertSame(1, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM system_config WHERE configuration_key = :key AND sales_channel_id = :sc',
            ['key' => Migration1792598655HeadlessRobotsRules::CONFIG_KEY, 'sc' => Uuid::fromHexToBytes($this->ids->get('channel'))]
        ), 'Chạy lại không nhân bản');
    }

    private function rawRules(string $salesChannelId): ?string
    {
        $value = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id = :sc',
            ['key' => Migration1792598655HeadlessRobotsRules::CONFIG_KEY, 'sc' => Uuid::fromHexToBytes($salesChannelId)]
        );

        return \is_string($value) ? json_decode($value, true, 512, \JSON_THROW_ON_ERROR)['_value'] : null;
    }

    private function config(): SystemConfigService
    {
        return static::getContainer()->get(SystemConfigService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function shopSettings(): array
    {
        $request = Request::create('/store-api/shop-settings', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_' . PlatformRequest::HEADER_ACCESS_KEY => $this->accessKey,
            'HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN => Uuid::randomHex(),
        ]);
        $response = static::getKernel()->handle($request);

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
