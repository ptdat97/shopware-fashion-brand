<?php declare(strict_types=1);

namespace Acme\Brand\Command;

use Acme\Brand\Seo\BrandSeoUrlUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sinh lại toàn bộ URL thương hiệu — sau khi cài/cập nhật plugin, hoặc sau khi sửa
 * template SEO trong Administration.
 *
 * `dal:refresh:index` của core KHÔNG phủ route này.
 *
 * @internal
 */
#[AsCommand(name: 'acme:brand:seo-urls', description: 'Sinh lại URL /brands/... cho mọi thương hiệu')]
class RefreshBrandSeoUrlsCommand extends Command
{
    public function __construct(private readonly BrandSeoUrlUpdater $updater)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = $this->updater->allBrandManufacturerIds();
        $this->updater->update($ids);

        (new SymfonyStyle($input, $output))->success(sprintf('Đã sinh lại URL cho %d thương hiệu.', \count($ids)));

        return Command::SUCCESS;
    }
}
