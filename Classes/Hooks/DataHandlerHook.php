<?php

namespace Ujamii\UjamiiGeocoder\Hooks;

use Geocoder\Geocoder;
use Geocoder\Query\GeocodeQuery;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Ujamii\UjamiiGeocoder\Service\GeoCoderService;

/**
 * Class DataHandlerHook
 * @package Ujamii\UjamiiGeocoder\Hooks
 */
class DataHandlerHook {

	/**
	 * @param string $status
	 * @param string $table
	 * @param integer $uid
	 * @param array $fieldArray
	 * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
	 *
	 * @see \TYPO3\CMS\Core\DataHandling\DataHandler::process_datamap
	 */
	public function processDatamap_postProcessFieldArray($status, $table, $uid, &$fieldArray, &$dataHandler) {
		if (isset($GLOBALS['TCA'][$table]['ctrl']['geocoder'])) {
			try {
				$geocoderConfig = GeoCoderService::checkGeocoderConfig( $GLOBALS['TCA'][ $table ] );
				if ( $status == 'new' ) {
					$triggered = true;
				} else {
					$changedFields = array_keys( $fieldArray );
					if ( count( array_intersect( $changedFields, $geocoderConfig['triggerFields'] ) ) > 0 ) {
						$triggered = true;
					} else {
						$triggered = false;
					}
				}

				if ( $triggered ) {
					if ( is_numeric( $uid ) ) {
						$res      = $GLOBALS['TYPO3_DB']->exec_SELECTquery( '*', $table, 'uid = ' . $uid );
						$origData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc( $res );
					} else {
						$origData = array();
					}
					$mergedData      = array_merge( $origData, $fieldArray );
					$stringToGeocode = GeneralUtility::callUserFunction( $geocoderConfig['getAddressString'], $mergedData, $this, '', 2 );

					/* @var $geocoder Geocoder */
					$geocoder = GeoCoderService::getGeoCoder( $geocoderConfig );

					$result = $geocoder->geocodeQuery( GeocodeQuery::create( $stringToGeocode ) );
					if ( ! $result->isEmpty() ) {
						$firstResult = $result->first();

						$fieldArray[ $geocoderConfig['latField'] ] = round($firstResult->getCoordinates()->getLatitude(), 6);
						$fieldArray[ $geocoderConfig['lngField'] ] = round($firstResult->getCoordinates()->getLongitude(), 6);
					}
				}
			} catch (\Geocoder\Exception\Exception $e) {
				GeneralUtility::devLog($e->getMessage(), 'ujamii_geocoder', GeneralUtility::SYSLOG_SEVERITY_ERROR);
			} catch (\Exception $e) {
				GeneralUtility::devLog($e->getMessage(), 'ujamii_geocoder', GeneralUtility::SYSLOG_SEVERITY_ERROR);
			}
		}
	}
}