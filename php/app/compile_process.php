<?php

/**
 * -------------------------------------------------------------------------
 * 
 * generate a propfile (actually a PHP array) with property names stored for 
 * use in constructing a GBP object. This propfile is loaded by GBP during 
 * server-side generation of the GBP object
 * this is PHP -> PHP array output. We construct the array in memory, then 
 * serialize (uncompressed) into a PHP output file
 * process property input or update
 * 
 * load our objects GBP_UTIL, GBP_BASE, GBP_PROPERTY, GBP_CLIENT
 * if we load GBP_PROPERTY and GBP_CLIENT, GBP_BASE and GBP_UTIL must exist.
 *
 * TODO: process some components differently from others
 * client  - client-version-property tables
 * network - network property tables, network hops are low, fast network (?computed on client)
 * server  - server-property tables, host is green, local host, Web Index of server
 * config  - one-time config table
 * human factors - company, workflow, office practices,
 * design practices (e.g. standards used, use of code elements, code quality),
 * Web Index for developer
 * user, type of user transaction, Web Index of user
 * 
 * ------------------------------------------------------------------------- 
 */
require_once("../init.php");

if(class_exists('GBP_UTIL'))
{
	$util = new GBP_UTIL;
}
else
{
	echo "failed to load GBP_UTIL";
	exit;
}

if(class_exists('GBP_PROPERTY'))
{
	$prop = new GBP_PROPERTY;
}
else 
{
	echo "Failed to load GBP_PROPERTY";
	exit;
}

if(class_exists('GBP_CLIENT'))
{
	$clt = new GBP_CLIENT;
}
else
{
	echo "failed to load GBP_CLIENT";
	exit;
}


/**
 * -------------------------------------------------------------------------
 * CONFIGURATION
 * defaults and things set up in form
 * ------------------------------------------------------------------------- 
 */
 
/** 
 * PHP directives
 * http://stackoverflow.com/questions/5533076/processing-large-amounts-of-data-in-php-without-a-browser-timeout
 */
set_time_limit(0);                   // ignore php timeout
ignore_user_abort(true);             // keep on going even if user pulls the plug*
while(ob_get_level())ob_end_clean(); // remove output buffers
ob_implicit_flush(true);             // output stuff directly


//flip $DEBUG2 if we need verbose reporting

$DEBUG  = true;
$DEBUG2 = false;

//whether to put a <?php... into the string
//TODO: Make this part of the form

$USE_PI_STR = true;

//TODO: ADD SELECTION OF CLIENTS BY CATEGORIES, e.g. "Recent" clients, "full" clients
//and other useful combos

//TODO: OPTION TO ELIMINATE 'ancient' on all browsers (the standard GBP year cutoff)
//TODO: CUTOFF BY YEAR

$REMOVE_ANCIENT = false;
$REMOVE_EDGE    = false;

//TODO: make only the web daemon able to write (not other users) to GBP_DB

/**
 * create the client-version $USER_AGENT list as a string
 * NOTE: the following lists require that the search_group and properties listed DO NOT CHANGE in order to work.
 * TODO: make separate files for 'common', 'mobile' and 'rare'
 */
$DISALLOWED_PROPS  = array('comments');      //add any properties that we always want to exclude here


//whether to check back-dependencies (small effect on performance)

$CHECK_DEPENDENCIES = true;

/**
 * -------------------------------------------------------------------------
 * profiling
 * -------------------------------------------------------------------------
 */
$time_start = $util->microtime_float();
$time_end   = 0;

function check_time($time_diff = true) {
	global $time_start, $time_end;
	global $util;
	$new_time = 0;
	
	$time = $util->microtime_float();
	
	if($time_diff === true) {
		$new_time = ($time - $time_end);
	}
	else {
		$new_time =($time - $time_start);
	}
	
	$time_end = $time;
	
	return $new_time;

}

echo "Starting Time:".check_time(false)."<br>";

/**
 * ------------------------------------------------------------------------- 
 * FUNCTIONS AND GLOBALS
 * formatting constants
 * ------------------------------------------------------------------------- 
 */
$TAB0      = "\n\t";
$TAB1      = "\n\t\t";
$TAB2      = "\n\t\t\t";
$BREAK0    = "\n";
$BREAK1    = "\n\n";
$ZEROVER   = "00000";
$UNDEFINED = 'undefined';

/**
 * we need this function to generate proper keys. If you try to use
 * floating-point numbers for keys, they are truncated to integers.
 * So, we convert all keys to strings
 * Steps taken:
 * Multiply numbers, e.g. 1.3 = 0130. The '0' in front prevents
 * PHP from converting our key to an integer - it stays a string.
 * If not a number, strip out the number part, multiply, then add
 * back the non-numeric part.
 * Multiply by 1000
 * Version  0.1  = 00010
 * Version  1.0  = 00100
 * Version 10.0  = 01000
 * Version 100.0 = 10000
 */
