<?php 
namespace Mjsi\Distribution\Model;

use Magento\Framework\App\ObjectManager; 
 
class PostManagement {

    /**
     * {@inheritdoc}
     */
    public function getPost($param)
    {

        $params = explode("~", $param);

        $request = $params[0];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $readConnection = $resource->getConnection('core_read');

        $product            = $objectManager->create('\Magento\Catalog\Model\Product');
        $productRepository  = $objectManager->get('\Magento\Catalog\Model\ProductRepository');
        
        $productCollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');

        try {
            switch ($request) {
                case 'getCategories':
                    
                    break;

                case 'save':
                    $this->save($params);
                    break;

                case 'getProductData':
                    $sku = $params[1];
                    $product = $productRepository->get($sku);
                    
                    return json_encode($product->getData());
                    break;

                case 'getProducts': 
                    $items          = [];
                    $response       = [];
                    $attribute_id   = $readConnection->fetchOne("SELECT attribute_id FROM eav_attribute WHERE `attribute_code` = 'price' limit 1");
                    $prices         = $readConnection->fetchAll("SELECT entity_id, value FROM catalog_product_entity_decimal WHERE attribute_id = {$attribute_id}");
                    $quantities     = $readConnection->fetchAll("SELECT product_id, qty FROM cataloginventory_stock_item");
                    $products       = $readConnection->fetchAll("SELECT entity_id, sku FROM catalog_product_entity");
                    
                    foreach ($products as $product) {
                        $key = $product['entity_id'];
                        $items[$key] = [
                            'sku'           => $product['sku'],
                            'price'         => 0,
                            'qty'           => 0,
                            'entity_id'     => $product['entity_id'],
                        ];
                    }

                    foreach ($prices as $price) {
                        $key = $price['entity_id'];

                        if (array_key_exists($key, $items)) {
                            $items[$key] = [
                                'sku'       => $items[$key]['sku'],
                                'price'     => floatval($price['value']),
                                'qty'       => 0,
                                'entity_id' => $items[$key]['entity_id'],
                            ];
                        }
                    }

                    foreach ($quantities as $qty) {
                        $key = $qty['product_id'];

                        if (array_key_exists($key, $items)) {
                            $price = $items[$key]['price'];
                            if(!isset($items[$key]['price'])) { $price = 0; }
                            $items[$key] = [
                                'sku'       => $items[$key]['sku'],
                                'price'     => $price,
                                'qty'       => floatval($qty['qty']),
                                'entity_id' => $items[$key]['entity_id'],
                            ];
                        }
                    }

                    foreach ($items as $value) {
                        $response[] = $value;
                    }

                    return json_encode($response);
                    break;    
                
                case 'getProductSkus': 
                    $items  = $readConnection->fetchAll("SELECT entity_id, sku FROM catalog_product_entity");
                    return json_encode($items);
                    break;

                case 'getProductQuantities': 
                    $items  = $readConnection->fetchAll("SELECT product_id, qty FROM cataloginventory_stock_item");
                    return json_encode($items);
                    break;   
                    
                case 'getProductPrices': 
    
                    $attribute_id = $readConnection->fetchOne("SELECT attribute_id FROM eav_attribute WHERE `attribute_code` = 'price' limit 1");
                    $items  = $readConnection->fetchAll("SELECT entity_id, value FROM catalog_product_entity_decimal WHERE attribute_id = {$attribute_id}");
                    return json_encode($items);
                    break;       
                    
                case 'getProductsCount':
                    $count = $readConnection->fetchOne('SELECT COUNT(entity_id) FROM catalog_product_entity');
                    return (int)$count;
                    break;  

                case 'test_custom':
                    /* $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); */
                    
                    
                    /* $data = $readConnection->fetchAll("SELECT name FROM sales_order_item WHERE sku = {$params[1]}" ); */
                    /* $data = $readConnection->fetchAll("SELECT sales_order_item.name, sales_order.entity_id FROM sales_order 
                        JOIN sales_order_item ON sales_order.entity_id=sales_order_item.order_id
                        WHERE sales_order_item.sku = {$params[1]}" 
                    ); */
                    /* $test = "TEST"; */
                    $orderDetail = $objectManager->create('Magento\Sales\Model\Order')->load(33);
                    $order_status = $orderDetail->getStatus();
                    $test = $order_status;
                    /* $orderItems = $orderDetail->getAllItems();
                    foreach ($orderItems as $value) {
                        if($value['product_id']==38)
                        {    
                            // $test = $value['name'];
                            $value->setQtyCanceled($value['qty_ordered']);
                            $value->save();
                            $test = $value['name'];
                        }
                        else
                        {   
                            continue;
                        }
                    } */
                    /* if ($order_status != "complete") {
                        # code...
                    } */
                    /* if ($orderDetail->canCancel()) {
                        $orderItems = $orderDetail->getAllItems();
                        foreach ($orderItems as $value) {
                            if($value['product_id']==39)
                            {    
                                $test = $value['name'];
                                // $value->setQtyCanceled(1.0000);
                                // $value->save();
                                
                            }
                            else
                            {   
                             continue;
                            }
                        }
                        // $orderDetail->save();
                    } */
                    
                    return json_encode($test);
                    break;
                    
                default:
                    return false;
                    break;
            }

        } catch (\Exception $e) {
            return false;
        }

    }

