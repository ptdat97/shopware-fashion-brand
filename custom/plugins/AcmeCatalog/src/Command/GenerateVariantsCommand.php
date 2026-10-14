<?php declare(strict_types=1);

namespace Acme\Catalog\Command;

use Acme\Catalog\CatalogException;
use Acme\Catalog\Variant\VariantGenerator;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bản dòng lệnh của {@see VariantGenerator}: đọc tham số, in kết quả.
 *
 * @internal
 */
#[AsCommand(
    name: 'acme:catalog:variants',
    description: 'Sinh biến thể Màu × Size cho sản phẩm từ thuộc tính mô tả đang gắn'
)]
class GenerateVariantsCommand extends Command
{
    public function __construct(private readonly VariantGenerator $generator)
    {
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
        $sku = $input->getOption('sku');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Sinh biến thể Màu × Size');

        if ($dryRun) {
            $io->note('Chế độ dry-run — không ghi gì vào database.');
        }

        try {
            $result = $this->generator->generate(Context::createCLIContext(), $sku, $dryRun);
        } catch (CatalogException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($result['generated'] === [] && $result['skipped'] === []) {
            $io->warning($sku !== null
                ? \sprintf('Không tìm thấy sản phẩm %s, hoặc sản phẩm đã có biến thể.', $sku)
                : 'Không có sản phẩm nào cần sinh biến thể.');

            return Command::SUCCESS;
        }

        $created = 0;
        foreach ($result['generated'] as $line) {
            $io->text(\sprintf(
                '%s — %d màu × %d size = <info>%d biến thể</info>',
                $line['productNumber'],
                $line['colours'],
                $line['sizes'],
                $line['variants']
            ));
            $created += $line['variants'];
        }

        foreach ($result['skipped'] as $line) {
            $io->warning(\sprintf(
                '%s — thiếu thuộc tính (%d màu, %d size)',
                $line['productNumber'],
                $line['colours'],
                $line['sizes']
            ));
        }

        $io->success(\sprintf(
            $dryRun ? 'Dự kiến sinh %d biến thể cho %d sản phẩm.' : 'Đã sinh %d biến thể cho %d sản phẩm.',
            $created,
            \count($result['generated'])
        ));

        if (!$dryRun && $created > 0) {
            $io->note('Chạy tiếp: bin/console dal:refresh:index && bin/console cache:clear');
        }

        return Command::SUCCESS;
    }
}
