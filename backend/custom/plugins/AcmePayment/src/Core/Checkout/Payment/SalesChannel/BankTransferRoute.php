<?php declare(strict_types=1);

namespace Acme\Payment\Core\Checkout\Payment\SalesChannel;

use Acme\Payment\AcmePaymentException;
use Acme\Payment\Service\BankTransferInfoProvider;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET|POST /store-api/acme/payment/bank-transfer/{orderId} — tài khoản nhận, số tiền, nội
 * dung chuyển khoản và hướng dẫn cho trang cảm ơn / chi tiết đơn của frontend headless.
 *
 * Đơn đọc qua route /store-api/order của core: chỉ đơn của khách đang đăng nhập (kể cả
 * khách vãng lai vừa đặt) trong channel hiện tại — không tự viết lại phần kiểm quyền.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BankTransferRoute extends AbstractBankTransferRoute
{
    final public const ROUTE_NAME = 'store-api.acme.payment.bank-transfer';

    public function __construct(
        private readonly AbstractOrderRoute $orderRoute,
        private readonly BankTransferInfoProvider $provider,
    ) {
    }

    public function getDecorated(): AbstractBankTransferRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/acme/payment/bank-transfer/{orderId}',
        name: self::ROUTE_NAME,
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function load(string $orderId, SalesChannelContext $context): BankTransferRouteResponse
    {
        if (!Uuid::isValid($orderId)) {
            throw AcmePaymentException::orderNotFound($orderId);
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.paymentMethod');

        $order = $this->orderRoute->load(new Request(), $context, $criteria)->getOrders()->first();
        if (!$order instanceof OrderEntity) {
            throw AcmePaymentException::orderNotFound($orderId);
        }

        return new BankTransferRouteResponse($this->provider->forOrder($order, $context->getSalesChannelId()));
    }
}
