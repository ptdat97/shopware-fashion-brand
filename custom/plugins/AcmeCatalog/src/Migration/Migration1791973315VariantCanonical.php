<?php declare(strict_types=1);

namespace Acme\Catalog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * SEO: mọi biến thể của một mẫu cùng canonical về biến thể đầu (mã nhỏ nhất).
 *
 * Trường native `canonicalProductId` trên sản phẩm cha, biến thể kế thừa; sitemap của core
 * khi đó chỉ đưa biến thể canonical vào. Lệnh `acme:catalog:variants` đặt trường này cho
 * biến thể sinh mới; migration này điền cho sản phẩm đã có biến thể từ trước.
 *
 * Trước 14/10/2026 việc điền này nằm trong AcmeDemoData — quy tắc catalog thì thuộc
 * AcmeCatalog, và phải đúng cả với hàng thật, không riêng dữ liệu demo.
 *
 * Chỉ đặt khi đang trống: người vận hành có thể đã chọn canonical khác trong Administration.
 *
 * @internal
 */
class Migration1791973315VariantCanonical extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791973315;
    }

    public function update(Connection $connection): void
    {
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $rows = $connection->fetchAllAssociative(
            'SELECT parent.id AS parent_id,
                    (SELECT child.id FROM product child
                      WHERE child.parent_id = parent.id AND child.version_id = :live
                      ORDER BY child.product_number LIMIT 1) AS first_child_id
               FROM product parent
              WHERE parent.parent_id IS NULL
                AND parent.version_id = :live
                AND parent.child_count > 0
                AND parent.canonical_product_id IS NULL',
            ['live' => $liveVersion]
        );

        foreach ($rows as $row) {
            if ($row['first_child_id'] === null) {
                continue;
            }

            $connection->update('product', [
                'canonical_product_id' => $row['first_child_id'],
                'canonical_product_version_id' => $liveVersion,
            ], [
                'id' => $row['parent_id'],
                'version_id' => $liveVersion,
            ]);
        }
    }
}
