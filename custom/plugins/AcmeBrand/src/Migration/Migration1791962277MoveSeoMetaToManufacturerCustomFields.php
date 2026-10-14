<?php declare(strict_types=1);

namespace Acme\Brand\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Meta SEO của brand chuyển từ bảng dịch riêng `acme_brand_translation` sang
 * custom field **dịch được sẵn có** của `product_manufacturer`.
 *
 * Lý do: Shopware đã có đủ — `customFields` của nhà sản xuất dịch theo ngôn ngữ, và
 * trang chi tiết nhà sản xuất trong Administration tự hiển thị custom field. Bảng dịch
 * riêng + 3 class entity + 3 ô nhập JS là làm lại thứ đã có (xem 08-quy-tac-phat-trien.md,
 * Quy tắc số 4).
 *
 * - update(): tạo bộ custom field, chép dữ liệu cũ sang, gỡ quyền ACL của bảng cũ.
 * - updateDestructive(): xoá bảng cũ — chạy `bin/console database:migrate-destructive AcmeBrand --all`.
 *   `plugin:update` không chạy phần này.
 *
 * @internal
 */
class Migration1791962277MoveSeoMetaToManufacturerCustomFields extends MigrationStep
{
    // Giá trị chép cứng (không tham chiếu BrandCustomFields): migration phải giữ nguyên
    // hành vi kể cả khi code chạy thật đổi tên hằng số về sau.
    public const SET_NAME = 'acme_brand_seo';

    public const FIELD_META_TITLE = 'acme_brand_meta_title';

    public const FIELD_META_DESCRIPTION = 'acme_brand_meta_description';

    public const FIELD_KEYWORDS = 'acme_brand_keywords';

    /** cột bảng cũ => custom field mới */
    private const COLUMN_MAP = [
        'meta_title' => self::FIELD_META_TITLE,
        'meta_description' => self::FIELD_META_DESCRIPTION,
        'keywords' => self::FIELD_KEYWORDS,
    ];

    public function getCreationTimestamp(): int
    {
        return 1791962277;
    }

