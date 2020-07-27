<?php

namespace Mjsi\Minicart\Plugin\Checkout\CustomerData;

class DefaultItem
{
    public function aroundGetItemData(
        \Magento\Checkout\CustomerData\AbstractItem $subject,
        \Closure $proceed,
        \Magento\Quote\Model\Quote\Item $item
    ) {
        $data = $proceed($item);
        $sku = $item->getProduct()->getSku();

        /*$info = @file_get_contents('http://dm.ecommercebusinessprime.com/content/details/'.urlencode($sku));*/
        $info = @file_get_contents('https://www.console.yourbetsy.com/content/details/'.urlencode($sku));
                
        if($info) {
            $info = json_decode($info, 1);
            if(isset($info['name']) && $info['name']!='') {   
                $data['product_name'] = substr($info['name'], 0, 28) . '...';
            }
            if(isset($info['images'][0]) && $info['images'][0]!='') {   
                $data['product_image']['src'] = $info['images'][0];
                $data['product_image']['alt'] = $info['name'];
                $data['product_image']['width'] = 50;
                $data['product_image']['height'] = 50;
            }

        }  
        
        /*return \array_merge(
            $result,
            $data
        );*/
        return $data;
    }


}