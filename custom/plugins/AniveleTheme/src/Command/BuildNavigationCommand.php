<?php declare(strict_types=1);

namespace AniveleTheme\Command;

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
 * Dựng menu (megamenu) và footer cho storefront.
 *
 * Megamenu là tính năng có sẵn của core: flyout nhiều cột tự render khi
 * category có con và sales channel cho phép đủ độ sâu. Không cần override template.
 *
 * Footer đọc từ `footer_category_id` của sales channel — mặc định NULL nên
 * storefront chỉ hiện cột hotline cứng.
 *
 * Chạy lại nhiều lần được.
 */
#[AsCommand(name: 'anivele:theme:build-navigation', description: 'Dựng megamenu và footer')]
class BuildNavigationCommand extends Command
{
    private const FOOTER_ROOT = 'Footer Anivele';

    /**
     * Cấu trúc menu cấp 3 cho megamenu — mỗi danh mục cha có nhóm con.
     *
     * @var array<string, array<string>>
     */
    private const MENU_TREE = [
        'Đầm' => ['Đầm maxi', 'Đầm midi', 'Đầm công sở', 'Đầm dạ hội'],
        'Áo' => ['Áo sơ mi', 'Áo thun', 'Áo polo', 'Áo khoác'],
        'Chân váy' => ['Chân váy dài', 'Chân váy ngắn', 'Chân váy xếp ly'],
        'Bộ sưu tập' => ['Set công sở', 'Set dạo phố', 'Hàng mới về'],
    ];

    /**
     * Cột footer. Mỗi cột là một category cấp 2 dưới root footer.
     *
     * @var array<string, array<string>>
     */
    private const FOOTER_TREE = [
        'Về Anivele' => ['Câu chuyện thương hiệu', 'Hệ thống cửa hàng', 'Tuyển dụng'],
        'Hỗ trợ khách hàng' => ['Hướng dẫn chọn size', 'Chính sách đổi trả', 'Chính sách bảo mật', 'Câu hỏi thường gặp'],
        'Mua sắm' => ['Hướng dẫn đặt hàng', 'Phương thức thanh toán', 'Chính sách vận chuyển'],
    ];

