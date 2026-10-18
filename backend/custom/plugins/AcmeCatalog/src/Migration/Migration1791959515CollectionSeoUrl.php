<?php declare(strict_types=1);

namespace Acme\Catalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Bộ sưu tập = danh mục được đánh dấu "Là bộ sưu tập" → URL `/collections/{tên}`.
 *
 * Bộ sưu tập vẫn là category + product stream của core (ADR-003, nguyên tắc 5). Plugin
 * chỉ thêm một ô đánh dấu và đổi template SEO của trang danh mục:
 *
 *   có đánh dấu   → collections/hang-moi-ve
 *   không đánh dấu → giữ nguyên đường dẫn theo cây danh mục (Dam/, Ao/…)
 *
 * Chỉ đổi template khi nó còn là bản mặc định của core — người vận hành đã tự sửa
 * trong Settings → SEO thì không ghi đè.
 *
 * Sau migration phải sinh lại URL danh mục: `bin/console dal:refresh:index`.
 *
 * @internal
 */
class Migration1791959515CollectionSeoUrl extends MigrationStep
{
    public const SET_NAME = 'acme_collection';

    public const FIELD_NAME = 'acme_collection';

    public const ROUTE_NAME = 'frontend.navigation.page';

    public const CORE_TEMPLATE = '{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}';

    public const COLLECTION_TEMPLATE = '{% if category.translated.customFields.acme_collection|default(false) %}'
        . 'collections/{{ category.translated.name|lower }}'
        . '{% else %}'
        . '{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}'
        . '{% endif %}';

    public function getCreationTimestamp(): int
    {
        return 1791959515;
    }

    public function update(Connection $connection): void
    {
        $setId = $this->ensureCustomFieldSet($connection);
        $this->ensureField($connection, $setId);

        $connection->executeStatement(
            'UPDATE `seo_url_template`
                SET `template` = :collection, `updated_at` = :now
              WHERE `route_name` = :route AND `sales_channel_id` IS NULL AND `is_headless` = 0
                AND `template` = :core',
            [
                'collection' => self::COLLECTION_TEMPLATE,
                'core' => self::CORE_TEMPLATE,
                'route' => self::ROUTE_NAME,
                'now' => $this->now(),
            ]
        );
    }

    private function ensureCustomFieldSet(Connection $connection): string
    {
        $existing = $connection->fetchOne('SELECT `id` FROM `custom_field_set` WHERE `name` = :name LIMIT 1', ['name' => self::SET_NAME]);
        if ($existing !== false) {
            return $existing;
        }

        $setId = Uuid::randomBytes();
        $connection->insert('custom_field_set', [
            'id' => $setId,
            'name' => self::SET_NAME,
            'active' => 1,
            'config' => json_encode([
                'label' => ['vi-VN' => 'Bộ sưu tập', 'en-GB' => 'Collection'],
                'translated' => true,
            ], \JSON_THROW_ON_ERROR),
            'created_at' => $this->now(),
        ]);

        $connection->insert('custom_field_set_relation', [
            'id' => Uuid::randomBytes(),
            'set_id' => $setId,
            'entity_name' => 'category',
            'created_at' => $this->now(),
        ]);

        return $setId;
    }

    private function ensureField(Connection $connection, string $setId): void
    {
        $exists = $connection->fetchOne('SELECT 1 FROM `custom_field` WHERE `name` = :name LIMIT 1', ['name' => self::FIELD_NAME]);
        if ($exists !== false) {
            return;
        }

        $connection->insert('custom_field', [
            'id' => Uuid::randomBytes(),
            'name' => self::FIELD_NAME,
            'type' => CustomFieldTypes::BOOL,
            'config' => json_encode([
                'label' => [
                    'vi-VN' => 'Là bộ sưu tập (URL /collections/…)',
                    'en-GB' => 'Is a collection (URL /collections/…)',
                ],
                'helpText' => [
                    'vi-VN' => 'Bật thì URL của danh mục là /collections/{tên}. Nên gán sản phẩm bằng Nhóm sản phẩm động.',
                    'en-GB' => 'When enabled the category URL becomes /collections/{name}. Assign products with a dynamic product group.',
                ],
                'componentName' => 'sw-field',
                'customFieldType' => 'switch',
                'type' => 'switch',
                'customFieldPosition' => 1,
            ], \JSON_THROW_ON_ERROR),
            'active' => 1,
            'set_id' => $setId,
            'created_at' => $this->now(),
        ]);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
