<?php declare(strict_types=1);

namespace Acme\Vietnam\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Cấu hình tìm kiếm sản phẩm riêng cho vi-VN (Administration → Settings → Search).
 *
 * Bản cài chỉ có cấu hình cho ngôn ngữ hệ thống (English). SearchConfigLoader đi theo
 * chuỗi ngôn ngữ nên vi-VN dùng luôn cấu hình đó — kèm 181 stopword tiếng Anh, khiến
 * "to", "do", "an", "no"… bị bỏ khi lập chỉ mục tên sản phẩm tiếng Việt ("áo to",
 * "an toàn"). Migration chép trường tìm kiếm + ranking của cấu hình hệ thống, thay danh
 * sách từ loại bằng hư từ tiếng Việt.
 *
 * Hư từ chọn bảo thủ: chỉ từ chức năng không mang nghĩa trong tên sản phẩm. Cố ý KHÔNG
 * loại "một", "có", "trong" — "áo một vai", "áo có mũ", "vải trong suốt". Chỉ dạng có
 * dấu: dạng không dấu trùng từ có nghĩa ("do" ≈ "đỏ" khi gõ không dấu).
 *
 * Đã có cấu hình vi-VN (người vận hành tạo trong Administration) thì giữ nguyên (MIG-03).
 *
 * @internal
 */
class Migration1792572392VietnameseSearchConfig extends MigrationStep
{
    public const EXCLUDED_TERMS = [
        'và', 'của', 'cho', 'là', 'các', 'những', 'với', 'để', 'thì', 'mà', 'này', 'được',
    ];

    public function getCreationTimestamp(): int
    {
        return 1792572392;
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

        $existing = $connection->fetchOne(
            'SELECT 1 FROM `product_search_config` WHERE `language_id` = :lang',
            ['lang' => $vietnameseId]
        );
        if ($existing !== false) {
            return;
        }

        $source = $connection->fetchAssociative(
            'SELECT `id`, `and_logic`, `min_search_length` FROM `product_search_config` WHERE `language_id` = :lang',
            ['lang' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        if ($source === false) {
            return;
        }

        $configId = Uuid::randomBytes();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

        $connection->insert('product_search_config', [
            'id' => $configId,
            'language_id' => $vietnameseId,
            'and_logic' => $source['and_logic'],
            'min_search_length' => $source['min_search_length'],
            'excluded_terms' => json_encode(self::EXCLUDED_TERMS, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);

        $fields = $connection->fetchAllAssociative(
            'SELECT `custom_field_id`, `field`, `tokenize`, `searchable`, `ranking`, `use_exact_subfield`
             FROM `product_search_config_field` WHERE `product_search_config_id` = :id',
            ['id' => $source['id']]
        );

        foreach ($fields as $field) {
            $connection->insert('product_search_config_field', [
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'custom_field_id' => $field['custom_field_id'],
                'field' => $field['field'],
                'tokenize' => $field['tokenize'],
                'searchable' => $field['searchable'],
                'ranking' => $field['ranking'],
                'use_exact_subfield' => $field['use_exact_subfield'],
                'created_at' => $now,
            ]);
        }
    }
}
