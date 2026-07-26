<?php declare(strict_types=1);

namespace AniveleTheme\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Dựng megamenu và footer.
 *
 * Cả lệnh CLI lẫn module Administration đều gọi service này, nên hai đường
 * không thể lệch hành vi.
 *
 * @internal
 */
class NavigationBuilder
{
    public const FOOTER_ROOT = 'Footer Anivele';

    /** @var array<string, array<string>> */
    public const MENU_TREE = [
        'Đầm' => ['Đầm maxi', 'Đầm midi', 'Đầm công sở', 'Đầm dạ hội'],
        'Áo' => ['Áo sơ mi', 'Áo thun', 'Áo polo', 'Áo khoác'],
        'Chân váy' => ['Chân váy dài', 'Chân váy ngắn', 'Chân váy xếp ly'],
        'Bộ sưu tập' => ['Set công sở', 'Set dạo phố', 'Hàng mới về'],
    ];

    /** @var array<string, array<string>> */
    public const FOOTER_TREE = [
        'Về Anivele' => ['Câu chuyện thương hiệu', 'Hệ thống cửa hàng', 'Tuyển dụng'],
        'Hỗ trợ khách hàng' => ['Hướng dẫn chọn size', 'Chính sách đổi trả', 'Chính sách bảo mật', 'Câu hỏi thường gặp'],
        'Mua sắm' => ['Hướng dẫn đặt hàng', 'Phương thức thanh toán', 'Chính sách vận chuyển'],
    ];

