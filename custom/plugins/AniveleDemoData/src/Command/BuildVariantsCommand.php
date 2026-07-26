<?php declare(strict_types=1);

namespace Anivele\DemoData\Command;

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
 * Phase 2 — dựng cấu trúc variant cho catalog thời trang.
 *
 * Quyết định cấu trúc: docs/decisions/ADR-003-cau-truc-variant.md
 * Kế hoạch và thứ tự: docs/10-phase2-catalog.md
 *
 * Chạy lại nhiều lần được (idempotent).
 */
#[AsCommand(name: 'anivele:catalog:build-variants', description: 'Tách nhóm Hoạ tiết và sinh variant Màu × Size')]
class BuildVariantsCommand extends Command
{
    /** Mã màu 3 ký tự không dấu dùng trong SKU — ADR-003 mục 4 */
    private const COLOR_CODES = [
        'Đen' => 'DEN',
        'Trắng' => 'TRG',
        'Kem' => 'KEM',
        'Be' => 'BEI',
        'Nâu' => 'NAU',
        'Xanh pastel' => 'XPA',
    ];

    /** Giá trị nhóm Hoạ tiết — không phải trục variant, chỉ dùng lọc */
    private const PATTERNS = ['Hoa nhí', 'Kẻ sọc', 'Trơn', 'Hoạ tiết khác'];

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $propertyGroupRepository,
        private readonly EntityRepository $propertyGroupOptionRepository,
        private readonly EntityRepository $languageRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Chỉ sinh variant cho một SKU cụ thể');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Sinh variant cho mọi sản phẩm chưa có variant');
        $this->addOption('closeout', null, InputOption::VALUE_NONE, 'Bật is_closeout chặn bán quá tồn kho');
        $this->addOption('set-color', null, InputOption::VALUE_REQUIRED, 'Gán màu nền cho một SKU, dạng SKU:TênMàu');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $io->title('Phase 2 — Dựng cấu trúc variant');

        $languageIds = $this->resolveLanguageIds($context);
        if ($languageIds === []) {
            $io->error('Không tìm thấy ngôn ngữ vi-VN.');

            return Command::FAILURE;
        }

        $io->section('Bước 2 — Nhóm Hoạ tiết');
        $this->buildPatternGroup($context, $languageIds, $io);

        $setColor = $input->getOption('set-color');
        if ($setColor !== null) {
            $io->section('Gán màu nền');
            if ($this->assignColor($context, (string) $setColor, $io) === Command::FAILURE) {
                return Command::FAILURE;
            }
        }

        $sku = $input->getOption('sku');
        if ($sku !== null) {
            $io->section(sprintf('Sinh variant cho %s', $sku));
            if ($this->buildVariantsFor($context, (string) $sku, $io) === Command::FAILURE) {
                return Command::FAILURE;
            }
        }

        if ($input->getOption('all')) {
            $io->section('Sinh variant cho toàn bộ catalog');
            $this->buildAll($context, $io);
        }

        if ($input->getOption('closeout')) {
            $io->section('Bước 6 — Bật is_closeout');
            $this->enableCloseout($context, $io);
        }

        $io->success('Hoàn tất.');

        return Command::SUCCESS;
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
     * Tạo nhóm Hoạ tiết và chuyển "Hoa nhiều màu" sang.
     *
     * Giữ nguyên id của option khi chuyển nhóm để liên kết product_property không đứt.
     *
     * @param array<string> $languageIds
     */
    private function buildPatternGroup(Context $context, array $languageIds, SymfonyStyle $io): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Hoạ tiết'));
        $groupId = $this->propertyGroupRepository->searchIds($criteria, $context)->firstId();

        if ($groupId === null) {
            $groupId = Uuid::randomHex();
            $this->propertyGroupRepository->create([[
                'id' => $groupId,
                'displayType' => 'text',
                'sortingType' => 'position',
                'filterable' => true,
                'visibleOnProductDetailPage' => true,
                'translations' => $this->translate($languageIds, 'Hoạ tiết', 3),
            ]], $context);
            $io->text('Đã tạo nhóm "Hoạ tiết".');
        } else {
            $io->text('Nhóm "Hoạ tiết" đã có, dùng lại.');
        }

        $payload = [];

        // Chuyển "Hoa nhiều màu" khỏi nhóm Màu sắc — hoạ tiết không phải màu.
        $movedId = $this->findOptionIdByName($context, 'Hoa nhiều màu');
        if ($movedId !== null) {
            $payload[] = [
                'id' => $movedId,
                'groupId' => $groupId,
                'colorHexCode' => null,
                'translations' => $this->translate($languageIds, 'Hoa nhiều màu', 5),
            ];
        }

        foreach (self::PATTERNS as $position => $name) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('groupId', $groupId));
            $criteria->addFilter(new EqualsFilter('name', $name));
            if ($this->propertyGroupOptionRepository->searchIds($criteria, $context)->firstId() !== null) {
                continue;
            }
            $payload[] = [
                'id' => Uuid::randomHex(),
                'groupId' => $groupId,
                'translations' => $this->translate($languageIds, $name, $position + 1),
            ];
        }

        if ($payload !== []) {
            $this->propertyGroupOptionRepository->upsert($payload, $context);
        }
        $io->text(sprintf('Giá trị hoạ tiết đã ghi: %d', count($payload)));
    }

    /**
     * Sinh variant Màu × Size cho một sản phẩm.
     *
     * Tồn kho của sản phẩm cha được chia đều cho các variant; giá thừa kế từ cha.
     */
    private function buildVariantsFor(Context $context, string $productNumber, SymfonyStyle $io, bool $quiet = false): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('children');

        $product = $this->productRepository->search($criteria, $context)->first();
        if ($product === null) {
            $io->error(sprintf('Không tìm thấy sản phẩm %s.', $productNumber));

            return Command::FAILURE;
        }

        if ($product->getChildCount() > 0) {
            $io->text(sprintf('%s đã có %d variant, bỏ qua.', $productNumber, $product->getChildCount()));

            return Command::SUCCESS;
        }

        // Tách property của sản phẩm thành trục màu và trục size.
        $colors = [];
        $sizes = [];
        foreach ($product->getProperties() as $option) {
            $groupName = $option->getGroup()?->getTranslation('name') ?? $option->getGroup()?->getName();
            if ($groupName === 'Màu sắc') {
                $colors[$option->getId()] = $option->getTranslation('name') ?? $option->getName();
            } elseif ($groupName === 'Kích cỡ') {
                $sizes[$option->getId()] = $option->getTranslation('name') ?? $option->getName();
            }
        }

        if ($colors === [] || $sizes === []) {
            if (!$quiet) {
                $io->error(sprintf('%s thiếu trục màu hoặc size (màu: %d, size: %d).', $productNumber, count($colors), count($sizes)));
            }

            return Command::FAILURE;
        }

        $parentStock = $product->getStock();
        $variantCount = count($colors) * count($sizes);

        // Chia đều tồn kho; phần dư rải vào các variant đầu để tổng khớp đúng tồn kho cha.
        $stockPer = intdiv($parentStock, $variantCount);
        $remainder = $parentStock % $variantCount;

        $variants = [];
        $index = 0;
        foreach ($colors as $colorId => $colorName) {
            foreach ($sizes as $sizeId => $sizeName) {
                $code = self::COLOR_CODES[$colorName] ?? strtoupper(substr($colorName, 0, 3));
                $variants[] = [
                    'id' => Uuid::randomHex(),
                    'parentId' => $product->getId(),
                    'productNumber' => sprintf('%s-%s-%s', $productNumber, $code, $sizeName),
                    'stock' => $stockPer + ($index < $remainder ? 1 : 0),
                    'isCloseout' => true,
                    'options' => [
                        ['id' => $colorId],
                        ['id' => $sizeId],
                    ],
                ];
                ++$index;
            }
        }

        // configuratorSettings quyết định trục nào hiện thành bộ chọn trên storefront.
        $settings = [];
        foreach (array_merge(array_keys($colors), array_keys($sizes)) as $optionId) {
            $settings[] = ['id' => Uuid::randomHex(), 'optionId' => $optionId];
        }

        $this->productRepository->upsert([[
            'id' => $product->getId(),
            'configuratorSettings' => $settings,
        ]], $context);

        $this->productRepository->create($variants, $context);

        $io->text(sprintf(
            '%s: %d màu × %d size = %d variant, tồn kho %d/variant%s (tổng %d = tồn kho cha).',
            $productNumber,
            count($colors),
            count($sizes),
            $variantCount,
            $stockPer,
            $remainder > 0 ? sprintf(', %d variant đầu +1', $remainder) : '',
            $parentStock
        ));

        return Command::SUCCESS;
    }

    /**
     * Sinh variant cho mọi sản phẩm cha chưa có variant.
     *
     * Sản phẩm thiếu trục màu hoặc size bị bỏ qua và báo lại, không làm dừng cả lượt chạy.
     */
    private function buildAll(Context $context, SymfonyStyle $io): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->addFilter(new EqualsFilter('childCount', 0));

        $products = $this->productRepository->search($criteria, $context);

        $done = 0;
        $skipped = [];
        foreach ($products as $product) {
            $number = $product->getProductNumber();
            if ($this->buildVariantsFor($context, $number, $io, true) === Command::SUCCESS) {
                ++$done;
            } else {
                $skipped[] = $number;
            }
        }

        $io->newLine();
        $io->text(sprintf('Đã sinh variant cho %d sản phẩm.', $done));
        if ($skipped !== []) {
            $io->warning(sprintf('Bỏ qua %d sản phẩm thiếu trục màu/size: %s', count($skipped), implode(', ', $skipped)));
        }
    }

    /**
     * Gán một giá trị màu vào sản phẩm, dạng "ANV-1001:Kem".
     *
     * Dùng khi sản phẩm chỉ có hoạ tiết mà chưa có màu nền nên không sinh được variant.
     */
    private function assignColor(Context $context, string $spec, SymfonyStyle $io): int
    {
        if (!str_contains($spec, ':')) {
            $io->error('Sai định dạng. Dùng --set-color=ANV-1001:Kem');

            return Command::FAILURE;
        }

        [$number, $colorName] = explode(':', $spec, 2);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $number));
        $product = $this->productRepository->search($criteria, $context)->first();
        if ($product === null) {
            $io->error(sprintf('Không tìm thấy sản phẩm %s.', $number));

            return Command::FAILURE;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $colorName));
        $criteria->addFilter(new EqualsFilter('group.name', 'Màu sắc'));
        $optionId = $this->propertyGroupOptionRepository->searchIds($criteria, $context)->firstId();
        if ($optionId === null) {
            $io->error(sprintf('Không tìm thấy màu "%s" trong nhóm Màu sắc.', $colorName));

            return Command::FAILURE;
        }

        $this->productRepository->upsert([[
            'id' => $product->getId(),
            'properties' => [['id' => $optionId]],
        ]], $context);

        $io->text(sprintf('%s: đã gán màu "%s".', $number, $colorName));

        return Command::SUCCESS;
    }

    private function enableCloseout(Context $context, SymfonyStyle $io): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isCloseout', false));
        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();

        if ($ids === []) {
            $io->text('Mọi sản phẩm đã bật is_closeout.');

            return;
        }

        $payload = array_map(static fn (string $id): array => ['id' => $id, 'isCloseout' => true], $ids);
        $this->productRepository->upsert($payload, $context);
        $io->text(sprintf('Đã bật is_closeout cho %d sản phẩm.', count($ids)));
    }

    private function findOptionIdByName(Context $context, string $name): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        return $this->propertyGroupOptionRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * @param array<string> $languageIds
     *
     * @return array<string, array{name: string, position: int}>
     */
    private function translate(array $languageIds, string $name, int $position): array
    {
        $translations = [];
        foreach ($languageIds as $languageId) {
            $translations[$languageId] = ['name' => $name, 'position' => $position];
        }

        return $translations;
    }
}
