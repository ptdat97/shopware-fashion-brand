<?php declare(strict_types=1);

namespace Acme\Payment\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Thông tin chuyển khoản của một đơn cho frontend headless. Decorate được (API-02).
 */
abstract class AbstractBankTransferRoute
{
    abstract public function getDecorated(): AbstractBankTransferRoute;

    abstract public function load(string $orderId, SalesChannelContext $context): BankTransferRouteResponse;
}
