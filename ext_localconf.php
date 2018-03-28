<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {
	    $GLOBALS["TYPO3_CONF_VARS"]["SC_OPTIONS"]["t3lib/class.t3lib_tcemain.php"]["processDatamapClass"]['ujamii_geocoder'] = Ujamii\UjamiiGeocoder\Hooks\DataHandlerHook::class;
    }
);
