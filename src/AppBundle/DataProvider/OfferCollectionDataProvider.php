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

    protected function getCarrierName(array $carriers,string $code) {
        foreach ($carriers as $key => $value) {
            if ($value->code === $code) {
                return $value->name;
            }
        }
        return $code;
    }

    protected function getCityName(array $cities,string $code) {
        foreach ($cities as $key => $value) {
            if ($value->getStreetAddress() === $code) {
                return $value;
            }
        }
        $temp = new PostalAddress();
        $temp->setId(0);
        return $temp;
    }

    protected function getAirport(array $airports,string $code) {
        foreach ($airports as $key => $value) {
            if ($value->getIataCode() === $code) {
                return $value;
            }
        }
        $temp = new Airport();
        $temp->setId(0);
        return $temp;
    }

    public function getCollection(string $resourceClass, string $operationName = null)
    {
        if (Offer::class !== $resourceClass) {
            throw new ResourceClassNotSupportedException();
        }

        $skyscannerKey = 'un875336557978775954531833342497';
        $googleQPXKey = 'AIzaSyCH14oiphFMQ2Tx6qzP8bAIH781kmWtAsw';
        // https://developers.google.com/qpx-express/v1/trips/search
        $googleURL = 'https://www.googleapis.com/qpxExpress/v1/trips/search?key=AIzaSyCH14oiphFMQ2Tx6qzP8bAIH781kmWtAsw';//+$googleQPXKey;

        $request = $this->requestStack->getCurrentRequest();
        $props = $request->query->all();

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
            $inboundPartialDate =  $da->format('YYYY-mm-dd');
        } else {
            $inboundPartialDate = '2017-12-10';
        }
        if (array_key_exists('itemOffered_departureTime',$props)) {
            $dd = \DateTime::createFromFormat("D M d Y",$props['itemOffered_departureTime']);
            $outboundPartialDate =  $dd->format('YYYY-mm-dd');
        } else {
            $outboundPartialDate = '2017-12-20';
        }
        // $propKeys = array_keys($props);

        // QPX query
        $slice = array();
        $slice[0] = array(
            "kind" => "qpxexpress#sliceInput",
            'origin' => $originPlace,
            'destination' => $destinationPlace,
            'date' => $inboundPartialDate);
        $slice[1] = array(
            "kind" => "qpxexpress#sliceInput",
            'origin' => $destinationPlace,
            'destination' => $originPlace,
            'date' => $outboundPartialDate);
        $passengers = array(
            "kind" => "qpxexpress#passengerCounts",
            'adultCount' => 1,
            'infantInLapCount' => 0,
            'infantInSeatCount' => 0,
            'childCount' => 0,
            'seniorCount' => 0
        );
        $flight = array('slice'=>$slice,'passengers'=>$passengers,'solutions'=>30,'refundable'=>false);

        $headers = array('Accept' => 'application/json',"Content-Type" => "application/json");


            $googleReq = array('request'=>$flight);
            $body = Unirest\Request\Body::json($googleReq);
            dump($body);
            $googleResponse = Unirest\Request::post($googleURL,$headers,$body);
            dump($googleResponse);
            if ($googleResponse->code === 200){
                $quotes = $googleResponse->body->trips->tripOption;
                $tripData = $googleResponse->body->trips->data;
                $cityArray = $tripData->city;
                $addresses = array();
                $airports = array();
                $airportArray = $tripData->airport;
                foreach ($cityArray as $key => $value) {
                    $addresses[$key] = new PostalAddress();
                    $addresses[$key]->setId($key+1);
                    $addresses[$key]->setAddressLocality($value->name);
                    $addresses[$key]->setAddressRegion("");
                    $addresses[$key]->setPostalCode("");
                    $addresses[$key]->setStreetAddress($value->code);
                }
                foreach ($airportArray as $key => $value) {
                    $airports[$key] = new Airport();
                    $airports[$key]->setIataCode($value->code);
                    $airports[$key]->setId($key+1);
                    $airports[$key]->setAddress($this->getCityName($addresses,$value->city));
                }

                $carrierArray = $tripData->carrier;
                $airportKey = 0;
                foreach ($quotes as $key => $value) {
                    $totalFare = $value->saleTotal;
                    $depart = $value->slice[0];
                    $ret = $value->slice[1];
                    // foreach ($depart->segment as $depKey => $depValue) {
                    $depValue = $depart->segment[0];
                        $flightLeg = $depValue->leg[0];
                        $departureTime = \DateTime::createFromFormat('Y-m-d\TH:iP',$flightLeg->departureTime);
                        $depCarrier = $this->getCarrierName($carrierArray,$depValue->flight->carrier);
                        $origin = $this->getAirport($airports,$flightLeg->origin);
                    // }
                    // foreach ($return->segment as $retKey => $retValue) {
                    $retValue = $ret->segment[0];
                        $flightLeg = $retValue->leg[0];
                        $arrivalTime = \DateTime::createFromFormat('Y-m-d\TH:iP',$flightLeg->departureTime);
                        $retCarrier = $this->getCarrierName($carrierArray,$retValue->flight->carrier);
                        $destination = $this->getAirport($airports,$flightLeg->origin);
                        dump($destination);
                    // }

                    $flights[$key] = new Flight();
                    $flights[$key]->setId($key+1);
                    $flights[$key]->setAdditionalType('https://schema.org/Product');
                    $flights[$key]->setArrivalAirport($destination);
                    $flights[$key]->setDepartureAirport($origin);
                    $flights[$key]->setDepartureTime($departureTime);
                    $flights[$key]->setArrivalTime($arrivalTime);
                    if ($depCarrier != $retCarrier) {
                        $flights[$key]->setProvider($depCarrier+','+$retCarrier);
                    } else {
                        $flights[$key]->setProvider($retCarrier);
                    }
                    $offered[$key] = new Offer();
                    $offered[$key]->setId($key+1);
                    $price = floatval(substr($totalFare, 3));
                    $curr = substr($totalFare, 0, 3);
                    $offered[$key]->setPrice($price);
                    $offered[$key]->setPriceCurrency($curr);
                    $offered[$key]->setSeller('qpx-express');
                    $offered[$key]->setItemOffered($flights[$key]);
                }

            } else {
                // Skyscanner variables
                $query = array();
                $query['apikey'] = $skyscannerKey;
                $marketCountry = 'GR';
                $currency = 'EUR';
                $locale = 'en-US';
                $inboundPartialDate = '';
                $url = 'http://partners.api.skyscanner.net/apiservices/browsequotes/v1.0/'.$marketCountry.'/'.$currency.'/'.$locale.'/'.$originPlace.'/'.$destinationPlace.'/'.$outboundPartialDate.'/'.$inboundPartialDate;
                $response = Unirest\Request::get($url,$headers,$query);
                dump($response);
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
            }

            return $offered;
    }
}