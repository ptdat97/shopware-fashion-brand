<?php declare(strict_types=1);

namespace Acme\Catalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Hai thông tin khách thời trang hay hỏi ở trang sản phẩm: chất liệu, người mẫu mặc size gì.
 *
 * Chất liệu là **nhóm thuộc tính native** "Chất liệu", không phải custom field
 * (08-quy-tac-phat-trien.md, Quy tắc số 4): core đã có sẵn cho thuộc tính bảng thông số
 * ở trang sản phẩm, bộ lọc ở danh mục, bản dịch, nhập qua Import/Export. Giá trị (Voan
 * lụa, Tweed…) người vận hành thêm trong Administration → Catalog → Thuộc tính.
 *
 * Người mẫu mặc size là chữ tự do riêng từng mẫu, core không có trường tương ứng →
 * custom field dịch được `acme_model_size` của sản phẩm. Theme hiển thị nếu có.
 *
 * Trước 14/10/2026 AcmeDemoData ghi `acme_material`, `acme_model_size` vào custom field
 * mà không plugin nào định nghĩa (không có ô nhập trong Administration).
 *
 * Mọi bước chỉ tạo khi chưa có → chạy lại vô hại, không ghi đè thứ người vận hành đã sửa.
 *
 * @internal
 */
class Migration1791978755ProductFashionAttributes extends MigrationStep
{
    public const MATERIAL_GROUP_NAME = 'Chất liệu';
    public const CUSTOM_FIELD_SET = 'acme_product_fashion';
    public const FIELD_MODEL_SIZE = 'acme_model_size';

    public function getCreationTimestamp(): int
    {
        return 1791978755;
    }

    public function update(Connection $connection): void
    {
        $this->ensureMaterialGroup($connection);
        $this->ensureModelSizeField($connection);
    }

    /**
     * Dò theo id cố định trước, rồi theo tên (nhóm do người vận hành hoặc AcmeDemoData bản cũ
     * tạo) — không tạo nhóm "Chất liệu" thứ hai.
     */
    private function ensureMaterialGroup(Connection $connection): void
    {
        $groupId = Uuid::fromHexToBytes(Uuid::fromStringToHex('acme-catalog-property-group-material'));

        $exists = $connection->fetchOne('SELECT 1 FROM `property_group` WHERE `id` = :id', ['id' => $groupId])
            ?: $connection->fetchOne(
                'SELECT 1 FROM `property_group_translation` WHERE `name` = :name LIMIT 1',
                ['name' => self::MATERIAL_GROUP_NAME]
            );

        if ($exists !== false) {
            return;
        }

        $connection->insert('property_group', [
            'id' => $groupId,
            'display_type' => 'text',
            'sorting_type' => 'alphanumeric',
            'filterable' => 1,
            'visible_on_product_detail_page' => 1,
            'created_at' => $this->now(),
        ]);

        // Tên tiếng Việt ở cả ngôn ngữ hệ thống, cùng quy ước với "Màu sắc", "Kích cỡ":
        // shop chỉ bán bằng tiếng Việt, Administration mở bằng ngôn ngữ hệ thống.
        $languageIds = array_unique(array_filter([
            Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            $this->languageIdByLocale($connection, 'vi-VN'),
        ]));

        foreach ($languageIds as $languageId) {
            $connection->insert('property_group_translation', [
                'property_group_id' => $groupId,
                'language_id' => $languageId,
                'name' => self::MATERIAL_GROUP_NAME,
                'position' => 3,
                'created_at' => $this->now(),
            ]);
        }
    }

    private function ensureModelSizeField(Connection $connection): void
    {
        $setId = $connection->fetchOne(
            'SELECT `id` FROM `custom_field_set` WHERE `name` = :name LIMIT 1',
            ['name' => self::CUSTOM_FIELD_SET]
        );

        if ($setId === false) {
            $setId = Uuid::randomBytes();
            $connection->insert('custom_field_set', [
                'id' => $setId,
                'name' => self::CUSTOM_FIELD_SET,
                'active' => 1,
                'config' => json_encode([
                    'label' => ['vi-VN' => 'Thông tin thời trang', 'en-GB' => 'Fashion details'],
                    'translated' => true,
                ], \JSON_THROW_ON_ERROR),
                'created_at' => $this->now(),
            ]);
            $connection->insert('custom_field_set_relation', [
                'id' => Uuid::randomBytes(),
                'set_id' => $setId,
                'entity_name' => 'product',
                'created_at' => $this->now(),
            ]);
        }

        $fieldExists = $connection->fetchOne(
            'SELECT 1 FROM `custom_field` WHERE `name` = :name',
            ['name' => self::FIELD_MODEL_SIZE]
        );

        if ($fieldExists !== false) {
            return;
        }

        $connection->insert('custom_field', [
            'id' => Uuid::randomBytes(),
            'name' => self::FIELD_MODEL_SIZE,
            'type' => CustomFieldTypes::TEXT,
            'config' => json_encode([
                'label' => ['vi-VN' => 'Người mẫu mặc size', 'en-GB' => 'Model wears'],
                'placeholder' => ['vi-VN' => 'Người mẫu cao 1m68, mặc size S', 'en-GB' => 'Model is 1.68 m tall and wears size S'],
                'helpText' => [
                    'vi-VN' => 'Hiện dưới nút chọn size ở trang sản phẩm. Để trống thì không hiện.',
                    'en-GB' => 'Shown below the size selector on the product page. Leave empty to hide.',
                ],
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
                'customFieldPosition' => 1,
            ], \JSON_THROW_ON_ERROR),
            'active' => 1,
            'set_id' => $setId,
            'created_at' => $this->now(),
        ]);
    }

    private function languageIdByLocale(Connection $connection, string $locale): ?string
    {
        $id = $connection->fetchOne(
            'SELECT l.`id` FROM `language` l JOIN `locale` loc ON loc.`id` = l.`locale_id` WHERE loc.`code` = :code LIMIT 1',
            ['code' => $locale]
        );

        return $id === false ? null : $id;
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
