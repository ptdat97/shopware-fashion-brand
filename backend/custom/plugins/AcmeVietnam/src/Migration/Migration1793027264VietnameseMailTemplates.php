<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Bản dịch vi-VN cho mẫu email của core — trước đây mọi mẫu chỉ có tiếng Anh, khách VN nhận
 * "Order confirmation" (phát hiện 22/10/2026, ADR-006). Nội dung nằm ở
 * `Resources/mail-templates/vi-VN/<technical name>/{html,plain}.twig` + `templates.json`
 * (tiêu đề, mô tả trong Admin) — có lịch sử git, review được.
 *
 * Dữ liệu native của core (bản dịch `mail_template_translation`, quy tắc 6): người vận hành
 * sửa tiếp trong Administration → Settings → Email templates. Chỉ thêm khi mẫu chưa có bản
 * vi-VN — không ghi đè (MIG-03). Mẫu nào chưa dịch vẫn dùng tiếng Anh (ngôn ngữ hệ thống).
 *
 * Đổi nội dung file sau khi migration đã chạy: viết migration mới chỉ cập nhật khi bản trong
 * DB còn đúng nội dung cũ (MIG-04).
 *
 * @internal
 */
class Migration1793027264VietnameseMailTemplates extends MigrationStep
{
    final public const TEMPLATE_DIR = __DIR__ . '/../Resources/mail-templates/vi-VN';

    /** Tên người gửi: tên shop (Settings → Basic information), thiếu thì tên sales channel */
    final public const SENDER_NAME = "{{ config('core.basicInformation.shopName') ?: salesChannel.translated.name }}";

    public function getCreationTimestamp(): int
    {
        return 1793027264;
    }

    public function update(Connection $connection): void
    {
        $vietnameseId = $connection->fetchOne(
            'SELECT l.`id` FROM `language` l
             JOIN `locale` loc ON loc.`id` = l.`locale_id`
             WHERE loc.`code` = :code LIMIT 1',
            ['code' => 'vi-VN']
        );

        if ($vietnameseId === false) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

        foreach (self::templates() as $technicalName => $template) {
            // Mẫu mặc định của từng loại; INSERT IGNORE theo khoá (mẫu, ngôn ngữ) — đã có bản vi-VN thì giữ
            $connection->executeStatement(
                'INSERT IGNORE INTO `mail_template_translation`
                    (`mail_template_id`, `language_id`, `sender_name`, `subject`, `description`,
                     `content_html`, `content_plain`, `created_at`)
                 SELECT m.`id`, :lang, :sender, :subject, :description, :html, :plain, :now
                   FROM `mail_template` m
                   JOIN `mail_template_type` t ON t.`id` = m.`mail_template_type_id`
                  WHERE t.`technical_name` = :type AND m.`system_default` = 1',
                [
                    'lang' => $vietnameseId,
                    'sender' => self::SENDER_NAME,
                    'subject' => $template['subject'],
                    'description' => $template['description'],
                    'html' => $template['html'],
                    'plain' => $template['plain'],
                    'now' => $now,
                    'type' => $technicalName,
                ]
            );
        }
    }

    /**
     * @return array<string, array{subject: string, description: string, html: string, plain: string}>
     */
    public static function templates(): array
    {
        /** @var array<string, array{subject: string, description: string}> $index */
        $index = json_decode((string) file_get_contents(self::TEMPLATE_DIR . '/templates.json'), true, 512, \JSON_THROW_ON_ERROR);

        $templates = [];
        foreach ($index as $technicalName => $meta) {
            $templates[$technicalName] = [
                ...$meta,
                'html' => (string) file_get_contents(self::TEMPLATE_DIR . '/' . $technicalName . '/html.twig'),
                'plain' => (string) file_get_contents(self::TEMPLATE_DIR . '/' . $technicalName . '/plain.twig'),
            ];
        }

        return $templates;
    }
}
