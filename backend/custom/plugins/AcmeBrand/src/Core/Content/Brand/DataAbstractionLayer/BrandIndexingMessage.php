<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

/**
 * Dữ liệu là id NHÀ SẢN XUẤT (khoá của SEO URL trang brand), không phải id acme_brand.
 */
class BrandIndexingMessage extends EntityIndexingMessage
{
}