    /**
     * Từ khoá trong tên sản phẩm → danh mục cấp 3 tương ứng.
     *
     * Danh mục con rỗng là ngõ cụt: khách bấm vào menu rồi thấy trang trắng.
     * Khi có hàng thật, gán danh mục trong Administration thay vì dựa vào đây.
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
        private readonly EntityRepository $mediaRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sales-channel', null, InputOption::VALUE_REQUIRED, 'ID sales channel', '019f9d8157c5724999d4b32d220a5b41');
        $this->addOption('menu-only', null, InputOption::VALUE_NONE, 'Chỉ dựng megamenu');
        $this->addOption('footer-only', null, InputOption::VALUE_NONE, 'Chỉ dựng footer');
        $this->addOption('with-images', null, InputOption::VALUE_NONE, 'Gán ảnh teaser cho megamenu (lấy ảnh bìa sản phẩm trong danh mục)');
        $this->addOption('set-image', null, InputOption::VALUE_REQUIRED, 'Gán ảnh cụ thể, dạng "Đầm:<media-id>"');
        $this->addOption('clear-images', null, InputOption::VALUE_NONE, 'Gỡ ảnh teaser khỏi megamenu');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();
        $salesChannelId = (string) $input->getOption('sales-channel');

        $io->title('Dựng menu và footer');

        $languageIds = $this->resolveLanguageIds($context);
        if ($languageIds === []) {
            $io->error('Không tìm thấy ngôn ngữ vi-VN.');

            return Command::FAILURE;
        }

        // Các tuỳ chọn ảnh chạy độc lập, không dựng lại cả cây.
        $setImage = $input->getOption('set-image');
        if ($setImage !== null) {
            $io->section('Gán ảnh megamenu');

            return $this->setCategoryImage($context, (string) $setImage, $io);
        }

        if ($input->getOption('clear-images')) {
            $io->section('Gỡ ảnh megamenu');
            $this->clearCategoryImages($context, $io);

            $io->success('Hoàn tất. Chạy cache:clear để thấy thay đổi.');

            return Command::SUCCESS;
        }

        $footerOnly = (bool) $input->getOption('footer-only');
        $menuOnly = (bool) $input->getOption('menu-only');

        if (!$footerOnly) {
            $io->section('Megamenu');
            $this->buildMegaMenu($context, $languageIds, $salesChannelId, $io);

            if ($input->getOption('with-images')) {
                $this->assignCategoryImages($context, $io);
            }
        }

        if (!$menuOnly) {
            $io->section('Footer');
            $this->buildFooter($context, $languageIds, $salesChannelId, $io);
        }

        $io->success('Hoàn tất. Chạy cache:clear để thấy thay đổi.');

        return Command::SUCCESS;
    }

    /**
     * Thêm danh mục cấp 3 dưới mỗi danh mục cha để flyout có nội dung,
     * đồng thời nâng navigation_category_depth lên 3.
     *
     * @param array<string> $languageIds
     */
    private function buildMegaMenu(Context $context, array $languageIds, string $salesChannelId, SymfonyStyle $io): void
    {
        $criteria = new Criteria([$salesChannelId]);
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->first();
        if ($salesChannel === null) {
            $io->error('Không tìm thấy sales channel.');

            return;
        }

        $created = 0;
        $skipped = 0;

        foreach (self::MENU_TREE as $parentName => $childNames) {
            $parentId = $this->findCategoryIdByName($context, $parentName);
            if ($parentId === null) {
                $io->warning(sprintf('Không tìm thấy danh mục "%s", bỏ qua.', $parentName));
                continue;
            }

            $payload = [];
            foreach ($childNames as $childName) {
                if ($this->findCategoryIdByName($context, $childName) !== null) {
                    ++$skipped;
                    continue;
                }

                $payload[] = [
                    'id' => Uuid::randomHex(),
                    'parentId' => $parentId,
                    'active' => true,
                    'visible' => true,
                    'type' => 'page',
                    'displayNestedProducts' => true,
                    'afterCategoryId' => null,
                    'translations' => $this->translateName($languageIds, $childName),
                ];
                ++$created;
            }

            if ($payload !== []) {
                $this->categoryRepository->create($payload, $context);
            }
        }

        // Không có cấp 3 thì flyout không có gì để xổ.
        $this->salesChannelRepository->update([[
            'id' => $salesChannelId,
            'navigationCategoryDepth' => 3,
        ]], $context);

        $io->text(sprintf('Đã tạo %d danh mục cấp 3 (bỏ qua %d đã có).', $created, $skipped));
        $io->text('Đã đặt navigation_category_depth = 3 để flyout xổ được.');

        $this->assignProductsToSubcategories($context, $io);
    }

    /**
     * Gán sản phẩm vào danh mục cấp 3 theo từ khoá trong tên.
     *
     * Giữ nguyên danh mục cấp 2 đang có — sản phẩm thuộc cả hai.
     */
    private function assignProductsToSubcategories(Context $context, SymfonyStyle $io): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->addAssociation('categories');
        $products = $this->productRepository->search($criteria, $context);

        $payload = [];
        $matched = 0;

        foreach ($products as $product) {
            $name = mb_strtolower((string) $product->getTranslation('name'));

            foreach (self::KEYWORD_MAP as $keyword => $categoryName) {
                if (!str_contains($name, $keyword)) {
                    continue;
                }

                $categoryId = $this->findCategoryIdByName($context, $categoryName);
                if ($categoryId === null) {
                    continue;
                }

                $payload[] = [
                    'id' => $product->getId(),
                    'categories' => [['id' => $categoryId]],
                ];
                ++$matched;
                break;
            }
        }

        if ($payload !== []) {
            $this->productRepository->update($payload, $context);
        }

        $io->text(sprintf('Đã gán %d sản phẩm vào danh mục cấp 3.', $matched));

