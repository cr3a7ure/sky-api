<?php

// src/AppBundle/DataProvider/OfferCollectionDataProvider.php

namespace AppBundle\DataProvider;

use AppBundle\Entity\Offer;
use AppBundle\Entity\Airport;
use AppBundle\Entity\Flight;
use AppBundle\Entity\PostalAddress;
use AppBundle\Entity\GeoCoordinates;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use Unirest;
// use Mashape\UnirestPhp\Unirest;

final class OfferCollectionDataProvider implements CollectionDataProviderInterface
{
  protected $requestStack;

  public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getCollection(string $resourceClass, string $operationName = null)
    {
        if (Offer::class !== $resourceClass) {
            throw new ResourceClassNotSupportedException();
        }
        //Temp Objects

        $skyscannerKey = 'un875336557978775954531833342497';

        $request = $this->requestStack->getCurrentRequest();
        // $test = $request->query;//->get('');
        $props = $request->query->all();
        // dump($props);
        // $da = \DateTime::createFromFormat("D M d Y",$props['itemOffered_arrivalTime']);
        // $dd = \DateTime::createFromFormat("D M d Y",$props['itemOffered_departureTime']);
        // $da->format('Y-m-d');
        // dump($da->format('Y-m-d'));
        // dump($dd);

         if(array_key_exists('itemOffered_arrivalAirport_iataCode',$props)) {
            $destinationPlace = $props['itemOffered_arrivalAirport_iataCode'];
        } else {
          $destinationPlace = 'ATH';
        }
        if (array_key_exists('itemOffered_departureAirport_iataCode',$props)) {
            $originPlace = $props['itemOffered_departureAirport_iataCode'];
        } else {
            $originPlace = 'LGW';
        }
        if (array_key_exists('itemOffered_arrivalTime',$props)) {
            $da = \DateTime::createFromFormat("D M d Y",$props['itemOffered_arrivalTime']);
            // $inboundPartialDate =  $da->format('Y-m-d');
            $inboundPartialDate =  $da->format('Y-m');
        } else {
            $inboundPartialDate = '';
        }
        if (array_key_exists('itemOffered_departureTime',$props)) {
            $dd = \DateTime::createFromFormat("D M d Y",$props['itemOffered_departureTime']);
            // $outboundPartialDate =  $dd->format('Y-m-d');
            $outboundPartialDate =  $dd->format('Y-m');
        } else {
            $outboundPartialDate = '2017-12';
        }
        // $departureTime = \DateTime::createFromFormat("D M d Y",$value->QuoteDateTime);
        $propKeys = array_keys($props);
        // dump($propKeys[1]);
        // dump($props[$propKeys[1]]);
        $headers = array('Accept' => 'application/json');
        $query = array();
        $query['apikey'] = $skyscannerKey;
        // Skyscanner variables
        $marketCountry = 'GR';
        $currency = 'EUR';
        $locale = 'en-US';
        // $originPlace = 'LGW';
        // $destinationPlace = 'ATH';
        // $outboundPartialDate = '2017-06';
        $inboundPartialDate = '';
        $url = 'http://partners.api.skyscanner.net/apiservices/browsequotes/v1.0/'.$marketCountry.'/'.$currency.'/'.$locale.'/'.$originPlace.'/'.$destinationPlace.'/'.$outboundPartialDate.'/'.$inboundPartialDate;

    //     $em = $this->getDoctrine()->getManager();
///offers?itemOffered=arrivalAirport,%5Bobject%20Object%5D,departureAirport,%5Bobject%20Object%5D,departureTime,%5Bobject%20Object%5D,arrivalTime,%5Bobject%20Object%5D

    // // tells Doctrine you want to (eventually) save the Product (no queries yet)
    //     $em->persist($product);

    // // actually executes the queries (i.e. the INSERT query)
    //     $em->flush();

        // if ((isset($props['departureAirport'])) && (isset($props['departureTime']))&& (isset($props['arivalAirport']))&& (isset($props['arrivalTime']))) {
            // $originPlace = $props['departureAirport']);
            // $destinationPlace = $props['arivalAirport'];
            // $outboundPartialDate = $props['departureTime'];
            // $inboundPartialDate = $props['arrivalTime'];

            $response = Unirest\Request::get($url,$headers,$query);
            // dump($response);
            $Quotes = $response->body->Quotes;
            $Places = $response->body->Places;
            // dump($Places);
            $Carriers = $response->body->Carriers;
            $carriers =  array();
            foreach ($Carriers as $key => $value) {
                $carriers[$value->CarrierId] = $value->Name;
            }
            $places = array();
            $places2airports = array();
            $addresses = array();
            $airports = array();
            foreach ($Places as $key => $value) {
                $places[$value->PlaceId] =  array();
                if (array_key_exists('IataCode', get_object_vars($value))) {
                    $addresses[$key] = new PostalAddress();
                    $addresses[$key]->setAddressCountry($value->CountryName);
                    $addresses[$key]->setId($key);
                    $addresses[$key]->setAddressLocality($value->CityName);
                    $addresses[$key]->setAddressRegion("kjhkj");
                    $addresses[$key]->setPostalCode("82100");
                    $addresses[$key]->setStreetAddress('sdf');
                    $airports[$key] = new Airport();
                    $airports[$key]->setIataCode($value->IataCode);
                    $airports[$key]->setId($key);
                    $airports[$key]->setAddress($addresses[$key]);
                    $places[$value->PlaceId]['airport'] = $key;
                }
                foreach (get_object_vars($value) as $key2 => $value2) {
                    $places[$value->PlaceId][$key2] = $value2;
                }
            }

            $flights = array();
            $offered = array();
            // throw new \Exception('DUMPSTERRRRR!');
            $Currencies = $response->body->Currencies;
            $defCurr = $Currencies[0]->Code;
            foreach ($Quotes as $key => $value) {
                // dump($value->OutboundLeg->OriginId);
                // dump($places[$value->OutboundLeg->OriginId]['airport']);
                $origin = $airports[$places[$value->OutboundLeg->OriginId]['airport']];
                $destination = $airports[$places[$value->OutboundLeg->DestinationId]['airport']];
                // dump($destination);
                $flights[$key] = new Flight();
                $flights[$key]->setId($key);
                $flights[$key]->setAdditionalType('https://schema.org/Product');
                $flights[$key]->setArrivalAirport($destination);
                $flights[$key]->setDepartureAirport($origin);
                $departureTime = \DateTime::createFromFormat("Y-m-d?H:i:s",$value->QuoteDateTime);
                $arrivalTime = \DateTime::createFromFormat("Y-m-d?H:i:s",$value->QuoteDateTime);
                $flights[$key]->setDepartureTime($departureTime);
                $flights[$key]->setArrivalTime($arrivalTime);
                $provider = $value->OutboundLeg->CarrierIds; //Array
                if (sizeof($provider)<=0) {
                    $flights[$key]->setProvider('No direct flight!');
                } else {
                    $flights[$key]->setProvider($carriers[$provider[0]]);
                }
                $offered[$key] = new Offer();
                $offered[$key]->setId($key);
                $offered[$key]->setPrice($value->MinPrice);
                $offered[$key]->setPriceCurrency($defCurr);
                $offered[$key]->setSeller('skyscanner');
                $offered[$key]->setItemOffered($flights[$key]);
                // $flights[$key]->setOffers($offered[$key]);
            }

        // $variable = 'Chios';
        // $url = 'https://airports.p.mashape.com/';
        // $headers = array("X-Mashape-Key" => "YFdPBwiJGdmshYQtQEreC8qJuRphp1LOHRejsn2hVY3OdMBnf0",
        //                 "Content-Type" => "application/json",
        //                 "Accept" => "application/json");
        // $query = "{\"search\":\"".$variable."\"}";

        // $response = Unirest\Request::post($url,$headers,$query);
        // dump($response);
            // dump($offered);
            return [$offered];
    }
}