function generate_php_version_key ($raw_key)
{
    $num  = $raw_key;
    $text = '';
    $key = '';
    
    //split out text and numeric portions of the raw key
    
    if(!is_numeric($raw_key))
    {
        $text = preg_replace('/[^\\/\-a-z\s]/i', '', $raw_key);
        $num = preg_replace("/[^0-9\.]/", "", $raw_key);
    }
    
    $num *= 100;
    $num = intval($num);
    
    //create a key which won't be parsed to an integer
    
    if($num < 1)
    {
        $key = '00000'.$num;
    }
    else if($num < 10)
    {
        $key = '0000'.$num;
    }
    else if($num < 100)
    {
        $key = '000'.$num;
    }
    else if($num < 1000)
    {
        $key = '00'.$num;
    }
    else if($num < 10000)
    {
        $key = '0'.$num;
    }
    else //a huge version, e.g. version 200.5 - unlikely?
    {
        if($text == '')
        {
            $text = 'x';
        }
        $key = $num;
    }
    
    return $key.$text;
}


/**
 * -------------------------------------------------------------------------
 * build the GBP prop array that acts as a database in the final GBP object.
 * 1. Check which components were specified in the configurator screen. Create an 
 *    array with all those components.
 * 2. Get a list of all the clients specified in the configurator, and all the 
 *    versions of those clients.
 * 3. For each client-version, write known properties into the final $USER_AGENT array
 * 4. Save this database into the GPB folder, or output as part of a larger ZIP download 
 *    of the GBP object.
 * 
 * NOTE: this is a time and cpu-intensive function (2-3 seconds on typical server). To
 * reduce, we cache the value in a global. We don't use "static" since that would keep
 * the variable in memory after use, and we only need it at the beginning of the script.
 * So, we implement a singleton-like pattern using a global variable
 * -------------------------------------------------------------------------
 */

$GB_prop_arr = array(); //@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@GLOBA@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
function get_properties_by_component()
{
	global $prop;
	global $GB_prop_arr;
	global $SOURCE_PRIMARY;

	$GB_prop_arr = array();
	
	//get all the components

	$component_arr = $prop->get_all_components();
	
	if(count($component_arr) == 0)
	{
		return false;
	}
	
	//initialize our GBP_PROPERTY db object (just resets the $ERROR array)
 
	$prop->init();
	
	/**
	 * get all the properties, grouped by component. This allows us to remove
	 * some components that aren't user-editable, and prioritize others to the
	 * top of the final PHP array written to output
	 */
	foreach($component_arr as $component)
	{
		$GB_prop_arr[$component['name']]               = array();
		$GB_prop_arr[$component['name']]['name']       = $component['name'];
		$GB_prop_arr[$component['name']]['title']      = $component['title'];
		$GB_prop_arr[$component['name']]['id']         = $component['id'];
		$GB_prop_arr[$component['name']]['user_edit']  = $component['user_edit'];
		$GB_prop_arr[$component['name']]['properties'] = $prop->get_all_properties($SOURCE_PRIMARY, $component['id'], false, false);
		
	}
	
	return true;
}

	
/**
 * -------------------------------------------------------------------------
 * @function get requested_properties
 * get all the properties requested in the calling form, sorted by component
 * We assume encoding of property and component as $_POST['component_id-property_id']
 * @return the array, sorted as $use_prop_component[$property_id] = $component_id;
 * -------------------------------------------------------------------------
 */
$GB_use_prop_component = array(); //@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@GLOBA@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
function get_requested_properties()
{
	//collect the set of properties we should include, specified by the fill-out form
	
	global $GB_use_prop_component;
	
	$GB_use_prop_component = array();
	
	foreach($_POST as $key => $value)
	{
		$exp = explode('-', $key);
		if(count($exp) == 2)
		{
			$GB_use_prop_component[$exp[1]] = $exp[0];
		}
	}
	
	//return $GB_use_prop_component;
	return true;
}

/*
 * sorting function for priority
 */
function sortByPriority($a, $b) {
	return $a['order'] - $b['order'];
}


/**
 * -------------------------------------------------------------------------
 * @function create_user_agents_all
 * write out a $USER_AGENTS['all'] based on the database, and the items the user
 * selected on the preceeding form.
 * @param {Boolean} $dont_make_array if true, make an array, otherwise, make a string which can be evaled as an array
 * @return {String|Array} if ok, return either an array, or a string which can be evaluated to the same array.
 * -------------------------------------------------------------------------
 */
