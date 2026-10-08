<?php

/**
 * @category OpenCart
 * @package StdE
 * @description Standard Library for creating of Extensions
 * @version 1.0.1
 * @copyright CodeCart PRO, https://codecartpro.com
 */

// Different OpenCart Versions
if (version_compare(VERSION, '3.0') >= 0) {
	$oc_v = '3.0';
} elseif(version_compare(VERSION, '2.3') >= 0) {
	$oc_v = '2.3';
} elseif (version_compare(VERSION, '2.2') >= 0) {
	$oc_v = '2.2';
} elseif (version_compare(VERSION, '2.1') >= 0) {
	$oc_v = '2.1';
} elseif (version_compare(VERSION, '2.0') >= 0) {
	$oc_v = '2.0';
} elseif (version_compare(VERSION, '1.5.6') >= 0) {
	$oc_v = '1.5.6';
} else {
	throw new Exception("StdE: OpenCart version " . VERSION . " is not supported by ImportXML Clean.");
}

if (is_file(DIR_SYSTEM . 'library/stde/stde_' . $oc_v . '.php')) {
	require_once DIR_SYSTEM . 'library/stde/stde_' . $oc_v . '.php';
} else {
	throw new Exception('StdE: required library file is missing: ' . DIR_SYSTEM . 'library/stde/stde_' . $oc_v . '.php');
}