<?php declare(strict_types=1);

namespace Anivele\DemoData\Command;

use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Defaults;
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

#[AsCommand(name: 'anivele:demo:seed', description: 'Tạo dữ liệu demo thời trang từ ảnh có sẵn')]
class SeedDemoCommand extends Command
{
    /** Ảnh đóng gói kèm plugin — không phụ thuộc plugin khác */
    private const MEDIA_DIR = __DIR__ . '/../Resources/media/demo';

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly EntityRepository $propertyGroupRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly FileSaver $fileSaver
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Xoá dữ liệu demo cũ trước khi tạo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $io->title('Tạo dữ liệu demo thời trang Anivele');

        // --- Thuế VAT 10% ---
        $taxId = $this->findTaxId($context, 10.0);
        if (!$taxId) {
            $io->error('Không tìm thấy thuế 10%. Hãy chạy Phase 0 (cấu hình thuế VN) trước.');
            return Command::FAILURE;
        }

        // --- Danh mục ---
        $io->section('1/5 Tạo danh mục');
        $rootId = $this->findRootCategoryId($context);
        if (!$rootId) {
            $io->error('Không tìm thấy danh mục gốc.');
            return Command::FAILURE;
        }
        $categories = $this->createCategories($context, $rootId, $io);

        // --- Thuộc tính (Màu / Size) ---
        $io->section('2/5 Tạo thuộc tính Màu & Size');
        [$colorMap, $sizeMap] = $this->createProperties($context, $io);

        // --- Ảnh ---
        $io->section('3/5 Nạp ảnh vào thư viện media');
        $folderId = $this->findProductMediaFolderId($context);
        $mediaMap = $this->importMedia($context, $folderId, $io);
        if ($mediaMap === []) {
            $io->error('Không nạp được ảnh nào từ ' . self::MEDIA_DIR);
            return Command::FAILURE;
        }

        // --- Sản phẩm ---
        $io->section('4/5 Tạo sản phẩm');
        $salesChannelId = $this->findStorefrontSalesChannelId($context);
        $created = $this->createProducts(
            $context, $taxId, $categories, $colorMap, $sizeMap, $mediaMap, $salesChannelId, $io
        );

        $io->section('5/5 Hoàn tất');
        $io->success(sprintf('Đã tạo %d sản phẩm với %d ảnh.', $created, count($mediaMap)));
        $io->note('Chạy tiếp: bin/console dal:refresh:index && bin/console cache:clear');

