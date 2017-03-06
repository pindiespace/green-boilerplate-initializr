<?php

/** 
 * API calls for GBP Initializr
 * Structure of an API call (key in the $_REQUEST array)
 * cmd - which API call to make
 * sid, cid, pid - $SOURCE_PRIMARY, $COMPONENT_PRIMARY, $PROPERTY_PRIMARY reset
 * If they aren't present, the API returns 'all' (entire table)
 *init.php must have been included earlier
 */

//need the init script to include GBP_UTIL.php

require("init.php");

/**
 * strip PHP and HTML tags from incoming data
 * also happens in the internal classes
 */


/**
 * for some reason, GBP_CLIENT fails if we try to
 * create the classes locally in the switch() statement
 * so, they are created here
 */

if(!class_exists('GBP_PROPERTY'))
{
	echo "Property class missing";
	exit;
}

if(!class_exists('GBP_REFERENCE'))
{
	echo "Reference class missing";
	exit;
}

if(!class_exists('GBP_CLIENT'))
{
	echo "Client class missing";
	exit;
}

if(!class_exists('GBP_CONVERT_BASE'))
{
	echo "Convert base class missing";
	exit;
}

if(!class_exists('GBP_IMPORT_FULLTESTS'))
{
	echo "Import fulltest class missing";
	exit;
}

/**
 * clean up the $_REQUEST array. Since we send our data via Ajax, using
 * the header: "Content-type","application/x-www-form-urlencoded" we get
 * automatic decoding at this end. Decoding 
 */


/**
 * provide standard names matching init.php
 */

if(isset($_REQUEST['sid']))   $SOURCE_PRIMARY         = $_REQUEST['sid'];   //id of current database source
if(isset($_REQUEST['cid']))   $COMPONENT_PRIMARY      = $_REQUEST['cid'];   //id of current component
if(isset($_REQUEST['pid']))   $PROPERTY_PRIMARY       = $_REQUEST['pid'];   //id of current property
if(isset($_REQUEST['clid']))  $CLIENT_PRIMARY         = $_REQUEST['clid'];  //id of current client
if(isset($_REQUEST['clvid'])) $CLIENT_VERSION_PRIMARY = $_REQUEST['clvid']; //version of a client

/**
 * get the API command, and switch
 */

$cmd = $_REQUEST['cmd'];

$cls = ''; //CLASS VARIABLE

