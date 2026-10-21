<?php declare(strict_types=1);

namespace Acme\Headless\System\SalesChannel;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Struct\Struct;

/**
 * `extensions.acmeHeadless` của /store-api/shop-settings.
 */
class HeadlessShopSettings extends Struct
{
    final public const EXTENSION_NAME = 'acmeHeadless';

    public function __construct(
        /** Logo trong Media; null → frontend hiện tên shop dạng chữ */
        public readonly ?MediaEntity $logo,
        /** core.basicInformation.robotsRules của channel — nguyên văn, mỗi dòng một chỉ thị */
        public readonly string $robotsRules,
        /** core.basicInformation.robotsDisableDefaults — bỏ "User-agent: *" và quy tắc mặc định của frontend */
        public readonly bool $robotsDisableDefaults,
        /** core.staging — môi trường staging: robots.txt chặn tất cả */
        public readonly bool $staging,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'acme_headless_shop_settings';
    }
}
