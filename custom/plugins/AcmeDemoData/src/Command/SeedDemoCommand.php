<?php declare(strict_types=1);

namespace Acme\DemoData\Command;

use Acme\DemoData\Service\DemoDataException;
use Acme\DemoData\Service\DemoDataImporter;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bản dòng lệnh của nút "Nhập dữ liệu demo" trong Administration.
 * Cùng chạy qua {@see DemoDataImporter} nên hai đường cho ra kết quả giống nhau.
 */
#[AsCommand(name: 'acme:demo:seed', description: 'Tạo dữ liệu demo thời trang từ ảnh có sẵn')]
class SeedDemoCommand extends Command
{
    public function __construct(private readonly DemoDataImporter $importer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $io->title('Tạo dữ liệu demo thời trang Acme');

        $status = $this->importer->getStatus($context);
        if (!$status['ready']) {
            $io->error((string) $status['blocker']);

            return Command::FAILURE;
        }

        $progress = $io->createProgressBar($status['mediaTotal']);
        $progress->start();

        $offset = 0;
        $warnings = [];
        $products = 0;

        try {
            do {
                $result = $this->importer->import($context, $offset);
                $offset = $result['offset'];
                $products = $result['products'];
                $warnings = [...$warnings, ...$result['warnings']];
                $progress->setProgress(min($offset, $status['mediaTotal']));
            } while (!$result['done']);
        } catch (DemoDataException $e) {
            $io->newLine(2);
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $progress->finish();
        $io->newLine(2);

        foreach ($warnings as $warning) {
            $io->warning($warning);
        }

        $io->success(sprintf('Đã tạo %d sản phẩm với %d ảnh.', $products, $status['mediaTotal']));

        return Command::SUCCESS;
    }
}
