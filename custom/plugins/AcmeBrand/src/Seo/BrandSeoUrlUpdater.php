<?php declare(strict_types=1);

namespace Acme\Brand\Seo;

use Acme\Brand\Core\Content\Brand\BrandDefinition;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sinh lại URL thương hiệu khi tên nhà sản xuất hoặc trạng thái brand đổi.
 *
 * Core chỉ tự cập nhật SEO URL cho sản phẩm, danh mục, landing page (SeoUrlUpdateListener
 * của Storefront) — route của plugin phải tự lo.
 *
 * @internal
 */
class BrandSeoUrlUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onEntitiesWritten',
        ];
    }

    public function onEntitiesWritten(EntityWrittenContainerEvent $event): void
    {
        $manufacturerIds = $event->getPrimaryKeys(ProductManufacturerDefinition::ENTITY_NAME);

        $brandIds = $event->getPrimaryKeys(BrandDefinition::ENTITY_NAME);
        if ($brandIds !== []) {
            $manufacturerIds = [...$manufacturerIds, ...$this->manufacturerIdsOfBrands($brandIds)];
        }

        $this->update(array_values(array_unique($manufacturerIds)));
    }

    /**
     * @param list<string> $manufacturerIds
     */
    public function update(array $manufacturerIds): void
    {
        if ($manufacturerIds === []) {
            return;
        }

        $this->seoUrlUpdater->update(BrandPageSeoUrlRoute::ROUTE_NAME, $manufacturerIds);
    }

    /**
     * @return list<string>
     */
    public function allBrandManufacturerIds(): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(product_manufacturer_id)) FROM acme_brand');
    }

    /**
     * @param list<string> $brandIds
     *
     * @return list<string>
     */
    private function manufacturerIdsOfBrands(array $brandIds): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(product_manufacturer_id)) FROM acme_brand WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($brandIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
