<?php declare(strict_types=1);

namespace AniveleTheme\Command;

use Shopware\Core\Content\Category\CategoryEntity;
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
 * Dựng trang chủ bằng CMS block có sẵn trong core.
 *
 * Không tự viết block mới: core 6.7 đã có text-hero, product-slider, image-text,
 * text-three-column — đủ cho một trang chủ thời trang.
 *
 * Chạy lại nhiều lần được: xoá section cũ của trang rồi dựng lại.
 */
#[AsCommand(name: 'anivele:theme:build-homepage', description: 'Dựng trang chủ từ CMS block của core')]
class BuildHomepageCommand extends Command
{
    private const PAGE_NAME = 'Trang chủ Anivele';

    public function __construct(
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $productRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('assign', null, InputOption::VALUE_NONE, 'Gán trang vừa dựng làm trang chủ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $io->title('Dựng trang chủ Anivele');

        $languageIds = $this->resolveLanguageIds($context);
        if ($languageIds === []) {
            $io->error('Không tìm thấy ngôn ngữ vi-VN.');

            return Command::FAILURE;
        }

        // Xoá trang cũ để lệnh chạy lại được mà không nhân bản block.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::PAGE_NAME));
        $existingId = $this->cmsPageRepository->searchIds($criteria, $context)->firstId();
        if ($existingId !== null) {
            // DAL chặn xoá khi còn category trỏ tới trang. Gỡ liên kết trước.
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('cmsPageId', $existingId));
            $linkedIds = $this->categoryRepository->searchIds($criteria, $context)->getIds();
            if ($linkedIds !== []) {
                $this->categoryRepository->update(
                    array_map(static fn (string $id): array => ['id' => $id, 'cmsPageId' => null], $linkedIds),
                    $context
                );
            }

            $this->cmsPageRepository->delete([['id' => $existingId]], $context);
            $io->text('Đã xoá trang chủ cũ để dựng lại.');
        }

        $pageId = Uuid::randomHex();
        $this->cmsPageRepository->create([[
            'id' => $pageId,
            'type' => 'landingpage',
            'translations' => $this->translateName($languageIds, self::PAGE_NAME),
            'sections' => [$this->buildSection($languageIds, $context)],
        ]], $context);

        $io->text('Đã dựng trang với 5 block: hero, sản phẩm nổi bật, câu chuyện, hàng mới, cam kết.');

        if ($input->getOption('assign')) {
            $this->assignToHomepage($context, $pageId, $io);
        }

        $io->success('Hoàn tất.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string> $languageIds
     *
     * @return array<string, mixed>
     */
    private function buildSection(array $languageIds, Context $context): array
    {
        // Chỉ lấy sản phẩm cha: slider hiện mẫu, không hiện từng biến thể.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->setLimit(10);
        $ids = array_values($this->productRepository->searchIds($criteria, $context)->getIds());

        // Hai slider dùng hai nửa khác nhau để trang chủ không lặp lại sản phẩm.
        $featured = \array_slice($ids, 0, 6);
        $newArrivals = \array_slice($ids, 6) ?: $featured;

        return [
            'id' => Uuid::randomHex(),
            'type' => 'default',
            'position' => 0,
            'blocks' => [
                $this->heroBlock($languageIds, 0),
                $this->productSliderBlock($languageIds, 1, 'Sản phẩm nổi bật', $featured),
                $this->storyBlock($languageIds, 2),
                $this->productSliderBlock($languageIds, 3, 'Hàng mới về', $newArrivals),
                $this->promiseBlock($languageIds, 4),
            ],
        ];
    }

    /**
     * @param array<string> $languageIds
     *
     * @return array<string, mixed>
     */
    private function heroBlock(array $languageIds, int $position): array
    {
        return [
            'id' => Uuid::randomHex(),
            'type' => 'text-hero',
            'position' => $position,
            'sectionPosition' => 'main',
            'marginTop' => '40px',
            'marginBottom' => '40px',
            'slots' => [[
                'id' => Uuid::randomHex(),
                'type' => 'text',
                'slot' => 'content',
                'translations' => $this->translateConfig($languageIds, [
                    'content' => [
                        'source' => 'static',
                        'value' => '<h1 style="text-align:center">Anivele</h1>'
                            . '<p style="text-align:center">Thời trang nữ tối giản, tôn dáng người Việt.</p>',
                    ],
                    'verticalAlign' => ['source' => 'static', 'value' => 'center'],
                ]),
            ]],
        ];
    }

    /**
     * @param array<string> $languageIds
     * @param array<string> $productIds
     *
     * @return array<string, mixed>
     */
    private function productSliderBlock(array $languageIds, int $position, string $title, array $productIds): array
    {
        return [
            'id' => Uuid::randomHex(),
            'type' => 'product-slider',
            'position' => $position,
            'sectionPosition' => 'main',
            'marginTop' => '20px',
            'marginBottom' => '20px',
            'translations' => $this->translateName($languageIds, $title),
            'slots' => [[
                'id' => Uuid::randomHex(),
                'type' => 'product-slider',
                'slot' => 'productSlider',
                'translations' => $this->translateConfig($languageIds, [
                    'title' => ['source' => 'static', 'value' => $title],
                    'products' => ['source' => 'static', 'value' => $productIds],
                    'productStreamSorting' => ['source' => 'static', 'value' => 'name:ASC'],
                    'productStreamLimit' => ['source' => 'static', 'value' => 10],
                    'boxLayout' => ['source' => 'static', 'value' => 'standard'],
                    'displayMode' => ['source' => 'static', 'value' => 'cover'],
                    'navigation' => ['source' => 'static', 'value' => true],
                    'rotate' => ['source' => 'static', 'value' => false],
                    'border' => ['source' => 'static', 'value' => false],
                    'elMinWidth' => ['source' => 'static', 'value' => '300px'],
                    'verticalAlign' => ['source' => 'static', 'value' => null],
                ]),
            ]],
        ];
    }

    /**
     * @param array<string> $languageIds
     *
     * @return array<string, mixed>
     */
    private function storyBlock(array $languageIds, int $position): array
    {
        return [
            'id' => Uuid::randomHex(),
            'type' => 'text',
            'position' => $position,
            'sectionPosition' => 'main',
            'marginTop' => '40px',
            'marginBottom' => '40px',
            'slots' => [[
                'id' => Uuid::randomHex(),
                'type' => 'text',
                'slot' => 'content',
                'translations' => $this->translateConfig($languageIds, [
                    'content' => [
                        'source' => 'static',
                        'value' => '<h2 style="text-align:center">Câu chuyện thương hiệu</h2>'
                            . '<p style="text-align:center">Anivele chọn chất liệu mềm, form dáng tối giản '
                            . 'và bảng màu trung tính — những món đồ mặc được nhiều lần, nhiều dịp, '
                            . 'thay vì chạy theo mùa.</p>',
                    ],
                    'verticalAlign' => ['source' => 'static', 'value' => 'center'],
                ]),
            ]],
        ];
    }

    /**
     * Ba cam kết — rào cản tâm lý lớn nhất khi mua quần áo online tại VN.
     *
     * @param array<string> $languageIds
     *
     * @return array<string, mixed>
     */
    private function promiseBlock(array $languageIds, int $position): array
    {
        $columns = [
            'left' => ['Đổi trả 7 ngày', 'Không vừa size đổi miễn phí trong 7 ngày.'],
            'center' => ['Thanh toán khi nhận', 'Kiểm tra hàng rồi mới trả tiền (COD).'],
            'right' => ['Giao toàn quốc', 'Giao tới 34 tỉnh thành, nội thành 1–2 ngày.'],
        ];

        $slots = [];
        foreach ($columns as $slot => [$heading, $body]) {
            $slots[] = [
                'id' => Uuid::randomHex(),
                'type' => 'text',
                'slot' => $slot,
                'translations' => $this->translateConfig($languageIds, [
                    'content' => [
                        'source' => 'static',
                        'value' => sprintf(
                            '<h3 style="text-align:center">%s</h3><p style="text-align:center">%s</p>',
                            $heading,
                            $body
                        ),
                    ],
                    'verticalAlign' => ['source' => 'static', 'value' => 'center'],
                ]),
            ];
        }

        return [
            'id' => Uuid::randomHex(),
            'type' => 'text-three-column',
            'position' => $position,
            'sectionPosition' => 'main',
            'marginTop' => '40px',
            'marginBottom' => '60px',
            'slots' => $slots,
        ];
    }

    private function assignToHomepage(Context $context, string $pageId, SymfonyStyle $io): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $root = $this->categoryRepository->search($criteria, $context)->first();

        if (!$root instanceof CategoryEntity) {
            $io->warning('Không tìm thấy category gốc, bỏ qua bước gán.');

            return;
        }

        $this->categoryRepository->update([[
            'id' => $root->getId(),
            'cmsPageId' => $pageId,
        ]], $context);

        $io->text('Đã gán làm trang chủ.');
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

    /**
     * @param array<string>        $languageIds
     * @param array<string, mixed> $config
     *
     * @return array<string, array{config: array<string, mixed>}>
     */
    private function translateConfig(array $languageIds, array $config): array
    {
        $translations = [];
        foreach ($languageIds as $languageId) {
            $translations[$languageId] = ['config' => $config];
        }

        return $translations;
    }
}
