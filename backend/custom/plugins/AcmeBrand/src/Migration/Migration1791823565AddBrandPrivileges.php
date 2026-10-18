<?php declare(strict_types=1);

namespace Acme\Brand\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Role Administration đã lưu sẵn trong DB không tự nhận quyền mới khai báo ở JS.
 * Ai có quyền nhà sản xuất thì nhận thêm quyền tương ứng trên phần mở rộng brand.
 *
 * Role tạo mới sau này lấy quyền từ mapping trong
 * `Resources/public/administration/js/acme-brand.js`.
 *
 * @internal
 */
class Migration1791823565AddBrandPrivileges extends MigrationStep
{
    public const PRIVILEGES = [
        'product_manufacturer.viewer' => [
            'acme_brand:read',
            'acme_brand_translation:read',
            'cms_page:read',
        ],
        'product_manufacturer.editor' => [
            'acme_brand:create',
            'acme_brand:update',
            'acme_brand_translation:create',
            'acme_brand_translation:update',
        ],
        'product_manufacturer.deleter' => [
            'acme_brand:delete',
            'acme_brand_translation:delete',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1791823565;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::PRIVILEGES);
    }
}
