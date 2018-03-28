<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {
	    $GLOBALS["TYPO3_CONF_VARS"]["SC_OPTIONS"]["t3lib/class.t3lib_tcemain.php"]["processDatamapClass"]['ujamii_geocoder'] = "EXT:parisax_partner/Hooks/coordinates_tca_hook.php:tx_parisaxpartner_tcemainprocdm";
    }
);
