<?php
namespace Mjsi\Distribution\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;

class Generate extends Command
{
    /**
    * Constructor
    *
    * @param State $state A Magento app State instance
    *
    * @return void
    */
    public function __construct(State $state, ProductRepositoryInterface $prepo)
    {
        try {
            $state->setAreaCode('adminhtml');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Intentionally left empty.
        }
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('distribution:generate');
        $this->setDescription('Generate Distribution Product Batches');
       
        parent::configure();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory          = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        $product            = $objectManager->create('\Magento\Catalog\Model\Product');
        $productRepository  = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
        
        $root   = $directory->getRoot();
        $dmPath = $directory->getPath('var').'/dm/';

        $writer = new \Zend\Log\Writer\Stream($root . '/var/log/distribution.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();

        $start = time();
        $output->write("Generate started at ");
        $output->writeln(date('h:i:s A'));

        $handle = fopen('http://dm.ecommercebusinessprime.com/storage/distributions/store/transfer/new.csv', 'r');

        $itemLoop       = 0;
        $perBatch       = 100;
        $currentBatch   = 0;
        $countLoop      = 0;

        while (! feof($handle)) { 
            $item = fgetcsv($handle); 

            if($item[0] != 'sku') {
                if($countLoop == 0) {
                    $currentBatch++;
                    $handleNew = fopen($dmPath.'b'.$currentBatch.'.csv', 'w+');
                    fputcsv($handleNew, $this->mageProductCsvHeader());
                }

                fputcsv($handleNew, $this->setMageProductCsv($item));
                
                $countLoop++;
                
                if($countLoop == $perBatch) {
                    fclose($handleNew);
                    $countLoop = 0;
                    // exit;
                }
            }
            
        }
        fclose($handleNew);
        fclose($handle);
        
    }

    protected function setMageProductCsv($item) 
    {
        $sku            = $item[0];
        $manufacturer   = $item[1];
        $price          = $item[2];
        $msrp           = $item[3];
        $qty            = $item[4];
        $name           = $item[5];
        $description    = $item[6];
        $weight         = $item[7];
        $map_price      = $item[8];
        $special_price  = $item[9];
        $rebate_start   = $item[10];
        $rebate_end     = $item[11];
        $msrp2          = $item[12];
        $url_key        = $item[13];

        return [
            $sku, '', 'Default', 'simple', '',
            'base', $name, $description, '', $weight, 1, 'Taxable Goods',
            'Catalog, Search', $price, $special_price, $rebate_start, $rebate_end,
            $url_key, '', '', '',
            date('m/d/Y h:i:s A'),
            date('m/d/Y h:i:s A'),
            '',
            '',
            'Block after Info Column', '', $msrp, '', '', '', '', '', '', '', '', 'Use config', '',
            'has_options=1,quantity_and_stock_status=In Stock,required_options=0',
            $qty, 0, 1, 0, 0, 1, 1, 0, 0, 1, 1, '', 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, '', 
            '', '', '', '', '', '', '', '', '', '', $manufacturer, $msrp2
        ];
    }

    protected function mageProductCsvHeader() {
        return ['sku', 'store_view_code', 'attribute_set_code', 'product_type', 'categories', 'product_websites', 'name', 'description', 'short_description', 'weight', 'product_online', 'tax_class_name', 'visibility', 'price', 'special_price', 'special_price_from_date', 'special_price_to_date', 'url_key', 'meta_title', 'meta_keywords', 'meta_description', 'created_at', 'updated_at', 'new_from_date', 'new_to_date', 'display_product_options_in', 'map_price', 'msrp_price', 'map_enabled', 'gift_message_available', 'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'page_layout', 'product_options_container', 'msrp_display_actual_price_type', 'country_of_manufacture', 'additional_attributes', 'qty', 'out_of_stock_qty', 'use_config_min_qty', 'is_qty_decimal', 'allow_backorders', 'use_config_backorders', 'min_cart_qty', 'use_config_min_sale_qty', 'max_cart_qty', 'use_config_max_sale_qty', 'is_in_stock', 'notify_on_stock_below', 'use_config_notify_stock_qty', 'manage_stock', 'use_config_manage_stock', 'use_config_qty_increments', 'qty_increments', 'use_config_enable_qty_inc', 'enable_qty_increments', 'is_decimal_divided', 'website_id', 'deferred_stock_update', 'use_config_deferred_stock_update', 'related_skus', 'crosssell_skus', 'upsell_skus', 'hide_from_product_page', 'custom_options', 'bundle_price_type', 'bundle_sku_type', 'bundle_price_view', 'bundle_weight_type', 'bundle_values', 'associated_skus', 'manufacturer','msrp2'];
    }
}
