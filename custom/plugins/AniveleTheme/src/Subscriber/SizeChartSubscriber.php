<?php declare(strict_types=1);

namespace AniveleTheme\Subscriber;

use AniveleTheme\Struct\SizeChartData;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lấy bảng size của danh mục chứa sản phẩm rồi gắn vào trang chi tiết.
 *
 * Bảng size lưu ở custom field của CATEGORY (xem Migration...SizeChartCustomField)
 * vì đầm và chân váy không cùng thang số đo, nhưng mọi mẫu đầm dùng chung
 * một bảng — nhập một lần thay vì lặp cho từng SKU.
 *
 * Sản phẩm thuộc nhiều danh mục thì lấy bảng của danh mục ĐẦU TIÊN có bảng,
 * theo thứ tự danh mục được gán.
 *
 * @internal
 */
class SizeChartSubscriber implements EventSubscriberInterface
{
    private const FIELD_TABLE = 'anivele_size_chart_table';
    private const FIELD_NOTE = 'anivele_size_chart_note';

    public function __construct(private readonly EntityRepository $categoryRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'addSizeChart',
        ];
    }

    public function addSizeChart(ProductPageLoadedEvent $event): void
    {
        $product = $event->getPage()->getProduct();

        $categoryIds = $product->getCategoryIds() ?? [];

        if ($categoryIds === []) {
            return;
        }

        $criteria = new Criteria($categoryIds);
        $categories = $this->categoryRepository->search($criteria, $event->getContext())->getEntities();

        if (!$categories instanceof CategoryCollection) {
            return;
        }

        foreach ($categories as $category) {
            $customFields = $category->getTranslation('customFields') ?? [];

            $table = $customFields[self::FIELD_TABLE] ?? null;

            if (!\is_string($table) || trim($table) === '') {
                continue;
            }

            $note = $customFields[self::FIELD_NOTE] ?? null;

            $event->getPage()->addExtension(
                SizeChartData::EXTENSION_NAME,
                new SizeChartData(
                    table: $table,
                    note: \is_string($note) ? $note : null,
                    categoryName: (string) $category->getTranslation('name')
                )
            );

            return;
        }
    }
}
