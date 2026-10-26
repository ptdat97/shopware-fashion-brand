<?php declare(strict_types=1);

namespace Acme\Vietnam\Checkout\Order;

use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Khách vãng lai mở đơn từ link trong email bằng **email + số điện thoại** khi đơn không có
 * mã bưu chính.
 *
 * Core (/store-api/order với filter deepLinkCode) bắt xác minh email + mã bưu chính và từ
 * chối khi địa chỉ thanh toán không có mã bưu chính — mà đơn VN không có (mã bưu chính không
 * bắt buộc ở VN, Phase 0; SĐT bắt buộc, AcmeVietnam 1.4.0). Kết quả: khách vãng lai không bao
 * giờ mở được đơn của mình (phát hiện 22/10/2026, ADR-006).
 *
 * Chỉ can thiệp đúng trường hợp đó: chưa đăng nhập + có deepLinkCode + gửi `email` và
 * `phoneNumber` (không gửi `zipcode`) + đơn của khách vãng lai không có mã bưu chính. Mọi
 * trường hợp khác đi thẳng vào core. Cùng giới hạn số lần thử (`guest_login`) và cùng thời hạn
 * link như core; xác minh xong thì đăng nhập phiên khách như tham số `login` của core.
 *
 * @internal
 */
class GuestPhoneOrderRoute extends AbstractOrderRoute
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly AbstractOrderRoute $decorated,
        private readonly EntityRepository $orderRepository,
        private readonly RateLimiter $rateLimiter,
        private readonly AccountService $accountService,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly int $deepLinkExpireDays = 30,
    ) {
    }

    public function getDecorated(): AbstractOrderRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse
    {
        $deepLinkCode = $this->deepLinkCode($criteria);
        $email = (string) RequestParamHelper::get($request, 'email', '');
        $phone = self::normalizePhone((string) RequestParamHelper::get($request, 'phoneNumber', ''));

        if ($context->getCustomer() !== null || $deepLinkCode === null || $email === '' || $phone === ''
            || (string) RequestParamHelper::get($request, 'zipcode', '') !== '') {
            return $this->decorated->load($request, $context, $criteria);
        }

        $cacheKey = strtolower($deepLinkCode) . '-' . $request->getClientIp();
        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::GUEST_LOGIN, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw OrderException::customerAuthThrottledException($exception->getWaitTime(), $exception);
        }

        $order = $this->findOrder($deepLinkCode, $context);
        if ($order === null || !$this->matches($order, $email, $phone)) {
            throw OrderException::wrongGuestCredentials();
        }

        $this->rateLimiter->reset(RateLimiter::GUEST_LOGIN, $cacheKey);

        $customerId = (string) $order->getOrderCustomer()?->getCustomerId();
        $token = $this->accountService->loginById($customerId, $context);
        $guestContext = $this->contextService->get(new SalesChannelContextServiceParameters(
            $context->getSalesChannelId(),
            $token,
            $context->getLanguageId(),
            $context->getCurrencyId(),
            $context->getDomainId(),
        ));

        // Đã là phiên của chính khách → core lọc đơn theo khách, như đơn của khách đã đăng nhập
        $response = $this->decorated->load(new Request(), $guestContext, $criteria);
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        return $response;
    }

    /**
     * "+84 987-654-321", "84987654321", "0987654321" → "0987654321"
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        return str_starts_with($digits, '84') && \strlen($digits) >= 11 ? '0' . substr($digits, 2) : $digits;
    }

    private function matches(OrderEntity $order, string $email, string $phone): bool
    {
        $orderCustomer = $order->getOrderCustomer();
        $billing = $order->getBillingAddress();

        return $orderCustomer !== null
            && $orderCustomer->getCustomer()?->getGuest() === true
            && $billing !== null
            // Đơn có mã bưu chính vẫn theo đường của core (email + mã bưu chính)
            && trim((string) $billing->getZipcode()) === ''
            && mb_strtolower($email) === mb_strtolower($orderCustomer->getEmail())
            && $phone === self::normalizePhone((string) $billing->getPhoneNumber());
    }

    private function findOrder(string $deepLinkCode, SalesChannelContext $context): ?OrderEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode))
            ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()))
            ->addAssociations(['billingAddress', 'orderCustomer.customer'])
            ->setLimit(1);

        $order = $this->orderRepository->search($criteria, $context->getContext())->getEntities()->first();
        if ($order === null) {
            return null;
        }

        // Cùng thời hạn link như core (shopware.order.deep_link.expire_days)
        $expiry = new \DateTimeImmutable(\sprintf('-%d days', abs($this->deepLinkExpireDays)));
        $recent = $order->getCreatedAt() > $expiry || ($order->getUpdatedAt() !== null && $order->getUpdatedAt() > $expiry);

        return $recent ? $order : null;
    }

    private function deepLinkCode(Criteria $criteria): ?string
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && \in_array($filter->getField(), ['deepLinkCode', 'order.deepLinkCode'], true)) {
                return \is_string($filter->getValue()) && $filter->getValue() !== '' ? $filter->getValue() : null;
            }
        }

        return null;
    }
}
