# ujamii-geocoder

[![Packagist](https://img.shields.io/packagist/v/ujamii/ujamii-geocoder.svg?colorB=green&style=flat)](https://packagist.org/packages/ujamii/ujamii-geocoder)

Connecting http://geocoder-php.org/Geocoder with TYPO3 DataHandler API.
With this extension you can easily add geo data to records while they are changed by editors in the TYPO3 backend. You just have to configure
how your entity "looks like" in the eyes of the geocoder via TCA anf that's it.

* [Installation](#installation)
* [Usage](#usage)
* [Example](#example)
* [TODOs](#todos)

## Installation

Currently only works in composer mode of TYPO3, so

```shell
composer require ujamii/ujamii-geocoder
```

## Usage

Just add a new config array to the `ctrl` section of your TCA.

```php
..['ctrl']['geocoder'] = [
	'triggerFields' => ['street', 'zip', 'city'],
	'getAddressString' => 'Your\Namespace\Domain\Model\YourEntity->getAddressString'
];
```

And provide a method (e.g. in an entity or helper class) to generate a compound
address string based on the database data of one entity (example below).

### Options

Those options are possible:

**triggerFields (mandatory)**

Changes in those fields will trigger the process of geocoding.

**getAddressString (mandatory)**

A method called via `TYPO3\CMS\Core\Utility\GeneralUtility::callUserFunction` which is supposed
to return a complete address string. The first parameter provided to this method is the merged
data array (unchanged entity data from db + changed values from the backend form).

Example:
```php
public function getAddressString($dataArray) {
	return $dataArray['location'];
}
```

**locale** (default: de)

The locale which is used in the geocoder. 

**latField** (default: lat)

Name of the target field for the latitude value.

**lngField** (default: lng)

Name of the target field for the longitude value.

**httpClientClass** (default: \Http\Adapter\Guzzle6\Client::class)

Class name of the http client, see [possible packages](https://packagist.org/providers/php-http/client-implementation)

**providerClass** (default: \Geocoder\Provider\GoogleMaps\GoogleMaps::class)

Class name of the provider, see [possible packages](https://packagist.org/providers/geocoder-php/provider-implementation)

**providerParams**

Optional parameters for the provider. (e.g. an API key for Google Maps)

**geocoderClass** (default: \Geocoder\StatefulGeocoder::class)

Class name of the geocoder.

## Example

Let's assume the record is a news record in the database with 3 fields: location, lat and lng. The field location
contains something like `Alexanderplatz, Berlin, Deutschland` and lat and lng are the fields you want to be filled
automatically as soon as an editor changes the location.

Add this to your `typo3conf/ext/your_extension/Configuration/TCA/Overrides/tx_news_domain_model_news.php` 
or `typo3conf/ext/your_extension/Configuration/TCA/tx_ext_domain_model_entity.php` file.

```php
$GLOBALS['TCA']['tx_news_domain_model_news']['ctrl']['geocoder'] = [
	'triggerFields' => ['street', 'zip', 'city'],
	'getAddressString' => 'Your\Namespace\Domain\Model\YourEntity->getAddressString',
	'providerParams' => [
        0 => null,
        1 => null,
        2 => '<GMAPS_API_KEY>'
    ]
];
```

In `YourEntity`, add a method like this:
```php
public function getAddressString($dataArray) {
	return sprintf('%s, %s %s', $dataArray['street'], $dataArray['zip'], $dataArray['city']);
}
```

## Usage as command or in scheduler

The extension also provides command to populate rows with 0 values for the lat/lng fields. 
It reads the configuration from TCA and iterates through each configured table, searching with lat = 0 OR lng = 0. For each matching row, the geocoding
process is executed and the values are then updated in the database. The command produces some log output to track what has been done.

As it is a default extbase command controller, this can also be called by a scheduler task

```php
php typo3/cli_dispatch.phpsh extbase geocode:fillmissinggeocodingdata
```

## TODOs

* publish in TER
* right now providerParams[0] is always filled with the httpClient, which may not work for all providers
