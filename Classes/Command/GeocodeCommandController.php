<?php

namespace Ujamii\UjamiiGeocoder\Command;

use Geocoder\Geocoder;
use Geocoder\Query\GeocodeQuery;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use Ujamii\UjamiiGeocoder\Service\GeoCoderService;

/**
 * Class GeocodeCommandController
 * @package Ujamii\UjamiiGeocoder\Command
 */
class GeocodeCommandController extends CommandController {

    /**
     * Fills missing geo data in configured tables.
     */
    public function fillMissingGeoCodingDataCommand()
    {
        foreach ($GLOBALS['TCA'] as $table => $tca) {
            if (isset($GLOBALS['TCA'][$table]['ctrl']['geocoder'])) {
                $geocoderConfig = GeoCoderService::checkGeocoderConfig( $GLOBALS['TCA'][ $table ] );
                /* @var $geocoder Geocoder */
                $geocoder = GeoCoderService::getGeoCoder( $geocoderConfig );

                $this->outputLine('Found config for table "%s"', [$table]);

                $res      = $GLOBALS['TYPO3_DB']->exec_SELECTquery( '*', $table, $geocoderConfig['latField'] . ' = 0 OR ' . $geocoderConfig['lngField'] . ' = 0' );
                while($origData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc( $res )) {

                    try {
                        $stringToGeocode = GeneralUtility::callUserFunction( $geocoderConfig['getAddressString'], $origData, $this, '', 2 );
                        $result = $geocoder->geocodeQuery(GeocodeQuery::create($stringToGeocode));
                        if ( ! $result->isEmpty() ) {
                            $firstResult = $result->first();

                            $fieldArray = [];
                            $fieldArray[ $geocoderConfig['latField'] ] = round($firstResult->getCoordinates()->getLatitude(), 6);
                            $fieldArray[ $geocoderConfig['lngField'] ] = round($firstResult->getCoordinates()->getLongitude(), 6);
                            $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, 'uid = ' . $origData['uid'], $fieldArray);

                            $this->outputLine('Updated record "%s:%s"', [$table, $origData['uid']]);
                        }
                    } catch (\Geocoder\Exception\Exception $e) {
                        GeneralUtility::devLog($e->getMessage(), 'ujamii_geocoder', GeneralUtility::SYSLOG_SEVERITY_ERROR);
                    } catch (\Exception $e) {
                        GeneralUtility::devLog($e->getMessage(), 'ujamii_geocoder', GeneralUtility::SYSLOG_SEVERITY_ERROR);
                    }
                }
            }
        }
    }
}