<?php
 
namespace Mjsi\Distribution\Console;
 
use Symfony\Component\Console\Command\Command;
use Magento\Framework\App\State;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\ImportExport\Model\ImportFactory;
use Magento\ImportExport\Model\Import\Source\CsvFactory;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Symfony\Component\Console\Helper\ProgressBar;
 
/**
 * Command to import products.
 */
class Sync extends Command
{

    /**
     * @var State $state
     */
    private $state;
 
    /**
     * @var Import $importFactory
     */
    protected $importFactory;
 
    /**
     * @var CsvFactory
     */
    private $csvSourceFactory;
 
    /**
     * @var ReadFactory
     */
    private $readFactory;
 
    /**
     * Constructor
     *
     * @param State $state  A Magento app State instance
     * @param ImportFactory $importFactory Factory to create entiry importer
     * @param CsvFactory $csvSourceFactory Factory to read CSV files
     * @param ReadFactory $readFactory Factory to read files from filesystem
     *
     * @return void
     */
    public function __construct(
        State $state,
        ImportFactory $importFactory,
        CsvFactory $csvSourceFactory,
        ReadFactory $readFactory
    ) {
        $this->state = $state;
        $this->importFactory = $importFactory;
        $this->csvSourceFactory = $csvSourceFactory;
        $this->readFactory = $readFactory;
        parent::__construct();
    }
 
    /**
     * Configures arguments and display options for this command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('distribution:sync');
        $this->setDescription('Sync Distribution List to Product Catalog');
        $this->addArgument('line', InputArgument::OPTIONAL, 'Line');
        parent::configure();
    }
 
    /**
     * Executes the command to add products to the database.
     *
     * @param InputInterface  $input  An input instance
     * @param OutputInterface $output An output instance
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $objectManager      = \Magento\Framework\App\ObjectManager::getInstance();
        $directory          = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        $product            = $objectManager->create('\Magento\Catalog\Model\Product');
        $productRepository  = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
        
        $root               = $directory->getRoot();

        $writer = new \Zend\Log\Writer\Stream($root . '/var/log/distribution.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Intentionally left empty.
        }

        $start = time();
        $output->write("Sync started at ");
        $output->writeln(date('h:i:s A'));
        $dmPath = $directory->getPath('var').'/dm/';


        $files = scandir($dmPath, 1);

        array_pop($files);
        array_pop($files);

        if (count($files)) {
            $import_path = $dmPath.$files[0];
            $import_file = pathinfo($import_path);

            $import = $this->importFactory->create();
            $import->setData(
                array(
                    'entity' => 'catalog_product',
                    'behavior' => 'append',
                    // 'validation_strategy' => 'validation-stop-on-errors',
                    'validation_strategy' => 'validation-skip-errors',
                )
            );
     
            $read_file = $this->readFactory->create($import_file['dirname']);
            $csvSource = $this->csvSourceFactory->create(
                array(
                    'file' => $import_file['basename'],
                    'directory' => $read_file,
                )
            );


            $startValidate = time();
            $output->write('<comment>'.date('h:i:sA').'</comment> <info>'.$files[0].'</info> validating...');

            $validate = $import->validateSource($csvSource);
            
            if (!$validate) {
                $message = $import->getOperationResultMessages($import->getErrorAggregator());
                $output->writeln("ERROR");
                if (count($message)>1) {
                    $output->writeln($message[1]);
                } 
                // print_r($import->getLogComment());
                $output->writeln('<error>Unable to validate the CSV.</error>');
            }
            $filesize = filesize($import_path); // bytes
            $filesize = round($filesize / 1024 / 1024, 1);

            $output->writeln('<info>DONE</info> '.date('H:i:s', (time()-$startValidate)).' ('.$filesize.'mb)');

            $startImport = time();
            $output->write('<comment>'.date('h:i:sA').'</comment> <info>'.$files[0].'</info> importing....');
            unlink($import_path);
            
            try {
                $result = $import->importSource();
                if ($result) {
                    $output->writeln('<info>DONE</info> '.date('H:i:s', (time()-$startImport)));
                    $import->invalidateIndex();
                }
            } catch (Exception $e) {

                $output->writeln("STOPPED ".date('H:i:s', (time()-$startImport)));
            }
        }
        
        
    }
}
