<?php
namespace Mjsi\TableratePerSku\Model\Carrier;
 
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
 
class ShippingPerSku extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'tableratepersku';

    /**
     * @var RateRequest
     */
    private $request;
 
    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        array $data = [],
        \Magento\Quote\Model\Quote\Address\RateRequest $request
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->request = $request;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }
 
    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['tableratepersku' => $this->getConfigData('name')];
    }
 
    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $readConnection = $resource->getConnection('core_read');

        $cart = $objectManager->get('\Magento\Checkout\Model\Cart'); 
        $items = $cart->getQuote()->getAllItems();
        $query_sku = '';
        // $query_sku = [];
        foreach($items as $item) {
            $product = $objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());
            $sku = $product->getSku();
            $query_sku .= ",'{$sku}'";
            // $query_sku[] = $sku;
        }
        $query_sku = ltrim($query_sku, ',');

        $website_ID = $request->getWebsiteId() ? $request->getWebsiteId() : '';
        $country_id = $request->getDestCountryId() ? $request->getDestCountryId() : 0;
        $region_id = $request->getDestRegionId() ? $request->getDestRegionId() : 0;
        $postcode = $request->getDestPostcode() ? $request->getDestPostcode() : '';
        $postcode_prefix = $this->getDestPostcodePrefix($postcode) ? $this->getDestPostcodePrefix($postcode) : '';

        $query_result = $readConnection->fetchAll("SELECT * FROM shipping_tablerate_persku 
                                    WHERE website_id = '{$website_ID}'
                                    AND(
                                    (dest_country_id = '{$country_id}' AND dest_region_id = '{$region_id}' AND dest_zip = '{$postcode}' )
                                    OR (dest_country_id = '{$country_id}' AND dest_region_id = '{$region_id}' AND dest_zip = '{$postcode_prefix}' )
                                    OR (dest_country_id = '{$country_id}' AND dest_region_id = '{$region_id}' AND dest_zip = '' )
                                    OR (dest_country_id = '{$country_id}' AND dest_region_id = '{$region_id}' AND dest_zip = '*' )
                                    OR (dest_country_id = '{$country_id}' AND dest_region_id = 0 AND dest_zip = '*' )
                                    OR (dest_country_id = '0' AND dest_region_id = '{$region_id}' AND dest_zip = '*' )
                                    OR (dest_country_id = '0' AND dest_region_id = 0 AND dest_zip = '*' )
                                    OR (dest_country_id = '{$country_id}' AND dest_region_id = 0 AND dest_zip = '' )
                                    OR (dest_country_id = '{$country_id}' AND dest_region_id = 0 AND dest_zip = '{$postcode}' )
                                    OR (dest_country_id = '{$country_id}' AND dest_region_id = 0 AND dest_zip = '{$postcode_prefix}' ))
                                    AND sku IN ($query_sku)
                                    GROUP BY sku
                                ");
        /* $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/mylog.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($query_result);
        $logger->info('country'.$country_id);
        $logger->info('region'.$region_id);
        $logger->info('zip'.$postcode_prefix); */
        if (!count($query_result)) {
            return;
        }
        
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();
 
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();
 
        $method->setCarrier('tableratepersku');
        $method->setCarrierTitle($this->getConfigData('title'));
 
        $method->setMethod('tableratepersku');
        $method->setMethodTitle($this->getConfigData('name'));
 
        $amount = 0;
        foreach($query_result as $val) {
            $amount += $val['price'];
            
        }

        /* if($this->getConfigData('handling_type') == 'P'){
            $finalAmount = $amount + (($amount * $this->getConfigData('handling_fee'))/100); 
        }else{
            $finalAmount = $amount + $this->getConfigData('handling_fee'); 
        } */
        
        /* End */
        $test = '';
        if ($amount <= 0) {
            return;
        }
 
        $method->setPrice($amount);
        $method->setCost($amount);
 
        $result->append($method);

        return $result;
    }

    private function getDestPostcodePrefix($getDestPostcode)
    {
        if (!preg_match("/^(.+)-(.+)$/", $getDestPostcode, $zipParts)) {
            return $getDestPostcode;
        }

        return $zipParts[1];
    }
}