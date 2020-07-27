<?php
namespace Mjsi\Distribution\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;

class Update extends Command
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
        // We cannot use core functions (like saving a product) unless the area
        // code is explicitly set.
        try {
            $state->setAreaCode('adminhtml');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Intentionally left empty.
        }
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('distribution:update');
        $this->setDescription('Update Distribution List to Product Catalog');
       
        parent::configure();
    }
    
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

        $start = time();
        $output->write("Update started at ");
        $output->writeln(date('h:i:s A'));


        $handleQty = fopen('http://dm.ecommercebusinessprime.com/storage/distributions/store/transfer/qty.csv', 'r');
        $handlePrice = fopen('http://dm.ecommercebusinessprime.com/storage/distributions/store/transfer/price.csv', 'r');

        while (! feof($handleQty)) { 
            $item = fgetcsv($handleQty); 
            $entity_id  = $item[0];
            $qty = $item[1];

            if($entity_id != '' && $entity_id != 'entity_id') {
                $connection->query("UPDATE cataloginventory_stock_item SET `qty` = $qty WHERE `product_id` = $entity_id");
            }
        }

        while (! feof($handlePrice)) { 
            $item = fgetcsv($handlePrice); 

            $entity_id              = $item[0];
            $price                  = $item[1];
            $msrp                   = $item[2];
            $msrp2                  = $item[3];
            $special_price          = $item[4];

            $special_price_from_date = $item[5];
            $special_price_to_date  = $item[6];

            //'entity_id','price','msrp','msrp2', 'special_price','special_price_from_date','special_price_to_date'

            if($entity_id != '' && $entity_id != 'entity_id') { 

                $countSprice = $connection->fetchOne("SELECT COUNT(*) FROM catalog_product_entity_decimal WHERE `entity_id` = $entity_id AND attribute_id = 78");

                if((int)$countSprice) { 
                    $connection->query("UPDATE catalog_product_entity_decimal SET `value` = $special_price WHERE `entity_id` = $entity_id AND attribute_id = 78");
                } else {
                    $connection->query("INSERT INTO `catalog_product_entity_decimal`(`attribute_id`, `store_id`, `entity_id`, `value`) VALUES (78,0,$entity_id, '$special_price')");
                }


                $connection->query("UPDATE catalog_product_entity_decimal SET `value` = $price WHERE `entity_id` = $entity_id AND attribute_id = 77");
                
                $connection->query("UPDATE catalog_product_entity_decimal SET `value` = $msrp WHERE `entity_id` = $entity_id AND attribute_id = 117");
                $connection->query("UPDATE catalog_product_entity_decimal SET `value` = $msrp2 WHERE `entity_id` = $entity_id AND attribute_id = 141");

                $connection->query("DELETE FROM catalog_product_entity_datetime WHERE `store_id` = 1");


                if($special_price_from_date!='') {
                    $count = $connection->fetchOne("SELECT COUNT(*) FROM catalog_product_entity_datetime WHERE `entity_id` = $entity_id");

                    if((int)$count) {
                        $connection->query("UPDATE catalog_product_entity_datetime SET `value` = '$special_price_from_date' WHERE `entity_id` = $entity_id AND attribute_id = 79");
                        $connection->query("UPDATE catalog_product_entity_datetime SET `value` = '$special_price_to_date' WHERE `entity_id` = $entity_id AND attribute_id = 80");
                    } else {
                        $connection->query("INSERT INTO `catalog_product_entity_datetime`(`attribute_id`, `store_id`, `entity_id`, `value`) VALUES (79,0,$entity_id, '$special_price_from_date'), (80,0,$entity_id, '$special_price_to_date')");
                    }
                }
            }
            
        }

        fclose($handleQty);
        fclose($handlePrice);

        exit;


        /**
        *
        * DISTRIBUTION MASTER
        *
        */
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        
        $root = $directory->getRoot();
        $countItem = 0;
        $file = fopen($root."/var/DISTRIBUTION-INGRAM.csv", 'r');

        $perBatch = 500;
        $currentBatch = 1;

        $output->write("Creating ".$perBatch." Product Catalog for Batch ".sprintf("%02d", $currentBatch));
        $IGhandle = fopen("var/DISTRIBUTION-PRODUCT-FILTERED-".sprintf("%02d", $currentBatch).".csv", 'w+');

        $catalogCsvHeader = ['sku', 'store_view_code', 'attribute_set_code', 'product_type', 'categories', 'product_websites', 'name', 'description', 'short_description', 'weight', 'product_online', 'tax_class_name', 'visibility', 'price', 'special_price', 'special_price_from_date', 'special_price_to_date', 'url_key', 'meta_title', 'meta_keywords', 'meta_description', 'created_at', 'updated_at', 'new_from_date', 'new_to_date', 'display_product_options_in', 'map_price', 'msrp_price', 'map_enabled', 'gift_message_available', 'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'page_layout', 'product_options_container', 'msrp_display_actual_price_type', 'country_of_manufacture', 'additional_attributes', 'qty', 'out_of_stock_qty', 'use_config_min_qty', 'is_qty_decimal', 'allow_backorders', 'use_config_backorders', 'min_cart_qty', 'use_config_min_sale_qty', 'max_cart_qty', 'use_config_max_sale_qty', 'is_in_stock', 'notify_on_stock_below', 'use_config_notify_stock_qty', 'manage_stock', 'use_config_manage_stock', 'use_config_qty_increments', 'qty_increments', 'use_config_enable_qty_inc', 'enable_qty_increments', 'is_decimal_divided', 'website_id', 'deferred_stock_update', 'use_config_deferred_stock_update', 'related_skus', 'crosssell_skus', 'upsell_skus', 'hide_from_product_page', 'custom_options', 'bundle_price_type', 'bundle_sku_type', 'bundle_price_view', 'bundle_weight_type', 'bundle_values', 'associated_skus', 'manufacturer', 'msrp2'];

        fputcsv($IGhandle, $catalogCsvHeader);

        while (! feof($file)) {
            $item = fgetcsv($file);

            if ($countItem >= (($perBatch * $currentBatch) - $perBatch )) {
                $sku            = $item[0];
                $product_name   = $item[1];
                $description    = $item[2];
                $weight         = $item[3];
                $newIG_cost     = $item[4];
                $url            = $item[5];
                $msrp           = $item[6];
                $manufacturer   = $item[7];
                $IgItemNumber   = $item[8];
                $ingram_qty     = $this->getIgQty($IgItemNumber);
                $output->write('.');
                // $output->writeln($countItem);
                // $output->writeln($sku);

                fputcsv(
                    $IGhandle,
                    [
                        $sku, '', 'Default', 'simple', '',
                        'base', $product_name, $description, '', $weight, 1, 'Taxable Goods',
                        'Catalog, Search', $newIG_cost, '', '', '',
                        $url, '', '', '',
                        date('m/d/Y h:i:s A'),
                        date('m/d/Y h:i:s A'),
                        '',
                        '',
                        'Block after Info Column', '', $msrp, '', '', '', '', '', '', '', '', 'Use config', '',
                        'has_options=1,quantity_and_stock_status=In Stock,required_options=0',
                        $ingram_qty, 0, 1, 0, 0, 1, 1, 0, 0, 1, 1, '', 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, '', '', '', '', '', '', '', '', '', '', '', $manufacturer, $msrp
                    ]
                );

                if ($countItem == ($perBatch * $currentBatch)) {
                    $output->writeln("DONE");
                    fclose($IGhandle);
                    $currentBatch++;
                    $output->write("Creating ".$perBatch." Product Catalog for Batch ".sprintf("%02d", $currentBatch));
                    $IGhandle = fopen("var/DISTRIBUTION-PRODUCT-FILTERED-".sprintf("%02d", $currentBatch).".csv", 'w+');
                    fputcsv($IGhandle, $catalogCsvHeader);
                }
            }
            
            // $product = [
            //     'sku'           => $item[0],
            //     'price'         => $item[2],
            //     'qty'           => $item[4],
            //     'msrp'          => array_key_exists(8, $item) ? $item[8] : '',
            //     'name'          => array_key_exists(5, $item) ? $item[5] : '',
            //     'desc'          => array_key_exists(6, $item) ? $item[6] : '',
            //     'manufacturer'  => array_key_exists(7, $item) ? $item[7] : '',
            //     'weight'        => array_key_exists(9, $item) ? $item[9] : ''
            // ];

            // if ($countItem>0) {
            //     $output->write($this->save($product));
                
            //     // print_r($product);
            //     // die();
            // }
            $countItem++;
        }
        $output->writeln("DONE");
        fclose($IGhandle);
    }


    protected function getIgQty($sku)
    {
        $data = [
            'url'     =>'https://newport.ingrammicro.com',
            'login'   =>'U6PPSVHUK3',
            'password'=>'J5ZjWTMkfgx',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <PNARequest>
            <Version>2.0</Version> 
            <TransactionHeader>
            <SenderID>MD</SenderID>
            <ReceiverID>YOU</ReceiverID>
            <CountryCode>MD</CountryCode>
            <LoginID>U6PPSVHUK3</LoginID>
            <Password>J5ZjWTMkfgx</Password>
            <TransactionID>12345</TransactionID>
            </TransactionHeader>
            <PNAInformation SKU="'.$sku.'" Quantity="1" />
            <ShowDetail>2</ShowDetail>
            </PNARequest>';
        //BC3031
        $ch = curl_init('https://newport.ingrammicro.com');
        // curl_setopt($ch, CURLOPT_MUTE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);

        $xml = simplexml_load_string($output);
        
        $qty = 0;
        foreach ($xml->PriceAndAvailability->Branch as $value) {
            $qty += $value->Availability;
        }

        // print_r($qty);

        curl_close($ch);

        // die();
        return $qty;
    }

    public function save($p)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $product            = $objectManager->create('\Magento\Catalog\Model\Product');
        $productRepository  = $objectManager->get('\Magento\Catalog\Model\ProductRepository');

        $sku            = $p['sku'];
        $price          = $p['price']!=''?$p['price']:0;
        $msrp           = $p['msrp']!=''?$p['msrp']:0;
        $qty            = (float)$p['qty'];
        $name           = $p['name'];
        $desc           = $p['desc'];
        $manufacturer   = $p['manufacturer'];
        $weight         = $p['weight'];

        $csvPrice = (float)$price;
        $newPrice = ((20/100) * $csvPrice) + $csvPrice;
        $newPrice = rtrim($newPrice, "0");

        try {
            $productExist = $productRepository->get($sku);
        } catch (\Exception $e) {
            $productExist = false;
        }


        if (!$productExist && $name != '') {
            $product->setSku($sku); // Set your sku here
            $product->setName($name); // Name of Product
            $product->setDescription($desc); // Description of Product
            $product->setAttributeSetId(4); // Attribute set id
            $product->setStatus(1); // Status on product enabled/ disabled 1/0
            $product->setWeight($weight); // weight of product
            $product->setVisibility(4); // visibilty of product (catalog / search / catalog, search / Not visible individually)
            $product->setTaxClassId(0); // Tax class id
            $product->setTypeId('simple'); // type of product (simple/virtual/downloadable/configurable)
            $product->setPrice($newPrice); // price of product
            $product->setMsrp($msrp); // price of product
            $product->setStockData(
                array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => $qty
                )
            );
            try {
                $product->save();
                return '~';
            } catch (Exception $e) {
                return 'FAILED: '.$e->getMessage.' => '.$name;
            }
        } else {
            $product = $productExist;

            $product->setPrice($newPrice); // price of product
            $product->setMsrp($msrp); // price of product
            $product->setStockData(
                array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => $qty
                )
            );
                
            try {
                $product->save();
                return '.';
            } catch (Exception $e) {
                return 'FAILED: '.$e->getMessage.' => '.$name;
            }

            // return $text;
        }
    }
}
