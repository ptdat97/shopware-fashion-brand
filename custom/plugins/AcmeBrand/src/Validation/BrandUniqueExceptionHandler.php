<?php declare(strict_types=1);

namespace Acme\Brand\Validation;

use Acme\Brand\BrandException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

/**
 * Dịch lỗi trùng khoá UNIQUE của MySQL thành lỗi 400 có mã riêng, thay vì 500.
 *
 * Kiểm trùng bằng chính ràng buộc của DB (không SELECT trước khi ghi) để không
 * có kẽ hở giữa hai request ghi đồng thời.
 *
 * @internal
 */
class BrandUniqueExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        $message = $e->getMessage();

        if (preg_match('/1062 Duplicate entry \'([^\']*)\' for key \'(?:acme_brand\.)?uniq\.acme_brand\.code\'/', $message, $matches)) {
            return BrandException::duplicateCode($matches[1], $e);
        }

        if (preg_match('/1062 Duplicate entry .* for key \'(?:acme_brand\.)?uniq\.acme_brand\.manufacturer\'/', $message)) {
            return BrandException::duplicateManufacturer($e);
        }

        return null;
    }
}