function create_user_agents_all($dont_make_array = true, $disallowed_properties = array())
{
	global $DEBUG, $DEBUG2;
	global $USE_PI_STR;
	global $TAB0, $TAB1, $TAB2, $BREAK0, $BREAK1, $ZEROVER, $UNDEFINED;
	
	//grab all the properties defined by the database, sorted by component
	
	global $GB_prop_arr;
	$prop_arr = &$GB_prop_arr;
	////////$prop_arr = get_properties_by_component();
	
	global $GB_use_prop_component;
	$use_prop_component = &$GB_use_prop_component;
	//////////$use_prop_component = get_requested_properties();

	
	if(count($GB_prop_arr) === 0)
	{
		echo "ERROR: no property array GB_prop_arr<br>";
		return false;
	}
	
	if(count($GB_use_prop_component) === 0)
	{
		echo "ERROR: no property component array GB_use_prop_component<br>";
		return false;
	}
	
	if($dont_make_array === true)
	{
		
		$USER_AGENTS_STR = '';
		
		if($USE_PI_STR)
		{
			$USER_AGENTS_STR .= '<?php'.$BREAK0.$BREAK0;
			
		}
		
		$USER_AGENTS_STR .= '$USER_AGENTS = array();'.$BREAK1;
		$USER_AGENTS_STR .= '$USER_AGENTS['."'all'".'] = array('.$BREAK0.$TAB1."'$ZEROVER' => array(".$BREAK0;
	}
	else
	{
		$USER_AGENTS = array();
		$USER_AGENTS['all'] = array();
		$USER_AGENTS['all'][$ZEROVER] = array();
	}
	
	$USER_AGENTS_SORT_ARR = array();
	$SORT = array();
	$ct = 0;
	
	$component_array = array();
	
	//from fill-out form which called this script
	
	//loop through the components

	foreach($prop_arr as $name => $component_group)
	{
		if($component_group['user_edit'] == true) //column in the component database  $GB_prop_arr[$component['name']]['properties']
		{
			//loop through the properties associated with this component
			//TODO: this is where we can push "required" to the top
			
			foreach($component_group['properties'] as $property_list)
			{
				//if the property is specified in the form, take info from our global property grab
				//echo "ID:$id<br>";
				
				if(array_key_exists($property_list['id'], $use_prop_component) && !in_array($property_list['name'], $disallowed_properties))
				{
					if($DEBUG)
					{
						//store the current component array
						
						$component_array[$component_group['name']] = $component_group['name']; //getting this for debugging only	
					}
					
					if($dont_make_array === true)
					{
						$pid = $property_list['id'];
						$USER_AGENTS_SORT_ARR[$pid]['name']     = $property_list['name'];
						$USER_AGENTS_SORT_ARR[$pid]['exe_lock'] = $property_list['exe_lock'];
						
						if($USER_AGENTS_SORT_ARR[$pid]['exe_lock'] == 1)
						{
							$USER_AGENTS_SORT_ARR[$pid]['exe_lock'] = 0;	
						}
						else
						{
							$USER_AGENTS_SORT_ARR[$pid]['exe_lock'] = 1;
						}
						$USER_AGENTS_SORT_ARR[$pid]['exe_lock_priority'] = $property_list['exe_lock_priority'];
						$SORT['exe_lock'][$pid] = $property_list['exe_lock'];
						$SORT['exe_lock_priority'][$pid] = $property_list['exe_lock_priority'];
					}
					else
					{
						$USER_AGENTS['all'][$ZEROVER][$property_list['name']] = $UNDEFINED;
					}
					
					$ct++; //count how many properties we've written
					
				} //end of property specified in fill-out form
				
			} //end of loop through all properties
			
		} //end of user_edit test
		
	} //end of loop through all properties defined in the db

	
	if($dont_make_array === true)
	{
		if($DEBUG)
		{
			echo "<hr><strong>Components Used:</strong><br><pre>";
			print_r($component_array);
			echo "\n$ct properties written\n";
			echo "</pre><hr>";
		}
		
		array_multisort($SORT['exe_lock_priority'], SORT_NUMERIC, $SORT['exe_lock'], SORT_NUMERIC, $USER_AGENTS_SORT_ARR);
		
		foreach($USER_AGENTS_SORT_ARR as $p)
		{
			$USER_AGENTS_STR .= $TAB2."'".$p['name']."' => '".$UNDEFINED."',";
		}
		
		$USER_AGENTS_STR  = rtrim($USER_AGENTS_STR,","); //strip trailing comma the easy way
		$USER_AGENTS_STR .= $TAB2.")";
		$USER_AGENTS_STR .= $TAB1.");";
		
		if($USE_PI_STR)
		{
			$USER_AGENTS_STR .= $BREAK0.'?>';
			
		}
		
		return $USER_AGENTS_STR;	
	}
	else
	{
		return $USER_AGENTS;
	}
	
	
	
} //end of create_user_agents_all


/**
 * -------------------------------------------------------------------------
 * @function get dependency (recursive)
 * extract a dependency, and add it to a 'dependency_found' array
 * @param {Array} $prop PARENT property
 * @param {String} $prop_val the value of the child property. Only Boolean values
 * are computed at present.
 * 
 * @param {String} $dependency_state one of the values in the 'dependency_state' table
 * in the database, 'DEPENDENCY_TRUE', 'DEPENDENCY_FALSE'
 * @param {&Array} $dependencies_found array to add the dependency state to. The
 * array key is the property name, and the value is deduced from the property state.
 *
 * since dependencies can be recursive, create globals that monitor for
 * circular dependencies accidentially introduced into the database. This
 * variable needs to be re-set for each client-version in create_user_agents()
 * $NUM_RECRSIONS
 * $MAX_RECURSIONS
 * -------------------------------------------------------------------------
 */
