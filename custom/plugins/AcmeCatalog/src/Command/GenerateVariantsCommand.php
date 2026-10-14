<?php declare(strict_types=1);

namespace Acme\Catalog\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sinh biến thể Màu × Size cho sản phẩm thời trang.
 *
 * Trục biến thể cố ý chỉ có 2 (Màu, Size) — thêm trục thứ ba nhân số SKU lên
 * quá tầm vận hành: 7 màu × 4 size = 28 SKU/mẫu, thêm chất liệu thành 84.
 * Chất liệu/kiểu dáng giữ nguyên dạng property mô tả. Xem docs/10-phase2-catalog.md.
 *
 * Vì sao không dùng bộ sinh biến thể của core (Quy tắc số 4): core chỉ có bộ sinh trong
 * Administration (`sw-products-variants-generator.js`, chạy ở trình duyệt, từng sản phẩm
 * một) — không có service hay lệnh phía server. Lệnh này ghi đúng cấu trúc native mà bộ
 * sinh của Admin tạo ra (`configuratorSettings` + `children` + `options`), nên sản phẩm
 * sinh ở đây sửa tiếp được bằng Admin như bình thường. Hàng thật nhập qua Import/Export
 * native hoặc bộ sinh của Admin; lệnh này phục vụ nhập hàng loạt và dữ liệu demo.
 */
#[AsCommand(
    name: 'acme:catalog:variants',
    description: 'Sinh biến thể Màu × Size cho sản phẩm từ thuộc tính mô tả đang gắn'
)]
class GenerateVariantsCommand extends Command
{
    private const COLOUR_GROUP = 'Màu sắc';
    private const SIZE_GROUP = 'Kích cỡ';

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $propertyGroupRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Chỉ xử lý một sản phẩm (ví dụ ANV-1001)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Chỉ in ra dự định, không ghi database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $sku = $input->getOption('sku');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Sinh biến thể Màu × Size');

        if ($dryRun) {
            $io->note('Chế độ dry-run — không ghi gì vào database.');
        }

        $groups = $this->loadVariantGroups($context);
        if ($groups === null) {
            $io->error(sprintf('Không tìm thấy nhóm thuộc tính "%s" và "%s".', self::COLOUR_GROUP, self::SIZE_GROUP));

            return Command::FAILURE;
        }
        [$colourGroupId, $sizeGroupId] = $groups;

        $parents = $this->loadParentProducts($context, $sku);
        if ($parents === []) {
            $io->warning($sku !== null
                ? sprintf('Không tìm thấy sản phẩm %s, hoặc sản phẩm đã có biến thể.', $sku)
                : 'Không có sản phẩm nào cần sinh biến thể.');

            return Command::SUCCESS;
        }

        $created = 0;
        $skipped = [];

        foreach ($parents as $parent) {
            $colours = $this->optionsOfGroup($parent, $colourGroupId);
            $sizes = $this->optionsOfGroup($parent, $sizeGroupId);

            if ($colours === [] || $sizes === []) {
                $skipped[] = sprintf(
                    '%s — thiếu thuộc tính (%d màu, %d size)',
                    $parent['productNumber'],
                    \count($colours),
                    \count($sizes)
                );

                continue;
            }

            $payload = $this->buildVariantPayload($parent, $colours, $sizes);
            $count = \count($payload['children']);

            $io->text(sprintf(
                '%s — %d màu × %d size = <info>%d biến thể</info>',
                $parent['productNumber'],
                \count($colours),
                \count($sizes),
                $count
            ));

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

            $created += $count;
        }

        foreach ($skipped as $line) {
            $io->warning($line);
        }

        $io->success(sprintf(
            $dryRun ? 'Dự kiến sinh %d biến thể cho %d sản phẩm.' : 'Đã sinh %d biến thể cho %d sản phẩm.',
            $created,
            \count($parents) - \count($skipped)
        ));

        if (!$dryRun && $created > 0) {
            $io->note('Chạy tiếp: bin/console dal:refresh:index && bin/console cache:clear');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}|null [colourGroupId, sizeGroupId]
     */
    private function loadVariantGroups(Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('options');

        $groups = $this->propertyGroupRepository->search($criteria, $context);

        $colourGroupId = null;
        $sizeGroupId = null;

        foreach ($groups as $group) {
            match ($group->getTranslation('name')) {
                self::COLOUR_GROUP => $colourGroupId = $group->getId(),
                self::SIZE_GROUP => $sizeGroupId = $group->getId(),
                default => null,
            };
        }

        if ($colourGroupId === null || $sizeGroupId === null) {
            return null;
        }

        return [$colourGroupId, $sizeGroupId];
    }

    /**
     * Chỉ lấy sản phẩm cha chưa có biến thể — chạy lại lệnh không sinh trùng.
     *
     * @return list<array{id: string, productNumber: string, stock: int, propertyIds: list<string>}>
     */
    private function loadParentProducts(Context $context, ?string $sku): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->addFilter(new EqualsFilter('childCount', 0));
        $criteria->addAssociation('properties.group');

        if ($sku !== null) {
            $criteria->addFilter(new EqualsFilter('productNumber', $sku));
        }

        $result = [];

        foreach ($this->productRepository->search($criteria, $context) as $product) {
            $properties = $product->getProperties();

            $byGroup = [];
            foreach ($properties ?? [] as $option) {
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
     * @param array{optionsByGroup: array<string, list<string>>} $parent
     *
     * @return list<string>
     */
    private function optionsOfGroup(array $parent, string $groupId): array
    {
        return $parent['optionsByGroup'][$groupId] ?? [];
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
     * @return array<string, mixed>
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
                    'productNumber' => sprintf('%s-%02d', $parent['productNumber'], $index),
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
