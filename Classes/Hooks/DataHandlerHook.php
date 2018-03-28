<?php

namespace Ujamii\UjamiiGeocoder\Hooks;

use Geocoder\Query\GeocodeQuery;
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
				$geocoderConfig = $this->checkGeocoderConfig($GLOBALS['TCA'][$table]);

				if ($status == 'new') {
					$triggered = true;
				} else {
					$changedFields = array_keys($fieldArray);
					if (count(array_intersect($changedFields, $geocoderConfig['triggerFields'])) > 0) {
						$triggered = true;
					} else {
						$triggered = false;
					}
				}

				if ($triggered) {
					if (is_numeric($uid)) {
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $table, 'uid = ' . $uid);
						$origData = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					} else {
						$origData = array();
					}
					$mergedData = array_merge($origData, $fieldArray);
					$stringToGeocode = GeneralUtility::callUserFunction($geocoderConfig['getAddressString'], $mergedData, $this);

					$httpClient = new \Http\Adapter\Guzzle6\Client();
					$provider = new \Geocoder\Provider\GoogleMaps\GoogleMaps($httpClient);
					$geocoder = new \Geocoder\StatefulGeocoder($provider, 'en');

					$result = $geocoder->geocodeQuery(GeocodeQuery::create($stringToGeocode));
					if (!$result->isEmpty()) {
						$firstResult = $result->first();

						$fieldArray[$geocoderConfig['latField']] = $firstResult->getCoordinates()->getLatitude();
						$fieldArray[$geocoderConfig['lngField']] = $firstResult->getCoordinates()->getLongitude();
					}
				}
			} catch (\Exception $e) {
				//TODO: write to log?
			}
		}
	}

	/**
	 * @param array $tableTca The TCA array for one table.
	 *
	 * @return array The checked ['ctrl']['geocoder'] part of the given array.
	 */
	protected function checkGeocoderConfig(array $tableTca) {
		$config = $tableTca['ctrl']['geocoder'];
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
		if (!isset($config['latField'])) {
			$config['latField'] = 'lat';
		}
		if (!isset($config['lngField'])) {
			$config['lngField'] = 'lng';
		}
		return $config;
	}
}