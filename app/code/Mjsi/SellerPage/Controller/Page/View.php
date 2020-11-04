<?php

declare(strict_types=1);

namespace Mjsi\SellerPage\Controller\Page;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;
/* use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory; */

class View extends Action
{
/* 
    protected $_productCollectionFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,        
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
    )
    {    
        $this->_productCollectionFactory = $productCollectionFactory;    
        parent::__construct($context);
    }
 */

	public function execute()
	{
        /* $jsonResult = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $jsonResult->setData([
            'message' => 'My First Page'
        ]);
        return $jsonResult; */

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);

        /** @var Template $block */
        $sellerName = $this->getRequest()->getParam('sellername');
        /* $sellerName = json_decode($sellerName); */
        /* $product_list = $this->getProductListByManufactuer($sellerName); */
        $page_title = __('Seller Page | ').$sellerName;
        $page->getConfig()->getTitle()->set($page_title);
        $block = $page->getLayout()->getBlock('mjsi.sellerpage');
        
        $block->setData('sellerName', $sellerName);
        /* $block->setData('product_list', $product_list); */

        return $page;
    }
    
    /* public function getProductListByManufactuer($manufacturer) {
        $product = $this->collectionFactory->create();
        $product->addAttributeToSelect('*');
        $product->setPageSize(3);
        // $list = $product->addFieldToFilter('manufacturer', $manufacturer);
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/mylog.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info($product);
        return $list->getData();
    }*/
} 