    /**
     * Từ khoá trong tên sản phẩm → danh mục cấp 3.
     *
     * Chỉ dùng cho dữ liệu demo. Hàng thật thì gán danh mục trong Administration.
     *
     * @var array<string, string>
     */
    private const KEYWORD_MAP = [
        'đầm maxi' => 'Đầm maxi',
        'đầm midi' => 'Đầm midi',
        'dạ hội' => 'Đầm dạ hội',
        'sơ mi' => 'Áo sơ mi',
        'áo thun' => 'Áo thun',
        'polo' => 'Áo polo',
        'chân váy dài' => 'Chân váy dài',
        'xếp ly' => 'Chân váy xếp ly',
        'set áo' => 'Set công sở',
    ];

    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $productRepository,
        private readonly string $defaultSalesChannelId
    ) {
    }

    /**
     * Mô tả cây menu và footer hiện tại cho module Administration.
     *
     * @return array{menu: array<int, mixed>, footer: array<int, mixed>, depth: int}
     */
    public function describe(Context $context): array
    {
        $criteria = new Criteria([$this->defaultSalesChannelId]);
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();

        $navigationRootId = $salesChannel?->getNavigationCategoryId();
        $footerRootId = $salesChannel?->getFooterCategoryId();

        return [
            'menu' => $navigationRootId !== null ? $this->describeChildren($context, $navigationRootId) : [],
            'footer' => $footerRootId !== null ? $this->describeChildren($context, $footerRootId) : [],
            'depth' => $salesChannel?->getNavigationCategoryDepth() ?? 0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function describeChildren(Context $context, string $parentId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addSorting(new FieldSorting('name'));
        $criteria->addAssociation('media');

        $items = [];
        foreach ($this->categoryRepository->search($criteria, $context) as $category) {
            $items[] = [
                'id' => $category->getId(),
                'name' => $category->getTranslation('name'),
                'visible' => $category->getVisible(),
                'active' => $category->getActive(),
                'hasImage' => $category->getMediaId() !== null,
                'children' => $this->describeChildren($context, $category->getId()),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        Context $context,
        bool $menu = true,
        bool $footer = true,
        bool $images = false,
        bool $assignProducts = true
    ): array {
        $languageIds = $this->resolveLanguageIds($context);
        $result = ['menuCreated' => 0, 'footerCreated' => 0, 'productsAssigned' => 0, 'imagesAssigned' => 0, 'unmatched' => []];

        if ($menu) {
            $result['menuCreated'] = $this->buildMenu($context, $languageIds);

            if ($assignProducts) {
                [$assigned, $unmatched] = $this->assignProducts($context);
                $result['productsAssigned'] = $assigned;
                $result['unmatched'] = $unmatched;
            }

            if ($images) {
                $result['imagesAssigned'] = $this->assignImages($context);
            }
        }

        if ($footer) {
            $result['footerCreated'] = $this->buildFooter($context, $languageIds);
        }

        return $result;
    }

    /**
     * @param array<string> $languageIds
     */
    private function buildMenu(Context $context, array $languageIds): int
    {
        $created = 0;

        foreach (self::MENU_TREE as $parentName => $childNames) {
            $parentId = $this->findCategoryIdByName($context, $parentName);
            if ($parentId === null) {
                continue;
            }

            $payload = [];
            foreach ($childNames as $childName) {
                if ($this->findCategoryIdByName($context, $childName) !== null) {
                    continue;
                }

                $payload[] = [
                    'id' => Uuid::randomHex(),
                    'parentId' => $parentId,
                    'active' => true,
                    'visible' => true,
                    'type' => 'page',
                    'displayNestedProducts' => true,
                    'translations' => $this->translateName($languageIds, $childName),
                ];
                ++$created;
            }

            if ($payload !== []) {
                $this->categoryRepository->create($payload, $context);
            }
        }

        // Không có depth >= 3 thì flyout không xổ dù đã có danh mục con.
        $this->salesChannelRepository->update([[
            'id' => $this->defaultSalesChannelId,
            'navigationCategoryDepth' => 3,
        ]], $context);

        return $created;
    }

    /**
     * @param array<string> $languageIds
     */
    private function buildFooter(Context $context, array $languageIds): int
    {
        $rootId = $this->findCategoryIdByName($context, self::FOOTER_ROOT);

        if ($rootId === null) {
            $rootId = Uuid::randomHex();
            $this->categoryRepository->create([[
                'id' => $rootId,
                'active' => true,
                'visible' => true,
                'type' => 'link',
                'translations' => $this->translateName($languageIds, self::FOOTER_ROOT),
            ]], $context);
        }

        $created = 0;
        foreach (self::FOOTER_TREE as $columnName => $linkNames) {
            $columnId = $this->findCategoryIdByName($context, $columnName);

            if ($columnId === null) {
                $columnId = Uuid::randomHex();
                $this->categoryRepository->create([[
                    'id' => $columnId,
                    'parentId' => $rootId,
                    'active' => true,
                    'visible' => true,
                    'type' => 'link',
                    'translations' => $this->translateName($languageIds, $columnName),
                ]], $context);
                ++$created;
            }

            $payload = [];
            foreach ($linkNames as $linkName) {
                if ($this->findCategoryIdByName($context, $linkName) !== null) {
                    continue;
                }

                $payload[] = [
                    'id' => Uuid::randomHex(),
                    'parentId' => $columnId,
                    'active' => true,
                    'visible' => true,
                    'type' => 'link',
                    'translations' => $this->translateName($languageIds, $linkName),
                ];
                ++$created;
            }

            if ($payload !== []) {
                $this->categoryRepository->create($payload, $context);
            }
        }

        $this->salesChannelRepository->update([[
            'id' => $this->defaultSalesChannelId,
            'footerCategoryId' => $rootId,
        ]], $context);

        return $created;
    }

    /**
     * @return array{0: int, 1: array<string>}
     */
    private function assignProducts(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->addAssociation('categories');
        $products = $this->productRepository->search($criteria, $context);

        $payload = [];
        $unmatched = [];

        foreach ($products as $product) {
            $name = (string) $product->getTranslation('name');
            $lower = mb_strtolower($name);
            $found = false;

            foreach (self::KEYWORD_MAP as $keyword => $categoryName) {
                if (!str_contains($lower, $keyword)) {
                    continue;
                }

                $categoryId = $this->findCategoryIdByName($context, $categoryName);
                if ($categoryId === null) {
                    continue;
                }

                $payload[] = ['id' => $product->getId(), 'categories' => [['id' => $categoryId]]];
                $found = true;
                break;
            }

            if (!$found) {
                $unmatched[] = $name;
            }
        }

        if ($payload !== []) {
            $this->productRepository->update($payload, $context);
        }

        return [count($payload), $unmatched];
    }

    private function assignImages(Context $context): int
    {
        $payload = [];

        foreach (array_keys(self::MENU_TREE) as $categoryName) {
            $categoryId = $this->findCategoryIdByName($context, $categoryName);
            if ($categoryId === null) {
                continue;
            }

            $mediaId = $this->findCoverMediaIdInCategory($context, $categoryId);
            if ($mediaId !== null) {
                $payload[] = ['id' => $categoryId, 'mediaId' => $mediaId];
            }
        }

        if ($payload !== []) {
            $this->categoryRepository->update($payload, $context);
        }

        return count($payload);
    }

    /**
     * Ẩn/hiện một mục menu. Dùng `visible` để mục vẫn truy cập được bằng URL
     * trực tiếp; `active = false` mới là tắt hẳn.
     */
    public function setVisibility(Context $context, string $categoryId, bool $visible): void
    {
        $this->categoryRepository->update([[
            'id' => $categoryId,
            'visible' => $visible,
        ]], $context);
    }

    public function addItem(Context $context, string $parentId, string $name, string $type = 'page'): string
    {
        $languageIds = $this->resolveLanguageIds($context);
        $id = Uuid::randomHex();

        $this->categoryRepository->create([[
            'id' => $id,
            'parentId' => $parentId,
            'active' => true,
            'visible' => true,
            'type' => $type,
            'displayNestedProducts' => true,
            'translations' => $this->translateName($languageIds, $name),
        ]], $context);

        return $id;
    }

    public function setImage(Context $context, string $categoryId, ?string $mediaId): void
    {
        $this->categoryRepository->update([[
            'id' => $categoryId,
            'mediaId' => $mediaId,
        ]], $context);
    }

    private function findCoverMediaIdInCategory(Context $context, string $categoryId): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categories.id', $categoryId));
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->addAssociation('cover');
        $criteria->setLimit(5);

        foreach ($this->productRepository->search($criteria, $context) as $product) {
            $mediaId = $product->getCover()?->getMediaId();
            if ($mediaId !== null) {
                return $mediaId;
            }
        }

        return null;
    }

    public function findCategoryIdByName(Context $context, string $name): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));
        $criteria->setLimit(1);

        return $this->categoryRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * @return array<string>
     */
    private function resolveLanguageIds(Context $context): array
    {
        $ids = [];
        foreach (['vi-VN', 'en-US'] as $code) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('locale.code', $code));
            $id = $this->languageRepository->searchIds($criteria, $context)->firstId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string> $languageIds
     *
     * @return array<string, array{name: string}>
     */
    private function translateName(array $languageIds, string $name): array
    {
        $translations = [];
        foreach ($languageIds as $languageId) {
            $translations[$languageId] = ['name' => $name];
        }

        return $translations;
    }
}
