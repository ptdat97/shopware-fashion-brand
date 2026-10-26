<?php declare(strict_types=1);

namespace Acme\Vietnam\Tests\Integration\Migration;

use Acme\Vietnam\Migration\Migration1793027264VietnameseMailTemplates as Migration;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class VietnameseMailTemplatesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testEveryTemplateCompiles(): void
    {
        $twig = static::getContainer()->get('twig');

        foreach (Migration::templates() as $name => $template) {
            foreach (['subject', 'html', 'plain'] as $part) {
                // Lỗi cú pháp Twig lộ ra ở đây thay vì lúc gửi email cho khách
                $twig->createTemplate(Migration::SENDER_NAME . $template[$part], $name . '/' . $part);
            }
        }

        static::assertCount(16, Migration::templates());
    }

    public function testAddsVietnameseWithoutOverwritingOperatorEdits(): void
    {
        $vietnameseId = $this->ensureVietnamese();
        $orderConfirmation = $this->templateId('order_confirmation_mail');

        // Người vận hành đã có bản vi-VN riêng cho email xác nhận đơn
        $this->connection->executeStatement(
            'DELETE FROM mail_template_translation WHERE language_id = :lang',
            ['lang' => $vietnameseId]
        );
        $this->connection->insert('mail_template_translation', [
            'mail_template_id' => $orderConfirmation,
            'language_id' => $vietnameseId,
            'subject' => 'Shop tự viết',
            'content_html' => 'x',
            'content_plain' => 'x',
            'created_at' => '2026-10-26 00:00:00',
        ]);

        $migration = new Migration();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame('Shop tự viết', $this->subject('order_confirmation_mail', $vietnameseId));
        static::assertSame('Đã nhận thanh toán đơn hàng #{{ order.orderNumber }}', $this->subject('order_transaction.state.paid', $vietnameseId));
        static::assertSame(16, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM mail_template_translation WHERE language_id = :lang',
            ['lang' => $vietnameseId]
        ));
    }

    private function subject(string $type, string $languageId): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT mt.subject FROM mail_template_translation mt
               JOIN mail_template m ON m.id = mt.mail_template_id
               JOIN mail_template_type t ON t.id = m.mail_template_type_id
              WHERE t.technical_name = :type AND m.system_default = 1 AND mt.language_id = :lang',
            ['type' => $type, 'lang' => $languageId]
        );
    }

    private function templateId(string $type): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT m.id FROM mail_template m JOIN mail_template_type t ON t.id = m.mail_template_type_id
              WHERE t.technical_name = :type AND m.system_default = 1',
            ['type' => $type]
        );
    }

    /**
     * DB test chỉ có en-GB/de-DE — thêm ngôn ngữ vi-VN nếu thiếu.
     */
    private function ensureVietnamese(): string
    {
        $localeId = $this->connection->fetchOne('SELECT id FROM locale WHERE code = "vi-VN"');
        static::assertNotFalse($localeId, 'Core có sẵn locale vi-VN');

        $languageId = $this->connection->fetchOne('SELECT id FROM language WHERE locale_id = :locale', ['locale' => $localeId]);
        if ($languageId !== false) {
            return (string) $languageId;
        }

        $languageId = Uuid::randomBytes();
        $this->connection->insert('language', [
            'id' => $languageId,
            'name' => 'Tiếng Việt',
            'locale_id' => $localeId,
            'translation_code_id' => $localeId,
            'created_at' => '2026-10-26 00:00:00',
        ]);

        return $languageId;
    }
}
