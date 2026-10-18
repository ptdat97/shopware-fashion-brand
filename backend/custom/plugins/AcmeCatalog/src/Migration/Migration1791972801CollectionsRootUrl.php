<?php declare(strict_types=1);

namespace Acme\Catalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Trang tổng bộ sưu tập ở `/collections/` — gộp `/bo-suu-tap/` vào cùng tiền tố với
 * `/collections/{tên}`.
 *
 * Thêm ô "Là trang tổng bộ sưu tập" (custom field `acme_collection_root`) vào bộ trường
 * "Bộ sưu tập" của danh mục, và một nhánh trong template SEO danh mục. Vẫn chỉ là cấu
 * hình native — không route, không code.
 *
 * Chỉ MỘT danh mục được bật ô này: hai danh mục cùng URL `collections/` thì core chỉ
 * giữ được một.
 *
 * Template chỉ đổi khi còn đúng bản Migration1791972614LowercaseSeoUrls đặt. URL cũ
 * (`bo-suu-tap/`) thành không-canonical và được core chuyển hướng 301.
 * Sau migration: `bin/console dal:refresh:index`.
 *
 * @internal
 */
class Migration1791972801CollectionsRootUrl extends MigrationStep
{
    public const FIELD_NAME = 'acme_collection_root';

    public const SET_NAME = 'acme_collection';

    public const ROUTE_NAME = 'frontend.navigation.page';

    /** Bản do Migration1791972614LowercaseSeoUrls đặt */
    public const PREVIOUS_TEMPLATE = '{% if category.translated.customFields.acme_collection|default(false) %}'
        . 'collections/{{ category.translated.name|lower }}'
        . '{% else %}'
        . '{% for part in category.seoBreadcrumb %}{{ part|lower }}/{% endfor %}'
        . '{% endif %}';

    public const TEMPLATE = '{% if category.translated.customFields.acme_collection_root|default(false) %}'
        . 'collections/'
        . '{% elseif category.translated.customFields.acme_collection|default(false) %}'
        . 'collections/{{ category.translated.name|lower }}'
        . '{% else %}'
        . '{% for part in category.seoBreadcrumb %}{{ part|lower }}/{% endfor %}'
        . '{% endif %}';

    public function getCreationTimestamp(): int
    {
        return 1791972801;
    }

    public function update(Connection $connection): void
    {
        $setId = $connection->fetchOne('SELECT `id` FROM `custom_field_set` WHERE `name` = :name LIMIT 1', ['name' => self::SET_NAME]);

        if ($setId !== false && $connection->fetchOne('SELECT 1 FROM `custom_field` WHERE `name` = :name', ['name' => self::FIELD_NAME]) === false) {
            $connection->insert('custom_field', [
                'id' => Uuid::randomBytes(),
                'name' => self::FIELD_NAME,
                'type' => CustomFieldTypes::BOOL,
                'config' => json_encode([
                    'label' => [
                        'vi-VN' => 'Là trang tổng bộ sưu tập (URL /collections/)',
                        'en-GB' => 'Is the collections overview (URL /collections/)',
                    ],
                    'helpText' => [
                        'vi-VN' => 'Bật cho đúng MỘT danh mục — thường là danh mục cha chứa các bộ sưu tập.',
                        'en-GB' => 'Enable for exactly ONE category — usually the parent of all collections.',
                    ],
                    'componentName' => 'sw-field',
                    'customFieldType' => 'switch',
                    'type' => 'switch',
                    'customFieldPosition' => 0,
                ], \JSON_THROW_ON_ERROR),
                'active' => 1,
                'set_id' => $setId,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $connection->executeStatement(
            'UPDATE `seo_url_template`
                SET `template` = :new, `updated_at` = :now
              WHERE `route_name` = :route AND `sales_channel_id` IS NULL AND `is_headless` = 0
                AND `template` = :previous',
            [
                'new' => self::TEMPLATE,
                'previous' => self::PREVIOUS_TEMPLATE,
                'route' => self::ROUTE_NAME,
                'now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
