<?php declare(strict_types=1);

namespace Acme\Headless\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Mẫu email của core không còn gọi route storefront Twig (ADR-006 bước 1).
 *
 * 20 mẫu (xác nhận đơn, đổi trạng thái, hoá đơn…) dựng link bằng
 * `rawUrl('frontend.account.order.single.page', …)` và hai route storefront khác. Gỡ gói
 * `shopware/storefront` thì route không còn, `rawUrl` ném lỗi → email không gửi được.
 *
 * Thay mỗi lời gọi bằng phép nối chuỗi cho ra **đúng y đường dẫn cũ** trên domain của
 * channel (domain frontend) — email khách nhận không đổi một ký tự:
 * - `/account/order/{deepLinkCode}` — trang `pages/account/order/[deepCode].vue` của Nuxt
 * - `/account/order/document/{documentId}/{deepLinkCode}/{fileType}` — bản tài liệu dễ đọc
 * - `/account/order/download/{orderId}/{downloadId}?deepLinkCode=…` — tải sản phẩm số
 *
 * Không ghi đè cấu hình người vận hành (MIG-03): chỉ thay đúng đoạn `rawUrl(...)` nguyên văn
 * của core, phần còn lại của mẫu (kể cả chỗ người vận hành đã sửa) giữ nguyên. Chạy lại vô hại.
 *
 * @internal
 */
class Migration1792666425MailTemplateFrontendUrls extends MigrationStep
{
    /**
     * Domain của channel, bỏ "/" cuối — như `addDomain()` của RawUrlFunctionExtension.
     */
    private const DOMAIN = "salesChannel.domains|first.url|trim('/', 'right')";

    /**
     * @var array<string, string> regex đoạn rawUrl của core => biểu thức Twig thay thế
     */
    public const REPLACEMENTS = [
        '/rawUrl\(\s*\'frontend\.account\.order\.single\.page\',\s*\{\s*\'deepLinkCode\':\s*order\.deepLinkCode\s*\},\s*salesChannel\.domains\|first\.url\s*\)/'
            => '(' . self::DOMAIN . " ~ '/account/order/' ~ order.deepLinkCode)",
        '/rawUrl\(\s*\'frontend\.account\.order\.single\.document\.a11y\',\s*\{\s*documentId:\s*a11y\.documentId,\s*\'?deepLinkCode\'?:\s*a11y\.deepLinkCode,\s*fileType:\s*a11y\.fileExtension,?\s*\},\s*salesChannel\.domains\|first\.url\s*\)/'
            => '(' . self::DOMAIN . " ~ '/account/order/document/' ~ a11y.documentId ~ '/' ~ a11y.deepLinkCode ~ '/' ~ a11y.fileExtension)",
        '/rawUrl\(\s*\'frontend\.account\.order\.single\.download\',\s*\{\s*\'orderId\':\s*order\.id,\s*\'downloadId\':\s*download\.id,\s*\'deepLinkCode\':\s*order\.deepLinkCode\s*\},\s*salesChannel\.domains\|first\.url\s*\)/'
            => '(' . self::DOMAIN . " ~ '/account/order/download/' ~ order.id ~ '/' ~ download.id ~ '?deepLinkCode=' ~ order.deepLinkCode)",
    ];

    public function getCreationTimestamp(): int
    {
        return 1792666425;
    }

    public function update(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT `mail_template_id`, `language_id`, `content_html`, `content_plain`
               FROM `mail_template_translation`
              WHERE `content_html` LIKE :needle OR `content_plain` LIKE :needle',
            ['needle' => '%rawUrl(%']
        );

        foreach ($rows as $row) {
            $html = self::replace((string) $row['content_html']);
            $plain = self::replace((string) $row['content_plain']);

            if ($html === $row['content_html'] && $plain === $row['content_plain']) {
                continue;
            }

            $connection->update('mail_template_translation', [
                'content_html' => $html,
                'content_plain' => $plain,
                'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [
                'mail_template_id' => $row['mail_template_id'],
                'language_id' => $row['language_id'],
            ]);
        }
    }

    public static function replace(string $template): string
    {
        return (string) preg_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $template);
    }
}