    public function save($p)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $product            = $objectManager->create('\Magento\Catalog\Model\Product');
        $productRepository  = $objectManager->get('\Magento\Catalog\Model\ProductRepository');

        $sku            = isset($p[1])&&$p[1]!=''?$p[1]:'';
        $price          = isset($p[2])&&$p[2]!=''?$p[2]:0;
        $msrp           = isset($p[3])&&$p[3]!=''?$p[3]:0;
        $qty            = isset($p[4])&&$p[4]!=''?$p[4]:0;
        $name           = isset($p[5])&&$p[5]!=''?$p[5]:'';
        $desc           = isset($p[6])&&$p[6]!=''?$p[6]:'';
        $manufacturer   = isset($p[7])&&$p[7]!=''?$p[7]:'';
        $weight         = isset($p[8])&&$p[8]!=''?$p[8]:0;
        $categories     = [];
        $sprice         = $p[10];
        $spriceFrom     = $p[11];
        $spriceTo       = $p[12];

        if(isset($p[9])&&$p[9]!=='') {
            $categories = explode(',', $p[9]);
        }

        // $csvPrice = (float)$price;
        // $newPrice = ((20/100) * $csvPrice) + $csvPrice;
        // $newPrice = rtrim($newPrice, "0");

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
            $product->setPrice($price); // price of product
            $product->setMsrp($msrp); // price of product

            $product->setCategoryIds($categories);
            $product->setStoreId(1);

            $product->setStockData(
                array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => $qty
                )
            );

            if(isset($sprice)&&$sprice!='') {
                $product->setSpecialPrice($sprice);

                $spriceFrom = date_create($spriceFrom);
                $spriceFrom = date_format($spriceFrom,"Y-m-d H:i:s");
                $product->setSpecialFromDate($spriceFrom);
                // $product->setSpecialFromDateIsFormated(true);

                $spriceTo = date_create($spriceTo);
                $spriceTo = date_format($spriceTo,"Y-m-d H:i:s");
                $product->setSpecialToDate($spriceTo);
                // $product->setSpecialToDateIsFormated(true);
            }

            try {
                $product->save();
                return true;
            } catch (Exception $e) {
                return 'FAILED: '.$e->getMessage.' => '.$name;
            }
        } else {
            $product = $productExist;

            $product->setName($name); // Name of Product
            $product->setDescription($desc); // Description of Product
            $product->setWeight($weight); // weight of product

            $product->setPrice($price); // price of product
            $product->setMsrp($msrp); // price of product

            $product->setCategoryIds($categories);
            $product->setStoreId(1);

            $product->setStockData(
                array(
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => $qty
                )
            );

            if(isset($sprice)&&$sprice!='') {
                $product->setSpecialPrice($sprice);

                $spriceFrom = date_create($spriceFrom);
                $spriceFrom = date_format($spriceFrom,"Y-m-d H:i:s");
                $product->setSpecialFromDate($spriceFrom);
                // $product->setSpecialFromDateIsFormated(true);

                $spriceTo = date_create($spriceTo);
                $spriceTo = date_format($spriceTo,"Y-m-d H:i:s");
                $product->setSpecialToDate($spriceTo);
                // $product->setSpecialToDateIsFormated(true);
            }
                
            try {
                $product->save();
                return true;
            } catch (Exception $e) {
                return 'FAILED: '.$e->getMessage.' => '.$name;
            }

            // return $text;
        }
    }
}