        return Command::SUCCESS;
    }

    private function findTaxId(Context $context, float $rate): ?string
    {
        $c = new Criteria();
        $c->addFilter(new EqualsFilter('taxRate', $rate));
        $c->setLimit(1);
        return $this->taxRepository->searchIds($c, $context)->firstId();
    }

    private function findRootCategoryId(Context $context): ?string
    {
        $c = new Criteria();
        $c->addFilter(new EqualsFilter('level', 1));
        $c->setLimit(1);
        return $this->categoryRepository->searchIds($c, $context)->firstId();
    }

    private function findProductMediaFolderId(Context $context): ?string
    {
        $c = new Criteria();
        $c->addFilter(new EqualsFilter('defaultFolder.entity', 'product'));
        $c->setLimit(1);
        return $this->mediaFolderRepository->searchIds($c, $context)->firstId();
    }

    private function findStorefrontSalesChannelId(Context $context): ?string
    {
        $c = new Criteria();
        $c->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $c->setLimit(1);
        return $this->salesChannelRepository->searchIds($c, $context)->firstId();
    }

    /** @return array<string,string> tên => id */
    private function createCategories(Context $context, string $rootId, SymfonyStyle $io): array
    {
        $names = ['Đầm', 'Áo', 'Chân váy', 'Bộ sưu tập'];
        $payload = [];
        $map = [];

        foreach ($names as $name) {
            $existing = new Criteria();
            $existing->addFilter(new EqualsFilter('name', $name));
            $existing->setLimit(1);
            $found = $this->categoryRepository->searchIds($existing, $context)->firstId();

            if ($found) {
                $map[$name] = $found;
                continue;
            }

            $id = Uuid::randomHex();
            $map[$name] = $id;
            $payload[] = [
                'id' => $id,
                'parentId' => $rootId,
                'name' => $name,
                'active' => true,
                'displayNestedProducts' => true,
                'afterCategoryId' => null,
                'type' => 'page',
                'productAssignmentType' => 'product',
            ];
        }

        if ($payload !== []) {
            $this->categoryRepository->create($payload, $context);
        }
        $io->text(sprintf('  Danh mục: %s', implode(', ', array_keys($map))));

        return $map;
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} */
    private function createProperties(Context $context, SymfonyStyle $io): array
    {
        $colors = [
            'Đen' => '#1a1a1a', 'Trắng' => '#ffffff', 'Kem' => '#f0e6d2',
            'Be' => '#d9c9b0', 'Nâu' => '#6b4a34', 'Xanh pastel' => '#b9d4d8',
            'Hoa nhiều màu' => '#c8a2c8',
        ];
        $sizes = ['S', 'M', 'L', 'XL'];

        $colorMap = $this->ensurePropertyGroup($context, 'Màu sắc', 'color', array_keys($colors), $colors);
        $sizeMap = $this->ensurePropertyGroup($context, 'Kích cỡ', 'text', $sizes, []);

        $io->text(sprintf('  Màu: %d giá trị | Size: %d giá trị', count($colorMap), count($sizeMap)));

        return [$colorMap, $sizeMap];
    }

    /**
     * @param string[] $options
     * @param array<string,string> $colorCodes
     * @return array<string,string>
     */
    private function ensurePropertyGroup(
        Context $context,
        string $groupName,
        string $displayType,
        array $options,
        array $colorCodes
    ): array {
        $c = new Criteria();
        $c->addFilter(new EqualsFilter('name', $groupName));
        $c->addAssociation('options');
        $c->setLimit(1);
        $existing = $this->propertyGroupRepository->search($c, $context)->first();

        if ($existing !== null) {
            $map = [];
            foreach ($existing->getOptions() as $opt) {
                $map[$opt->getName()] = $opt->getId();
            }
            return $map;
        }

        $groupId = Uuid::randomHex();
        $map = [];
        $optionPayload = [];

        foreach ($options as $i => $name) {
            $optId = Uuid::randomHex();
            $map[$name] = $optId;
            $row = ['id' => $optId, 'name' => $name, 'position' => $i + 1];
            if (isset($colorCodes[$name])) {
                $row['colorHexCode'] = $colorCodes[$name];
            }
            $optionPayload[] = $row;
        }

        $this->propertyGroupRepository->create([[
            'id' => $groupId,
            'name' => $groupName,
            'displayType' => $displayType,
            'sortingType' => 'position',
            'filterable' => true,
            'options' => $optionPayload,
        ]], $context);

        return $map;
    }

    /** @return array<string,string> filename => mediaId */
    private function importMedia(Context $context, ?string $folderId, SymfonyStyle $io): array
    {
        if (!is_dir(self::MEDIA_DIR)) {
            $io->error('Không tìm thấy thư mục ảnh: ' . self::MEDIA_DIR);
            return [];
        }

        $files = array_values(array_filter(
            scandir(self::MEDIA_DIR) ?: [],
            static fn ($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f) === 1
        ));

        $map = [];
        $progress = $io->createProgressBar(count($files));
        $progress->start();

        foreach ($files as $file) {
            $path = self::MEDIA_DIR . '/' . $file;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $slug = $this->slugify(pathinfo($file, PATHINFO_FILENAME));

            // Bỏ qua nếu đã tồn tại
            $c = new Criteria();
            $c->addFilter(new EqualsFilter('fileName', $slug));
            $c->setLimit(1);
            $found = $this->mediaRepository->searchIds($c, $context)->firstId();
            if ($found) {
                $map[$file] = $found;
                $progress->advance();
                continue;
            }

            $mediaId = Uuid::randomHex();
            $this->mediaRepository->create([[
                'id' => $mediaId,
                'mediaFolderId' => $folderId,
                'private' => false,
            ]], $context);

            $mediaFile = new MediaFile(
                $path,
                mime_content_type($path) ?: 'image/jpeg',
                $ext,
                (int) filesize($path)
            );

            try {
                $this->fileSaver->persistFileToMedia($mediaFile, $slug, $mediaId, $context);
                $map[$file] = $mediaId;
            } catch (\Throwable $e) {
                $io->warning(sprintf('Lỗi nạp %s: %s', $file, $e->getMessage()));
                $this->mediaRepository->delete([['id' => $mediaId]], $context);
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);
        $io->text(sprintf('  Đã nạp %d ảnh', count($map)));

        return $map;
    }

    private function slugify(string $text): string
    {
        $text = \transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text) ?: strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        return trim($text, '-');
    }

    /**
     * @param array<string,string> $categories
     * @param array<string,string> $colorMap
     * @param array<string,string> $sizeMap
     * @param array<string,string> $mediaMap
     */
    private function createProducts(
        Context $context,
        string $taxId,
        array $categories,
        array $colorMap,
        array $sizeMap,
        array $mediaMap,
        ?string $salesChannelId,
        SymfonyStyle $io
    ): int {
        $definitions = $this->productDefinitions();
        $payload = [];
        $number = 1000;

        foreach ($definitions as $def) {
            // Chỉ lấy ảnh thực sự tồn tại
            $images = array_values(array_filter(
                $def['images'],
                static fn ($f) => isset($mediaMap[$f])
            ));

            if ($images === []) {
                $io->warning(sprintf('Bỏ qua "%s" — không có ảnh khớp', $def['name']));
                continue;
            }

            $productId = Uuid::randomHex();
            $coverMediaId = Uuid::randomHex();

            $mediaPayload = [];
            foreach ($images as $i => $file) {
                $mediaPayload[] = [
                    'id' => $i === 0 ? $coverMediaId : Uuid::randomHex(),
                    'mediaId' => $mediaMap[$file],
                    'position' => $i + 1,
                ];
            }

            $properties = [];
            foreach ($def['colors'] as $c) {
                if (isset($colorMap[$c])) {
                    $properties[] = ['id' => $colorMap[$c]];
                }
            }
            foreach (array_keys($sizeMap) as $s) {
                $properties[] = ['id' => $sizeMap[$s]];
            }

            $gross = $def['price'];
            $net = round($gross / 1.10);

            $row = [
                'id' => $productId,
                'productNumber' => 'ANV-' . (++$number),
                'name' => $def['name'],
                'description' => $def['description'],
                'stock' => random_int(15, 80),
                'taxId' => $taxId,
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $gross,
                    'net' => $net,
                    'linked' => true,
                ]],
                'active' => true,
                'media' => $mediaPayload,
                'coverId' => $coverMediaId,
                'properties' => $properties,
                'categories' => isset($categories[$def['category']])
                    ? [['id' => $categories[$def['category']]]]
                    : [],
                'customFields' => [
                    'anivele_material' => $def['material'],
                    'anivele_model_size' => $def['modelSize'],
                ],
            ];

            if ($salesChannelId !== null) {
                $row['visibilities'] = [[
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 30,
                ]];
            }

            $payload[] = $row;
        }

        if ($payload === []) {
            return 0;
        }

        foreach (array_chunk($payload, 5) as $chunk) {
            $this->productRepository->create($chunk, $context);
        }

        return count($payload);
    }

    /** @return array<int,array<string,mixed>> */
    private function productDefinitions(): array
    {
        return [
            [
                'name' => 'Đầm maxi hoa nhí dáng xoè',
                'category' => 'Đầm',
                'price' => 1_250_000,
                'colors' => ['Hoa nhiều màu'],
                'material' => 'Voan lụa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['DTT_8954.jpg', 'DTT_9009.jpg', 'DTT_9123.jpg', 'DTT_9150.jpg'],
                'description' => '<p>Đầm maxi hoạ tiết hoa rực rỡ, thân trên chun co giãn ôm nhẹ, chân váy xoè nhiều tầng tạo độ bay bổng. Chất voan lụa mỏng nhẹ, thoáng mát — phù hợp khí hậu nắng nóng và các chuyến đi biển, dạo phố cuối tuần.</p><ul><li>Dáng maxi dài qua mắt cá</li><li>Thân trên chun co giãn, dây hai quai điều chỉnh được</li><li>Chất liệu voan lụa mềm, không bí</li></ul>',
            ],
            [
                'name' => 'Áo polo dệt kim tay ngắn',
                'category' => 'Áo',
                'price' => 690_000,
                'colors' => ['Nâu'],
                'material' => 'Len dệt kim',
                'modelSize' => 'Người mẫu cao 1m70, mặc size M',
                'images' => ['VNQ00498 copy.jpg', 'VNQ00869 copy.jpg', 'VNQ00887 copy.jpg', 'VNQ00905 copy.jpg'],
                'description' => '<p>Áo polo dệt kim tay ngắn với cổ bẻ và hàng cúc kim loại nhỏ, phom vừa vặn tôn dáng. Sắc nâu trầm dễ phối cùng quần âu hoặc chân váy dài, mang hơi hướng cổ điển nhưng vẫn hiện đại.</p><ul><li>Chất len dệt kim mịn, co giãn nhẹ</li><li>Cổ bẻ, cúc trang trí</li><li>Phom regular fit</li></ul>',
            ],
            [
                'name' => 'Đầm midi cổ thuyền xếp ly',
                'category' => 'Đầm',
                'price' => 1_450_000,
                'colors' => ['Kem'],
                'material' => 'Lụa tơ',
                'modelSize' => 'Người mẫu cao 1m70, mặc size S',
                'images' => ['_DSC6767.jpg', '_DSC6793.jpg', '_DSC6826.jpg', '_DSC6834.jpg'],
                'description' => '<p>Đầm midi tay bồng, cổ cao thanh lịch, eo chiết tạo khối kết hợp chân váy xoè mềm mại. Tông kem sáng nhẹ nhàng, phù hợp dự tiệc nhẹ, đi làm hoặc chụp ảnh.</p><ul><li>Tay bồng dài, bo cổ tay</li><li>Eo chiết ly nổi khối</li><li>Chất lụa tơ rũ, giữ phom tốt</li></ul>',
            ],
            [
                'name' => 'Đầm đen cổ chữ V dáng xoè',
                'category' => 'Đầm',
                'price' => 1_390_000,
                'colors' => ['Đen'],
                'material' => 'Tuyết mưa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['IMG_5804.jpeg', 'IMG_5805.jpeg', 'IMG_5806.jpeg', 'IMG_5807.jpeg'],
                'description' => '<p>Đầm đen cổ chữ V với chi tiết gấp nếp ở vai tạo điểm nhấn, chân váy xoè nhẹ đến bắp chân. Món đồ cơ bản không bao giờ lỗi mốt, dễ mặc từ đi làm đến dự sự kiện buổi tối.</p><ul><li>Cổ chữ V, vai gấp nếp</li><li>Chất tuyết mưa dày dặn, ít nhăn</li><li>Có lót trong</li></ul>',
            ],
            [
                'name' => 'Set áo sơ mi & chân váy tông kem',
                'category' => 'Bộ sưu tập',
                'price' => 1_690_000,
                'colors' => ['Kem', 'Be'],
                'material' => 'Đũi lụa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['VNQ05143 copy.jpg', 'VNQ05152 copy.jpg', 'VNQ05166 copy.jpg', 'VNQ05184 copy.jpg'],
                'description' => '<p>Set hai món gồm áo sơ mi cộc tay phom rộng và chân váy dài cùng tông. Chất đũi lụa thoáng mát, thấm hút tốt — lựa chọn lý tưởng cho ngày hè. Có thể mặc rời từng món để phối đa dạng.</p><ul><li>Áo sơ mi cổ đức, phom rộng thoải mái</li><li>Chân váy dài có dây rút eo</li><li>Chất đũi lụa mát, ít nhăn</li></ul>',
            ],
            [
                'name' => 'Đầm trắng sát nách cúc bọc',
                'category' => 'Đầm',
                'price' => 1_290_000,
                'colors' => ['Trắng'],
                'material' => 'Tweed',
                'modelSize' => 'Người mẫu cao 1m70, mặc size S',
                'images' => ['VNQ06518 copy.jpg', 'VNQ06526 copy.jpg', 'VNQ06565 copy.jpg'],
                'description' => '<p>Đầm trắng sát nách phom ôm, hàng cúc bọc chạy dọc thân trước và xẻ tà nhẹ. Chất tweed dệt nổi tinh tế mang nét thanh lịch cổ điển, tôn đường cong tự nhiên.</p><ul><li>Phom ôm body, xẻ tà trước</li><li>Cúc bọc cùng màu</li><li>Chất tweed dệt nổi, có lót</li></ul>',
            ],
            [
                'name' => 'Đầm đen tay loe dáng ôm',
                'category' => 'Đầm',
                'price' => 1_490_000,
                'colors' => ['Đen'],
                'material' => 'Tuyết mưa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size M',
                'images' => ['VNQ06903 copy.jpg', 'VNQ06910 copy.jpg', 'VNQ06955 copy.jpg', 'VNQ06971 copy.jpg'],
                'description' => '<p>Đầm đen dáng ôm với tay loe nhẹ và cổ chữ V sâu vừa phải, đi kèm đai eo bản nhỏ. Thiết kế tôn dáng, thích hợp cho những buổi tiệc tối hoặc sự kiện trang trọng.</p><ul><li>Tay loe, cổ chữ V</li><li>Đai eo rời tạo điểm nhấn</li><li>Chất tuyết mưa co giãn nhẹ</li></ul>',
            ],
            [
                'name' => 'Áo thun kẻ sọc phối chân váy',
                'category' => 'Áo',
                'price' => 590_000,
                'colors' => ['Xanh pastel', 'Trắng'],
                'material' => 'Cotton pha',
                'modelSize' => 'Người mẫu cao 1m70, mặc size M',
                'images' => ['VNQ09889 copy.jpg', 'VNQ09903 copy.jpg', 'VNQ09918 copy.jpg', 'VNQ09943 copy.jpg'],
                'description' => '<p>Áo thun kẻ sọc ngang phối cổ polo, phom rộng trẻ trung năng động. Chất cotton pha thoáng khí, thấm hút mồ hôi tốt — thoải mái cho cả ngày dài.</p><ul><li>Hoạ tiết kẻ sọc ngang cổ điển</li><li>Cổ polo, phom oversize nhẹ</li><li>Cotton pha thoáng mát</li></ul>',
            ],
            [
                'name' => 'Đầm lụa xanh pastel dáng ôm',
                'category' => 'Đầm',
                'price' => 1_590_000,
                'colors' => ['Xanh pastel'],
                'material' => 'Lụa tơ',
                'modelSize' => 'Người mẫu cao 1m70, mặc size S',
                'images' => ['_1772166247_DSC7352_1.jpg', '_1772166249_DSC7277_1.jpg', '_1772166251_DSC7304_1.jpg', '_1772166255_DSC7333_1.jpg'],
                'description' => '<p>Đầm lụa tông xanh pastel dịu mắt, cổ thuyền cách điệu với phần vai đắp chéo mềm mại. Đai eo bản nhỏ tôn vòng hai, chân váy ôm nhẹ tạo dáng thanh thoát.</p><ul><li>Cổ thuyền, vai đắp chéo</li><li>Đai eo bản nhỏ có khoá</li><li>Chất lụa tơ rũ mềm</li></ul>',
            ],
            [
                'name' => 'Set áo lụa & chân váy xếp ly',
                'category' => 'Bộ sưu tập',
                'price' => 1_790_000,
                'colors' => ['Kem', 'Be'],
                'material' => 'Lụa tơ',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['_1773800496VNQ06585 copy_1.jpg', '_1773800496VNQ06609 copy_1.jpg', '_1773800497VNQ06582 copy_1.jpg', '_1773800497VNQ06590 copy_1.jpg'],
                'description' => '<p>Set hai món thanh lịch: áo lụa sát nách cổ thuyền phối chân váy xếp ly dài. Bảng màu trung tính dễ phối, phù hợp đi tiệc trà, sự kiện ban ngày hoặc chụp ảnh.</p><ul><li>Áo lụa cổ thuyền, tay cánh tiên</li><li>Chân váy xếp ly dài qua gối</li><li>Có thể mặc rời từng món</li></ul>',
            ],
            [
                'name' => 'Chân váy dài xếp ly tông be',
                'category' => 'Chân váy',
                'price' => 790_000,
                'colors' => ['Be', 'Kem'],
                'material' => 'Đũi lụa',
                'modelSize' => 'Người mẫu cao 1m68, mặc size S',
                'images' => ['_1776485633VNQ00471 copy_1.jpg', '_1776485634VNQ00473 copy_1.jpg', '_1776485636VNQ00480 copy_1.jpg', '_1776485638VNQ00536 copy_1.jpg'],
                'description' => '<p>Chân váy dài xếp ly tông be trung tính, cạp chun co giãn thoải mái. Dễ phối cùng áo thun, sơ mi hay áo len — món đồ nền tảng cho tủ quần áo hằng ngày.</p><ul><li>Xếp ly đều, tạo độ rơi đẹp</li><li>Cạp chun co giãn</li><li>Chất đũi lụa thoáng mát</li></ul>',
            ],
            [
                'name' => 'Đầm dạ hội dáng dài tay bồng',
                'category' => 'Đầm',
                'price' => 1_890_000,
                'colors' => ['Kem'],
                'material' => 'Lụa tơ',
                'modelSize' => 'Người mẫu cao 1m70, mặc size S',
                'images' => ['_DSC6841.jpg', '_DSC6863.jpg', '_DSC7359.jpg', '_dsc7406-1772166267.jpg'],
                'description' => '<p>Đầm dài tay bồng với phần eo chiết tinh tế, chân váy xoè rộng tạo hiệu ứng thướt tha khi di chuyển. Lựa chọn nổi bật cho tiệc cưới, sự kiện trang trọng.</p><ul><li>Tay bồng dài</li><li>Eo chiết, chân váy xoè rộng</li><li>Chất lụa tơ cao cấp, có lót</li></ul>',
            ],
        ];
    }
}
