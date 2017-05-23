<?php

// src/AppBundle/DataProvider/AirportCollectionDataProvider.php

namespace AppBundle\DataProvider;

use AppBundle\Entity\Offer;
use AppBundle\Entity\Airport;
use AppBundle\Entity\Flight;
use AppBundle\Entity\PostalAddress;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Unirest;

final class AirportCollectionDataProvider implements CollectionDataProviderInterface
{
  protected $requestStack;
  protected $managerRegistry;
  // protected $objectManager;

  public function __construct(RequestStack $requestStack,ManagerRegistry $managerRegistry)
    {
        $this->requestStack = $requestStack;
        $this->managerRegistry = $managerRegistry;
        // $this->objectManager = $objectManager;
    }

    public function getCollection(string $resourceClass, string $operationName = null)
    {
        if (Airport::class !== $resourceClass) {
            throw new ResourceClassNotSupportedException();
        }
        // Keyword in Airport Name, City or Code.
        $request = $this->requestStack->getCurrentRequest();
        // $test = $request->query;//->get('');
        $searchParametersObj = $request->query->all();
        $searchParametersKeys = array_keys($searchParametersObj);
        // dump($request);
        // dump($searchParametersObj);
        // dump($searchParametersKeys);
        $searchQuery = [];
        $variable = '';
        foreach ($searchParametersObj as $key => $value) {
            if(is_array($value)){
                // foreach ($value as $i => $arrayValue) {
                //     $chainPropsKey = explode("_", $key);
                //     $propertyKey = end($chainPropsKey);
                //     dump($propertyKey);
                //     $searchQuery[$propertyKey] = $value;
                //     dump($searchQuery[$propertyKey]);
                // }
            } else {
                $chainPropsKey = explode("_", $key);
                $propertyKey = end($chainPropsKey);
                dump($propertyKey);
                $searchQuery[$propertyKey] = $value;
                dump($searchQuery[$propertyKey]);
            }

        }

        if(array_key_exists('iataCode',$searchQuery)) {
            $variable = $searchQuery['iataCode'];
        } elseif (array_key_exists('addressLocality',$searchQuery)) {
            $variable = $searchQuery['addressLocality'];
        } elseif (array_key_exists('addressCountry',$searchQuery)) {
            $variable = $searchQuery['addressCountry'];
        } else {
            $variable = 'PNQ';
        }
        // $variable = 'Chios';
        $url = 'https://airports.p.mashape.com/';
        $headers = array("X-Mashape-Key" => "YFdPBwiJGdmshYQtQEreC8qJuRphp1LOHRejsn2hVY3OdMBnf0",
                        "Content-Type" => "application/json",
                        "Accept" => "application/json");
        // $query['search'] = $variable;
        $temp["search"] = $variable;
        // dump($variable);
        // dump(json_encode($variable));
        $test = 'PNQ';
        $tempQ = "{\"search\":".json_encode($variable)."}";
        $query = "{\"search\":\"".$test."\"}";
        // dump($tempQ);
        // dump($query);
        $data = '';
        $response = Unirest\Request::post($url,$headers,$tempQ);
        dump($response);
        $data = $response->body;
        // dump($data);
        if($data){
            $address = new PostalAddress();
            $address->setAddressCountry($data[0]->cc);
            $address->setId(1);
            $address->setAddressLocality($data[0]->ct);
            $address->setAddressRegion($data[0]->ct);
            $airport = new Airport();
            $airport->setId(1);
            $airport->setIataCode($data[0]->ac);
            $airport->setAddress($address);
        } else {
            $airport = new Airport();
            $airport->setId(0);
            $airport->setIataCode('TEST');
            // $airport->setAddress($address);
        }
        // $airport->setAddress($address);
        // dump($this->managerRegistry);
        // dump($this->managerRegistry->getManagerForClass('AppBundle\Entity\Airport'));
        // $em = $this->managerRegistry->getManagerForClass('AppBundle\Entity\Airport');
        // // $emOrm = ObjectManager::getDoctrine();
        // $em->persist($airport);
        // $em->flush();
        return [$airport];
    }
}