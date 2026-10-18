<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Đưa channel headless (type API) về thị trường VN — channel bán hàng duy nhất từ ADR-005.
 *
 * Migration1786157045VietnameseStorefront chỉ xử lý channel type Storefront. Từ khi
 * chuyển sang headless, frontend Nuxt gọi Store API bằng channel type API, nên channel
 * này cũng phải chạy vi-VN, và cần một domain `is_external_storefront = 1` — core chỉ
 * sinh SEO URL và sitemap cho channel API qua domain loại này
 * (SeoUrlUpdater, SeoUrlGenerator, SitemapSalesChannelLoader).
 *
 * URL frontend khác nhau theo môi trường nên đọc từ biến môi trường ACME_FRONTEND_URL
 * (backend/.env.local). Thiếu biến → bỏ qua bước domain, thêm sau trong Administration.
 *
 * Không ghi đè cấu hình người vận hành đã sửa (MIG-03): chỉ đổi ngôn ngữ khi channel
 * và domain còn ở ngôn ngữ mặc định của hệ thống.
 *
 * @internal
 */
class Migration1792336157VietnameseHeadlessChannel extends MigrationStep
{
    public const FRONTEND_URL_ENV = 'ACME_FRONTEND_URL';

    public function getCreationTimestamp(): int
    {
        return 1792336157;
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
            // SwagLanguagePack chưa cài — giống migration storefront, chưa tới lúc.
            return;
        }

        $headlessIds = $connection->fetchFirstColumn(
            'SELECT `id` FROM `sales_channel` WHERE `type_id` = :typeId ORDER BY `created_at`, `id`',
            ['typeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)]
        );

        if ($headlessIds === []) {
            return;
        }

        $snippetSetId = $connection->fetchOne(
            'SELECT `id` FROM `snippet_set` WHERE `iso` = :iso ORDER BY `name` LIMIT 1',
            ['iso' => 'vi-VN']
        );

        $frontendUrl = $this->frontendUrl();
        $systemLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        foreach ($headlessIds as $salesChannelId) {
            // Tiền tệ mặc định của channel phải nằm trong danh sách tiền tệ được gán — thiếu thì
            // Store API trả SYSTEM__CURRENCY_DOES_NOT_EXISTS_EXCEPTION (gặp 18/10/2026).
            $connection->executeStatement(
                'INSERT IGNORE INTO `sales_channel_currency` (`sales_channel_id`, `currency_id`)
                 SELECT `id`, `currency_id` FROM `sales_channel` WHERE `id` = :salesChannelId',
                ['salesChannelId' => $salesChannelId]
            );

            $connection->executeStatement(
                'INSERT IGNORE INTO `sales_channel_language` (`sales_channel_id`, `language_id`)
                 VALUES (:salesChannelId, :languageId)',
                ['salesChannelId' => $salesChannelId, 'languageId' => $vietnameseId]
            );

            $connection->executeStatement(
                'UPDATE `sales_channel` SET `language_id` = :languageId, `updated_at` = :now
                 WHERE `id` = :id AND `language_id` = :systemLanguageId',
                [
                    'languageId' => $vietnameseId,
                    'now' => $this->now(),
                    'id' => $salesChannelId,
                    'systemLanguageId' => $systemLanguageId,
                ]
            );

            $connection->executeStatement(
                'UPDATE `sales_channel_domain` SET `language_id` = :languageId, `updated_at` = :now
                 WHERE `sales_channel_id` = :salesChannelId AND `language_id` = :systemLanguageId',
                [
                    'languageId' => $vietnameseId,
                    'now' => $this->now(),
                    'salesChannelId' => $salesChannelId,
                    'systemLanguageId' => $systemLanguageId,
                ]
            );

            if ($snippetSetId !== false) {
                $connection->executeStatement(
                    'UPDATE `sales_channel_domain` d
                     JOIN `snippet_set` s ON s.`id` = d.`snippet_set_id`
                     SET d.`snippet_set_id` = :snippetSetId, d.`updated_at` = :now
                     WHERE d.`sales_channel_id` = :salesChannelId AND d.`language_id` = :languageId
                       AND s.`iso` <> :iso',
                    [
                        'snippetSetId' => $snippetSetId,
                        'now' => $this->now(),
                        'salesChannelId' => $salesChannelId,
                        'languageId' => $vietnameseId,
                        'iso' => 'vi-VN',
                    ]
                );
            }
        }

        if ($frontendUrl !== null && $snippetSetId !== false) {
            // Một URL chỉ gắn được một channel (UNIQUE url) — gắn vào channel API cũ nhất
            // (channel Headless bản cài tạo sẵn; ADR-005 chỉ dùng một channel).
            $this->ensureExternalDomain($connection, $headlessIds[0], $frontendUrl, $vietnameseId, $snippetSetId);
        }
    }

    private function ensureExternalDomain(
        Connection $connection,
        string $salesChannelId,
        string $url,
        string $languageId,
        string $snippetSetId,
    ): void {
        // Core cho mỗi (channel, ngôn ngữ) đúng một domain external storefront
        // (uniq.sales_channel_domain.external_storefront). Đã có — kể cả URL khác do
        // người vận hành đặt — thì giữ nguyên.
        $exists = $connection->fetchOne(
            'SELECT 1 FROM `sales_channel_domain`
             WHERE `url` = :url
                OR (`sales_channel_id` = :salesChannelId AND `language_id` = :languageId AND `is_external_storefront` = 1)',
            ['url' => $url, 'salesChannelId' => $salesChannelId, 'languageId' => $languageId]
        );
        if ($exists !== false) {
            return;
        }

        $connection->insert('sales_channel_domain', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => $salesChannelId,
            'language_id' => $languageId,
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'snippet_set_id' => $snippetSetId,
            'url' => $url,
            'hreflang_use_only_locale' => 0,
            'is_external_storefront' => 1,
            'created_at' => $this->now(),
        ]);
    }

    private function frontendUrl(): ?string
    {
        $url = EnvironmentHelper::getVariable(self::FRONTEND_URL_ENV);
        if (!\is_string($url) || trim($url) === '') {
            return null;
        }

        return rtrim(trim($url), '/');
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
