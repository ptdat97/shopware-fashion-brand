<?php declare(strict_types=1);

namespace Acme\Payment\Core\Checkout\Payment\SalesChannel;

use Acme\Payment\Struct\BankTransferInfo;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<BankTransferInfo>
 */
class BankTransferRouteResponse extends StoreApiResponse
{
    public function getBankTransferInfo(): BankTransferInfo
    {
        return $this->object;
    }
}
