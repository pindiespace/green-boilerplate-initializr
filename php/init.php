<?php 
session_start();

/** 
 * init.php
 * define an object which provides the common database read-wrie
 * operations for this app, as well as output to PHP code
 */

 
//max number of recursion for dependencies. Otherwise, we 
//run the risk of creating an endless loop

$MAX_DEPENDENCY_CHAIN = 5;
 
/** 
 * object for standard low-level database operations
 * get absolute path. Objects are always in a path 
 * relative to this file.
 * dirname(__FILE__) always calculates the location 
 * of this file, even if it is being required by another file
 */
$dir = dirname(__FILE__);

if(file_exists("$dir/lib/GBP_UTIL.php") && file_exists("$dir/lib/GBP_BASE.php"))
{

	require_once("$dir/lib/GBP_UTIL.php");
	$util = new GBP_UTIL;
	
	require_once("$dir/lib/GBP_BASE.php");     //base for all classes except GBP_UTIL
}
else
{
	echo "Base library files not found";
	exit;
}

if(file_exists("$dir/lib/GBP_PROPERTY.php"))
{
	require_once("$dir/lib/GBP_PROPERTY.php");  //property manipulations

}
else
{
	echo "Property library not found";
	exit;
}

if (file_exists("$dir/lib/GBP_CLIENT.php"))
{
	require_once("$dir/lib/GBP_CLIENT.php");   //client manipulations
}
else
{
	echo "Client library not found";
	exit;
}

/**
 * the reference object may ce called on any file
 */
if(file_exists("$dir/lib/GBP_REFERENCE.php"))
{
	require_once("$dir/lib/GBP_REFERENCE.php"); //reference manipulations
}
else
{
	echo "Reference library not found";
	exit;
}

/**
 * get the base import. Derived import class are only loaded in api.php
 */
if(file_exists("$dir/lib/GBP_CONVERT_BASE.php"))
{
	require_once("$dir/lib/GBP_CONVERT_BASE.php");     //convert base class
}
else
{
	echo "Conversion library not found";
	exit;
}

if(file_exists("$dir/lib/GBP_IMPORT_FULLTESTS.php"))
{
	require_once("$dir/lib/GBP_IMPORT_FULLTESTS.php");  //import fulltests class
}
else
{
	echo "Import library not found";
	exit;
}


/**
 * check for default $_GET values, and provide one if 
 * no value is defined.
 */
if(isset($_GET['state']))
{
	$state = $_GET['state'];
}
else
{
	$state = 'entry';
}


/** 
 * GLOBALS
 * for a non-existent property, define a special variable to check for
 */
$DEBUG                = false;

//source databases

$SOURCE_GBP            = 'gbp';
$SOURCE_MODERNIZR      = 'modernizr';
$SOURCE_CANIUSE        = 'caniuse';

//default values for components and sources (may change)

$SOURCE_PRIMARY         = '1000';     //GreenBoilerplate
$COMPONENT_PRIMARY      = '2005';     //HTML Markup
$COMPONENT_GBP          = '2009';     //GBP Configuration
$PROPERTY_PRMARY        = 'hasHTML';
$CLIENT_PRIMARY         = '30000';   //Google Chrome;
$CLIENT_VERSION_PRIMARY = '20';

//the 'system' table groups components into groups

$SYSTEM_CLOUD           = '66000';
$SYSTEM_BROWSER         = '66001';
$SYSTEM_DEVICE          = '66002';
$SYSTEM_USER            = '66003';
$SYSTEM_DESIGNER        = '66004';
$SYSTEM_STAKEHOLDER     = '66005';
$SYSTEM_GBP             = '66006';
	
/** 
 * If we just left a form where we set component or source, 
 * update the defaults.
 */
if(isset($_REQUEST['source']))          $SOURCE_PRIMARY    = $_REQUEST['source'];
if(isset($_REQUEST['component']))       $COMPONENT_PRIMARY = $_REQUEST['component'];
if(isset($_REQUEST['update_property'])) $PROPERTY_PRMARY   = $_REQUEST['update_property'];


