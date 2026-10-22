<?php declare(strict_types=1);

namespace Acme\Headless\Tests\Integration;

use Acme\Headless\Migration\Migration1792666425MailTemplateFrontendUrls as Migration;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * Link trong email không đổi sau khi bỏ rawUrl (ADR-006 bước 1), và mẫu email render được
 * mà không cần route storefront.
 *
 * @internal
 */
class MailTemplateFrontendUrlsTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * Đoạn nguyên văn trong mẫu email của core (có cả biến thể xuống dòng) => URL mong đợi
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function coreSnippets(): iterable
    {
        foreach (['http://localhost:3000' => 'http://localhost:3000', 'https://shop.example.vn/vi/' => 'https://shop.example.vn/vi'] as $domain => $base) {
            yield 'trang đơn ' . $domain => [
                "rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url)",
                $domain,
                $base . '/account/order/Abc-123_xyz',
            ];
            yield 'tài liệu dễ đọc ' . $domain => [
                "rawUrl(\n    'frontend.account.order.single.document.a11y',\n    {\n        documentId: a11y.documentId,\n        'deepLinkCode': a11y.deepLinkCode,\n        fileType: a11y.fileExtension,\n    },\n    salesChannel.domains|first.url\n)",
                $domain,
                $base . '/account/order/document/0190cc/Abc-123_xyz/html',
            ];
            yield 'tải sản phẩm số ' . $domain => [
                "rawUrl('frontend.account.order.single.download', {'orderId': order.id, 'downloadId': download.id, 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url)",
                $domain,
                $base . '/account/order/download/0190aa/0190bb?deepLinkCode=Abc-123_xyz',
            ];
        }
    }

    #[DataProvider('coreSnippets')]
    public function testReplacementRendersTheSameUrl(string $snippet, string $domain, string $expected): void
    {
        $replaced = Migration::replace($snippet);

        static::assertStringNotContainsString('rawUrl', $replaced);
        static::assertSame($expected, $this->render($replaced, $domain));

        // Khi storefront còn cài: đối chiếu thẳng với rawUrl() của core
        if (static::getContainer()->get('router')->getRouteCollection()->get('frontend.account.order.single.page') !== null) {
            static::assertSame($this->render($snippet, $domain), $this->render($replaced, $domain));
        }
    }

    public function testMigrationRemovesStorefrontRoutesKeepsOperatorEdits(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        // Mẫu của core + một chỗ người vận hành tự sửa (DB test có thể đã chạy migration lúc cài plugin)
        $connection->executeStatement(
            'UPDATE mail_template_translation SET content_plain = :content LIMIT 1',
            ['content' => "Shop tự sửa. {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}"]
        );

        $migration = new Migration();
        $migration->update($connection);
        $migration->update($connection);

        static::assertSame(0, (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM mail_template_translation WHERE content_html LIKE '%rawUrl(%' OR content_plain LIKE '%rawUrl(%'"
        ));
        static::assertGreaterThan(0, (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM mail_template_translation WHERE content_plain LIKE 'Shop tự sửa. {{ (salesChannel.domains%/account/order/%'"
        ));
    }

    public function testEveryMigratedTemplateCompiles(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        (new Migration())->update($connection);

        $twig = static::getContainer()->get('twig');
        $templates = $connection->fetchAllAssociative('SELECT content_html, content_plain FROM mail_template_translation');
        static::assertNotEmpty($templates);

        foreach ($templates as $template) {
            foreach ($template as $source) {
                // Biên dịch: lỗi cú pháp do thay chuỗi sẽ lộ ra ở đây
                $twig->createTemplate((string) $source);
            }
        }
        $this->addToAssertionCount(1);
    }

    private function render(string $expression, string $domain): string
    {
        return static::getContainer()->get(StringTemplateRenderer::class)->render('{{ ' . $expression . ' }}', [
            'order' => ['id' => '0190aa', 'deepLinkCode' => 'Abc-123_xyz'],
            'download' => ['id' => '0190bb'],
            'a11y' => ['documentId' => '0190cc', 'deepLinkCode' => 'Abc-123_xyz', 'fileExtension' => 'html'],
            'salesChannel' => ['domains' => [['url' => $domain]]],
        ], Context::createDefaultContext());
    }
}
