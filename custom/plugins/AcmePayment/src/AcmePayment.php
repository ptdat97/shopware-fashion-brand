<?php declare(strict_types=1);

namespace Acme\Payment;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Phương thức thanh toán cho thị trường Việt Nam.
 *
 * Cách đăng ký payment method theo đúng mẫu của swag/paypal
 * (PaymentMethodInstaller): upsert entity trước, upsert bản dịch sau.
 */
class AcmePayment extends Plugin
{
    public const VIETQR_TECHNICAL_NAME = 'acme_vietqr';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->upsertVietQrPaymentMethod($installContext->getContext());
    }

    /**
     * Ghi lại phương thức thanh toán khi cập nhật plugin — từ 1.1.0 handler đổi sang
     * `PrePayment` của core, bản ghi đã có phải được cập nhật theo.
     */
    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        $this->upsertVietQrPaymentMethod($updateContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        $this->setPaymentMethodActive(true, $activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);

        $this->setPaymentMethodActive(false, $deactivateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        // Không xoá payment method: đơn hàng cũ tham chiếu tới nó, xoá sẽ
        // làm hỏng lịch sử đơn. Chỉ tắt đi — giống cách core xử lý.
        $this->setPaymentMethodActive(false, $uninstallContext->getContext());
    }

    private function upsertVietQrPaymentMethod(Context $context): void
    {
        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);

        $existingId = $this->findPaymentMethodId($context);

        $data = [
            'id' => $existingId ?? Uuid::randomHex(),
            // Handler "Chuyển khoản trước" của core: không redirect, giao dịch giữ "open"
            // chờ đối soát — đúng hành vi VietQR cần. Bản handler tự viết trước 1.1.0 giống
            // hệt từng dòng, nên đã bỏ (08-quy-tac-phat-trien.md, Quy tắc số 4). Phần riêng
            // của VietQR chỉ là mã QR trên trang cảm ơn (CheckoutFinishSubscriber).
            'handlerIdentifier' => PrePayment::class,
            'technicalName' => self::VIETQR_TECHNICAL_NAME,
            'name' => 'Chuyển khoản QR (VietQR)',
            'position' => 10,
            'afterOrderEnabled' => true,
            'pluginId' => $pluginIdProvider->getPluginIdByBaseClass(static::class, $context),
        ];

        $paymentMethodRepository->upsert([$data], $context);

        // Bản dịch ghi riêng — cùng lý do swag/paypal làm vậy (NEXT-12900).
        $paymentMethodRepository->upsert([[
            'id' => $data['id'],
            'translations' => [
                'vi-VN' => [
                    'name' => 'Chuyển khoản QR (VietQR)',
                    'description' => 'Quét mã QR bằng ứng dụng ngân hàng để chuyển khoản. Đơn hàng được giao sau khi cửa hàng xác nhận nhận được tiền.',
                ],
                'en-GB' => [
                    'name' => 'Bank transfer via VietQR',
                    'description' => 'Scan the QR code with your banking app. Your order ships once payment is confirmed.',
                ],
            ],
        ]], $context);
    }

    private function setPaymentMethodActive(bool $active, Context $context): void
    {
        $id = $this->findPaymentMethodId($context);

        if ($id === null) {
            return;
        }

        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');
        $paymentMethodRepository->update([['id' => $id, 'active' => $active]], $context);
    }

    private function findPaymentMethodId(Context $context): ?string
    {
        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::VIETQR_TECHNICAL_NAME));
        $criteria->setLimit(1);

        return $paymentMethodRepository->searchIds($criteria, $context)->firstId();
    }
}