$NUM_RECURSIONS  = 0;
$MAX_RECURSIONS  = 10; //TODO: SET THIS SO IT IS CONSISTENT WITH OUR DATABASE!
function get_dependency($prop, $prop_val, $dependency_state, &$dependencies_found)
{
	global $DEBUG, $DEBUG2;
	global $NUM_RECURSIONS, $MAX_RECURSIONS; //safety check for runaway circular dependencies
	
	if(is_array($prop))
	{
		if($prop['datatype']['name'] == 'boolean' && is_array($prop['dependency'][0])) //NOTE: hard-coded database value
		{
			if($DEBUG2)
			{
				echo "<pre>get_dependency()";
				print_r($prop['dependency'][0]);
				echo "ADDING DEPENDENCY:".$dependency_state." for ".$prop['name']."<br>";
				echo "</pre>"; 	
			}
			
			//assign dependency state based on child. At present, only Boolean are assigned
			
			switch($dependency_state)
			{
				case 'DEPENDENCY_TRUE':
					$dependencies_found[$prop['dependency'][0]['name']] = $prop_val;
					break;
				case 'DEPENDENCY_FALSE':
					if($prop_value == 'true')
					{
						$dependencies_found[$prop['dependency'][0]['name']] = 'false';
					}
					else
					{
						$dependencies_found[$prop['dependency'][0]['name']] = 'true';
					}
					
					break;
				default:
					return false; //unrecognized dependency state
					break;
			}
			
			$NUM_RECURSIONS++;
			
			if($NUM_RECURSIONS < $MAX_RECURSIONS)
			{
				/**
				 * recursively call myself to get all the dependencies
				 * TODO: test with a long chain of dependencies
				*/
				if(is_array($prop['dependency'][0]['dependency']))
				{
					get_dependency($prop['dependency'][0],
						       $dependencies_found[$prop['dependency'][0]['name']],
						       $prop['dependency'][0]['state']['name'],
						       $dependencies_found);	
				}
			}
			
			return true;
		}
	}
	return false;
 }

 
/**
 * -------------------------------------------------------------------------
 * @function get_client_search_group
 * a method that looks at a client's versions, and computes which $USER_AGENT file to assign a client to,
 * based on the most recent search_group defined for that client.
 *
 * Possible assignments:
 * 1. user_agents_common.php
 * 2. user_agents_mobile.php
 * 3. user_agents_edge.php
 * 4. user_agents_ancient.php
 * 
 * NOTE: this is different than an individual client-version-property 'searchgroup'. The property 'searchgroup' can be defined
 * for individual client-versions, and may differ from the classification of the client as a whole. So, MSIE is a 'common'
 * browser, even though its earliest versions are 'ancient' and intermediate versions are 'edge'. We use this function
 * to sort browsers into a files to make access more efficient. Even if MSIE is in user_agents_common.php, it is possible
 * that its oldest versions will have searchgroup = 'ancient' defined separately
 * 
 * Browsers are assigned based on the newest version in the database. So MSIE gets assigned to 'common' even
 * though MSIE 1.0 is an 'ancient' web browser.
 * Browsers are also classified via rarity. A very rare browser, even one newly released, will have the
 * search_group 'edge' for all its members.
 * @param {Array} $client_version_arr array of all the versions for a client
 * -------------------------------------------------------------------------
 */
$GB_search_group_arr = $clt->get_all_search_groups(true); //get only the minimal list  @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
function get_client_search_group($client_version_arr)
{
	global $DEBUG, $DEBUG2;
	global $GB_search_group_arr;
	
	if(count($GB_search_group_arr) == 0)
	{
		echo "ERROR: no search group array";
		return false;
	}
	
	$search_group_val = 'ancient';
	
	if($DEBUG2)
	{
		$scan_arr = array();
	}
	
	//scan through each recorded version for this client
	
	foreach($client_version_arr as $client_version)
	{
		//get the search group for the current version.
		//NOTE: names of search groups must not change for this to work!
		
		foreach($GB_search_group_arr as $search_group)
		{	
			if($client_version['searchgroup_id'] == $search_group['id'])
			{
				if($DEBUG2)
				{
					$scan_arr[] = $search_group['name'];/////////////////////////////
				}
				
				switch($search_group['name'])
				{
					case 'common':
						if($search_group_val != 'mobile')
						{
							$search_group_val = 'common';
						}
						break;
					
					case 'mobile':
						if($search_group_val != 'common')
						{
							$search_group_val = 'mobile';
						}
						break;
					
					case 'edge':
						if($search_group_val == 'ancient')
						{
							$search_group_val = 'edge';
						}
						break;
						
					case 'unmoveable':
						break;
					
					case 'ancient':
						//no change
						break;
					
					case 'future':
						//no change
						break;
						
					case 'dead':
						break;
					
					default:
						echo "ERROR: no defined search group! for ".$client_version['name']."<br>";
						$search_group_val = false;
						break;
				}
			}
		}
		
	}
	
	//debugging
	
	if($DEBUG2)
	{
		//compare, and set to the oldest, mobile-ist or edge-ist
		echo "<pre>";
		print_r($scan_arr);
		echo "</pre>";	
	}

	return $search_group_val;
}



/**
 * -------------------------------------------------------------------------
 * @function get_client_version_properties
 * get all the clients in the database, along with all the versions for each client
 * attach properties for each client
 * -------------------------------------------------------------------------
 */
$GB_client_arr = array();              //@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
$GB_client_version_all_arr = array();  //@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
function get_client_version_properties()
{
	global $clt;
	global $GB_client_arr;
	global $GB_client_version_all_arr;
	
	$GB_client_arr = $clt->get_all_clients();
	
	$GB_client_version_all_arr = array();
	
	foreach($GB_client_arr as $client)
	{
		$GB_client_version_all_arr[$client['id']] = $clt->get_client_versions($client['id']);
		//$GB_client_version_all_arr[$client['id']]['properties'] = $clt->get_client_properties($client['id'], $GB_client_version_all_arr[$client['id']['id']);
	}
}