switch($cmd)
{
	case "get_reference_list":
		$cls = new GBP_REFERENCE;
		if(isset($cls) && isset($_REQUEST['tnm']) && isset($_REQUEST['iid']))
		{
			$ref_table_name    = $_REQUEST['tnm'];
			$ref_table_item_id = $_REQUEST['iid'];
			$arr = $cls->get_reference_list($ref_table_name, $ref_table_item_id);	
		}
		break;
	
	case "get_property_by_id":
		$cls = new GBP_PROPERTY;
		$arr = $cls->get_property($PROPERTY_PRIMARY);
		break;
	
	case "get_property_by_component":
		if(isset($_REQUEST['list_only']))$list_only = $_REQUEST['list_only'];
		else $list_only = false;
		$cls = new GBP_PROPERTY;
		$arr = $cls->get_all_properties($SOURCE_PRIMARY, $COMPONENT_PRIMARY, $list_only);
		break;

	case "get_client":
		$cls = new GBP_CLIENT;
		$arr = $cls->get_client($CLIENT_PRIMARY, false); //we want the description
		break;
	
	case "get_client_versions":
		$cls = new GBP_CLIENT;
		$arr = $cls->get_client_versions($CLIENT_PRIMARY);
		break;
	
	case "get_client_property_versions":
		$cls = new GBP_CLIENT;
		$arr = $cls->get_client_property_versions($CLIENT_PRIMARY, $PROPERTY_PRIMARY);
		break;
	
	case "get_dependency":
		$dependency_property_id = $_REQUEST['dependency_property_id'];
		$cls = new GBP_PROPERTY;
		$arr = $cls->get_dependency($dependency_property_id);
		break;
	
	case "get_translation":
		$cls = new GBP_PROPERTY;
		$arr = $cls->get_translation($PROPERTY_PRIMARY, $SOURCE_PRIMARY);
		break;
	
	//TODO: add a case "add_device" !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	case "add_device":
		break;
	
	case "insert_new_reference":
		$ref_table_name    = $_REQUEST['tblnm']; //name of table with the reference
		$ref_table_item_id = $_REQUEST['itmid']; //item id (typically the id of the record in above table)
		$ref_date          = $_REQUEST['dat'];   //date of reference, 00-00-0000
		$url               = $_REQUEST['url'];   //url of site page or article
		$title             = $_REQUEST['titl'];  //visible title of URL in hyperlink
		$description       = $_REQUEST['desc'];  //longer description of reference (why we care)
		$cls = new GBP_REFERENCE;
		$arr = $cls->insert_new_reference($ref_table_name, $ref_table_item_id, $ref_date, $url, $title, $description);
		break;
	
	case "insert_new_property":
		$datatype_id  = $_REQUEST['dtyp'];
		$name         = $_REQUEST['nm'];
		$title        = $_REQUEST['title'];
		$description  = $_REQUEST['desc'];
		$cls = new GBP_PROPERTY;
		$arr = $cls->insert_new_property($PROPERTY_PRIMARY, $COMPONENT_PRIMARY, $SOURCE_PRIMARY, $datatype_id, $name, $title, $description);
		break;
		
	case "update_property_field":
		$field_name  = $_REQUEST['fldn']; //field name
		$field_value = $_REQUEST['fldv']; //field value
		$cls = new GBP_PROPERTY;
		$arr = $cls->update_property_field($PROPERTY_PRIMARY, $field_name, $field_value);
		break;

	case "update_exe_required_field":
		$field_name  = $_REQUEST['fldn']; //field name
		$field_value = $_REQUEST['fldv']; //field value
		$cls = new GBP_PROPERTY;
		$arr = $cls->update_property_field($PROPERTY_PRIMARY, $field_name, $field_value);
		$priority_name  = $_REQUEST['priorn'];
		$priority_value = $_REQUEST['priorv'];
		$arr2 = array();
		$arr2 = $cls->update_property_field($PROPERTY_PRIMARY, $priority_name, $priority_value);
		$arr['exe_lock_priority'] = $arr2['column_name'];
		$arr['exe_lock_priority_value'] = $arr2['column_value'];
		$arr2 = '';
		break;
		
	case "update_discovery_field":
		$field_name  = $_REQUEST['fldn']; //field name
		$field_value = $_REQUEST['fldv']; //field value
		$cls = new GBP_PROPERTY;
		$arr = $cls->update_discovery_field($PROPERTY_PRIMARY, $field_name, $field_value);
		break;

	case "update_dependency_field":
		$parent_property_id   = $_REQUEST['prid']; //parent id
		$field_name           = $_REQUEST['fldn']; //field name
		$field_value          = $_REQUEST['fldv']; //field value
		$cls = new GBP_PROPERTY;
		$arr = $cls->update_dependency_field($PROPERTY_PRIMARY, $parent_property_id, $field_name, $field_value);
		break;

	case "update_translation_field":
		$field_name    = $_REQUEST['fldn']; //name of column to alter in translation table (alt_property_id)
		$field_value   = $_REQUEST['apid'];   //id of alternate property
		$alt_source_id = $_REQUEST['asid']; //alternate source (e.g. Modernizr)
		$cls = new GBP_PROPERTY;
		$arr = $cls->update_translation_field($PROPERTY_PRIMARY, $SOURCE_PRIMARY, $alt_source_id, $field_name, $field_value);
		break;
	
	case "update_client_field":
		$field_name  = $_REQUEST['fldn'];
		$field_value = $_REQUEST['fldv'];
		$cls = new GBP_CLIENT;
		$arr = $cls->update_client($CLIENT_PRIMARY, $field_name, $field_value);
		break;
	
	case "update_client_property_value":
		if(isset($_REQUEST['pval'])) $value = $_REQUEST['pval'];
		$cls = new GBP_CLIENT;
		$arr = $cls->update_client_property_versions($CLIENT_PRIMARY, $CLIENT_VERSION_PRIMARY, $PROPERTY_PRIMARY, 'property_value', $value);
		break;

	case "update_client_version_field":
		$field_name  = $_REQUEST['fldn'];
		$field_value = $_REQUEST['fldv'];
		$cls = new GBP_CLIENT;
		$arr = $cls->update_client_versions($CLIENT_VERSION_PRIMARY, $field_name, $field_value);
		break;
	
	case "insert_new_client":
		$nm = $_REQUEST['nm'];
		$title = $_REQUEST['title'];
		$description = $_REQUEST['desc'];
		$cls = new GBP_CLIENT;
		$arr = $cls->insert_new_client($CURRENT_CLIENT, $nm, $title, $description);
		break;
	
	case "insert_new_client_version":
		$sgid    = $_REQUEST['sgid'];    //search group id
		$reldate = $_REQUEST['reldate']; //release date
		$nm      = $_REQUEST['nm'];      //name is 'versionname' in 'client_versions', 'name' in 'client_properties'
		$vers    = $_REQUEST['vers'];    //vers is 'version' in 'client_versions', 'version' in 'client_properties'
		$comm    = $_REQUEST['comm'];    //comments
		$cls = new GBP_CLIENT;
		$arr = $cls->insert_new_client_version($CLIENT_VERSION_PRIMARY, $CLIENT_PRIMARY, $sgid, $reldate, $nm, $vers, $comm);
		break;

	//TODO:THIS SHOULD REALLY BE update_client_version_search_group !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	case "update_client_search_group":
		if(isset($_REQUEST['sgid'])) $search_group_id = $_REQUEST['sgid'];
		$cls = new GBP_CLIENT;
		$arr = $cls->update_client_search_group($CLIENT_VERSION_PRIMARY, $search_group_id);
		break;
		
	case "delete_reference":
		$ref_id = $_REQUEST['refid'];
		$cls = new GBP_REFERENCE;
		$arr = $cls->delete_reference($ref_id);
		break;
	
	case "delete_client":
		$cls = new GBP_CLIENT;
		$arr = $cls->delete_client($CLIENT_PRIMARY);
		break;
	
	case "delete_client_version":
		$cls = new GBP_CLIENT;
		$arr = $cls->delete_client_version($CLIENT_VERSION_PRIMARY);
		break;
	
	case "delete_property":
		$cls = new GBP_PROPERTY;
		$arr = $cls->delete_property($PROPERTY_PRIMARY);
		$arr = false;
		break;

	case "delete_dependency":
		$parent_property = $_REQUEST['prid'];
		$cls = new GBP_PROPERTY;
		$arr = $cls->delete_dependency($PROPERTY_PRIMARY, $parent_property);
		break;
	
	case "change_alt_db_source":
		$selected_source_name = $_REQUEST['fldv'];
		$cls = new GBP_CONVERT_BASE;
		$dir = dirname(__FILE__);
		$arr = $cls->scan_for_files($dir.'/import/'.$selected_source_name.'/');
		break;
	
	case "get_fulltests_list":
		$cls = new GBP_IMPORT_FULLTESTS;
		$arr = $cls->get_all_fulltests();
		break;
		
	case "compare_fulltests_results":
		$cls = new GBP_IMPORT_FULLTESTS;
		$fulltest_id = $_REQUEST['ftid']; //fulltests id
		$client_version_id = $_REQUEST['clvid']; //client-version id
		$arr = $cls->compare_fulltests($fulltest_id, $client_version_id);
		break;
	
	case "commit_fulltests_results":
		$cls = new GBP_IMPORT_FULLTESTS;
		$fulltest_id = $_REQUEST['ftid'];
		$client_version_id = $_REQUEST['clvid'];
		
		//contains a list of properties to ignore
		
		$ign = $_REQUEST['ign'];
		$ignore_arr = array();
		$ignore_arr[] = explode(',', $ign); //TODO: set a limit here
		$arr = $cls->commit_fulltest_results($fulltest_id, $client_version_id, $ignore_arr);
		break;
	
	case "delete_fulltests_results":
		$cls = new GBP_IMPORT_FULLTESTS;
		$fulltest_id = $_REQUEST['ftid']; //fulltests id
		$arr = $cls->delete_fulltests_results($fulltest_id);
		break;
	
	default:
		break;	

}

/** 
 * now echo JSON equivalent to output
 */
if(isset($arr)) 
{
	if($arr === true)
	{
		$arr = array('apiresult' => "true");
	}
	else if($arr === false)
	{
		$arr = array('apiresult' => "false");
	}
	else if(is_array($arr))
	{

		$arr['apiresult'] = "true";
	}
	
	$err = $cls->get_error();
	if(count($err))
	{
		$arr['error'] = $err;
	}
	
	$arr['apicall'] = $cmd;
}
else
{
	$arr = array('apiresult' => false, 'apicall' => $cmd);
}

header('Content-Type: application/json');
echo json_encode($arr);