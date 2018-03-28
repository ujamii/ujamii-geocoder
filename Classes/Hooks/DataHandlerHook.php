<?php

namespace Ujamii\UjamiiGeocoder\Hooks;

use Geocoder\Geocoder;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Http\Client\HttpClient;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
				$geocoderConfig = $this->checkGeocoderConfig( $GLOBALS['TCA'][ $table ] );

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
					$stringToGeocode = GeneralUtility::callUserFunction( $geocoderConfig['getAddressString'], $mergedData, $this );

					/* @var $httpClient HttpClient */
					$httpClient = GeneralUtility::makeInstance( $geocoderConfig['httpClientClass'] );
					/* @var $provider Provider */
					$provider = GeneralUtility::makeInstance( $geocoderConfig['providerClass'], $httpClient);
					/* @var $geocoder Geocoder */
					$geocoder = GeneralUtility::makeInstance( $geocoderConfig['geocoderClass'], $provider, $geocoderConfig['locale'] );

					$result = $geocoder->geocodeQuery( GeocodeQuery::create( $stringToGeocode ) );
					if ( ! $result->isEmpty() ) {
						$firstResult = $result->first();

						$fieldArray[ $geocoderConfig['latField'] ] = $firstResult->getCoordinates()->getLatitude();
						$fieldArray[ $geocoderConfig['lngField'] ] = $firstResult->getCoordinates()->getLongitude();
					}
				}
			} catch (\Geocoder\Exception\Exception $e) {
				GeneralUtility::devLog($e->getMessage(), 'ujamii_geocoder', GeneralUtility::SYSLOG_SEVERITY_ERROR);
			} catch (\Exception $e) {
				GeneralUtility::devLog($e->getMessage(), 'ujamii_geocoder', GeneralUtility::SYSLOG_SEVERITY_ERROR);
			}
		}
	}

	/**
	 * @param array $tableTca The TCA array for one table.
	 *
	 * @return array The checked ['ctrl']['geocoder'] part of the given array.
	 * @throws \LogicException
	 */
	protected function checkGeocoderConfig(array $tableTca) {
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
}