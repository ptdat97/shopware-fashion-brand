<?php declare(strict_types=1);

namespace Acme\DemoData\Service;

use Acme\DemoData\DemoDataException;
use Cocur\Slugify\SlugifyInterface;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Nạp dữ liệu demo theo từng đợt nhỏ.
 *
 * Chia đợt vì thư viện ảnh gần 80 MB — nạp một lần trong một request HTTP dễ chạm
 * giới hạn thời gian của PHP-FPM. Administration gọi lặp `import()` cho tới khi
 * `done = true`, nhờ vậy hiện được thanh tiến trình.
 *
 * Mọi id đều suy ra từ tên file / mã sản phẩm (@see Uuid::fromStringToHex) và ghi bằng
 * `upsert`, nên bấm nút nhiều lần chỉ cập nhật chứ không nhân bản dữ liệu. Cách này học
 * từ plugin chính thức SwagPlatformDemoData.
 */
class DemoDataImporter
{
    /** Số ảnh nạp trong một request. 8 ảnh ~ vài giây, an toàn với mọi cấu hình PHP. */
    public const CHUNK_SIZE = 8;

    /** Ảnh đóng gói kèm plugin — không phụ thuộc plugin khác */
    private const MEDIA_DIR = __DIR__ . '/../Resources/media/demo';

    private const VAT_RATE = 10.0;

