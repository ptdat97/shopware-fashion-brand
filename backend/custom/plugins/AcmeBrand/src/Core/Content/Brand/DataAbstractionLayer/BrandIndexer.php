<?php declare(strict_types=1);

namespace Acme\Brand\Core\Content\Brand\DataAbstractionLayer;

use Acme\Brand\Core\Content\Brand\BrandDefinition;
use Acme\Brand\Seo\BrandStoreApiUrlRoute;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Sinh SEO URL `/brands/{slug}` (channel headless) qua cơ chế indexing của core — cùng cách
 * `LandingPageIndexer` của core cập nhật URL landing page.
 *
 * Nhờ vậy không cần lệnh riêng: ghi brand/nhà sản xuất là URL tự cập nhật, và
 * `bin/console dal:refresh:index` (hoặc `--only=acme_brand.indexer`) sinh lại toàn bộ.
 *
 * @internal
 */
class BrandIndexer extends EntityIndexer
{
    final public const NAME = 'acme_brand.indexer';

    final public const SEO_URL_UPDATER = 'acme_brand.seo-url';

    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly BrandDefinition $brandDefinition,
        private readonly Connection $connection,
        private readonly SeoUrlUpdater $seoUrlUpdater,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->brandDefinition, $offset);

        $brandIds = $iterator->fetch();
        if ($brandIds === []) {
            return null;
        }

        return new BrandIndexingMessage($this->manufacturerIdsOfBrands(array_values($brandIds)), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $manufacturerIds = $event->getPrimaryKeys(ProductManufacturerDefinition::ENTITY_NAME);

        $brandIds = $event->getPrimaryKeys(BrandDefinition::ENTITY_NAME);
        if ($brandIds !== []) {
            $manufacturerIds = [...$manufacturerIds, ...$this->manufacturerIdsOfBrands($brandIds)];
        }

        $manufacturerIds = array_values(array_unique($manufacturerIds));
        if ($manufacturerIds === []) {
            return null;
        }

        return new BrandIndexingMessage($manufacturerIds, null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids) || $ids === [] || $message->allow(self::SEO_URL_UPDATER) === false) {
            return;
        }

        $ids = array_values(array_unique($ids));

        $this->seoUrlUpdater->update(BrandStoreApiUrlRoute::ROUTE_NAME, $ids);
    }

    public function getOptions(): array
    {
        return [self::SEO_URL_UPDATER];
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->brandDefinition)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
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
