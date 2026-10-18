<?php declare(strict_types=1);

namespace Acme\Brand\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Bảng mở rộng 1–1 cho `product_manufacturer`.
 *
 * - Xoá manufacturer → xoá luôn phần mở rộng (CASCADE).
 * - Xoá media banner / layout CMS → chỉ bỏ liên kết (SET NULL), brand vẫn còn.
 * - `code` là mã brand 3 ký tự dùng làm tiền tố SKU, duy nhất toàn hệ thống.
 *
 * @internal
 */
class Migration1791823368CreateBrandTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791823368;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `acme_brand` (
                `id`                              BINARY(16)  NOT NULL,
                `product_manufacturer_id`         BINARY(16)  NOT NULL,
                `product_manufacturer_version_id` BINARY(16)  NOT NULL,
                `code`                            VARCHAR(3)  NULL,
                `active`                          TINYINT(1)  NOT NULL DEFAULT 0,
                `featured`                        TINYINT(1)  NOT NULL DEFAULT 0,
                `position`                        INT         NOT NULL DEFAULT 0,
                `banner_media_id`                 BINARY(16)  NULL,
                `cms_page_id`                     BINARY(16)  NULL,
                `cms_page_version_id`             BINARY(16)  NULL,
                `created_at`                      DATETIME(3) NOT NULL,
                `updated_at`                      DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.acme_brand.manufacturer` (`product_manufacturer_id`, `product_manufacturer_version_id`),
                UNIQUE KEY `uniq.acme_brand.code` (`code`),
                KEY `idx.acme_brand.active_position` (`active`, `position`),
                CONSTRAINT `fk.acme_brand.product_manufacturer_id`
                    FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`)
                    REFERENCES `product_manufacturer` (`id`, `version_id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.acme_brand.banner_media_id`
                    FOREIGN KEY (`banner_media_id`)
                    REFERENCES `media` (`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.acme_brand.cms_page_id`
                    FOREIGN KEY (`cms_page_id`, `cms_page_version_id`)
                    REFERENCES `cms_page` (`id`, `version_id`)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `acme_brand_translation` (
                `acme_brand_id`    BINARY(16)   NOT NULL,
                `language_id`      BINARY(16)   NOT NULL,
                `meta_title`       VARCHAR(255) NULL,
                `meta_description` LONGTEXT     NULL,
                `keywords`         LONGTEXT     NULL,
                `created_at`       DATETIME(3)  NOT NULL,
                `updated_at`       DATETIME(3)  NULL,
                PRIMARY KEY (`acme_brand_id`, `language_id`),
                CONSTRAINT `fk.acme_brand_translation.acme_brand_id`
                    FOREIGN KEY (`acme_brand_id`)
                    REFERENCES `acme_brand` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.acme_brand_translation.language_id`
                    FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