    /** @var list<string>|null Nhớ trong một request, tránh truy vấn lại nhiều lần */
    private ?array $languageIds = null;

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly EntityRepository $propertyGroupRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $languageRepository,
        private readonly FileSaver $fileSaver,
        private readonly EntityRepository $manufacturerRepository,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly EntityRepository $productStreamRepository,
        // Thư viện slugify core đang dùng (Cocur), cấu hình chữ thường — khớp 67/67 tên
        // file với hàm tự viết trước đây, nên tên file media đã nạp không đổi
        private readonly SlugifyInterface $slugify
    ) {
    }

    /**
     * Tình trạng hiện tại để Administration hiển thị trước khi bấm nút.
     *
     * @return array{ready: bool, blocker: ?string, mediaTotal: int, mediaImported: int, productsTotal: int, productsImported: int}
     */
    public function getStatus(Context $context): array
    {
        $files = $this->mediaFiles();
        $definitions = DemoProductCatalog::definitions();

        $productIds = array_map(
            fn (array $definition): string => $this->productId($definition['key']),
            $definitions
        );

        $blocker = null;
        if ($files === []) {
            $blocker = 'Không tìm thấy ảnh nào trong ' . self::MEDIA_DIR;
        } elseif ($this->findTaxId($context) === null) {
            $blocker = 'Chưa có thuế ' . self::VAT_RATE . '%. Cài plugin AcmeVietnam (Phase 0) trước.';
        } elseif ($this->findRootCategoryId($context) === null) {
            $blocker = 'Không tìm thấy danh mục gốc của shop.';
        }

        return [
            'ready' => $blocker === null,
            'blocker' => $blocker,
            'mediaTotal' => count($files),
            'mediaImported' => count($this->findImportedMedia($context, $files)),
            'productsTotal' => count($definitions),
            'productsImported' => $this->productRepository->searchIds(new Criteria($productIds), $context)->getTotal(),
        ];
    }

    /**
     * Chạy một đợt nhập. Gọi lại với `offset` trả về cho tới khi `done = true`.
     *
     * Đợt đầu (offset 0) tạo danh mục và thuộc tính; các đợt giữa nạp ảnh;
     * đợt cuối (offset ≥ tổng số ảnh) tạo sản phẩm.
     *
     * @return array{done: bool, offset: int, total: int, step: string, products: int, warnings: list<string>}
     *
     * @throws DemoDataException khi môi trường chưa sẵn sàng
     */
    public function import(Context $context, int $offset = 0): array
    {
        $files = $this->mediaFiles();
        if ($files === []) {
            throw DemoDataException::mediaMissing(self::MEDIA_DIR);
        }

        $taxId = $this->findTaxId($context);
        if ($taxId === null) {
            throw DemoDataException::taxMissing(self::VAT_RATE);
        }

        $total = count($files);
        $offset = max(0, $offset);
        $warnings = [];

        // Đợt đầu: danh mục + thuộc tính. Nhanh, nên gộp luôn vào đợt nạp ảnh đầu tiên.
        if ($offset === 0) {
            $rootId = $this->findRootCategoryId($context);
            if ($rootId === null) {
                throw DemoDataException::rootCategoryMissing();
            }

            $this->ensureCategories($context, $rootId);
            $this->ensureProperties($context);
            $this->ensureBrands($context);
            $this->ensureCollections($context, $warnings);
            $this->ensureCategoryMetaDescriptions($context);
        }

        // Đợt cuối: tạo sản phẩm khi ảnh đã nạp xong
        if ($offset >= $total) {
            $created = $this->createProducts($context, $taxId, $warnings);

            return [
                'done' => true,
                'offset' => $total,
                'total' => $total,
                'step' => sprintf('Đã tạo %d sản phẩm', $created),
                'products' => $created,
                'warnings' => $warnings,
            ];
        }

        $chunk = \array_slice($files, $offset, self::CHUNK_SIZE);
        $this->importMediaChunk($context, $chunk, $warnings);
        $next = $offset + count($chunk);

        return [
            'done' => false,
            'offset' => $next,
            'total' => $total,
            'step' => sprintf('Đang nạp ảnh %d/%d', $next, $total),
            'products' => 0,
            'warnings' => $warnings,
        ];
    }

    /**
     * Ảnh trong thư mục demo, sắp xếp cố định để chia đợt luôn cho cùng thứ tự.
     *
     * @return list<string>
     */
    private function mediaFiles(): array
    {
        if (!is_dir(self::MEDIA_DIR)) {
            return [];
        }

        $files = array_values(array_filter(
            scandir(self::MEDIA_DIR) ?: [],
            static fn (string $file): bool => preg_match('/\.(jpe?g|png|webp)$/i', $file) === 1
        ));

        sort($files, \SORT_STRING);

        return $files;
    }

    /**
     * Ảnh đã nằm trong thư viện media, tra theo tên file đã chuẩn hoá.
     *
     * Tra theo tên chứ không theo id để dùng lại được ảnh do lần chạy cũ tạo ra —
     * nếu tạo mới, Shopware sẽ báo trùng tên file.
     *
     * @param list<string> $files
     *
     * @return array<string,string> tên file gốc => mediaId
     */
    private function findImportedMedia(Context $context, array $files): array
    {
        if ($files === []) {
            return [];
        }

        $bySlug = [];
        foreach ($files as $file) {
            $bySlug[$this->slugify->slugify(pathinfo($file, \PATHINFO_FILENAME))] = $file;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('fileName', array_keys($bySlug)));
        $criteria->setLimit(count($bySlug));

        $map = [];
        foreach ($this->mediaRepository->search($criteria, $context)->getEntities() as $media) {
            /** @var MediaEntity $media */
            $file = $bySlug[(string) $media->getFileName()] ?? null;
            if ($file !== null) {
                $map[$file] = $media->getId();
            }
        }

        return $map;
    }

    /**
     * @param list<string> $chunk
     * @param list<string> $warnings
     */
    private function importMediaChunk(Context $context, array $chunk, array &$warnings): void
    {
        $folderId = $this->findProductMediaFolderId($context);
        $alreadyImported = $this->findImportedMedia($context, $chunk);

        foreach ($chunk as $file) {
            if (isset($alreadyImported[$file])) {
                continue;
            }

            $path = self::MEDIA_DIR . '/' . $file;
            $mediaId = $this->mediaId($file);

            $this->mediaRepository->upsert([[
                'id' => $mediaId,
                'mediaFolderId' => $folderId,
                'private' => false,
            ]], $context);

            $mediaFile = new MediaFile(
                $path,
                mime_content_type($path) ?: 'image/jpeg',
                strtolower(pathinfo($file, \PATHINFO_EXTENSION)),
                (int) filesize($path)
            );

            try {
                $this->fileSaver->persistFileToMedia(
                    $mediaFile,
                    $this->slugify->slugify(pathinfo($file, \PATHINFO_FILENAME)),
                    $mediaId,
                    $context
                );
            } catch (\Throwable $e) {
                // Xoá bản ghi rỗng để lần sau nạp lại từ đầu
                $this->mediaRepository->delete([['id' => $mediaId]], $context);
                $warnings[] = sprintf('Lỗi nạp ảnh %s: %s', $file, $e->getMessage());
            }
        }
    }

    /** @param list<string> $warnings */
    private function createProducts(Context $context, string $taxId, array &$warnings): int
    {
        $mediaMap = $this->findImportedMedia($context, $this->mediaFiles());
        $categories = $this->findCategoryIds($context);
        [$colorMap, $sizeMap, $materialMap] = $this->findPropertyIds($context);
        $salesChannelIds = $this->findSellingSalesChannelIds($context);
        $deliveryTimeId = $this->findStandardDeliveryTimeId($context);

        $payload = [];

        foreach (DemoProductCatalog::definitions() as $definition) {
            $images = array_values(array_filter(
                $definition['images'],
                static fn (string $file): bool => isset($mediaMap[$file])
            ));

            if ($images === []) {
                $warnings[] = sprintf('Bỏ qua "%s" — không nạp được ảnh nào của sản phẩm này', $definition['name']);
                continue;
            }

            $key = $definition['key'];
            $number = $definition['number'];
            $productId = $this->productId($key);

            $mediaPayload = [];
            $coverId = null;
            foreach ($images as $position => $file) {
                $productMediaId = Uuid::fromStringToHex('acme-demo-product-media-' . $key . '-' . $file);
                $coverId ??= $productMediaId;

                $mediaPayload[] = [
                    'id' => $productMediaId,
                    'mediaId' => $mediaMap[$file],
                    'position' => $position + 1,
                ];
            }

            $properties = [];
            foreach ($definition['colors'] as $color) {
                if (isset($colorMap[$color])) {
                    $properties[] = ['id' => $colorMap[$color]];
                }
            }
            foreach ($sizeMap as $sizeId) {
                $properties[] = ['id' => $sizeId];
            }
            if (isset($materialMap[$definition['material']])) {
                $properties[] = ['id' => $materialMap[$definition['material']]];
            }

            $gross = $definition['price'];
            $listGross = $definition['listPrice'] ?? null;

            $row = [
                'id' => $productId,
                'tags' => array_map(
                    fn (string $tag): array => ['id' => $this->tagId($tag), 'name' => $tag],
                    $definition['tags'] ?? []
                ),
                'productNumber' => $number,
                'manufacturerId' => $this->manufacturerId($definition['brand']),
                'stock' => $definition['stock'],
                // Trường native — thiếu thì trang sản phẩm headless báo "không còn bán" dù còn hàng
                'deliveryTimeId' => $deliveryTimeId,
                'taxId' => $taxId,
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $gross,
                    'net' => round($gross / (1 + self::VAT_RATE / 100)),
                    'linked' => true,
                    // Giá niêm yết native → storefront tự gạch giá cũ, stream "Sale" lọc theo % giảm
                    'listPrice' => $listGross === null ? null : [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => $listGross,
                        'net' => round($listGross / (1 + self::VAT_RATE / 100)),
                        'linked' => true,
                    ],
                ]],
                'active' => true,
                'media' => $mediaPayload,
                'coverId' => $coverId,
                'properties' => $properties,
                'categories' => isset($categories[$definition['category']])
                    ? [['id' => $categories[$definition['category']]]]
                    : [],
                'translations' => $this->translate($context, [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'metaDescription' => DemoProductCatalog::productMetaDescription($definition['description']),
                    'customFields' => [
                        // Định nghĩa bởi AcmeCatalog ("Người mẫu mặc size")
                        'acme_model_size' => $definition['modelSize'],
                        // Bản ≤ 1.0.1 ghi chất liệu vào custom field không ai định nghĩa — nay là
                        // thuộc tính native "Chất liệu" ở trên; xoá giá trị cũ khi chạy lại
                        'acme_material' => null,
                    ],
                ]),
            ];

            if ($salesChannelIds !== []) {
                $row['visibilities'] = array_map(static fn (string $salesChannelId): array => [
                    // Id cố định: cặp (sản phẩm, kênh bán) là duy nhất, id ngẫu nhiên sẽ vỡ ở lần chạy thứ hai
                    'id' => Uuid::fromStringToHex('acme-demo-visibility-' . $key . '-' . $salesChannelId),
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 30,
                ], $salesChannelIds);
            }

            $payload[] = $row;
        }

        if ($payload === []) {
            return 0;
        }

        foreach (array_chunk($payload, 5) as $part) {
            $this->productRepository->upsert($part, $context);
        }

        $this->renumberVariants($context, array_column($payload, 'productNumber', 'id'));

        return count($payload);
    }

    /**
     * Biến thể do `acme:catalog:variants` sinh ra mang mã của cha tại thời điểm sinh
     * (`ANV-1005-01`). Khi cha đổi sang mã brand (`MAY-1005`) thì đổi theo, giữ nguyên
     * hậu tố thứ tự — để mã biến thể luôn mang đúng tiền tố brand của sản phẩm.
     *
     * @param array<string, string> $parentNumbers id sản phẩm cha => mã SKU mới
     */
    private function renumberVariants(Context $context, array $parentNumbers): void
    {
        if ($parentNumbers === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('parentId', array_keys($parentNumbers)));

        $updates = [];
        foreach ($this->productRepository->search($criteria, $context) as $variant) {
            $parentNumber = $parentNumbers[$variant->getParentId()] ?? null;
            $current = $variant->getProductNumber();
            if ($parentNumber === null || str_starts_with($current, $parentNumber . '-')) {
                continue;
            }

            $suffix = substr($current, (int) strrpos($current, '-') + 1);
            $updates[] = ['id' => $variant->getId(), 'productNumber' => $parentNumber . '-' . $suffix];
        }

        if ($updates !== []) {
            $this->productRepository->update($updates, $context);
        }
    }

    /**
     * Meta description demo cho danh mục chính — chỉ điền khi đang trống, không ghi đè
     * nội dung người vận hành đã viết.
     */
    private function ensureCategoryMetaDescriptions(Context $context): void
    {
        $descriptions = DemoProductCatalog::metaDescriptions();
        $updates = [];

        foreach ($this->findCategoryIds($context) as $name => $categoryId) {
            if (!isset($descriptions[$name])) {
                continue;
            }

            $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->first();
            if ($category === null || trim((string) $category->getTranslation('metaDescription')) !== '') {
                continue;
            }

            $updates[] = [
                'id' => $categoryId,
                'translations' => $this->translate($context, ['metaDescription' => $descriptions[$name]]),
            ];
        }

        if ($updates !== []) {
            $this->categoryRepository->upsert($updates, $context);
        }
    }

    /**
     * Bộ sưu tập demo: danh mục con của "Bộ sưu tập" + product stream, có cờ
     * `acme_collection` để URL thành /collections/{tên}.
     *
     * Id stream và filter suy ra từ tên → chạy lại seed ghi đè đúng chỗ, không nhân bản.
     *
     * @param list<string> $warnings
     */
    private function ensureCollections(Context $context, array &$warnings): void
    {
        $parentId = $this->findCategoryIds($context)['Bộ sưu tập'] ?? null;
        if ($parentId === null) {
            $warnings[] = 'Không thấy danh mục "Bộ sưu tập" — bỏ qua bộ sưu tập demo';

            return;
        }

        $streams = [];
        $categories = [];
        foreach (DemoProductCatalog::collections() as $name => $conditions) {
            $streamId = Uuid::fromStringToHex('acme-demo-stream-' . $name);
            $orId = Uuid::fromStringToHex('acme-demo-stream-filter-or-' . $name);
            $andId = Uuid::fromStringToHex('acme-demo-stream-filter-and-' . $name);

            $queries = [];
            foreach ($conditions as $position => $condition) {
                $queries[] = $condition + [
                    'id' => Uuid::fromStringToHex('acme-demo-stream-filter-' . $name . '-' . $position),
                    'position' => $position,
                ];
            }

            $streams[] = [
                'id' => $streamId,
                'translations' => $this->translate($context, ['name' => 'Bộ sưu tập: ' . $name]),
                'filters' => [[
                    'id' => $orId,
                    'type' => 'multi',
                    'operator' => 'OR',
                    'position' => 0,
                    'queries' => [[
                        'id' => $andId,
                        'type' => 'multi',
                        'operator' => 'AND',
                        'position' => 0,
                        'queries' => $queries,
                    ]],
                ]],
            ];

            $categories[] = [
                'id' => $this->categoryId($name),
                'parentId' => $parentId,
                'active' => true,
                'type' => 'page',
                'productAssignmentType' => 'product_stream',
                'productStreamId' => $streamId,
                'translations' => $this->translate($context, [
                    'name' => $name,
                    'metaDescription' => DemoProductCatalog::metaDescriptions()[$name] ?? null,
                    'customFields' => ['acme_collection' => true],
                ]),
            ];
        }

        // Danh mục cha là trang tổng bộ sưu tập → URL /collections/ (AcmeCatalog ≥ 1.3.0)
        $categories[] = [
            'id' => $parentId,
            'translations' => $this->translate($context, ['customFields' => ['acme_collection_root' => true]]),
        ];

        $this->productStreamRepository->upsert($streams, $context);
        $this->categoryRepository->upsert($categories, $context);
    }

    /**
     * Thương hiệu demo = nhà sản xuất của core. Có cài `AcmeBrand` thì ghi kèm mã brand
     * và bật trang thương hiệu; không có thì chỉ tạo nhà sản xuất — plugin này không bắt
     * buộc phụ thuộc `AcmeBrand`.
     */
    private function ensureBrands(Context $context): void
    {
        $withBrandData = $this->definitionRegistry->has('acme_brand');

        $payload = [];
        foreach (DemoProductCatalog::brands() as $code => $brand) {
            $row = [
                'id' => $this->manufacturerId($code),
                'translations' => $this->translate($context, [
                    'name' => $brand['name'],
                    'description' => $brand['description'],
                    // Meta SEO = custom field dịch được của nhà sản xuất (AcmeBrand ≥ 1.2.0)
                    'customFields' => ['acme_brand_meta_title' => $brand['name'] . ' — thời trang nữ'],
                ]),
            ];

            if ($withBrandData) {
                $row['acmeBrand'] = [
                    'id' => Uuid::fromStringToHex('acme-demo-brand-' . $code),
                    'code' => $code,
                    'active' => true,
                    'featured' => $brand['featured'],
                    'position' => $brand['position'],
                ];
            }

            $payload[] = $row;
        }

        $this->manufacturerRepository->upsert($payload, $context);
    }

    private function ensureCategories(Context $context, string $rootId): void
    {
        $existing = $this->findCategoryIds($context);
        $payload = [];

        foreach (DemoProductCatalog::categories() as $name) {
            if (isset($existing[$name])) {
                continue;
            }

            $payload[] = [
                'id' => $this->categoryId($name),
                'parentId' => $rootId,
                'translations' => $this->translate($context, ['name' => $name]),
                'active' => true,
                'displayNestedProducts' => true,
                'type' => 'page',
                'productAssignmentType' => 'product',
            ];
        }

        if ($payload !== []) {
            $this->categoryRepository->upsert($payload, $context);
        }
    }

    private function ensureProperties(Context $context): void
    {
        $colors = DemoProductCatalog::colors();
        $this->ensurePropertyGroup($context, 'Màu sắc', 'color', array_keys($colors), $colors);
        $this->ensurePropertyGroup($context, 'Kích cỡ', 'text', DemoProductCatalog::sizes(), []);
        // Nhóm "Chất liệu" do AcmeCatalog tạo; không cài AcmeCatalog thì tạo ở đây (có thì dùng)
        $this->ensurePropertyGroup($context, 'Chất liệu', 'text', DemoProductCatalog::materials(), []);
    }

    /**
     * Tạo nhóm thuộc tính nếu chưa có; nếu có rồi thì chỉ bổ sung giá trị còn thiếu.
     *
     * @param list<string> $options
     * @param array<string,string> $colorCodes
     */
    private function ensurePropertyGroup(
        Context $context,
        string $groupName,
        string $displayType,
        array $options,
        array $colorCodes
    ): void {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $groupName));
        $criteria->addAssociation('options');
        $criteria->setLimit(1);
        $group = $this->propertyGroupRepository->search($criteria, $context)->getEntities()->first();

        $groupId = $group?->getId() ?? $this->propertyGroupId($groupName);

        $existingNames = [];
        foreach ($group?->getOptions() ?? [] as $option) {
            $existingNames[] = $option->getName();
        }

        $optionPayload = [];
        foreach ($options as $position => $name) {
            if (\in_array($name, $existingNames, true)) {
                continue;
            }

            $row = [
                'id' => Uuid::fromStringToHex('acme-demo-option-' . $groupName . '-' . $name),
                'translations' => $this->translate($context, ['name' => $name]),
                'position' => $position + 1,
            ];
            if (isset($colorCodes[$name])) {
                $row['colorHexCode'] = $colorCodes[$name];
            }
            $optionPayload[] = $row;
        }

        if ($group !== null && $optionPayload === []) {
            return;
        }

        $groupPayload = ['id' => $groupId];

        if ($group === null) {
            $groupPayload += [
                'translations' => $this->translate($context, ['name' => $groupName]),
                'displayType' => $displayType,
                'sortingType' => 'position',
                'filterable' => true,
            ];
        }

        if ($optionPayload !== []) {
            $groupPayload['options'] = $optionPayload;
        }

        $this->propertyGroupRepository->upsert([$groupPayload], $context);
    }

    /** @return array<string,string> tên danh mục => id */
    private function findCategoryIds(Context $context): array
    {
        $names = DemoProductCatalog::categories();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));
        $criteria->setLimit(count($names) * 5);

        $map = [];
        foreach ($this->categoryRepository->search($criteria, $context)->getEntities() as $category) {
            $name = (string) $category->getName();
            // Danh mục trùng tên: giữ cái gặp trước, đủ dùng cho dữ liệu demo
            $map[$name] ??= $category->getId();
        }

        return $map;
    }

    /** @return array{0: array<string,string>, 1: array<string,string>, 2: array<string,string>} màu, size, chất liệu */
    private function findPropertyIds(Context $context): array
    {
        $groups = ['Màu sắc' => [], 'Kích cỡ' => [], 'Chất liệu' => []];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', array_keys($groups)));
        $criteria->addAssociation('options');
        $criteria->setLimit(\count($groups));

        foreach ($this->propertyGroupRepository->search($criteria, $context)->getEntities() as $group) {
            foreach ($group->getOptions() ?? [] as $option) {
                $groups[(string) $group->getName()][(string) $option->getName()] = $option->getId();
            }
        }

        return array_values($groups);
    }

    /**
     * Nhân bản các trường dịch được sang mọi ngôn ngữ cần ghi.
     *
     * DAL ghi theo ngôn ngữ của Context, mà Context ở đây là mặc định (en-US), nên nếu
     * chỉ đặt `name` ở cấp gốc thì storefront vi-VN phải sống nhờ cơ chế fallback và
     * người vận hành mở Administration bằng tiếng Việt sẽ thấy ô tên trống.
     * Trước đây lỗi này được vá bằng SQL tay — xem docs/runbook/05-demo-data.md.
     *
     * @param array<string,mixed> $fields
     *
     * @return array<string,array<string,mixed>>
     */
    private function translate(Context $context, array $fields): array
    {
        $translations = [];
        foreach ($this->findLanguageIds($context) as $languageId) {
            $translations[$languageId] = $fields;
        }

        return $translations;
    }

    /**
     * Ngôn ngữ hệ thống + vi-VN (nếu shop có cài).
     *
     * @return list<string>
     */
    private function findLanguageIds(Context $context): array
    {
        if ($this->languageIds !== null) {
            return $this->languageIds;
        }

        $ids = [Defaults::LANGUAGE_SYSTEM];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', 'vi-VN'));
        $criteria->setLimit(1);

        $vietnamese = $this->languageRepository->searchIds($criteria, $context)->firstId();
        if ($vietnamese !== null && $vietnamese !== Defaults::LANGUAGE_SYSTEM) {
            $ids[] = $vietnamese;
        }

        return $this->languageIds = $ids;
    }

    private function findTaxId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('taxRate', self::VAT_RATE));
        $criteria->setLimit(1);

        return $this->taxRepository->searchIds($criteria, $context)->firstId();
    }

    private function findRootCategoryId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('level', 1));
        $criteria->setLimit(1);

        return $this->categoryRepository->searchIds($criteria, $context)->firstId();
    }

    private function findProductMediaFolderId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('defaultFolder.entity', 'product'));
        $criteria->setLimit(1);

        return $this->mediaFolderRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * Thời gian giao của phương thức vận chuyển chuẩn (AcmeShipping tính phí trên phương thức này).
     * Không có thì để trống — sản phẩm vẫn bán được.
     */
    private function findStandardDeliveryTimeId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'shipping_standard'));
        $criteria->setLimit(1);

        /** @var ShippingMethodEntity|null $method */
        $method = $this->definitionRegistry->getRepository('shipping_method')->search($criteria, $context)->first();

        return $method?->getDeliveryTimeId();
    }

    /**
     * Channel khách mua hàng: Storefront (Twig) và API (frontend headless — ADR-005).
     * Bỏ qua các loại khác như Product comparison.
     *
     * @return list<string>
     */
    private function findSellingSalesChannelIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('typeId', [
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            Defaults::SALES_CHANNEL_TYPE_API,
        ]));

        return array_values($this->salesChannelRepository->searchIds($criteria, $context)->getIds());
    }

    private function mediaId(string $file): string
    {
        return Uuid::fromStringToHex('acme-demo-media-' . $file);
    }

    /** Suy ra từ khoá cố định (ANV-1001…), KHÔNG từ mã SKU — xem DemoProductCatalog */
    private function productId(string $key): string
    {
        return Uuid::fromStringToHex('acme-demo-product-' . $key);
    }

    private function tagId(string $name): string
    {
        return Uuid::fromStringToHex('acme-demo-tag-' . $name);
    }

    private function manufacturerId(string $brandCode): string
    {
        return Uuid::fromStringToHex('acme-demo-manufacturer-' . $brandCode);
    }

    private function categoryId(string $name): string
    {
        return Uuid::fromStringToHex('acme-demo-category-' . $name);
    }

    private function propertyGroupId(string $name): string
    {
        return Uuid::fromStringToHex('acme-demo-property-group-' . $name);
    }

}
