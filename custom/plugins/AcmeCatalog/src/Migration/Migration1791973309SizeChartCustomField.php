<?php declare(strict_types=1);

namespace Acme\Catalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Chuyển từ AcmeTheme ngày 14/10/2026 (Migration1786195057SizeChartCustomField): số đo
 * là DỮ LIỆU catalog, theme chỉ hiển thị. Tắt theme không được làm mất trường nhập
 * số đo trong Administration. Mọi bước chỉ ghi khi chưa có → chạy lại vô hại.
 *
 * Bảng size gắn theo DANH MỤC, sửa được trong Administration.
 *
 * Gắn theo danh mục thay vì từng sản phẩm vì đầm và chân váy không cùng thang
 * số đo, nhưng mọi mẫu đầm thì dùng chung một bảng — nhập một lần cho cả
 * danh mục thay vì lặp lại cho hàng trăm SKU.
 *
 * Người bán sửa tại: Administration → Danh mục → chọn danh mục → tab
 * "Tuỳ chỉnh" → Bảng size.
 *
 * @internal
 */
class Migration1791973309SizeChartCustomField extends MigrationStep
{
    private const SET_NAME = 'acme_size_chart';
    private const FIELD_TABLE = 'acme_size_chart_table';
    private const FIELD_NOTE = 'acme_size_chart_note';

    public function getCreationTimestamp(): int
    {
        return 1791973309;
    }

    public function update(Connection $connection): void
    {
        $setId = $this->ensureCustomFieldSet($connection);
        $this->ensureFields($connection, $setId);
        $this->seedReferenceCharts($connection);
    }