/**
 * -------------------------------------------------------------------------
 * @function create_user_agents
 * create the user_agents database, only writing the
 * properties listed in the $user_agents_all array. clients and
 * versions are sorted into different $USER_AGENT strings, in prep for writing
 * 'common', 'mobile', and 'rare' PHP database array files.
 * @param {Array} $user_agents_all the array listing all properties to be included in the
 * @param {Array} $search_group_list array with the names of all search groups to be included in the output array
 * @param {Array} $disallowed_properties an array with the names (not title, etc.) of properties we don't want to
 * include in final output, even if we find them in the client-property-version data.
 * @return {Array} multi-dimensional array with user agent databases, sorted by search groups.
 * -------------------------------------------------------------------------
 */
function create_user_agents(&$USER_AGENTS_ALL_ARR, $search_group_list, $disallowed_properties = array()) //TODO: micro-optimization from http://nikic.github.io/2011/11/11/PHP-Internals-When-does-foreach-copy.html
{
	global $DEBUG, $DEBUG2;
	global $USE_PI_STR;
	global $TAB0, $TAB1, $TAB2, $BREAK0, $BREAK1, $ZEROVER, $UNDEFINED;
	global $clt;
	global $NUM_RECURSIONS, $MAX_RECURSIONS; //prevent runaway recursion for property dependencies
	global $CHECK_DEPENDENCIES;
	
	//get all the clients, correct how count() manages non-array returned values
	
	global $GB_client_arr;
	//$GB_client_arr = $clt->get_all_clients();
	
	/*
	 * get all possible search groups, since the 'searchgroup' property for a particular
	 * client-version stores the key, not the value of 'searchgroup' for that client-version
	 */
	global $GB_search_group_arr;
	//$GB_search_group_arr = $clt->get_all_search_groups(true);
 
	/*
	 * get all possible client-versions. This may be a big array, but 
	 */
	global $GB_client_version_all_arr;
	
	/**
	 * NOTE: for printout only
	 */
	$client_list = array();
	
	if(!is_array($USER_AGENTS_ALL_ARR))
	{
		return false; //need the $USER_AGENTS['all'][$ZEROVER] array for GBP to work
	}

	$USER_AGENTS_STR = '';
	
	if($USE_PI_STR)
	{
		$USER_AGENTS_STR .= '<?php'.$BREAK0.$BREAK0;
	}
	
	$USER_AGENTS_STR .= '$USER_AGENTS = array('.$BREAK0;
	
	/*
	$client_count = 0;
	if(is_array($GB_client_arr))
	{
		$client_count = count($GB_client_arr) - 1;
	}
	$ct = 0;
	*/
	
	//loop through all the clients
	
	foreach($GB_client_arr as $client)
	{
		//$client_version_arr = &$GB_client_version_all_arr[$client['id']];  //@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
		$client_version_arr  = $clt->get_client_versions($client['id']);
		
		//get the search group (defined in 'clients' table) that this client belongs in
		
		$client_search_group = get_client_search_group($client_version_arr);
		
		if($DEBUG2) echo $client['name']." SEARCH GROUP: $client_search_group<br>";
		
		if(!in_array($client_search_group, $search_group_list))
		{
			continue; //IGNORE THIS CLIENT, since it isn't in the search_group we want to include in our $USER_AGENT
		}
		
		//NOTE: PRINTOUT ONLY - save the client we are working on
		
		$client_list[] = $client['name']." ($client_search_group)";
		
		//count the number of client-versions correctly
		
		$client_version_count = 0;
		if(is_array($client_version_arr))
		{
			$client_version_count = count($client_version_arr);	
		}
		/////////////$ct1 = 0;
		
		$zeroflag = false;
		foreach($client_version_arr as $client_version)
		{
			
			$vers = $client_version['version'];
			
			if (preg_match('/^(.)\0*$/', $vers)) //at least one of our keys must match '0' or '000....'
			{
				$zeroflag = true;
			}
		} //complete test for zeroth version in SQL database
		
		/**
		 * add in zeroth version '000...' of client-version, if not already present
		 * by using multiple zeroes we make this a text, rather than a numerical string
		 */
		if(!$zeroflag)
		{
			$USER_AGENTS_STR .= $TAB0."'".$client['name']."' => array(".$BREAK0.$TAB1."'".$ZEROVER."' => array(";
			
			if($client_version_count == 0)
			{
				$USER_AGENTS_STR .= $TAB2.')'.$BREAK0;
			}
			else
			{
				$USER_AGENTS_STR .= $TAB2.'),'.$BREAK0;
			}
		}
		
		//loop through all the versions for this client
		
		foreach($client_version_arr as $client_version)
		{
			//get the search group for this client-version, and store in array
			
			
			/**
			 * confirm that the value of $client_version['version'] can be used as a
			 * valid PHP Array key.
			 */
			$vers = $client_version['version'];
			$vers = generate_php_version_key($client_version['version']);
			
			
			/**
			 * get client-properties for a specific client and version
			 */
			$client_prop_arr = $clt->get_client_properties($client['id'], $client_version['id']);
				
			$USER_AGENTS_STR .= $TAB1.'\''.$vers.'\' => array(';
			
			/**
			 * loop over the requested properties from $USER_AGENTS_ALL_ARR
			 * NOTE: doing the loop this way makes it impossible for any property not listed in
			 * $USER_AGENTS_ALL to end up in the database.
			 */
			foreach($USER_AGENTS_ALL_ARR['all'][$ZEROVER] as $prop => $val)
			{
				//all the returned client-properties for a particular client-version
				
				$NUM_RECURSIONS = 0; //re-set the global checking for dependencies
				
				//store any dependencies for adding later, if necessary
				
				$props_added        = array();
				$dependencies_found = array();
				
				//loop through all the properties defined for the client-version
				
				foreach($client_prop_arr as $client_property)
				{
					//get values for current property
					
					$prop_name = $client_property['property']['name'];
					$prop_val  = $client_property['property_value'];
					$prop_id   = $client_property['property']['id'];
					
					/**
					 * 'searchgroup' is a special, LOCKED property. This is because
					 * it is defined in the client. By default, the value stored under 'searchgroup'
					 * is the ID of the search group value. Since we already translated it earlier,
					 * apply it here.
					 *
					 * If we don't have a client-version specific searchgroup, just apply the global
					 * client search group computed earlier in this function
					 */
					if($prop_name == 'searchgroup')
					{
						if(is_numeric($prop_val))
						{
							foreach($GB_search_group_arr as $sgroup)
							{
								if($sgroup['id'] == $prop_val)
								{
									$prop_val = $sgroup['name'];
								}
							}
							
							//still numeric?
							
							if(is_numeric($prop_val))
							{
								$prop_val = $client_search_group;
							}
						}
						
					} //end of local client-version 'searchgroup' computation
					
					//screen out 'comments' and other disallowed properties (set in function input)
					
					if(!in_array($prop_name, $disallowed_properties) && $prop_name == $prop && $prop_val != '')
					{
						//record the property we're adding
						
						$props_added[$prop_name] = $prop_val;
						
						if($CHECK_DEPENDENCIES)
						{	
							if(is_array($client_property['property']['dependency']))
							{
								//look for parent properties that we can implicitly define, if we know the
								//value of the child property
								
								if(get_dependency($client_property['property'], $prop_val, $client_property['property']['dependency'][0]['state']['name'], $dependencies_found))
								{
									if($DEBUG2)
									{
										echo "<pre>";
										echo "START*****";
										echo $client_version['versionname'];
										echo " DEPENDENCIES FOUND: for $prop_name => $prop_val ";
										print_r($dependencies_found)."<br>";
										echo "*******</pre>";
									}
								}
							}
						}
						
						//actually add our property
						
						$USER_AGENTS_STR .= $TAB2."'$prop_name' => '$prop_val',";
						
					}
					
				} //end of properties for a given client-version
				
				
				//add dependencies to the client here
					
				if(count($dependencies_found))
				{
					if($DEBUG2) echo "Found:".count($dependencies_found)." dependencies<br>"; /////////////////////
						
					foreach($dependencies_found as $prop_name => $prop_val)
					{
						if(array_key_exists($prop_name, $props_added))
						{
							if($prop_val != $props_added[$prop_name])
							{
								echo "ERROR: dependency has a value with a property assigned to this client-version:(".$prop_name, $prop_val.")"; /////////////
								exit;
							}
						}
						else
						{
							if($DEBUG2) echo "Adding parent dependency $prop_name => $prop_val<br>"; ///////////////////
							$USER_AGENTS_STR .= $TAB2."'$prop_name' => '$prop_val',";
						}
					}
				}
				
			} //end of scan through $USER_AGENTS[all]
			
			$USER_AGENTS_STR = rtrim($USER_AGENTS_STR, ',');
			
			//add the closing array parentheses, don't write the trailing comma for the last client-version
			
			$USER_AGENTS_STR .= $TAB2."),".$BREAK0; //close the client-version array
			
			
			/*
			if($ct1 < $client_version_count - 1)
			{
				$USER_AGENTS_STR .= $TAB2."),".$BREAK0; //close the client-version array
			}
			else
			{
				$USER_AGENTS_STR .= $TAB2.")".$BREAK0; //close the client array, rtrim() didn't work
			}
			*/
			
			//$ct1++; //increment number of versions processed
			
		} //end of all client-versions
		
		$USER_AGENTS_STR = rtrim($USER_AGENTS_STR, ','.$BREAK0);
		
		$USER_AGENTS_STR .= $TAB1."),".$BREAK0; //close the client array
		
		/*
		if($ct < $client_count)
		{
			$USER_AGENTS_STR .= $TAB1."),".$BREAK0; //close the client array
		}
		else
		{
			$USER_AGENTS_STR .= $TAB1.")".$BREAK0; //close the client array, rtrim() didn't work
		}
		*/
		
		//$ct++; //increment number of clients processed
		
	} //close of array of all clients
	
	
	//NOTE: Printout only, echo out client_list
	
	if($DEBUG)
	{
		echo "<pre>";
		print_r($client_list);
		echo "</pre>";	
	}
	
	$USER_AGENTS_STR = rtrim($USER_AGENTS_STR, ','.$BREAK0);
	
	$USER_AGENTS_STR .= $TAB0.");";
	
	if($USE_PI_STR)
	{
		$USER_AGENTS_STR .= $BREAK0.$BREAK0.'?>';
	}

	return $USER_AGENTS_STR;
}


