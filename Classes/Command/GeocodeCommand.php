<?php

namespace Ujamii\UjamiiGeocoder\Command;

use Geocoder\Geocoder;
use Geocoder\Query\GeocodeQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Ujamii\UjamiiGeocoder\Service\GeoCoderService;

/**
 * Class GeocodeCommandController
 * @package Ujamii\UjamiiGeocoder\Command
 */
class GeocodeCommand extends Command
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Configures the command.
     */
    protected function configure(): void
    {
        $this->setDescription('Populates rows with 0 values for the lat/lng fields.');
    }

    /**
     * Fills missing geo data in configured tables.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        foreach ($GLOBALS['TCA'] as $table => $tca) {
            if (isset($GLOBALS['TCA'][$table]['ctrl']['geocoder'])) {
                $geocoderConfig = GeoCoderService::checkGeocoderConfig($GLOBALS['TCA'][$table]);
                /* @var $geocoder Geocoder */
                $geocoder = GeoCoderService::getGeoCoder($geocoderConfig);

                $output->writeln("Found config for table '{$table}'");

                /** @var QueryBuilder $queryBuilder */
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                $res          = $queryBuilder
                    ->select('*')
                    ->from($table)
                    ->where($queryBuilder->expr()->orX(
                        $queryBuilder->expr()->eq($geocoderConfig['latField'], 0),
                        $queryBuilder->expr()->eq($geocoderConfig['lngField'], 0)
                    ))
                    ->execute();
                $output->writeln("Found {$res->rowCount()} relevant records");

                while ($origData = $res->fetch()) {
                    try {
                        $stringToGeocode = GeneralUtility::callUserFunction($geocoderConfig['getAddressString'], $origData, $this);
                        $result          = $geocoder->geocodeQuery(GeocodeQuery::create($stringToGeocode));
                        if ( ! $result->isEmpty()) {
                            $firstResult = $result->first();

                            /** @var QueryBuilder $updateQuery */
                            $updateQuery = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                            $result = $updateQuery
                                ->update($table)
                                ->set($geocoderConfig['latField'], round($firstResult->getCoordinates()->getLatitude(), 6))
                                ->set($geocoderConfig['lngField'], round($firstResult->getCoordinates()->getLongitude(), 6))
                                ->where($queryBuilder->expr()->eq('uid', $origData['uid']))
                                ->execute()
                            ;
                            if (1 === $result) {
                                $output->writeln("Updated record {$table}:{$origData['uid']}");
                            } else {
                                $output->writeln("FAILED to updated record {$table}:{$origData['uid']}");
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
    }
}