        $unmatched = $products->count() - $matched;
        if ($unmatched > 0) {
            $io->warning(sprintf(
                '%d sản phẩm chưa khớp từ khoá nào — danh mục con tương ứng sẽ rỗng. Gán tay trong Administration.',
                $unmatched
            ));
        }
    }

    /**
     * Dựng cây footer riêng rồi trỏ footer_category_id của sales channel vào đó.
     *
     * @param array<string> $languageIds
     */
    private function buildFooter(Context $context, array $languageIds, string $salesChannelId, SymfonyStyle $io): void
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
            $io->text('Đã tạo cây footer.');
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
            'id' => $salesChannelId,
            'footerCategoryId' => $rootId,
        ]], $context);

        $io->text(sprintf('Đã tạo %d mục footer.', $created));
        $io->text('Đã trỏ footer_category_id của sales channel vào cây footer.');
    }

    /**
     * Gán ảnh teaser cho megamenu bằng ảnh bìa của một sản phẩm trong danh mục.
     *
     * Core hiện teaser khi `category.media` khác null: cột link co lại còn col-8,
     * cột ảnh chiếm col-4. Không cần override template.
     */
    private function assignCategoryImages(Context $context, SymfonyStyle $io): void
    {
        $payload = [];

        foreach (array_keys(self::MENU_TREE) as $categoryName) {
            $categoryId = $this->findCategoryIdByName($context, $categoryName);
            if ($categoryId === null) {
                continue;
            }

            $mediaId = $this->findCoverMediaIdInCategory($context, $categoryId);
            if ($mediaId === null) {
                $io->warning(sprintf('"%s" chưa có sản phẩm nào kèm ảnh bìa, bỏ qua.', $categoryName));
                continue;
            }

            $payload[] = ['id' => $categoryId, 'mediaId' => $mediaId];
            $io->text(sprintf('%s → ảnh bìa của sản phẩm trong danh mục.', $categoryName));
        }

        if ($payload !== []) {
            $this->categoryRepository->update($payload, $context);
        }

        $io->text(sprintf('Đã gán ảnh teaser cho %d danh mục.', count($payload)));
    }

    /**
     * Gán ảnh chỉ định cho một danh mục, dạng "Đầm:<media-id>".
     */
    private function setCategoryImage(Context $context, string $spec, SymfonyStyle $io): int
    {
        if (!str_contains($spec, ':')) {
            $io->error('Sai định dạng. Dùng --set-image="Đầm:019f9ddbec96710c8426c75360c5cf29"');

            return Command::FAILURE;
        }

        [$categoryName, $mediaId] = explode(':', $spec, 2);
        $categoryName = trim($categoryName);
        $mediaId = trim($mediaId);

        $categoryId = $this->findCategoryIdByName($context, $categoryName);
        if ($categoryId === null) {
            $io->error(sprintf('Không tìm thấy danh mục "%s".', $categoryName));

            return Command::FAILURE;
        }

        if ($this->mediaRepository->searchIds(new Criteria([$mediaId]), $context)->firstId() === null) {
            $io->error(sprintf('Không tìm thấy media id "%s".', $mediaId));

            return Command::FAILURE;
        }

        $this->categoryRepository->update([[
            'id' => $categoryId,
            'mediaId' => $mediaId,
        ]], $context);

        $io->text(sprintf('Đã gán ảnh cho "%s".', $categoryName));
        $io->success('Hoàn tất. Chạy cache:clear để thấy thay đổi.');

        return Command::SUCCESS;
    }

    private function clearCategoryImages(Context $context, SymfonyStyle $io): void
    {
        $payload = [];
        foreach (array_keys(self::MENU_TREE) as $categoryName) {
            $categoryId = $this->findCategoryIdByName($context, $categoryName);
            if ($categoryId !== null) {
                $payload[] = ['id' => $categoryId, 'mediaId' => null];
            }
        }

        if ($payload !== []) {
            $this->categoryRepository->update($payload, $context);
        }

        $io->text(sprintf('Đã gỡ ảnh khỏi %d danh mục.', count($payload)));
    }

    /**
     * Lấy media id từ ảnh bìa của sản phẩm cha đầu tiên trong danh mục.
     */
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

    private function findCategoryIdByName(Context $context, string $name): ?string
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