/**
 * ------------------------------------------------------------------------- 
 * START OF MAIN PROGRAM
 * ------------------------------------------------------------------------- 
 */

/** 
 * define output files
 */
$gbp_db_dir     = '/gbp/db/php';
$gbp_properties = 'user-agents-properties.php';
$gbp_common     = 'user-agents-common.php';
$gbp_mobile     = 'user-agents-mobile.php';
$gbp_unmoveable = 'user-agents-unmoveable.php';  /* television, game consoles, interactive signs, set-top boxes */
$gbp_edge       = 'user-agents-edge.php';        /* low usage browsers, specialty use (e.g. text-only) not extremely old */
$gbp_ancient    = 'user-agents-ancient.php';     /* obsolete browsers (discontinued or not updated) that someone might still be using */
$gbp_dead       = 'user-agents-dead.php';        /* browsers that can't exist on the web today (e.g. no transcoder servers available) */


if($DEBUG)
{
	echo "<h2>Beginning GBP database property write...</h2>";
	echo "MAX_EXECUTION_TIME:".ini_get('max_execution_time')."<br>";	
	echo "Time to Load script: ".check_time(true)."<br>";
}


/**
 * ------------------------------------------------------------------------
 * initialize global arrays
 * ------------------------------------------------------------------------
 */
