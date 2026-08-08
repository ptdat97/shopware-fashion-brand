<?php declare(strict_types=1);

namespace Anivele\Vietnam;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Nền tảng thị trường Việt Nam.
 *
 * Migration của plugin này thay đổi dữ liệu cấu hình nền (tiền tệ, thuế, quốc gia)
 * mà toàn bộ shop phụ thuộc vào. Gỡ plugin KHÔNG hoàn tác các thay đổi đó:
 * đưa currency về USD khi shop đã có đơn hàng giá VND sẽ làm sai toàn bộ số tiền.
 */
class AniveleVietnam extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        // Cố ý không hoàn tác: xem chú thích ở đầu class.
        $uninstallContext->setKeepMigrations(false);
    }
}
