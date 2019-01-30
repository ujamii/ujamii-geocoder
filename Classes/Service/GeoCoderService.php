<?php

namespace Ujamii\UjamiiGeocoder\Service;

use Geocoder\Geocoder;
use Geocoder\Provider\Provider;
use Http\Client\HttpClient;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class GeoCoderService
 * @package Ujamii\UjamiiGeocoder\Service
 */
class GeoCoderService {

    /**
     * @param array $tableTca The TCA array for one table.
     *
     * @return array The checked ['ctrl']['geocoder'] part of the given array.
     * @throws \LogicException
     */
    public static function checkGeocoderConfig(array $tableTca) {
        $defaults = [
            'locale' => 'de',
            'latField' => 'lat',
            'lngField' => 'lng',
            'httpClientClass' => \Http\Adapter\Guzzle6\Client::class,
            'providerClass' => \Geocoder\Provider\GoogleMaps\GoogleMaps::class,
            'geocoderClass' => \Geocoder\StatefulGeocoder::class
        ];
        $config = array_merge($defaults, $tableTca['ctrl']['geocoder']);

        // there needs to be at least one field configured to trigger the geocoding process.
        if (!isset($config['triggerFields']) || !is_array($config['triggerFields']) || empty($config['triggerFields'])) {
            throw new \LogicException('no triggerFields set in TCA|tableName|ctrl|geocoder');
        } else {
            foreach ($config['triggerFields'] as $triggerField) {
                if (!isset($tableTca['columns'][$triggerField])) {
                    throw new \LogicException(sprintf('triggerField "%s" not set in TCA|tableName|columns|fieldName', $triggerField));
                }
            }
        }

        // make sure there are target fields set.
        if (!isset($tableTca['columns'][$config['latField']])) {
            throw new \LogicException(sprintf('latField "%s" not set in TCA|tableName|columns|fieldName', $config['latField']));
        }
        if (!isset($tableTca['columns'][$config['lngField']])) {
            throw new \LogicException(sprintf('lngField "%s" not set in TCA|tableName|columns|fieldName', $config['lngField']));
        }

        // verify class configuration
        if (!is_subclass_of($config['httpClientClass'], HttpClient::class)) {
            throw new \LogicException(sprintf('httpClientClass "%s" is not of type "%s"', $config['httpClientClass'], HttpClient::class));
        }
        if (!is_subclass_of($config['providerClass'], Provider::class)) {
            throw new \LogicException(sprintf('providerClass "%s" is not of type "%s"', $config['providerClass'], Provider::class));
        }
        if (!is_subclass_of($config['geocoderClass'], Geocoder::class)) {
            throw new \LogicException(sprintf('geocoderClass "%s" is not of type "%s"', $config['geocoderClass'], Geocoder::class));
        }
        return $config;
    }

    /**
     * @param array $geocoderConfig
     *
     * @return HttpClient
     */
    public static function getHttpClient($geocoderConfig)
    {
        /* @var $httpClient HttpClient */
        return GeneralUtility::makeInstance( $geocoderConfig['httpClientClass'] );
    }

    /**
     * @param array $geocoderConfig
     * @param HttpClient $httpClient
     *
     * @return Provider
     */
    public static function getProvider($geocoderConfig, HttpClient $httpClient)
    {
        /* @var $provider Provider */
        if (!is_array($geocoderConfig['providerParams'])) {
            $geocoderConfig['providerParams'] = [];
        }
        $geocoderConfig['providerParams'][0] = $httpClient;
        ksort($geocoderConfig['providerParams']);

        return GeneralUtility::makeInstance( $geocoderConfig['providerClass'], ...$geocoderConfig['providerParams']);
    }

    /**
     * @param array $geocoderConfig
     *
     * @return Geocoder
     */
    public static function getGeoCoder($geocoderConfig)
    {
        $httpClient = GeoCoderService::getHttpClient($geocoderConfig);
        $provider = GeoCoderService::getProvider($geocoderConfig, $httpClient);
        /* @var $geocoder Geocoder */
        return GeneralUtility::makeInstance( $geocoderConfig['geocoderClass'], $provider, $geocoderConfig['locale'] );
    }

}