    public function update(Connection $connection): void
    {
        $setId = $this->ensureCustomFieldSet($connection);
        $this->ensureFields($connection, $setId);
        $this->copyOldTranslations($connection);
        $this->removeOldPrivileges($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `acme_brand_translation`');
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
                'label' => ['vi-VN' => 'Thương hiệu — SEO', 'en-GB' => 'Brand — SEO'],
                'translated' => true,
            ], \JSON_THROW_ON_ERROR),
            'created_at' => $this->now(),
        ]);

        $connection->insert('custom_field_set_relation', [
            'id' => Uuid::randomBytes(),
            'set_id' => $setId,
            'entity_name' => 'product_manufacturer',
            'created_at' => $this->now(),
        ]);

        return $setId;
    }

    private function ensureFields(Connection $connection, string $setId): void
    {
        $fields = [
            self::FIELD_META_TITLE => [
                'label' => ['vi-VN' => 'Meta title trang thương hiệu', 'en-GB' => 'Brand page meta title'],
                'helpText' => ['vi-VN' => 'Bỏ trống thì dùng tên nhà sản xuất.', 'en-GB' => 'Falls back to the manufacturer name.'],
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
                'type' => 'text',
                'customFieldPosition' => 1,
            ],
            self::FIELD_META_DESCRIPTION => [
                'label' => ['vi-VN' => 'Meta description trang thương hiệu', 'en-GB' => 'Brand page meta description'],
                'helpText' => ['vi-VN' => 'Bỏ trống thì lấy 160 ký tự đầu của mô tả.', 'en-GB' => 'Falls back to the first 160 characters of the description.'],
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
                'type' => 'text',
                'customFieldPosition' => 2,
            ],
            self::FIELD_KEYWORDS => [
                'label' => ['vi-VN' => 'Từ khoá SEO', 'en-GB' => 'SEO keywords'],
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
                'type' => 'text',
                'customFieldPosition' => 3,
            ],
        ];

        foreach ($fields as $name => $config) {
            if ($connection->fetchOne('SELECT 1 FROM `custom_field` WHERE `name` = :name', ['name' => $name]) !== false) {
                continue;
            }

            $connection->insert('custom_field', [
                'id' => Uuid::randomBytes(),
                'name' => $name,
                'type' => CustomFieldTypes::TEXT,
                'config' => json_encode($config, \JSON_THROW_ON_ERROR),
                'active' => 1,
                'set_id' => $setId,
                'created_at' => $this->now(),
            ]);
        }
    }

    /**
     * Chép từng dòng (brand, ngôn ngữ) sang đúng dòng dịch của nhà sản xuất — lọc cả
     * `language_id` (bẫy bảng *_translation, runbook/06-tao-plugin.md). Giá trị đã có
     * trong custom field thì không ghi đè. Ngôn ngữ mà nhà sản xuất chưa có bản dịch
     * thì bỏ qua: không tự tạo bản dịch mới chỉ để chứa meta.
     */
    private function copyOldTranslations(Connection $connection): void
    {
        $tableExists = $connection->fetchOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = "acme_brand_translation"'
        );
        if ($tableExists === false) {
            return;
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT b.product_manufacturer_id, b.product_manufacturer_version_id, t.language_id,
                    t.meta_title, t.meta_description, t.keywords
               FROM acme_brand_translation t
               JOIN acme_brand b ON b.id = t.acme_brand_id'
        );

        foreach ($rows as $row) {
            $values = [];
            foreach (self::COLUMN_MAP as $column => $field) {
                if ($row[$column] !== null && $row[$column] !== '') {
                    $values[$field] = $row[$column];
                }
            }

            if ($values === []) {
                continue;
            }

            $current = $connection->fetchOne(
                'SELECT custom_fields FROM product_manufacturer_translation
                  WHERE product_manufacturer_id = :id AND product_manufacturer_version_id = :version AND language_id = :language',
                [
                    'id' => $row['product_manufacturer_id'],
                    'version' => $row['product_manufacturer_version_id'],
                    'language' => $row['language_id'],
                ]
            );

            if ($current === false) {
                continue;
            }

            $customFields = $current ? json_decode((string) $current, true, 512, \JSON_THROW_ON_ERROR) : [];
            $merged = $customFields + $values;

            if ($merged === $customFields) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE product_manufacturer_translation SET custom_fields = :customFields, updated_at = :now
                  WHERE product_manufacturer_id = :id AND product_manufacturer_version_id = :version AND language_id = :language',
                [
                    'customFields' => json_encode($merged, \JSON_THROW_ON_ERROR),
                    'now' => $this->now(),
                    'id' => $row['product_manufacturer_id'],
                    'version' => $row['product_manufacturer_version_id'],
                    'language' => $row['language_id'],
                ]
            );
        }
    }

    /**
     * Quyền của bảng cũ do Migration1791823565 thêm vào role đã có. Quyền sửa custom
     * field của nhà sản xuất đã nằm trong `product_manufacturer:update` của core.
     */
    private function removeOldPrivileges(Connection $connection): void
    {
        $roles = $connection->fetchAllAssociative('SELECT id, privileges FROM acl_role WHERE privileges LIKE :needle', ['needle' => '%acme_brand_translation%']);

        foreach ($roles as $role) {
            $privileges = json_decode((string) $role['privileges'], true, 512, \JSON_THROW_ON_ERROR);
            $kept = array_values(array_filter(
                $privileges,
                static fn (string $privilege): bool => !str_starts_with($privilege, 'acme_brand_translation:')
            ));

            $connection->update('acl_role', [
                'privileges' => json_encode($kept, \JSON_THROW_ON_ERROR),
                'updated_at' => $this->now(),
            ], ['id' => $role['id']]);
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
