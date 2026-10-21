<?php declare(strict_types=1);

namespace Acme\Payment;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class AcmePaymentException extends HttpException
{
    final public const ORDER_NOT_FOUND = 'ACME_PAYMENT__ORDER_NOT_FOUND';

    /**
     * Đơn không có hoặc không thuộc khách đang đăng nhập — cùng một 404, không lộ đơn của người khác.
     */
    public static function orderNotFound(string $orderId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_NOT_FOUND,
            'Order "{{ id }}" not found.',
            ['id' => $orderId],
        );
    }
}
