<?php declare(strict_types=1);

namespace Acme\Headless\System\SalesChannel;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SalesChannel\AbstractShopSettingsRoute;
use Shopware\Core\System\SystemConfig\SalesChannel\ShopSettingsRouteResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Decorate /store-api/shop-settings của core (API-02): thêm `extensions.acmeHeadless`.
 * Không thêm route riêng — frontend đã tải shop-settings một lần cho mọi trang.
 *
 * @internal
 */
class HeadlessShopSettingsRoute extends AbstractShopSettingsRoute
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly AbstractShopSettingsRoute $decorated,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $mediaRepository,
    ) {
    }

    public function getDecorated(): AbstractShopSettingsRoute
    {
        return $this->decorated;
    }

    public function load(SalesChannelContext $context): ShopSettingsRouteResponse
    {
        $response = $this->decorated->load($context);
        $salesChannelId = $context->getSalesChannelId();

        $response->getSettings()->addExtension(HeadlessShopSettings::EXTENSION_NAME, new HeadlessShopSettings(
            logo: $this->loadLogo($context),
            robotsRules: trim($this->systemConfigService->getString('core.basicInformation.robotsRules', $salesChannelId)),
            robotsDisableDefaults: $this->systemConfigService->getBool('core.basicInformation.robotsDisableDefaults', $salesChannelId),
            staging: $this->systemConfigService->getBool('core.staging', $salesChannelId),
        ));

        return $response;
    }

    private function loadLogo(SalesChannelContext $context): ?MediaEntity
    {
        $mediaId = $this->systemConfigService->getString('AcmeHeadless.config.logo', $context->getSalesChannelId());

        // Ảnh đã xoá khỏi Media nhưng cấu hình còn id → coi như chưa có logo
        if (!Uuid::isValid($mediaId)) {
            return null;
        }

        return $this->mediaRepository->search(new Criteria([$mediaId]), $context->getContext())->getEntities()->first();
    }
}
