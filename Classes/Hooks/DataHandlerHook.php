<?php

namespace Ujamii\UjamiiGeocoder\Hooks;

use Geocoder\Geocoder;
use Geocoder\Query\GeocodeQuery;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Ujamii\UjamiiGeocoder\Service\GeoCoderService;

/**
 * Class DataHandlerHook
 * @package Ujamii\UjamiiGeocoder\Hooks
 */
class DataHandlerHook
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param string $status
     * @param string $table
     * @param integer $uid
     * @param array $fieldArray
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     *
     * @see \TYPO3\CMS\Core\DataHandling\DataHandler::process_datamap
     */
    public function processDatamap_postProcessFieldArray($status, $table, $uid, &$fieldArray, &$dataHandler)
    {
        if (isset($GLOBALS['TCA'][$table]['ctrl']['geocoder'])) {
            try {
                $geocoderConfig = GeoCoderService::checkGeocoderConfig($GLOBALS['TCA'][$table]);
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
                        /** @var QueryBuilder $queryBuilder */
                        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                        $res          = $queryBuilder
                            ->select('*')
                            ->from($table)
                            ->where($queryBuilder->expr()->eq('uid', $uid))
                            ->execute();
                        $origData     = $res->fetch();
                    } else {
                        $origData = array();
                    }
                    $mergedData      = array_merge($origData, $fieldArray);
                    $stringToGeocode = GeneralUtility::callUserFunction($geocoderConfig['getAddressString'], $mergedData, $this);

                    /* @var $geocoder Geocoder */
                    $geocoder = GeoCoderService::getGeoCoder($geocoderConfig);

                    $result = $geocoder->geocodeQuery(GeocodeQuery::create($stringToGeocode));
                    if ( ! $result->isEmpty()) {
                        $firstResult = $result->first();

                        $fieldArray[$geocoderConfig['latField']] = round($firstResult->getCoordinates()->getLatitude(), 6);
                        $fieldArray[$geocoderConfig['lngField']] = round($firstResult->getCoordinates()->getLongitude(), 6);
                    }
                }
            } catch (\Geocoder\Exception\Exception $e) {
                $this->logger->error($e->getMessage(), ['extension' => 'ujamii_geocoder']);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), ['extension' => 'ujamii_geocoder']);
            }
        }
    }
}