    private function ensureCustomFieldSet(Connection $connection): string
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `custom_field_set` WHERE `name` = :name LIMIT 1',
            ['name' => self::SET_NAME]
        );

        if ($existing !== false) {
            return $existing;
        }

        $setId = Uuid::randomBytes();

        $connection->insert('custom_field_set', [
            'id' => $setId,
            'name' => self::SET_NAME,
            'active' => 1,
            'config' => json_encode([
                'label' => [
                    'vi-VN' => 'Bảng size',
                    'en-GB' => 'Size chart',
                ],
                'translated' => true,
            ], \JSON_THROW_ON_ERROR),
            'created_at' => $this->now(),
        ]);

        // Gắn bộ trường vào entity category.
        $connection->insert('custom_field_set_relation', [
            'id' => Uuid::randomBytes(),
            'set_id' => $setId,
            'entity_name' => 'category',
            'created_at' => $this->now(),
        ]);

        return $setId;
    }

    private function ensureFields(Connection $connection, string $setId): void
    {
        $fields = [
            self::FIELD_TABLE => [
                'type' => CustomFieldTypes::HTML,
                'config' => [
                    'label' => [
                        'vi-VN' => 'Bảng số đo',
                        'en-GB' => 'Measurement table',
                    ],
                    'helpText' => [
                        'vi-VN' => 'Bảng HTML hiện trong popup ở trang sản phẩm. Số đo tính bằng cm.',
                        'en-GB' => 'HTML table shown in the product page popup. Measurements in cm.',
                    ],
                    'componentName' => 'sw-text-editor',
                    'customFieldType' => 'textEditor',
                    'customFieldPosition' => 1,
                ],
            ],
            self::FIELD_NOTE => [
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'vi-VN' => 'Ghi chú dưới bảng',
                        'en-GB' => 'Note below table',
                    ],
                    'helpText' => [
                        'vi-VN' => 'Ví dụ: "Số đo có thể chênh 1–2cm tuỳ cách đo."',
                        'en-GB' => 'For example: "Measurements may vary by 1-2cm."',
                    ],
                    'componentName' => 'sw-field',
                    'customFieldType' => 'text',
                    'customFieldPosition' => 2,
                ],
            ],
        ];

        foreach ($fields as $name => $definition) {
            $exists = $connection->fetchOne(
                'SELECT `id` FROM `custom_field` WHERE `name` = :name LIMIT 1',
                ['name' => $name]
            );

            if ($exists !== false) {
                continue;
            }

            $connection->insert('custom_field', [
                'id' => Uuid::randomBytes(),
                'name' => $name,
                'type' => $definition['type'],
                'config' => json_encode($definition['config'], \JSON_THROW_ON_ERROR),
                'active' => 1,
                'set_id' => $setId,
                'created_at' => $this->now(),
            ]);
        }
    }

    /**
     * Điền số đo THAM KHẢO theo thang size phổ biến của thị trường VN.
     *
     * ⚠️ Đây KHÔNG phải số đo thật của thương hiệu nào. Người bán phải đo trên sản
     * phẩm thật rồi thay lại trước khi go-live — số đo sai là nguyên nhân
     * đổi trả hàng đầu với thời trang online.
     */
    private function seedReferenceCharts(Connection $connection): void
    {
        $charts = [
            'Đầm' => [
                ['S', '82 - 86', '62 - 66', '88 - 92', '1m50 - 1m57'],
                ['M', '86 - 90', '66 - 70', '92 - 96', '1m55 - 1m62'],
                ['L', '90 - 94', '70 - 74', '96 - 100', '1m60 - 1m67'],
                ['XL', '94 - 98', '74 - 78', '100 - 104', '1m63 - 1m70'],
            ],
            'Áo' => [
                ['S', '82 - 86', '62 - 66', '88 - 92', '1m50 - 1m57'],
                ['M', '86 - 90', '66 - 70', '92 - 96', '1m55 - 1m62'],
                ['L', '90 - 94', '70 - 74', '96 - 100', '1m60 - 1m67'],
                ['XL', '94 - 98', '74 - 78', '100 - 104', '1m63 - 1m70'],
            ],
            'Chân váy' => [
                ['S', '—', '62 - 66', '88 - 92', '1m50 - 1m57'],
                ['M', '—', '66 - 70', '92 - 96', '1m55 - 1m62'],
                ['L', '—', '70 - 74', '96 - 100', '1m60 - 1m67'],
                ['XL', '—', '74 - 78', '100 - 104', '1m63 - 1m70'],
            ],
        ];

        foreach ($charts as $categoryName => $rows) {
            // Lấy đúng CẢ category_id LẪN language_id của dòng dịch có tên này.
            // Bẫy đã gặp: chỉ lọc theo category_id thì UPDATE ghi vào MỌI ngôn
            // ngữ (31 dòng/danh mục), kể cả các thứ tiếng shop không dùng.
            $row = $connection->fetchAssociative(
                'SELECT `category_id`, `language_id` FROM `category_translation`
                 WHERE `name` = :name LIMIT 1',
                ['name' => $categoryName]
            );

            if ($row === false) {
                continue;
            }

            $categoryId = $row['category_id'];
            $languageId = $row['language_id'];

            $payload = [
                self::FIELD_TABLE => $this->renderTable($rows),
                self::FIELD_NOTE => 'Số đo tham khảo, có thể chênh 1–2cm tuỳ cách đo. Nếu số đo của bạn nằm giữa hai size, nên chọn size lớn hơn.',
            ];

            // Chỉ điền khi danh mục CHƯA có bảng size — không ghi đè số đo
            // người bán đã tự nhập.
            $connection->executeStatement(
                'UPDATE `category_translation`
                 SET `custom_fields` = :payload, `updated_at` = :now
                 WHERE `category_id` = :id
                   AND `language_id` = :lang
                   AND (`custom_fields` IS NULL
                        OR JSON_EXTRACT(`custom_fields`, :path) IS NULL)',
                [
                    'payload' => json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
                    'now' => $this->now(),
                    'id' => $categoryId,
                    'lang' => $languageId,
                    'path' => '$."' . self::FIELD_TABLE . '"',
                ]
            );
        }
    }

    /**
     * @param list<list<string>> $rows
     */
    private function renderTable(array $rows): string
    {
        $html = '<table class="acme-size-table">'
            . '<thead><tr>'
            . '<th>Size</th><th>Ngực (cm)</th><th>Eo (cm)</th><th>Mông (cm)</th><th>Chiều cao</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $index => $cell) {
                $html .= $index === 0 ? '<th scope="row">' . $cell . '</th>' : '<td>' . $cell . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
    }
}
