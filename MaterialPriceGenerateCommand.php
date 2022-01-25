<?php

namespace MaterialBundle\Command;

use MaterialBundle\Entity\Material;
use MaterialBundle\Entity\MaterialPrice;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaterialPriceGenerateCommand extends Command
{

    const RUB_CURRENCY = 'RUB';
    const EURO_CURRENCY = 'EUR';
    const EURO_TO_RUB_RATE = 81.42;

    protected static $defaultName = 'material:price:generate';

    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Material price updating start !');

        $em = $this->container->get('doctrine')->getManager();

        $insertCount = 0;

        /**
         * @var $materialRep MaterialPrice
         */
        $materialPriceRep = $em->getRepository(MaterialPrice::class);

        /**
         * @var $prices
         */
        $prices = $materialPriceRep->createQueryBuilder('mp')
                                   ->select('mp')
                                   ->getQuery()
                                   ->getArrayResult();

        // material items
        $materials = $em->getRepository(Material::class)->findAll();


        if(count($prices)){

            $currencyArray = $this->getCurrencyArray($prices);
            unset($prices);

            if(isset($currencyArray[self::RUB_CURRENCY]) && count($currencyArray[self::RUB_CURRENCY])){

                foreach ($currencyArray[self::RUB_CURRENCY] as $item){

                    // search if isset euro price
                    $euroKey = isset($currencyArray[self::EURO_CURRENCY]) ? array_search($item['material'], array_column($currencyArray[self::EURO_CURRENCY], 'material')) : false;

                    if($euroKey === false){

                        $euroPrice = function (self $thisObject) use ($item){
                            $euroPrice = $item['price'] / $thisObject::EURO_TO_RUB_RATE;
                            return $euroPrice > 100 ? (ceil($euroPrice / 100)) * 100 : round($euroPrice);
                        };

                        if($materialItem = $this->getMaterialById($materials, $item['material'])){

                            $newEuroItem = new MaterialPrice();
                            $newEuroItem->setCurrency(self::EURO_CURRENCY);
                            $newEuroItem->setPrice((float)$euroPrice);
                            $newEuroItem->setMaterial($materialItem);

                            $em->persist($newEuroItem);

                            ++$insertCount;
                        }

                    }

                }
            }

        }

        // flush the remaining objects
        $em->flush();
        $em->clear();

        $output->writeln("Updated $insertCount prices");
        $output->writeln('Material price updating end !');
    }
    
    /**
     * @param $prices
     * @return array
     */
    public function getCurrencyArray( $prices ): array
    {
        $currencyArray = [];

        if(count($prices)){
            foreach ($prices as $price){

                $currencyTmp = $price['currency'];
                unset($price['currency']);

                $currencyArray[$currencyTmp][] = $price;
            }
        }

        return $currencyArray;
    }

    /**
     * @param $materials
     * @param $id
     * @return mixed
     */
    public function getMaterialById( &$materials, $id )
    {
        $materialItem = false;

        if(count($materials)){
            foreach ($materials as $material){
                if($material->getId() == $id){
                    $materialItem = $material;
                    break;
                }
            }
        }

        return $materialItem;
    }

}