get_requested_properties();    //get the requested properties from the $_POST array

echo "Time to process get requested properties from POST: ".check_time(true)."<br>";

get_properties_by_component(); //create the component array

echo "Time to create the component array: ".check_time(true)."<br>";

/**
 * ------------------------------------------------------------------------- 
 * create $USER_AGENTS_ALL, either the actual Array, or the equivalent file string.
 * ------------------------------------------------------------------------- 
 */
$USER_AGENTS_ALL_STR = create_user_agents_all(true, $DISALLOWED_PROPS); //make a string (not an Array), and pass in disallowed props
$USER_AGENTS_ALL_ARR = create_user_agents_all(false); //create the actual array (avoid using eval() later)

//write the USER_AGENTS_ALL file

if($DEBUG) echo "<strong>Writing USER_AGENTS_ALL...</strong><br>";

if($util->write_db_file($USER_AGENTS_ALL_STR, $gbp_db_dir, $gbp_properties, false))
{
	if($DEBUG)
	{
		echo "<pre>";
		print_r($USER_AGENTS_ALL_ARR); //print_r is NOT cpu-intensive
		if($DEBUG2)
		{
			echo "USER_AGENTS_ALL:<br>";
			echo $USER_AGENTS_ALL_STR;
		}
		echo "</pre>";
		echo "Wrote global properties (USER_AGENTS_ALL) file: $gbp_properties<br>";
		echo "Time to create USER_AGENTS_ALL: ".check_time(true)."<br>";
	}
}

exit; //////////////////////////////////////////////////////////

//erase the globals used here to cache results. create_user_agents_all is cpu intensive.

$GB_prop_arr           = array();  //////////////////@@@@@@@@@@@@@@@@@@@@ erase @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
$GB_use_prop_component = array();  //////////////////@@@@@@@@@@@@@@@@@@@@ erase @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
$USER_AGENTS_ALL_STR   = "";       //////////////////@@@@@@@@@@@@@@@@@@@@ erase @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@

/**
 * -------------------------------------------------------------------------
 * create $USER_AGENTS COMMON as a file that can be burned to a GBP distribution
 * ------------------------------------------------------------------------- 
 */

//get the complete client_version_property array (all clients and versions)

get_client_version_properties();

if($DEBUG)
{
	echo "<strong>Creating USER_AGENTS_COMMON...</strong><br>";
	echo "Time to get_client_version_properties(): ".check_time(true)."<br>";
}

$SEARCH_GROUP_LIST = array('common'); //we can put 1 or more of the search_group types into one PHP database
$USER_AGENTS_STR   = create_user_agents($USER_AGENTS_ALL_ARR, $SEARCH_GROUP_LIST, $DISALLOWED_PROPS);

//write the COMMON file

if($DEBUG)
{
	echo "<strong>Writing USER_AGENTS_COMMON...</strong><br>";
}

if($util->write_db_file($USER_AGENTS_STR, $gbp_db_dir, $gbp_common, false))
{
	if($DEBUG)
	{
		echo "Wrote USER_AGENTS_COMMON: $gbp_common<br>";
		echo "Time to create USER_AGENTS_COMMON: ".check_time(true)."<br>";

	}
}
else
{
	echo "couldn't write common browser file: $gbp_common<br>";
}


/**
 * -------------------------------------------------------------------------
 * create $USER_AGENTS MOBILE as a file that can be burned to a GBP distribution
 * ------------------------------------------------------------------------- 
 */
if($DEBUG) echo "<strong>Writing USER_AGENTS_MOBILE...</strong><br>";

$SEARCH_GROUP_LIST = array('mobile'); //we can put 1 or more of the search_group types into one PHP database
$USER_AGENTS_STR   = create_user_agents($USER_AGENTS_ALL_ARR, $SEARCH_GROUP_LIST, $DISALLOWED_PROPS);

