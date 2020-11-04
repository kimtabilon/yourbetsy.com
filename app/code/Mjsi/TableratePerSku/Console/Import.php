<?php
namespace Mjsi\TableratePerSku\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Magento\Framework\App\ObjectManager;

class Import extends Command
{

    protected function configure()
    {
        $this->setName('tableratepersku:import');
        $this->setDescription('Import Tablerate Per SKU direct to database');
       
        parent::configure();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $readConnection = $resource->getConnection('core_read');
        
        $connection = $resource->getConnection();
        $handle = fopen('https://console.yourbetsy.com/storage/tablerate/forimport/import.csv', 'r');
        $items = [];
        while (! feof($handle)) { 
            $item = fgetcsv($handle); 
            $country = $item[0];
            $region = $item[1];
            $from_DB = $readConnection->fetchAll("SELECT region_id FROM directory_country_region WHERE `country_id` = '{$country}' AND `code` = '{$region}'");
            foreach ($from_DB as $key => $value) {
                $check_exist = $readConnection->fetchOne("SELECT pk FROM shipping_tablerate_persku 
                                                        WHERE `website_id` = 1 
                                                        AND `dest_country_id` = '{$country}'
                                                        AND `dest_region_id` = '{$value['region_id']}'
                                                        AND `dest_zip` = '{$item[2]}'
                                                        AND `sku` = '{$item[3]}'
                                                        limit 1
                                                        ");
                $tableratesku_pk = '';
                if ($check_exist) {
                    $tableratesku_pk = $check_exist;
                }
                $items[] = [
                    $country, $value['region_id'], $item[2], $item[3], $item[4], $tableratesku_pk
                ];
            }
        }

        fclose($handle);

        foreach ($items as $val) {
            if ($val['5'] != '') {
                $connection->query("UPDATE shipping_tablerate_persku SET `price` = '{$val['4']}' WHERE `pk` = '{$val['5']}'");
            }else {
                $connection->query("INSERT INTO `shipping_tablerate_persku`(`website_id`, `dest_country_id`, `dest_region_id`, `dest_zip`, `price`, `sku`) 
                                    VALUES (1, '$val[0]', '$val[1]', '$val[2]', '$val[4]', '$val[3]')
                                ");
            }
        }

        /* $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/mylog.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($items); */
        $output->writeln("DONE");
    }
}