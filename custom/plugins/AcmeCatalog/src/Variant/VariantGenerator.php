<?php declare(strict_types=1);

namespace Acme\Catalog\Variant;

use Acme\Catalog\CatalogException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Sinh biến thể Màu × Size cho sản phẩm thời trang.
 *
 * Trục biến thể cố ý chỉ có 2 (Màu, Size) — thêm trục thứ ba nhân số SKU lên
 * quá tầm vận hành: 7 màu × 4 size = 28 SKU/mẫu, thêm chất liệu thành 84.
 * Chất liệu/kiểu dáng giữ nguyên dạng property mô tả. Xem docs/10-phase2-catalog.md.
 *
 * Vì sao không dùng bộ sinh biến thể của core (Quy tắc số 4): core chỉ có bộ sinh trong
 * Administration (`sw-products-variants-generator.js`, chạy ở trình duyệt, từng sản phẩm
 * một) — không có service hay lệnh phía server. Service này ghi đúng cấu trúc native mà bộ
 * sinh của Admin tạo ra (`configuratorSettings` + `children` + `options`), nên sản phẩm
 * sinh ở đây sửa tiếp được bằng Admin như bình thường. Hàng thật nhập qua Import/Export
 * native hoặc bộ sinh của Admin; service này phục vụ nhập hàng loạt và dữ liệu demo.
 *
 * Chỉ xử lý sản phẩm cha chưa có biến thể — chạy lại không sinh trùng.
 */
class VariantGenerator
{
    public const COLOUR_GROUP = 'Màu sắc';
    public const SIZE_GROUP = 'Kích cỡ';

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<PropertyGroupCollection> $propertyGroupRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $propertyGroupRepository
    ) {
    }

    /**
     * @return array{
     *     generated: list<array{productNumber: string, colours: int, sizes: int, variants: int}>,
     *     skipped: list<array{productNumber: string, colours: int, sizes: int}>
     * }
     *
     * @throws CatalogException khi chưa có nhóm thuộc tính Màu sắc / Kích cỡ
     */
    public function generate(Context $context, ?string $productNumber = null, bool $dryRun = false): array
    {
        [$colourGroupId, $sizeGroupId] = $this->loadVariantGroups($context);

        $generated = [];
        $skipped = [];

        foreach ($this->loadParentProducts($context, $productNumber) as $parent) {
            $colours = $parent['optionsByGroup'][$colourGroupId] ?? [];
            $sizes = $parent['optionsByGroup'][$sizeGroupId] ?? [];

            if ($colours === [] || $sizes === []) {
                $skipped[] = [
                    'productNumber' => $parent['productNumber'],
                    'colours' => \count($colours),
                    'sizes' => \count($sizes),
                ];

                continue;
            }

            $payload = $this->buildVariantPayload($parent, $colours, $sizes);

            if (!$dryRun) {
                // upsert chứ không phải update: payload vừa sửa sản phẩm cha
                // vừa TẠO MỚI các biến thể con — update() chỉ chấp nhận UpdateCommand.
                $this->productRepository->upsert([$payload], $context);

                // SEO: mọi biến thể cùng một canonical (biến thể đầu tiên) — trường native
                // `canonicalProductId`, biến thể kế thừa từ cha. Sitemap của core khi đó chỉ
                // đưa biến thể canonical vào (ProductUrlProvider). Ghi riêng sau upsert vì cột
                // có khoá ngoại tới chính bảng product — biến thể phải tồn tại trước.
                $this->productRepository->update([[
                    'id' => $parent['id'],
                    'canonicalProductId' => $payload['children'][0]['id'],
                ]], $context);
            }

            $generated[] = [
                'productNumber' => $parent['productNumber'],
                'colours' => \count($colours),
                'sizes' => \count($sizes),
                'variants' => \count($payload['children']),
            ];
        }

        return ['generated' => $generated, 'skipped' => $skipped];
    }

    /**
     * @return array{0: string, 1: string} [colourGroupId, sizeGroupId]
     */
    private function loadVariantGroups(Context $context): array
    {
        $colourGroupId = null;
        $sizeGroupId = null;

        foreach ($this->propertyGroupRepository->search(new Criteria(), $context)->getEntities() as $group) {
            match ($group->getTranslation('name')) {
                self::COLOUR_GROUP => $colourGroupId = $group->getId(),
                self::SIZE_GROUP => $sizeGroupId = $group->getId(),
                default => null,
            };
        }

        if ($colourGroupId === null || $sizeGroupId === null) {
            throw CatalogException::variantGroupsMissing(self::COLOUR_GROUP, self::SIZE_GROUP);
        }

        return [$colourGroupId, $sizeGroupId];
    }

    /**
     * @return list<array{id: string, productNumber: string, stock: int, optionsByGroup: array<string, list<string>>}>
     */
    private function loadParentProducts(Context $context, ?string $productNumber): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->addFilter(new EqualsFilter('childCount', 0));
        $criteria->addAssociation('properties');

        if ($productNumber !== null) {
            $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));
        }

        $result = [];

        foreach ($this->productRepository->search($criteria, $context)->getEntities() as $product) {
            $byGroup = [];
            foreach ($product->getProperties() ?? [] as $option) {
                $byGroup[$option->getGroupId()][] = $option->getId();
            }

            $result[] = [
                'id' => $product->getId(),
                'productNumber' => $product->getProductNumber(),
                'stock' => $product->getStock() ?? 0,
                'optionsByGroup' => $byGroup,
            ];
        }

        return $result;
    }

    /**
     * Dựng payload cập nhật sản phẩm cha kèm toàn bộ biến thể con.
     *
     * Biến thể kế thừa giá, thuế, mô tả, ảnh từ cha — chỉ ghi đè những gì
     * thật sự riêng: mã SKU, tồn kho, và tổ hợp option.
     *
     * @param array{id: string, productNumber: string, stock: int} $parent
     * @param list<string> $colours
     * @param list<string> $sizes
     *
     * @return array{id: string, children: non-empty-list<array{id: string}>}&array<string, mixed>
     */
    private function buildVariantPayload(array $parent, array $colours, array $sizes): array
    {
        $children = [];

        // Tồn kho của cha chia đều cho các biến thể — dữ liệu demo, hàng thật sẽ nhập lại.
        $perVariant = (int) floor($parent['stock'] / max(1, \count($colours) * \count($sizes)));

        $index = 1;
        foreach ($colours as $colourId) {
            foreach ($sizes as $sizeId) {
                $children[] = [
                    'id' => Uuid::randomHex(),
                    'productNumber' => \sprintf('%s-%02d', $parent['productNumber'], $index),
                    'stock' => $perVariant,
                    'isCloseout' => true,
                    'options' => [
                        ['id' => $colourId],
                        ['id' => $sizeId],
                    ],
                ];
                ++$index;
            }
        }

        $configuratorSettings = [];
        foreach ([...$colours, ...$sizes] as $optionId) {
            $configuratorSettings[] = ['optionId' => $optionId];
        }

        return [
            'id' => $parent['id'],
            'isCloseout' => true,
            'configuratorSettings' => $configuratorSettings,
            // Ghi vào DAL phải là mảng, không phải struct VariantListingConfig:
            // VariantListingConfigFieldSerializer::encode() nhận array, chỉ decode()
            // mới trả về struct. displayParent=false → listing hiện từng biến thể.
            'variantListingConfig' => [
                'displayParent' => false,
                'mainVariantId' => null,
                'configuratorGroupConfig' => null,
            ],
            'children' => $children,
        ];
    }
}