//write the MOBILE file

if($util->write_db_file($USER_AGENTS_STR, $gbp_db_dir, $gbp_mobile, false))
{
	if($DEBUG)
	{
		echo "WROTE mobile browser file: $gbp_mobile<br>";
	}
}
else
{
	echo "Couldn't write mobile browser file: $gbp_mobile<br>";
}

if($DEBUG)
{
	echo "Output to GBP PHP USER_AGENTS_MOBILE database files complete<br>";
	$time_end = $util->microtime_float();
	$time = $time_end - $time_start;
	echo "Elapsed Time: $time seconds<hr>";
}



/**
 * -------------------------------------------------------------------------
 * create $USER_AGENTS UNMOVEABLE as a file that can be burned to a GBP distribution
 * ------------------------------------------------------------------------- 
 */
if($DEBUG) echo "<strong>Writing USER_AGENTS_UNMOVEABLE...</strong><br>";

$SEARCH_GROUP_LIST = array('unmoveable'); //we can put 1 or more of the search_group types into one PHP database
$USER_AGENTS_STR   = create_user_agents($USER_AGENTS_ALL_ARR, $SEARCH_GROUP_LIST, $DISALLOWED_PROPS);

//write the UNMOVEABLE file

if($util->write_db_file($USER_AGENTS_STR, $gbp_db_dir, $gbp_unmoveable, false))
{
	if($DEBUG)
	{
		echo "WROTE mobile unmoveable file: $gbp_unmoveable<br>";
	}
}
else
{
	echo "Couldn't write unmoveable browser file: $gbp_unmoveable<br>";
}

if($DEBUG)
{
	echo "Output to GBP PHP USER_AGENTS_UNMOVEABLE database files complete<br>";
	$time_end = $util->microtime_float();
	$time = $time_end - $time_start;
	echo "Elapsed Time: $time seconds<hr>";
}



/**
 * -------------------------------------------------------------------------
 * create $USER_AGENTS EDGE as a file that can be burned to a GBP distribution
 * ------------------------------------------------------------------------- 
 */

if($DEBUG)
{
	echo "<strong>Writing USER_AGENTS_EDGE...</strong><br>";
}

$SEARCH_GROUP_LIST = array('edge'); //we can put 1 or more of the search_group types into one PHP database
$USER_AGENTS_STR   = create_user_agents($USER_AGENTS_ALL_ARR, $SEARCH_GROUP_LIST, $DISALLOWED_PROPS);

//write the COMMON file

if($util->write_db_file($USER_AGENTS_STR, $gbp_db_dir, $gbp_edge, false))
{
	if($DEBUG)
	{
		echo "WROTE edge browser file: $gbp_edge<br>";
	}
}
else
{
	echo "Couldn't write edge browser file: $gbp_edge<br>";
}


if($DEBUG)
{
	echo "Output to GBP PHP USER_AGENTS_EDGE database files complete<br>";
	$time_end = $util->microtime_float();
	$time = $time_end - $time_start;
	echo "Elapsed Time: $time seconds<hr>";
}


/**
 * -------------------------------------------------------------------------
 * create $USER_AGENTS ANCIENT as a file that can be burned to a GBP distribution
 * ------------------------------------------------------------------------- 
 */
echo "<strong>Writing USER_AGENTS_ANCIENT...</strong><br>";

$SEARCH_GROUP_LIST = array('ancient'); //we can put 1 or more of the search_group types into one PHP database
$USER_AGENTS_STR   = create_user_agents($USER_AGENTS_ALL_ARR, $SEARCH_GROUP_LIST, $DISALLOWED_PROPS);

//write the DB_COMMON file


if($util->write_db_file($USER_AGENTS_STR, $gbp_db_dir, $gbp_ancient, false))
{
	if($DEBUG)
	{
		echo "WROTE ancient browser file: $gbp_ancient<br>";
	}
}
else
{
	echo "Couldn't write ancient browser file: $gbp_ancient<br>";
}



/**
 * -------------------------------------------------------------------------
 * create $USER_AGENTS DEAD as a file that can be burned to a GBP distribution
 * ------------------------------------------------------------------------- 
 */
echo "<strong>Writing USER_AGENTS_DEAD...</strong><br>";

$SEARCH_GROUP_LIST = array('dead'); //we can put 1 or more of the search_group types into one PHP database
$USER_AGENTS_STR   = create_user_agents($USER_AGENTS_ALL_ARR, $SEARCH_GROUP_LIST, $DISALLOWED_PROPS);

//write the DB_COMMON file


if($util->write_db_file($USER_AGENTS_STR, $gbp_db_dir, $gbp_dead, false))
{
	if($DEBUG)
	{
		echo "WROTE ancient browser file: $gbp_dead<br>";
	}
}
else
{
	echo "Couldn't write ancient browser file: $gbp_dead<br>";
}

/** 
 * ----------------------------------------------------------
 * write completion message
 * -----------------------------------------------------------
 */
if($DEBUG)
{
	echo "Output to GBP PHP database files complete<br>";
	$time_end = $util->microtime_float();
	$time = $time_end - $time_start;
	echo "Elapsed Time: $time seconds<hr>";
}

