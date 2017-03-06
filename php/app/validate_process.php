<?php


//validate_process.php


echo "In validate_process.php";

/** 
 * process property input or update
 * load our objects
 */
require_once("../init.php");


if(class_exists('GBP_PROPERTY'))
{
	$prop = new GBP_PROPERTY;
}
