<?php
namespace Mjsi\Distribution\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Command
{

    protected function configure()
    {
        $this->setName('distribution:import');
        $this->setDescription('Import Distribution Data to Master Table');
       
        parent::configure();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $old = [];
        $master = [];

        $file = fopen("var/DISTRIBUTION-MASTER.csv", 'r');
        
        $hpfile = fopen("var/HPCAP.csv", 'r');

        $hpcap = [];
        $loop = 0;
        $loopIn = 0;
        while (! feof($hpfile)) {
            $item = fgetcsv($hpfile);
            $images = explode('.jpg', $item[8]);

            $sku = $item[0];
            $key = trim(preg_replace('/\s+/', ' ', $sku));

            $product_name = $item[1];
            $width = floatval($item[2]);
            $height = floatval($item[3]);
            $weight = floatval($item[4]);
            $short_description = $item[5];
            $information = $item[6];
            $specification = $item[7];
            $image = $images[0];
            $addimg = '';
            $imgloop = 0;

            foreach ($images as $img) {
                if ($img != '') {
                    if (strlen($img) == 75) {
                        if ($imgloop > 0) {
                            $addimg .= $img.'.jpg,';
                        }
                    }
                    $imgloop++;
                }
            }

            // print_r(explode(',', $addimg));
            

            // if ($image != '') {
            //     $loopIn++;
            //     if (strlen($image) < 80) {
            //         $image = $image.'.jpg';
            //     }

            //     if (strlen($image) > 80) {
            //         $images = explode('.png', $image);
            //         $image = $images[0].'.png';
            //     }
            // }

            $loop++;

            // echo $loop." ".$image." ".strlen($image)." \n";


            $hpcap[$key] = [
                'sku' => $sku,
                'product_name' => $product_name,
                'short_description' => $short_description,
                'width' => $width,
                'height' => $height,
                'weight' => $weight,
                'information' => $information,
                'specification' => $specification,
                'image' => $image,
                'additional_images' => $addimg
            ];

            // print_r($old);
            // die();
        }
        // die();
        // echo "loop:".$loop." - in:".$loopIn."\n";
        // die();

        while (! feof($file)) {
            $item = fgetcsv($file);
            $sku = $item[0];
            $key = trim(preg_replace('/\s+/', ' ', $sku));

            $techdata_cost  = (float)$item[10];
            $dandh_cost     = (float)$item[7];
            $ingram_cost    = (float)$item[13];

            $techdata_qty   = (float)$item[9];
            $dandh_qty      = (float)$item[6];
            $ingram_qty     = (float)$item[12];

            $master[$key] = [
                'sku' => $item[0],
                'upc' => $item[1],
                'manufacturer' => $item[2],
                'product_name' => $item[3],
                'msrp' => $item[4],
                'dandh_item_number' => $item[5],
                'dandh_qty' => $item[6],
                'dandh_cost' => $item[7],
                'techdata_item_number' => $item[8],
                'techdata_qty' => $item[9],
                'techdata_cost' => $item[10],
                'ingram_item_number' => $item[11],
                'ingram_qty' => $item[12],
                'ingram_cost' => $item[13],
                'category' => $item[14],
                'length' => $item[15],
                'width' => $item[16],
                'height' => $item[17],
                'weight' => $item[18]
            ];

            $old[$key] = [
                'dandh_qty' => $dandh_qty,
                'dandh_cost' => $dandh_cost,
                'techdata_qty' => $techdata_qty,
                'techdata_cost' => $techdata_cost,
                'ingram_qty' => $ingram_qty,
                'ingram_cost' => $ingram_cost,
            ];

            // print_r($old);
            // die();
        }

        fclose($file);

        rename("var/DISTRIBUTION-MASTER.csv", "var/DISTRIBUTION-MASTER-OLD-".time().".csv");

        /*print_r(var_dump(!preg_match('/[\'^£$%&*()}{@#~?><>,|_+¬]/', 'RJKSD-JF')));
        die();*/
        /**
        *
        * DISTRIBUTION MASTER
        *
        */
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $directory = $objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        // $sync = $objectManager->get('\Mjsi\Distribution\Cron\Sync');

        // print_r($sync->execute());
        // die();

        /*$import = $objectManager->get('\Mjsi\Distribution\Cron\Import');

        $import->execute();
        die();*/

        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('mjsi_distribution_master');
        
        $dir = $directory->getRoot();
        $techdata = [];
        $start = time();

        $output->writeln("***********************************************************");
        $output->writeln("\tRUNNING UPDATE FOR DISTRIBUTION MASTER LIST");
        $output->writeln("***********************************************************");
        
        $output->write("Downloading Techdata.zip...");
        file_put_contents("var/DISTRIBUTION-TechData.zip", fopen("https://www.techdata.com/reseller/pfget.aspx?UserID=734648&password=@Ecom2020", 'r'));
        $output->writeln("DONE");
        
        $zip = zip_open("var/DISTRIBUTION-TechData.zip");

        if ($zip) {
            while ($zip_entry = zip_read($zip)) {
                $text = explode("_", zip_entry_name($zip_entry))[0];
            }

            /**
            * TECHDATA PRICE
            */
            $fh = fopen('zip://'.$dir.'/var/DISTRIBUTION-TechData.zip#'.$text.'_Price.txt', 'r');
            $output->write("Running Techdata Price...");
            $skip = 1;
            while ($items = fgets($fh)) {
                $item = explode("\t", $items);
                if ($skip>1) {
                    $key = trim(preg_replace('/\s+/', ' ', $item[0]));
                    $techdata[$key] = [
                    'cost' => (float)$item[1],
                    'qty' => '',
                    ];
                    // $output->write('.');
                }
                $skip++;
            }
            $output->writeln("DONE");
            fclose($fh);

            /**
            * TECHDATA Availability
            */
            $fh = fopen('zip://'.$dir.'/var/DISTRIBUTION-TechData.zip#'.$text.'_Availability.txt', 'r');
            $output->write("Running Techdata Availability...");
            $skip = 1;
            while ($items = fgets($fh)) {
                $item = explode("\t", $items);
                $key = trim(preg_replace('/\s+/', ' ', $item[0]));
                if ($skip>1 && array_key_exists($key, $techdata)) {
                    $techdata[$key] = [
                    'cost' => (float)$techdata[$key]['cost'],
                    'qty' => (float)$item[3]
                    ];
                }
                $skip++;
            }
            $output->writeln("DONE");
            fclose($fh);

            /**
            * TECHDATA MATERIAL TO MASTER TABLE
            */
            $fh = fopen('zip://'.$dir.'/var/DISTRIBUTION-TechData.zip#'.$text.'_Material.txt', 'r');
            $output->write("Running Techdata Material...");
            $count = 1;
            $skip = 1;
            while ($items = fgets($fh)) {
                if (($items!='') && ($skip>1)) {
                    $item = explode("\t", $items);
                    $key = trim(preg_replace('/\s+/', ' ', $item[3]));

                    // if ($key == 'CF377A#BGJ') {
                    //     print_r(array_key_exists($item[0], $techdata) ? $techdata[ $item[0] ]['cost'] : 0);
                    //     die();
                    // }
                    if (array_key_exists($key, $master)) {
                        $exist = $master[$key];
                        $master[$key] = [
                            'sku' => $exist['sku'],
                            'upc' => $exist['upc'],
                            'manufacturer' => $exist['manufacturer'],
                            'product_name' => $exist['product_name'],
                            'msrp' => (float)$exist['msrp'],
                            'dandh_item_number' => $exist['dandh_item_number'],
                            'dandh_qty' => (float)$exist['dandh_qty'],
                            'dandh_cost' => (float)$exist['dandh_cost'],
                            'techdata_item_number' => $exist['techdata_item_number'],
                            'techdata_qty' => array_key_exists($item[0], $techdata) ? (float)$techdata[ $item[0] ]['qty'] : (float)$exist['techdata_qty'],
                            'techdata_cost' => array_key_exists($item[0], $techdata) ? (float)$techdata[ $item[0] ]['cost'] : (float)$exist['techdata_cost'],
                            'ingram_item_number' => $exist['ingram_item_number'],
                            'ingram_qty' => (float)$exist['ingram_qty'],
                            'ingram_cost' => (float)$exist['ingram_cost'],
                            'category' => $exist['category'],
                            'length' => $exist['length'],
                            'width' => $exist['width'],
                            'height' => $exist['height'],
                            'weight' => $exist['weight']
                        ];
                    } else {
                        $master[$key] = [
                            'sku' => $key,
                            'upc' => $item[6],
                            'manufacturer' => $item[5],
                            'product_name' => $item[2],
                            'msrp' => (float)$item[16],
                            'dandh_item_number' => '',
                            'dandh_qty' => 0,
                            'dandh_cost' => 0,
                            'techdata_item_number' => $item[0],
                            'techdata_qty' => array_key_exists($item[0], $techdata) ? (float)$techdata[ $item[0] ]['qty'] : 0,
                            'techdata_cost' => array_key_exists($item[0], $techdata) ? (float)$techdata[ $item[0] ]['cost'] : 0,
                            'ingram_item_number' => '',
                            'ingram_qty' => 0,
                            'ingram_cost' => 0,
                            'category' => $item[8],
                            'length' => $item[17],
                            'width' => $item[18],
                            'height' => $item[19],
                            'weight' => $item[16]
                        ];
                    }
                }
                    
                // $output->write('.');
                $skip++;
                $count++;
            }
            $output->writeln("DONE");
            // $output->writeln("Techdata successfully imported.");
            
            fclose($fh);
        }
        zip_close($zip);
        
        /**
        * DANDH ITEMLIST
        */
        $output->write("Running Dandh Item List...");
        /*$fh = fopen('ftp://3080410000:DcrdkDKFE95p@ftp.dandh.com/ITEMLIST', 'r');*/
        $fh = fopen('var/ITEMLIST', 'r');
        
        while ($items = fgets($fh)) {
            $item = explode("|", $items);
            array_pop($item);
            $key = trim(preg_replace('/\s+/', ' ', $item[5]));
            
            if (array_key_exists($key, $master)) {
                $exist = $master[$key];
                $master[$key] = [
                    'sku' => $exist['sku'],
                    'upc' => $exist['upc'],
                    'manufacturer' => $exist['manufacturer'],
                    'product_name' => $exist['product_name'],
                    'msrp' => (float)$exist['msrp'],
                    'dandh_item_number' => $item[4],
                    'dandh_qty' => (float)$item[1],
                    'dandh_cost' => (float)$item[9],
                    'techdata_item_number' => $exist['techdata_item_number'],
                    'techdata_qty' => (float)$exist['techdata_qty'],
                    'techdata_cost' => (float)$exist['techdata_cost'],
                    'ingram_item_number' => $exist['ingram_item_number'],
                    'ingram_qty' => (float)$exist['ingram_qty'],
                    'ingram_cost' => (float)$exist['ingram_cost'],
                    'category' => $exist['category'],
                    'length' => $exist['length'],
                    'width' => $exist['width'],
                    'height' => $exist['height'],
                    'weight' => $exist['weight']
                ];
            } else {
                $master[$key] = [
                'sku' => $key,
                'upc' => $item[6],
                'manufacturer' => $item[8],
                'product_name' => $item[16],
                'msrp' => (float)$item[17],
                'dandh_item_number' => $item[4],
                'dandh_qty' => (float)$item[1],
                'dandh_cost' => (float)$item[9],
                'techdata_item_number' => '',
                'techdata_qty' => 0,
                'techdata_cost' => 0,
                'ingram_item_number' => '',
                'ingram_qty' => 0,
                'ingram_cost' => 0,
                'category' => '',
                'length' => '',
                'width' => '',
                'height' => '',
                'weight' => $item[14]
                ];
            }
            // $output->write('.');
        }
        $output->writeln("DONE");
        fclose($fh);

        /**
        * INGRAM PRICE
        */
        $IGhandle = fopen("var/DISTRIBUTION-INGRAM.csv", 'w+');
        /*file_put_contents("var/DISTRIBUTION-INGRAM-PRICE.zip", fopen("ftp://328554:xg4RPP@partnerreports.ingrammicro.com/FUSION/US/CR7VUY/PRICE.zip", 'r'));*/
        $output->write("Running Ingram Price...");
        $fh = fopen('zip://'.$dir.'/var/DISTRIBUTION-INGRAM-PRICE.zip#PRICE.TXT', 'r');
        $countIGItem = 1;

        while ($items = fgets($fh)) {
            $item = explode(",", $items);
            $key = trim(preg_replace('/\s+/', ' ', $item[7]));

            if (array_key_exists($key, $master)) {
                $exist = $master[$key];
                $master[$key] = [
                'sku' => $exist['sku'],
                'upc' => $exist['upc'],
                'manufacturer' => $exist['manufacturer'],
                'product_name' => $exist['product_name'],
                'msrp' => (float)$exist['msrp'],
                'dandh_item_number' => $exist['dandh_item_number'],
                'dandh_qty' => (float)$exist['dandh_qty'],
                'dandh_cost' => (float)$exist['dandh_cost'],
                'techdata_item_number' => $exist['techdata_item_number'],
                'techdata_qty' => (float)$exist['techdata_qty'],
                'techdata_cost' => (float)$exist['techdata_cost'],
                'ingram_item_number' => $item[1],
                'ingram_qty' => ($item[16]=='N') ? 0 : 10,
                'ingram_cost' => (float)$item[14],
                'category' => $exist['category'],
                'length' => $exist['length'],
                'width' => $exist['width'],
                'height' => $exist['height'],
                'weight' => $exist['weight']
                ];
            } else {
                $master[$key] = [
                'sku' => $key,
                'upc' => $item[9],
                'manufacturer' => $item[3],
                'product_name' => $item[4],
                'msrp' => (float)$item[6],
                'dandh_item_number' => '',
                'dandh_qty' => 0,
                'dandh_cost' => 0,
                'techdata_item_number' => '',
                'techdata_qty' => 0,
                'techdata_cost' => 0,
                'ingram_item_number' => $item[1],
                'ingram_qty' => ($item[16]=='N') ? 0 : 10,
                'ingram_cost' => (float)$item[14],
                'category' => '',
                'length' => $item[10],
                'width' => $item[11],
                'height' => $item[12],
                'weight' => $item[8]
                ];
            }

            if ($item[16] == 'Y') {
                $igitem = $master[$key];

                $ingram_cost    = (float)$igitem['ingram_cost'];

                $newIG_cost = (20/100) * $ingram_cost;
                $newIG_cost = $newIG_cost + $ingram_cost;
                $ingram_qty     = (float)$igitem['ingram_qty'];

                $sku = mb_convert_encoding($igitem['sku'], 'UTF-8', 'UTF-8');
                $description = mb_convert_encoding($igitem['product_name'], 'UTF-8', 'UTF-8');
                // $product_name = '('.$countItem.') '.substr($item['product_name'], 0, 50);
                // $product_name = $igitem['product_name'];
                
                $product_name = substr($igitem['product_name'], 0, 50);
                
                if (strlen($igitem['product_name']) > 50) {
                    $product_name .= '...';
                }

                $product_name = mb_convert_encoding($product_name, 'UTF-8', 'UTF-8');
                $product_name = str_replace('&', 'and', $product_name);
                $description = str_replace('&', 'and', $description);
                $weight = $igitem['weight'];
                $manufacturer = $igitem['manufacturer'];
                $msrp = $igitem['msrp'];
                $IgItemNumber = $igitem['ingram_item_number'];
                $url = $this->url($description, $sku, $countIGItem);
                $countIGItem++;

                fputcsv($IGhandle, [$sku, $product_name, $description, $weight, $newIG_cost, $url,$msrp,$manufacturer, $IgItemNumber]);
                // $output->write('.');
            }
        }
        $output->writeln("DONE");
        fclose($fh);

        /**
        * PRIVATE CATALOG
        */
        $output->write("Running Private Catalog...");
        $pcItems = [];
        $product            = $objectManager->create('\Magento\Catalog\Model\Product');
        $productRepository  = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
        $pctableName        = $resource->getTableName('mjsi_distribution_pcatalog');
        $pcatalog           = $connection->fetchAll("SELECT pcatalog_id, sku, qty, cost FROM {$pctableName}");

        if (count($pcatalog)) {
            foreach ($pcatalog as $value) {
                $sku = $value['sku'];
                $key = trim(preg_replace('/\s+/', ' ', $sku));

                try {
                    $productExist = $productRepository->get($sku);
                } catch (\Exception $e) {
                    $productExist = false;
                }


                if ($productExist) {
                    $id = $value['pcatalog_id'];
                    $stockItem = $productExist->getExtensionAttributes()->getStockItem();
                    $qty = round($stockItem->getQty(), 0);
                    $cost = round($productExist->getPrice(), 2);

                    if ($value['cost'] != $cost || $value['qty'] != $qty) {
                        $sql = "UPDATE {$pctableName} SET `cost` = '{$cost}', `qty`= '{$qty}' WHERE `pcatalog_id` = '{$id}'";
                        $connection->query($sql);

                        $pcItems[$key] = [
                            'qty'           => $qty,
                            'cost'          => $cost
                        ];
                    }
                }
            }
        }

        $output->writeln("DONE");

        /**
        * MASTER LIST CSV
        */

        $countTechdata = 0;
        $countDandh = 0;
        $countIngram = 0;
        $countItem = 0;
        $countFiltered = 0;
        $fileOpened = false;
        $items = count($master);
        $perBatch = 500;
        $perFiltered = 500;
        $countFilteredBatch = 1;

        $countBatch = (int) ($items / $perBatch);
        $readBatch = 0;

        if (($items % $perBatch)>0) {
            $countBatch = 1;
        }

        // header('Content-Encoding: UTF-8');
        // header("Content-type: text/csv; charset=UTF-8");
        // header("Content-Disposition: attachment; filename=processed_devices.csv");
        // header("Pragma: no-cache");
        // header("Expires: 0");

        // echo "\xEF\xBB\xBF";

        $handle = fopen("var/DISTRIBUTION-MASTER.csv", 'w+');
        $fHandle = fopen("var/DISTRIBUTION-PRODUCT-FILTERED-01.csv", 'w+');
        $change = fopen("var/DISTRIBUTION-MASTER-HAS-CHANGES-".time().".csv", 'w+');
        fputcsv(
            $change,
            ['sku', 'old_cost', 'new_cost', 'old_qty', 'new_qty', 'product_name', 'description', 'manufacturer', 'msrp', 'weight']
        );
        $catalogCsvHeader = ['sku', 'store_view_code', 'attribute_set_code', 'product_type', 'categories', 'product_websites', 'name', 'description', 'short_description', 'weight', 'product_online', 'tax_class_name', 'visibility', 'price', 'special_price', 'special_price_from_date', 'special_price_to_date', 'url_key', 'meta_title', 'meta_keywords', 'meta_description', 'created_at', 'updated_at', 'new_from_date', 'new_to_date', 'display_product_options_in', 'map_price', 'msrp_price', 'map_enabled', 'gift_message_available', 'custom_design', 'custom_design_from', 'custom_design_to', 'custom_layout_update', 'page_layout', 'product_options_container', 'msrp_display_actual_price_type', 'country_of_manufacture', 'additional_attributes', 'qty', 'out_of_stock_qty', 'use_config_min_qty', 'is_qty_decimal', 'allow_backorders', 'use_config_backorders', 'min_cart_qty', 'use_config_min_sale_qty', 'max_cart_qty', 'use_config_max_sale_qty', 'is_in_stock', 'notify_on_stock_below', 'use_config_notify_stock_qty', 'manage_stock', 'use_config_manage_stock', 'use_config_qty_increments', 'qty_increments', 'use_config_enable_qty_inc', 'enable_qty_increments', 'is_decimal_divided', 'website_id', 'deferred_stock_update', 'use_config_deferred_stock_update', 'related_skus', 'crosssell_skus', 'upsell_skus', 'hide_from_product_page', 'custom_options', 'bundle_price_type', 'bundle_sku_type', 'bundle_price_view', 'bundle_weight_type', 'bundle_values', 'associated_skus', 'manufacturer', 'msrp2'];

        fputcsv($fHandle, $catalogCsvHeader);
        
        $output->writeln("Preparing Master CSV");
        $output->write("Creating ".$perFiltered." Product Catalog for Batch ".$countFilteredBatch."...");
        
        // fputcsv($handle, ['SKU','UPC','Manufacturer','Product Name','MSRP','DANDH Number','DANDH QTY','DANDH Cost','Techdata Number','Techdata QTY','Techdata Cost','Ingram Number','Ingram QTY','Ingram Cost','Category','Length','Width','Height','Weight']);
        
        // fputcsv($handle, ['sku','upc','manufacturer','product_name','msrp','dandh_item_number','dandh_qty','dandh_cost','techdata_item_number','techdata_qty','techdata_cost','ingram_item_number','ingram_qty','ingram_cost','category','length','width','height','weight']);
        
        // $chandle = fopen("var/DISTRIBUTION-CATEGORIES.csv", 'w+');
        // $categories = [];

        foreach ($master as $list) {
            fputcsv($handle, $list);

            $item = $list;
            // $item = array_map("utf8_encode", $list);

            /* CATEGORIES */
            // $catValue = $item['category'];
            // $catkey = trim(preg_replace('/\s+/', ' ', $catValue));
            // $key = trim(preg_replace('/\s+/', ' ', $item['sku']));

            // if ($catValue != '') {
            //     if (!array_key_exists($catkey, $categories)) {
            //         fputcsv($chandle, [$catValue]);
            //     }

            //     $categories[$catkey][$key] = $item;
            // }

            if ($countItem == ($readBatch*$perBatch)) {
                $countBatch--;
                $readBatch++;
                // $handleLowPrice = fopen("var/DISTRIBUTION-PRODUCT-BATCH-".sprintf("%02d", $readBatch).".csv", 'w+');
                // fputcsv($handleLowPrice, $catalogCsvHeader);
                // $output->write("Creating ".$perBatch." Product Catalog for Batch ".$readBatch."...");
            }

            $countItem++;

            // if ($countItem == 2000) {
            //     break;
            // }

            // if (strpos($item['product_name'], 'Lexmark') !== false || strpos($item['product_name'], 'lexmark') !== false) {
            if (!preg_match('/[\'^$%&*()}{@~?><>,|_+¬]/', $item['sku'])) {
                $techdata_cost  = (float)$item['techdata_cost'];
                $dandh_cost     = (float)$item['dandh_cost'];
                $ingram_cost    = (float)$item['ingram_cost'];

                $newTD_cost = (20/100) * $techdata_cost;
                $newTD_cost = $newTD_cost + $techdata_cost;

                $newIG_cost = (20/100) * $ingram_cost;
                $newIG_cost = $newIG_cost + $ingram_cost;

                $newDH_cost = (20/100) * $dandh_cost;
                $newDH_cost = $newDH_cost + $dandh_cost;

                $techdata_qty   = (float)$item['techdata_qty'];
                $dandh_qty      = (float)$item['dandh_qty'];
                $ingram_qty     = (float)$item['ingram_qty'];
                $totalItems     = ($countTechdata+$countDandh+$countIngram);

                $sku = mb_convert_encoding($item['sku'], 'UTF-8', 'UTF-8');
                $description = mb_convert_encoding($item['product_name'], 'UTF-8', 'UTF-8');
                // $product_name = '('.$countItem.') '.substr($item['product_name'], 0, 50);
                $product_name = substr($item['product_name'], 0, 50);
                // $product_name = $item['product_name'];
                $csvCategory = $item['category'];
                if ($csvCategory != '') {
                    $category = 'Default Category/CATALOG/'.$csvCategory;
                } else {
                    $category = '';
                }
                
                if (strlen($item['product_name']) > 50) {
                    $product_name .= '...';
                }

                $product_name = mb_convert_encoding($product_name, 'UTF-8', 'UTF-8');
                $product_name = str_replace('&', 'and', $product_name);
                $description = str_replace('&', 'and', $description);
                $weight = $item['weight'];
                $manufacturer = $item['manufacturer'];
                $msrp = $item['msrp'];
                $IgItemNumber = $item['ingram_item_number'];

                $new_cost = 0;
                $new_qty = 0;

                $tdChange = false;
                $igChange = false;
                $dhChange = false;

                $lowest = '';
                $newItemTD = false;
                $newItemIG = false;
                $newItemDH = false;

                $key = trim(preg_replace('/\s+/', ' ', $item['sku']));
                if ($key != '') {
                    if (array_key_exists($key, $old)) {
                        $oldTD_cost = (float)$old[$key]['techdata_cost'];
                        $oldTD_qty = (float)$old[$key]['techdata_qty'];

                        $oldIG_cost = (float)$old[$key]['ingram_cost'];
                        $oldIG_qty = (float)$old[$key]['ingram_qty'];

                        $oldDH_cost = (float)$old[$key]['dandh_cost'];
                        $oldDH_qty = (float)$old[$key]['dandh_qty'];

                        if ($oldTD_cost != $techdata_cost || $oldTD_qty != $techdata_qty) {
                            $tdChange = true;
                        }
                        if ($oldIG_cost != $ingram_cost || $oldIG_qty != $ingram_qty) {
                            $igChange = true;
                        }
                        if ($oldDH_cost != $dandh_cost || $oldDH_qty != $dandh_qty) {
                            $dhChange = true;
                        }
                    } else {
                        if ($techdata_qty > 0) {
                            $testIngram2 = false;
                            $testDandh2 = false;

                            if ($ingram_qty == 0) {
                                $testIngram2 = true;
                            } elseif ($ingram_cost == 0) {
                                $testIngram2 = true;
                            } elseif ($techdata_cost < $ingram_cost) {
                                $testIngram2 = true;
                            }

                            if ($dandh_qty == 0) {
                                $testDandh2 = true;
                            } elseif ($dandh_cost == 0) {
                                $testDandh2 = true;
                            } elseif ($techdata_cost < $dandh_cost) {
                                $testDandh2 = true;
                            }
                                
                            if ($testDandh2 && $testIngram2) {
                                $newItemTD = true;
                            }
                        }
                        if ($ingram_qty > 0) {
                            $testTechdata2 = false;
                            $testDandh2 = false;

                            if ($techdata_qty == 0) {
                                $testTechdata2 = true;
                            } elseif ($techdata_cost == 0) {
                                $testTechdata2 = true;
                            } elseif ($ingram_cost < $techdata_cost) {
                                $testTechdata2 = true;
                            }

                            if ($dandh_qty == 0) {
                                $testDandh2 = true;
                            } elseif ($dandh_cost == 0) {
                                $testDandh2 = true;
                            } elseif ($ingram_cost < $dandh_cost) {
                                $testDandh2 = true;
                            }
                                
                            if ($testTechdata2 && $testDandh2) {
                                $newItemIG = true;
                            }
                        }
                        if ($dandh_qty > 0) {
                            $testIngram2 = false;
                            $testTechdata2 = false;

                            if ($ingram_qty == 0) {
                                $testIngram2 = true;
                            } elseif ($ingram_cost == 0) {
                                $testIngram2 = true;
                            } elseif ($dandh_cost < $ingram_cost) {
                                $testIngram2 = true;
                            }

                            if ($techdata_qty == 0) {
                                $testTechdata2 = true;
                            } elseif ($techdata_cost == 0) {
                                $testTechdata2 = true;
                            } elseif ($dandh_cost < $techdata_cost) {
                                $testTechdata2 = true;
                            }

                            if ($testTechdata2 && $testIngram2) {
                                $newItemDH = true;
                            }
                        }
                    }
                }

                if ($techdata_cost > 0 && $techdata_qty > 0) {
                    $testIngram = false;
                    $testDandh = false;

                    if ($ingram_qty == 0) {
                        $testIngram = true;
                    } elseif ($ingram_cost == 0) {
                        $testIngram = true;
                    } elseif ($techdata_cost < $ingram_cost) {
                        $testIngram = true;
                    }

                    if ($dandh_qty == 0) {
                        $testDandh = true;
                    } elseif ($dandh_cost == 0) {
                        $testDandh = true;
                    } elseif ($techdata_cost < $dandh_cost) {
                        $testDandh = true;
                    }
                        
                    if ($testDandh && $testIngram) {
                        $new_cost = $newTD_cost;
                        $new_qty = $techdata_qty;
                        $lowest = 'TD';
                        /*fputcsv(
                            $handleLowPrice,
                            [
                                $sku, '', 'Default', 'simple', '',
                                'base', $product_name, $description, '', $weight, 1, 'Taxable Goods',
                                'Catalog, Search', $newTD_cost, '', '', '',
                                $this->url($description, $sku, $countItem), '', '', '',
                                date('m/d/Y h:i:s A'),
                                date('m/d/Y h:i:s A'),
                                '',
                                '',
                                'Block after Info Column', '', $msrp, '', '', '', '', '', '', '', '', 'Use config', '',
                                'has_options=1,quantity_and_stock_status=In Stock,required_options=0',
                                $techdata_qty, 0, 1, 0, 0, 1, 1, 0, 0, 1, 1, '', 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, '', '', '', '', '', '', '', '', '', '', '', $manufacturer, $msrp
                            ]
                        );*/
                        $countTechdata++;
                    }
                }

                if ($ingram_cost > 0 && $ingram_qty > 0) {
                    $testTechdata = false;
                    $testDandh = false;

                    if ($techdata_qty == 0) {
                        $testTechdata = true;
                    } elseif ($techdata_cost == 0) {
                        $testTechdata = true;
                    } elseif ($ingram_cost < $techdata_cost) {
                        $testTechdata = true;
                    }

                    if ($dandh_qty == 0) {
                        $testDandh = true;
                    } elseif ($dandh_cost == 0) {
                        $testDandh = true;
                    } elseif ($ingram_cost < $dandh_cost) {
                        $testDandh = true;
                    }
                        
                    if ($testTechdata && $testDandh) {
                        $url = $this->url($description, $sku, $countItem);
                        $new_cost = $newIG_cost;
                        $new_qty = $ingram_qty;
                        $lowest = 'IG';
                        //fputcsv($IGhandle, [$sku, $product_name, $description, $weight, $newIG_cost, $url,$msrp,$manufacturer, $IgItemNumber]);
                        /*fputcsv(
                            $handleLowPrice,
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
                        );*/
                        $countIngram++;
                    }
                }

                if ($dandh_cost > 0 && $dandh_qty > 0) {
                    $testIngram = false;
                    $testTechdata = false;
                    
                    if ($ingram_qty == 0) {
                        $testIngram = true;
                    } elseif ($ingram_cost == 0) {
                        $testIngram = true;
                    } elseif ($dandh_cost < $ingram_cost) {
                        $testIngram = true;
                    }

                    if ($techdata_qty == 0) {
                        $testTechdata = true;
                    } elseif ($techdata_cost == 0) {
                        $testTechdata = true;
                    } elseif ($dandh_cost < $techdata_cost) {
                        $testTechdata = true;
                    }
                    if ($testTechdata && $testIngram) {
                        $new_cost = $newDH_cost;
                        $new_qty = $dandh_qty;
                        $lowest = 'DH';
                        /*fputcsv(
                            $handleLowPrice,
                            [
                                $sku, '', 'Default', 'simple', '',
                                'base', $product_name, $description, '', $weight, 1, 'Taxable Goods',
                                'Catalog, Search', $newDH_cost, '', '', '',
                                $this->url($description, $sku, $countItem), '', '', '',
                                date('m/d/Y h:i:s A'),
                                date('m/d/Y h:i:s A'),
                                '',
                                '',
                                'Block after Info Column', '', $msrp, '', '', '', '', '', '', '', '', 'Use config', '',
                                'has_options=1,quantity_and_stock_status=In Stock,required_options=0',
                                $dandh_qty, 0, 1, 0, 0, 1, 1, 0, 0, 1, 1, '', 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, '', '', '', '', '', '', '', '', '', '', '', $manufacturer, $msrp
                            ]
                        );*/
                        $countDandh++;
                    }
                }

                $triggerNewItem = false;
                $triggerExistItem = false;

                $old_cost = 0;
                $old_qty = 0;

                if ($tdChange && $lowest=='TD') {
                    $old_cost = $oldTD_cost;
                    $old_qty = $oldTD_qty;

                    if ($oldTD_qty==0) {
                        $triggerNewItem = true;
                    } else {
                        $triggerExistItem = true;
                    }
                }

                if ($igChange && $lowest=='IG') {
                    $old_cost = $oldIG_cost;
                    $old_qty = $oldIG_qty;

                    if ($oldIG_qty==0) {
                        $triggerNewItem = true;
                    } else {
                        $triggerExistItem = true;
                    }
                }

                if ($dhChange && $lowest=='DH') {
                    $old_cost = $oldDH_cost;
                    $old_qty = $oldDH_qty;
                    if ($oldDH_qty==0) {
                        $triggerNewItem = true;
                    } else {
                        $triggerExistItem = true;
                    }
                }

                /*if (($tdChange || $igChange || $dhChange) && $lowest == '') {
                    if ($oldTD_cost > 0) {
                        if ($oldTD_cost < $oldDH_cost && $oldTD_cost < $oldIG_cost) {
                            $old_cost = $oldTD_cost;
                            $old_qty = $oldTD_qty;
                        }
                    }

                    if ($oldDH_cost > 0) {
                        if ($oldDH_cost < $oldTD_cost && $oldDH_cost < $oldIG_cost) {
                            $old_cost = $oldDH_cost;
                            $old_qty = $oldDH_qty;
                        }
                    }

                    if ($oldIG_cost > 0) {
                        if ($oldIG_cost < $oldDH_cost && $oldIG_cost < $oldTD_cost) {
                            $old_cost = $oldIG_cost;
                            $old_qty = $oldIG_qty;
                        }
                    }

                    $new_cost = $old_cost;
                    $new_qty = 0;
                    $triggerExistItem = true;
                }*/

                if ($newItemTD) {
                    $new_cost = $newTD_cost;
                    $new_qty = $techdata_qty;
                    $triggerNewItem = true;
                }

                if ($newItemIG) {
                    $new_cost = $newIG_cost;
                    $new_qty = $ingram_qty;
                    $triggerNewItem = true;
                }

                if ($newItemDH) {
                    $new_cost = $newDH_cost;
                    $new_qty = $dandh_qty;
                    $triggerNewItem = true;
                }

                if ($triggerNewItem) {
                    $countFiltered++;
                    fputcsv(
                        $change,
                        [$sku, $old_cost, $new_cost, $old_qty, $new_qty, $product_name, $description, $manufacturer, $msrp, $weight]
                    );
                }

                if ($triggerExistItem) {
                    $countFiltered++;
                    fputcsv(
                        $change,
                        [$sku, $old_cost, $new_cost, $old_qty, $new_qty]
                    );
                }

                if (($perFiltered * $countFilteredBatch) == $countFiltered) {
                    $output->writeln("DONE");
                    fclose($fHandle);
                    $countFilteredBatch++;
                    $fHandle = fopen("var/DISTRIBUTION-PRODUCT-FILTERED-".sprintf("%02d", $countFilteredBatch).".csv", 'w+');
                    fputcsv($fHandle, $catalogCsvHeader);
                    $output->write("Creating ".$perFiltered." Product Catalog for Batch ".$countFilteredBatch."...");
                }

                if ($triggerNewItem || $triggerExistItem) {
                    $key = trim(preg_replace('/\s+/', ' ', $sku));
                    
                    if (array_key_exists($key, $pcItems)) {
                        $new_qty    = $pcItems[$key]['qty'];
                        $new_cost   = $pcItems[$key]['cost'];
                    }
                    
                    if (array_key_exists($key, $hpcap)) {
                        $sku = $hpcap[$key]['sku'];
                        $product_name = $hpcap[$key]['product_name'];
                        $width = $hpcap[$key]['width'];
                        $height = $hpcap[$key]['height'];
                        $weight = $hpcap[$key]['weight'];
                    }
                    
                    fputcsv(
                        $fHandle,
                        [
                            $sku, '', 'Default', 'simple', $category,
                            'base', $product_name, $description, $description, $weight, 1, 'Taxable Goods',
                            'Catalog, Search', $new_cost, '', '', '',
                            $this->url($description, $sku, $countFiltered), '', '', '',
                            date('m/d/Y h:i:s A'),
                            date('m/d/Y h:i:s A'),
                            '',
                            '',
                            'Block after Info Column', '', $msrp, '', '', '', '', '', '', '', '', 'Use config', '',
                            'has_options=1,quantity_and_stock_status=In Stock,required_options=0',
                            $new_qty, 0, 1, 0, 0, 1, 1, 0, 0, 1, 1, '', 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, '', '', '', '', '', '', '', '', '', '', '', $manufacturer, $msrp
                        ]
                    );

                    // if (array_key_exists($key, $hpcap)) {
                    //     $sku = $hpcap[$key]['sku'];
                    //     $product_name = $hpcap[$key]['product_name'];
                    //     $width = $hpcap[$key]['width'];
                    //     $height = $hpcap[$key]['height'];
                    //     $weight = $hpcap[$key]['weight'];
                    //     $short_description = $hpcap[$key]['short_description'];
                    //     $information = $hpcap[$key]['information'];
                    //     $specification = $hpcap[$key]['specification'];
                    //     $image = $hpcap[$key]['image'];
                    //     $addimg = $hpcap[$key]['additional_images'];

                    //     fputcsv(
                    //         $fHandle,
                    //         [
                    //             $sku, '', 'Default', 'simple', $category,
                    //             'base', $product_name, $description, $short_description, $weight, 1, 'Taxable Goods',
                    //             'Catalog, Search', $new_cost, '', '', '',
                    //             $this->url($description, $sku, $countFiltered), '', '', '',
                    //             date('m/d/Y h:i:s A'),
                    //             date('m/d/Y h:i:s A'),
                    //             '',
                    //             '',
                    //             'Block after Info Column', '', $msrp, '', '', '', '', '', '', '', '', 'Use config', '',
                    //             'has_options=1,quantity_and_stock_status=In Stock,required_options=0',
                    //             $new_qty, 0, 1, 0, 0, 1, 1, 0, 0, 1, 1, '', 1, 0, 1, 1, 0, 1, 0, 0, 1, 0, 1, '', '', '', '', '', '', '', '', '', '', '', $manufacturer, $msrp, $information, $specification, $image, $image, $image, $addimg
                    //         ]
                    //     );
                    // }
                }
            } //end if preg_match

            /*if ($countItem == ($readBatch*$perBatch)) {
                $output->writeln("DONE");
                fclose($handleLowPrice);
            }*/
            // break;
        }
        $output->writeln("DONE");
        $output->writeln('Techdata - '.$countTechdata.' inserted items');
        $output->writeln('Dandh - '.$countDandh.' inserted items');
        $output->writeln('Ingram - '.$countIngram .' inserted items');

        fclose($handle);
        fclose($IGhandle);
        fclose($change);
        fclose($fHandle);
        // fclose($chandle);

        $output->writeln('DONE preparing '.count($master).' items');

        $output->write('Empty master table...');
        $connection->query("TRUNCATE TABLE {$tableName}");
        // $connection->query("TRUNCATE TABLE catalog_product_index_price");
        $output->writeln('DONE');

        $output->write('Load '.count($master).' items to master table...');
        $sql = "LOAD DATA LOCAL INFILE 'var/DISTRIBUTION-MASTER.csv' 
            REPLACE INTO TABLE {$tableName}
            FIELDS TERMINATED BY ',' 
            OPTIONALLY ENCLOSED BY '\"'  
            LINES TERMINATED BY '\\n'
            (sku,upc,manufacturer,product_name,msrp,dandh_item_name,dandh_qty,dandh_cost,techdata_item_name,techdata_qty,techdata_cost,ingram_item_name,ingram_qty,ingram_cost,category,length,width,height,weight)
            SET master_id = NULL, updated_at = now(), created_at = now();";
        $connection->query($sql);
        $output->writeln('DONE');

        // foreach ($categories as $key => $cat) {
        //     $CIhandle = fopen("var/DISTRIBUTION-CATEGORY-".str_replace('/', '-', $key).".csv", 'w+');
        //     foreach ($cat as $item) {
        //         fputcsv($CIhandle, [$item['sku']]);
        //     }
        //     fclose($CIhandle);
        // }

        $output->write("RUNTIME ");
        $output->writeln(date('H:i:s', (time()-$start)));
    }

    protected function url($name, $sku, $count)
    {
        $name = $name.' '.substr(md5($sku), 0, 6).$count;
        if (strlen($name) > 25) {
            $name = substr(md5($sku), 0, 6).$count.' '.substr($name, 0, 18);
        }

        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $name);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("/[\/_|+ -]+/", '-', $clean);

        return time().$clean;
    }
}
