<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Command;

use Shopware\Core\Content\ProductExport\Service\ProductExporterInterface;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProductExportGenerateCommand extends Command
{
    /** @var SalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var ProductExporterInterface */
    private $productExportService;

    public function __construct(
        SalesChannelContextFactory $salesChannelContextFactory,
        ProductExporterInterface $productExportService
    ) {
        parent::__construct();

        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->productExportService = $productExportService;
    }

    protected function configure(): void
    {
        $this
            ->setName('product-export:generate')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignore cache and force generation')
            ->addOption('include-inactive', 'inactive', InputOption::VALUE_NONE, 'Include inactive exports')
            ->addArgument('sales-channel-id', InputArgument::REQUIRED, 'Sales channel to generate exports for')
            ->addArgument('product-export-id', InputArgument::OPTIONAL, 'Generate specific export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $productExportId = $input->getArgument('product-export-id');
        $salesChannelId = $input->getArgument('sales-channel-id');
        $forceGeneration = $input->getOption('force');
        $includeInactive = $input->getOption('include-inactive');

        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannelId);

        $this->productExportService->generate(
            $salesChannelContext,
            new ExportBehavior($forceGeneration, $includeInactive),
            $productExportId
        );
    }
}
