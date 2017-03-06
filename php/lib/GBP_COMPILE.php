<?php

/** 
 * GBP Complier
 * convert database into PHP arrays suitable for 
 * queries from GBP server-side modules
 * 
 * @author Pete Markiewicz 2014
 * 
 * RULES
 * 
 * 1. Compound property names (e.g. "version name") are hypenated in PHP & MySQL database (e.g. 'version-name')
 * 2. Compound properties are camelCased in JavaScript output (e.g. 'versionName')
 */


/** 
 * require base database functions
 * which also includes GBP_UTIL as an internal object $util->....
 */

require('GBP_BASE.php');

/** 
 * sorting algorithm for order that 
 * functions are executed (based on which 
 * other functions they call. This must be done because
 * most GBP JS functions convert themselves to properties
 * after first call
 */
require('TOPOLOGICAL_SORT.php');


/** 
 * Dependency calculator that translates a topological sort into JavaScript
 * code featuring a series of nested callbacks to enforce execution order in 
 * "laggy" scripts
 */
require('GBP_SEQUENCE.php');


/** 
 * @class GBP_COMPILE
 */
class GBP_COMPILE extends GBP_BASE {

	/** 
	 * debug state
	 */
	static private $DEBUG = true;

	/** 
	 * compile environment is ok flag
	 */
	static private $COMPILE_ENV_OK = true;

	/** 
	 * if true, write comments from the database, 
	 * otherwise do not
	 */
	static private $WRITE_COMMENTS = false;

	/** 
	 * CONSTANTS
	 * 
	 * placeholder property values for dynamic, server-side functions executed
	 * when gbp-bootstrap.php executes. These are in turn replaced by the 
	 * results of server-side functions when GBP first executes on the server.
	 */
	static private $DETECTOR_SERVER_PHP    = 'server-php';
	static private $DETECTOR_SERVER_JS     = 'server-js';
	static private $DETECTOR_SERVER_CSHARP = 'server-csharp';
	static private $DETECTOR_SERVER_PYTHON = 'server-python';
	static private $DETECTOR_SERVER_RUBY   = 'server-ruby';

	
	/** 
	 * template value to substitute with property name
	 * allows us to dynamically write the function for constructs like
	 * function %jsproperty% () { this.%jsproperty% = 5; }
	 * into the database, and change the name of the detector without
	 * having to re-write the function
	 * TODO: LOOK AT MOUSTACHE JS AND SIMILAR FOR SUBSTITUTIONS
	 */
	static private $DETECTOR_GBP_SUB_SYMBOL     = '%';
	static private $DETECTOR_PROP_GBP_NAME      = '%gbp%';            //name of GBP js object
	static private $DETECTOR_PROP_HELPER_NAME   = '%helper%';         //name of the sub-object with helper functions
	static private $DETECTOR_PROP_CALLBACK_NAME = '%callback%';       //keyword for a detector that uses a callback with its dependent detectors
	static private $DETECTOR_PROP_JS_NAME       = '%jsproperty%';     //name of a property with a JavaScipt detector
	static private $DETECTOR_PROP_PHP_NAME      = '%phpproperty%';    //name of a property with a PHP detector
	static private $DETECTOR_PROP_PYTHON_NAME   = '%pyproperty%';     //name of a property with a Python detector
	static private $DETECTOR_PROP_RUBY_NAME     = '%rbproperty%';     //name of a property with a Ruby detector
	
	static private $PROPERTY_NOT_PROP         = 1;
	static private $PROPERTY_CALLED_AS_PROP   = 2;
	static private $PROPERTY_CALLED_AS_FN     = 3;

	static private $GBP        = 'GBP';
	static private $HELPER     = 'helper';
	static private $CALLBACK   = 'callback';

	/** 
	 * special property names
	 */
	static private $PROPERTIES_COMMENTS       = 'comments';
	

	/** 
	 * sources for properties, GBP, caniuse, Modernizr, (re) 
	 * set in the constructor
	 */
	static private $SOURCE_GBP = 1000;


	/** 
	 * directory paths
	 */

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//TODO:
	//AN INCLUDE FOR BOOTSTRAP AND COMPILE?
	//TODO:
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	static private $GBP_DIR             = 'gbp/';    //subdirectory in document root
	static private $GBP_DB_DIR          = '';

	static private $GBP_CLIENT_JS_DIR   = '';
	static private $GBP_CLIENT_DIR      = '';
	static private $GBP_CLIENT_VB_DIR   = '';

	static private $GBP_SERVER_DIR      = '';
	static private $GBP_SERVER_PHP_DIR  = '';    //php
	static private $GBP_SERVER_PY_DIR   = '';    //python
	static private $GBP_SERVER_RB_DIR   = '';    //ruby

	static private $GBP_CLASS_DIR               = '';    //where we store the functional GBP server-side program
	static private $GBP_CLASS_GBP               = '';    //base GBP object
	static private $GBP_CLASS_ANALYZE           = '';    //GBP_ANALYZE (analyze user-agent)
	static private $GBP_CLASS_BOOTSTRAP         = '';    //GBP_BOOTSTRAP (create the JS object and insert into download)

	static private $GBP_BROWSER_PROPERTIES_FILE = '000000browser.php';
	static private $GBP_SERVER_PHP_FNS_FILE     = '00000server.php';
	static private $GBP_SERVER_PY_FNS_FILE      = '00000server.py';
	
	/** 
	 * arrays used during the build
	 */
	static private $properties_arr                  = null;     //property list, sorted by component
	static private $clients_arr                     = null;     //clients, with client-version sub-array
	static private $clients_versions_arr            = null;     //client with its versions
	static private $clients_versions_properties_arr = null;     //client with versions and properties
	static private $components_arr                  = null;     //list of high-level components
	static private $components_helpers_arr          = null;     //list of 'helper' detectors from other sub-objects
	static private $topo_img_arr                    = array();  //output from directed graph, in format for creating a visual display
	static private $subroot_arr                     = array();  //list of detectors that don't call other detectors

	/** 
	 * properties that are executable functions
	 */
	static private $properties_exe_arr              = array();  //list of JS properties that are functions (and have to be executed)
	

	/** 
	 * user list, input values, a subset of 
	 * all GBP properties
	 */
	static private $user_config                     = false;     //flag for existence of user information
	static private $user_clients_arr                = array();   //user-specified list of clients
	static private $user_properties_arr             = array();   //user-specified list of properties


	/** 
	 * the array used to construct the 
	 * execution array for the GBP JavaScript 
	 * module
	 */
	static private $js_exe_arr                      = array();  //properties that must execute before other properties
	
	static private $js_run_arr                      = array();  //special array for constructing JS run() function

	static private $js_run_str = array(); ////////////////////////
	
	/** 
	 * special array for server-side functions that 
	 * are executed on the server before GBP is downloaded
	 */
	static private $php_fns                         = array();
	static private $py_fns                          = array();
	static private $rb_fns                          = array();


	/** 
	 * output arrays
	 * $ERROR supplied by base class
	 */	
	static private $stats_arr                       = array();  //statistics for the build
	static private $DEBUG_intermediate              = array();  //print out intermediate properties during compile

	/** 
	 * DEBUG arrays
	 * these arrays store the status of specific properties and components
	 */
	static private $DEBUG_detectors;      //array of classification of detectors, js, php, ruby, python...
	static private $DEBUG_prop_unused;    //array of properties NOT requested by the user

	/** 
	 * stored regexp
	 * More examples of camelCase regexps
	 * http://us2.php.net/ucwords
	 */
	static private $CAMEL_REGEXP = '/(-|_)(.?)/eS';

	/** 
	 * output formatting
	 * SVG Tree graph of detector dependencies, 
	 * created using D3 library
	 * http://d3js.org/
	 */
	static private $TREE_GRAPH_WIDTH   = 1260;               //width
	static private $TREE_GRAPH_HEIGHT  = 960;                //height
	static private $TREE_GRAPH_ELEMENT = "d3-tree-graph";    //id of html page element to attach
	
	/** 
	 * INTERNAL CLASSES
	 * 
	 * GBP_UTIL
	 */
	static protected $util;      //a copy of GBP_UTIL
	static protected $analyze;   //a copy of GBP_ANALYZE
	static protected $bootstrap; //a copy of GBP_BOOTSTRAP

	/** 
	 * --------------------------------------------------------- 
	 * constructor
	 * --------------------------------------------------------- 
	 */	
	public function __construct ()
	{
		parent::__construct();

		error_reporting(E_ALL | E_STRICT);

		/** 
		 * define source  as the GBP database
		 * TODO: write to use other databases, e.g. Modernizr, caniuse
		 */
		 self::$SOURCE_GBP = self::get_source_id_by_name('gbp');
		
		/** 
		 * initialize the GBP_UTIL helper object
		 */
		self::$util = new GBP_UTIL;
		
		/** 
		 * we assume that GBP output is always installed as DOCUMENT_ROOT/gbp
		 * get our working directory, so we correctly find our files and folders
		 */
		$basedir = $_SERVER['DOCUMENT_ROOT'];
		self::$util->clean($basedir);
		
		//where we write client database path information
		
		self::$GBP_DB_DIR        = $basedir.'/gbp/db/';		
		self::$GBP_CLIENT_DIR    = self::$GBP_DB_DIR.'client/';
		self::$GBP_CLIENT_JS_DIR = self::$GBP_CLIENT_DIR.'js/';
		self::$GBP_CLIENT_VB_DIR = self::$GBP_CLIENT_DIR.'vb/';

		//path to server-side libraries for dynamic server-side properties (e.g. hostname)
		
		self::$GBP_SERVER_DIR = self::$GBP_DB_DIR.'server/';
		self::$GBP_SERVER_PHP_DIR = self::$GBP_SERVER_DIR.'php/';   //php
		self::$GBP_SERVER_PY_DIR = self::$GBP_SERVER_DIR.'py/';    //python
		self::$GBP_SERVER_RB_DIR = self::$GBP_SERVER_DIR.'rb/';    //ruby

		//path to GBP programs
		
		self::$GBP_CLASS_DIR       = $basedir.'/gbp/lib/php/';
		self::$GBP_CLASS_GBP       = self::$GBP_CLASS_DIR.'gbp.php';
		self::$GBP_CLASS_ANALYZE   = self::$GBP_CLASS_DIR.'gbp-analyze.php';
		self::$GBP_CLASS_BOOTSTRAP = self::$GBP_CLASS_DIR.'gbp-bootstrap.php';

		require(self::$GBP_CLASS_BOOTSTRAP);
		
		self::$analyze = new GBP_ANALYZE;


		/** 
		 * check which subset of properties and clients 
		 * should be included in this build. If called from an 
		 * Initializr form, the $_POST array will contain a subset of 
		 * the total GBP properties to include
		 */
		self::get_user_config($_POST);

		/** 
		 * initially, nothing is wrong
		 */
		self::$stats_arr['status'] = "compile ok";

	}


	/** 
	 * =========================================================
	 * UTILITIES
	 * =========================================================
	 */

	 
	/** 
	 * --------------------------------------------------------- 
	 * @method open_client_file
	 * @param {String} $client_name name of client property file
	 * @param {String} $dir directory where file should be written
	 * @returns {Handle|false} if ok, return file handle, else false
	 * --------------------------------------------------------- 
	 */
	private function open_client_file ($client_name, $dir)
	{			
		if(!is_writable($dir)) //directory has wrong write permissions
		{
			if(self::$DEBUG) self::$ERROR[__METHOD__][] = "Unable to write to specified directory ($dir)<br>";
			return false;
		}

		$file_name = $dir.$client_name;
		$handle = fopen($file_name, 'w') or die('Cannot open file:  '.$file_name."<br>"); //open file for writing ('w','r','a')...

		if($handle)
		{
			return $handle;
		}
		else 
		{
			self::$ERROR[__METHOD__][] = "Client file not opened";
		}

		$handle = null;
		return false;
	 }


	/** 
	 * --------------------------------------------------------- 
	 * @method close_client_file
	 * @param {File Pointer} $handle handle to open file
	 * @returns {Boolean} if closed, return true, else false
	 * --------------------------------------------------------- 
	 */
	 private function close_client_file ($handle)
	 {
		return fclose($handle);
	 }

	 
	/** 
	 * --------------------------------------------------------- 
	 * @method camel_case
	 * camelCase a property name from this_prop or this-prop to thisProp
	 * @param String $prop_name the property name
	 * @returns {String} a camelCased property
	 * --------------------------------------------------------- 
	 */
	 private function camel_case ($str) 
	 {
		preg_replace(self::$CAMEL_REGEXP, "strtoupper('$1')",$str);
		return $str; 
	 }
	 
	 

	/** 
	 * @method check_detector_code
	 * make sure the detector function has been written in a compatible way with GBP
	 * by default, detectors need to have the following:
	 * 
	 * self-rewriting
	 *
	 * non-rewriting
	 
	 * helpers
	 
	 * callbacks
	 * the run() function assumes any dependent functions that rewrite themselves have 
	 * callbacks
	 
	 * @param {String} $detector_code the detector function
	 * @returns {Boolean} if OK, return true, else false
	 */
	private function check_detector_code($detector_code)
	{
		
		return true;
	}


	/** 
	 * --------------------------------------------------------- 
	 * @method strip_comments
	 * remove comments from the detector code, either 
	 * single or multi-line. Ignore comments in quoted strings
	 * http://stackoverflow.com/questions/503871/best-way-to-automatically-remove-comments-from-php-code
	 * @param {String} $detector_code the detector function
	 * @returns {String} the stripped detector function
	 * --------------------------------------------------------- 
	 */
	private function strip_js_comments($detector_code)
	{
		$cmtstr = 'commentstr';
		$cmt_match = array();
		
		//since we're stripping JS comments we can't use the PHP tokenizer
		
		/** 
		 * use a regex that splits the detector code on a quoted string - and keeps all the 
		 * quoted strings its finds in the $matches array
		 * regex for a quoted string with a '//' comment
		 */
		$regex_quoted_string = '~(\".*\/\/.*\".*;?)~m'; //need the 'm' for this to work
		
		$tokens = preg_split($regex_quoted_string, $detector_code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		if(count($tokens) > 0)
		{		
			foreach($tokens as &$token)
			{
				if(substr($token, 0, 1)	!== '"') //ignore the quoted strings
				{
					$token = preg_replace("%(#|(//)).*%", "", $token);
					$token = preg_replace("%/\*(?:(?!\*/).)*\*/%s", "", $token);	
				}
			}
			$detector_code = implode($tokens);
			//echo "DETECTOR CODE REASSEMBLED:".$detector_code;			
		}
		
		//TODO: HAVE NOT HANDLED CASE OF MULTI-LINE COMMENT PATTERN INSIDE A QUOTED STRING
		//TODO: IS IS NEEDED FOR BASE64 encoding?
		
		
		//strip comments not inside a string
		
		//$detector_code = preg_replace("%(#|(//)).*%", "", $detector_code);
		//$detector_code = preg_replace("%/\*(?:(?!\*/).)*\*/%s", "", $detector_code);
		
		//add back quoted strings to code sequentially. Depends on $matches retaining order of discovery
		
		
		return $detector_code;
	}


	/** 
	 * --------------------------------------------------------- 
	 * @method check_if_quotes_needed
	 * given a property, see if we need to 
	 * surround it with quotes
	 *   - reserved words in JavaScript
	 *   - spaces
	 * @param {String} $prop the incoming property
	 * @returns {String} the property, quoted
	 * TODO: 
	 * TODO: incomplete
	 * TODO:
	 * --------------------------------------------------------- 
	 */
	private function check_if_quotes_needed ($prop)
	{
		return $prop;
	}	


	/** 
	 * ---------------------------------------------------------
	 * GET DATA FROM THE USER (input)
	 * ---------------------------------------------------------
	 */
 

	/** 
	 * ---------------------------------------------------------
	 * @method get_user_config
	 * scan $_POST for user choices for client, client-version, 
	 * and GBP properties to include in the build. Split into 
	 * client-versions, and properties. Store the array keys 
	 * for client-versions and properties
	 * ---------------------------------------------------------
	 */
	public function get_user_config ($arr)
	{
		self::$util->clean($arr); 
		
		//check for the token indicating this is a user config for GBP
		
		if(self::$user_config)
		{
			//loop through the properties
		
			foreach($arr as $key => $value)
			{
				//self::$user_clients_arr;
				//self::$user_properties_arr;	
			}
		}
	}


	/** 
	 * ---------------------------------------------------------
	 * @method get_user_client
	 * check the incoming $_POST array for a client 
	 * @param {String} $client_name the client to search for
	 * @returns {Boolean} if client is in the $_POST, return true, else false
	 * ---------------------------------------------------------
	 */
	public function get_user_clients ($client_name) 
	{
		if(self::$user_config)
		{
			/** 
			 * TODO: CHECK FOR USER CONFIGURATION - ONLY SET IF CALLED FROM CONFIGURATION FORM
			 */
			return false;
		}
		
		//our default is true, since if there is no user config, we include all browsers
		
		return true;	
	}


	/** 
	 * ---------------------------------------------------------
	 * @method get_user_properties
	 * check the incoming $_POST arrayfor a property
	 * @param {String} $property_name the property to search for
	 * @returns {Boolean} if property is in $_POST, return true, else false
	 * ---------------------------------------------------------
	 */
	public function get_user_properties ($property_name)
	{
		if(self::$user_config)
		{
			/** 
			 * TODO: CHECK FOR USER CONFIGURATION - ONLY SET IF CALLED FROM CONFIGURATION FORM
			 */
			return false;
		}

		//our default is true, since if there is no user config, we include all browsers
		
		return true;
	}

	
	/** 
	 * ---------------------------------------------------------
	 * @method is_user_client
	 * confirm that the user wants to include this client in the output. Check the specific strings 
	 * where we store user's choices for clients to include in this GBP compile
	 * @param {String} $client_name name of the client (browser) to include or not include in the output
	 * It requires that self::get_user_config() has been run in the constructor
	 * @returns {Boolean} if we should include, return true, else false
	 * ---------------------------------------------------------
	 */
	private function is_user_client ($client_name)
	{
		if(self::$user_config)
		{
			/** 
			 * TODO: CHECK FOR USER CONFIGURATION - ONLY SET IF CALLED FROM CONFIGURATION FORM
			 */
			return false;
		}

		//our default is true, since if there is no user config, we include all browsers
		
		return true;
	}


	/** 
	 * ---------------------------------------------------------
	 * @method is_user_property
	 * confirm that the user wants to include this property in the output
	 * Used for form-based output of a GBP system customized for the project
	 * @param {String} $property_name name of GBP property to test
	 * @returns {Boolean} if user selected property, return true, else false
	 * ---------------------------------------------------------
	 */
	private function is_user_property ($property_name)
	{
		//TODO: TESTING CONFIGURATION ONLY
		
		if($property_name === 'dom0')
		{
			return false;
		}
		
		
		if($property_name === 'cssprophelper')
		{
			return false;
		}
		
		if($property_name === 'jsprophelper')
		{
			return false;
		}
		
		return true;
	}


	/** 
	 * =========================================================
	 * GET DATA FROM THE SQL DB (Initializr)
	 * =========================================================
	 */
	
	
	/** 
	 * ---------------------------------------------------------
	 * @method db_get_components
	 * get the components available in the database, and store in 
	 * internal class variable
	 * @returns {Boolean} if db call ok, return true, else false
	 * ---------------------------------------------------------
	 */
	private function db_get_components ()
	{
		$query = "SELECT components.id,
components.name,
FROM `components`
ORDER BY components";

		$db = self::get_pdo();

		//if desired, sort the results by one or more columns

		try {
			$statement = $db->prepare($query);	
			$statement->execute();	
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			self::$components_arr = $statement->fetchAll();

			//multiple rows come back, an array of rows

			if(is_array(self::$components_arr) && count(self::$components_arr) > 0)
			{
				self::$stats_arr['components'] = true;	
				self::$stats_arr['components_count'] = count(self::$components_arr);	
				$db = null;	
				return true;
			}
		}
		
		catch (Exception $e) {
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table components, select with join failed";
		}

		$db = null;

		return false;
	}


	/** 
	 * ---------------------------------------------------------
	 * @method db_get_properties
	 * get properties, using a JOIN to fill additional fields
	 * like component and datatype, stroe in internal class array
	 * @returns {Boolean} if db call ok, return true, else false
	 * ---------------------------------------------------------
	 */
	private function db_get_properties ($source) 
	{
		$source = self::$SOURCE_GBP;

		//NOTE: this query generates MULTIPLE property records if there are multiple detectors for the property
		//NOTE: this query ONLY FINDS GBP PROPERTIES.
		//TODO: MAKE IT FIND NON-GBP PROPERTIES IF SOURCE DIFFERENT with $SOURCE

$query = "SELECT properties.id,
components.name as component_name,
properties.name as prop_name, 
datatypes.name as datatype_name, 
properties.exe_lock_priority, 
detectors.id as detector_id,
detectors.language as detector_language, 
detectors.code as detector_code
FROM `properties`
INNER JOIN `components` ON properties.component_id = components.id 
INNER JOIN `datatypes` ON properties.datatype_id = datatypes.id 
LEFT JOIN `detectors` ON properties.id = detectors.properties_id 
WHERE properties.source_id=$source 
ORDER BY properties.exe_lock_priority, component_name, properties.name";

		$db = self::get_pdo();

		//if desired, sort the results by one or more columns

		try {
			$statement = $db->prepare($query);	
			$statement->execute();	
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			self::$properties_arr = $statement->fetchAll();

			//multiple rows come back, an array of rows

			if(is_array(self::$properties_arr) && count(self::$properties_arr) > 0)
			{	
				self::$stats_arr['properties_count'] = count(self::$properties_arr);	
				$db = null;	
				return true;
			}
		}

		catch (Exception $e) {

			self::$ERROR[__METHOD__][] = $e->getMessage()." for table properties, select with join failed";
		}

		$db = null;

		return false;		
	}


	/** 
	 * ---------------------------------------------------------
	 * @method get_client_versions
	 * get a list of clients, with versions attached as a sub-array, 
	 * and store in an internal class array
	 *
	 * structure:
	 *
	 * clients['name'] |-> id
	 *                 |-> name
	 *                 |-> properties
	 * 
	 * @returns {Boolean} if db call ok, return true, else false
	 * ---------------------------------------------------------
	 */
	private function db_get_clients_versions () 
	{

		//do all the client-version linking in a big JOIN

		$query = "SELECT clients_versions.id, 
clients.name as client_name, 
clients_versions.version as version_num, 
clients_versions.versionname as version_name, 
searchgroup.name as searchgroup_name, 
FROM `clients_versions`
INNER JOIN `clients` ON clients_properties.clients_id = clients.id 
INNER JOIN `search_group` ON clients_versions.searchgroup_id = search_group.id";

		$db = self::get_pdo();

		//if desired, sort the results by one or more columns

		try {
			$statement = $db->prepare($query);
			$statement->execute();	
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			self::$clients_versions_properties_arr = $statement->fetchAll();

			//multiple rows come back, an array of rows

			if(is_array(self::$clients_versions_arr) && count(self::$clients_versions_arr) > 0)
			{
				self::$stats_arr['clients_versions'] = true;	
				self::$stats_arr['clients_versions_count'] = count(self::$clients_versions_arr);	
				$db = null;	
				return true;
			}
		}

		catch (Exception $e) {
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table clients_versions, select with join failed";
		}

		$db = null;

		return false;
	}


	/** 
	 * ---------------------------------------------------------
	 * @method db_get_clients_versions_properties
	 * get clients_properties, joined with relevant clients_versions 
	 * and properties fields. Assign to self::$clients_versions_properties_arr
	 * @returns {Boolean} if db call ok, return true, else false
	 * ---------------------------------------------------------
	 */  
	private function db_get_clients_versions_properties ()
	{

		$query = "SELECT clients_properties.id, 

components.name as component_name, 
clients.name as client_name, 
clients_versions.version as version_num, 
clients_versions.versionname as version_name, 
properties.id as prop_id,
properties.name as prop_name, 
clients_properties.property_value as prop_value 
FROM `clients_properties`
INNER JOIN `properties` ON clients_properties.properties_id = properties.id 
INNER JOIN `clients` ON clients_properties.clients_id = clients.id 
INNER JOIN `clients_versions` ON clients_properties.clients_versions_id = clients_versions.id 
LEFT JOIN `components` ON clients_properties.components_id = components.id 
ORDER BY clients.name, clients_versions.version";

		$db = self::get_pdo();

		//if desired, sort the results by one or more columns

		try {
			$statement = $db->prepare($query);	
			$statement->execute();	
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			self::$clients_versions_properties_arr = $statement->fetchAll();

			//multiple rows come back, an array of rows

			if(is_array(self::$clients_versions_properties_arr) && count(self::$clients_versions_properties_arr) > 0)
			{
				self::$stats_arr['clients_versions_properties_count'] = count(self::$clients_versions_properties_arr);	
				$db = null;	
				return true;
			}
		}

		catch (Exception $e) {
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table clients_properties, select with join failed";
		}

		$db = null;

		return false;
	}


	/** 
	 * =========================================================
	 * OUTPUT THE GBP JS and Server-side (PHP, Python, Perl, 
	 * Ruby, Node, CSharp) objects
	 * - GBP database (from the SQL Server)
	 * - PHP detectors (written a a single PHP class)
	 * - Python, Perl, Ruby detectors (written as individual scripts)
	 * =========================================================
	 */

	
	/**
	 * ---------------------------------------------------------
	 * @method get_all_detectors
	 * get the detector code, sorted, and stored in an internal 
	 * class array by location (client, server)
	 * @returns {Boolean} if db call ok, return true, else false
	 * ---------------------------------------------------------
	 */
	private function get_all_detectors ()
	{
		/** 
		 * get the detectors, sorted by
		 * location of detection 
		 */


$query = "SELECT 
detectors.id, 
detectors.properties_id, 
detectors.priority, 
detectors.discovery_id,
discovery_state.name, 
properties.name as property_name,
detectors.language,
detectors.language_version,
detectors.code as code 
FROM `detectors` 
INNER JOIN `discovery_state` ON detectors.discovery_id = discovery_state.id 
INNER JOIN `properties` ON detectors.properties_id = properties.id";

		$db = self::get_pdo();

		try {
			$statement = $db->prepare($query);	
			$statement->execute();	
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$detectors_arr = $statement->fetchAll();

			if(is_array($detectors_arr) && count($detectors_arr) > 0)
			{
				self::$stats_arr['detectors'] = true;	
				self::$stats_arr['detectors_count'] = count($detectors_arr);	
				$db = null;	
				return $detectors_arr;
			}
		}

		catch (Exception $e) {
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table detectors, select with join failed";
		}

		$db = null;

		return false;
	}
	

	/** 
	 * ---------------------------------------------------------
	 * @method substitute_gbp_js_fn
	 * replace %xxxx% values in a function with the value for gbp
	 * @param {String} $prop_name name of property whose JS detector function we are processing
	 * @param {Boolean} $strip_comments if true, remove single and multi-line comments, otherwise don't
	 * ---------------------------------------------------------
	 */
	private function substitute_gbp_js_fn($prop_name, $fn, $strip_comments=true)
	{
		/** 
		 * flag incorrect substitution string, e.g., '%helper' instead of '%helper%'
		 */
		if (substr_count($fn, self::$DETECTOR_GBP_SUB_SYMBOL) & 1)
		{
			self::$ERROR[__METHOD__][] = "ERROR: odd number of substitution flags, '".self::$DETECTOR_GBP_SUB_SYMBOL."' found in ".$prop_name;
		}
		
		/** 
		 * validate the detector function code
		 */
		if(!self::check_detector_code($fn))
		{
			self::$ERROR[__METHOD][] = "ERROR: invalid detector code for prop detector:$prop_name, $fn";
		} 
		
		/** 
		 * strip comments
		 */	
		if($strip_comments === true)
		{		
			$fn = self::strip_js_comments($fn);
		}
		
		/** 
		 * we don't need a named function, so strip function %jsproperty% () in the database to just function ()
		 */
		//TODO: regular expression
		
		/** 
		 * substitute %jsproperty%, %gbp%, %helper
		 */				
		if(strpos($fn, self::$DETECTOR_PROP_JS_NAME) !== false)
		{
			$fn = str_replace(self::$DETECTOR_PROP_JS_NAME, $prop_name, $fn);
		}
		if(strpos($fn, self::$DETECTOR_PROP_GBP_NAME) !== false)
		{
			$fn = str_replace(self::$DETECTOR_PROP_GBP_NAME, self::$GBP, $fn);
		}
		if(strpos($fn, self::$DETECTOR_PROP_HELPER_NAME) !== false)
		{
			$fn = str_replace(self::$DETECTOR_PROP_HELPER_NAME, self::$HELPER, $fn);
		}
		if(strpos($fn, self::$DETECTOR_PROP_CALLBACK_NAME) !== false)
		{
			$fn = str_replace(self::$DETECTOR_PROP_CALLBACK_NAME, self::$CALLBACK, $fn);
		}
		
		return $fn;	
	}


	/** 
	 * ---------------------------------------------------------
	 * @method create_helpers
	 * if detectors call other detectors in gbp, create a 'helpers' sub-object, and 
	 * make shortcuts to the other functions in the helper sub-object. Then write a 
	 * helpers: GBP.helpers into that sub-object
	 * Uses: self::$components_helpers_arr, which has a list of GBP properties called 
	 * by other properties
	 * 
	 * @param {&Array] $components_js_arr the nascent GBP object, as a PHP array
	 * ---------------------------------------------------------
	 */
	private function create_helpers(&$components_js_arr)
	{	
		/** 
		 * add in our 'helper' link if we called a helper
		 */
		$components_js_arr[self::$HELPER] = array();
		
		foreach(self::$components_helpers_arr as $component_key => &$component)
		{
			if(count($component) > 0)
			{	
				/** 
				 * loop through the list of helpers, and add to 
				 * the final helper array in $components_js_arr
				 */
				foreach($component as $component_key2 => &$component2)
				{
					foreach($component2 as $prop_key => $prop)
					{
						$components_js_arr[self::$HELPER][$prop_key] = $component_key2.'.'.$prop_key;
					}
				}
				
				//We will have redundant entires, so unique-ify
				//$components_js_arr[self::$HELPER] = array_unique($components_js_arr[self::$HELPER]);
				
			}
		}
		
		self::$stats_arr['helpers'] = $components_js_arr[self::$HELPER];
	}


	/** 
	 * ---------------------------------------------------------
	 * @method real_internal_prop
	 * simple lexer for our detectors finding real GBP properties in a 
	 * detector function.
	 * confirm that a possible internal property is really a property called 
	 * as a function or method, and not part of a comment or other code
	 * $prop = 'gif'
	 * 1. //myProp is never a gif (not a prop, in a comment)
	 * 2. json.stringify() (gif is not a prop, just substring in other code)
	 * @param {String} $detector_code the detector code we are inspecting
	 * @param {String} $internal_prop the possible internal property's name
	 * @returns {Number} - values related to type of property
	 *   -1 - ERROR
	 *    0 - not a real property
	 *    1 - property called as a property, e.g. this.myprop;
	 *    2 - property called as a function, e.g. this.myprop();
	 * ---------------------------------------------------------
	 */
	private function real_internal_prop($detector_code, $internal_prop, $prop)
	{ 

		if(strpos($detector_code, 'function') === false)
		{
			self::$ERROR[__METHOD__][] = "no function for detector $prop, $internal_prop";
			return false;
		}
		
		else if($prop == $internal_prop)
		{
			//don't need to check if we call ourselves
			
			return false;	
		}
		
		else if(strpos($detector_code, $internal_prop) === false)
		{
			//internal_prop does not exist in detector_code

			return false;
		}
		
		//helper.myProp test, if 'helper.prop' is not present, we have a non-property
		
		$regexp = '~('.self::$HELPER.'\.'.$internal_prop.')~';
		if(preg_match_all($regexp, $detector_code, $matches) < 1)
		{	
			return self::$PROPERTY_NOT_PROP;	
		}
		
		//xxx.myProp(); or (... xxx.myProp()) or xxx.myProp (whitespace)
		
		$regexp = '~'.$internal_prop.'\s*(\()[^\)]*(\))~';
		if(preg_match_all($regexp, $detector_code, $matches))
		{
			return self::$PROPERTY_CALLED_AS_FN;
		}
		
		//xxx.myProp; or (... xxx.myProp) or xxx.myProp (whitespace)
		
		$regexp = '~'.$internal_prop.'(\s|;|\))~';
		if(preg_match_all($regexp, $detector_code, $matches))
		{
			return self::$PROPERTY_CALLED_AS_PROP;
		}
		
		//not found, e.g. the prop name was just part of a string, 'css' prop in 'cssprophelper'
		self::$WARNING[__METHOD__][] = "internal prop $internal_prop is present as a string, but not called in detector function for $prop";

		return false;
	}


	/** 
	 * confirm that a detector function never over-writes itself into a 
	 * property. Otherwise, helper functions that are part of our object but 
	 * are never called will be added to the callback system
	 */
	private function detector_no_rewrite($prop_name, $detector_code)
	{
		if(!preg_match('~this.'.$prop_name.'\s*=~', $detector_code,$matches))
		{
			return true;
		}
		
		
		return false;
	}


	/** 
	 * ---------------------------------------------------------
	 * @method create_js_exe_array
	 * given a list of JavaScript functions, some of which may call others, 
	 * compute an order of execution. We need this because some functions 
	 * rewrite themselves on first execution from function to property, e.g. 
	 * function myFunc() { this.myFunc = true; }
	 * 
	 * To sort the array, we use included class TOPOLOGICAL_SORT
	 * and store the results in an internal class variable
	 * 
	 * Note: we assume that we never have detector functions call 'helpers' that 
	 * are not themselves functional. In other words, there are no predefined 
	 * hard-coded values. Predefined properties (e.g. in GBP.config) should be 
	 * "wrapped" in a helper function
	 * 
	 * @param {&Array} $components_js_arr a list of javascript detector functions, some of which may call others
	 * @returns {Boolean] if no errors, return true, else false
	 * ---------------------------------------------------------
	 */
	private function create_js_exe_array (&$components_js_arr)
	{

		$pairwise_dependencies_arr = array();  //pairwise dependencies array ( array(parent, child), array(parent, child)...)
		$prop_fn_name_arr = array();           //array listing all the property function names
		$prop_fn_arr = array();                //array listing all properties whose value is a detector function TEST/DEBUG
		$prop_component_arr = array();         //array for quick lookup of components assigned to property during compile
		$num_detectors = 0;                    //number of dectector functions defined
		$caller_prop_arr = array();            //all props that call a prop
		$callee_prop_arr = array();            //all props that are called by a prop
		$norun_fns_arr = array();             //properties called as helpers (e.g. NOT called as properties)
		$prop_hierarchy_arr = array();         //TODO: compute starting position for nodes (gravity) http://bl.ocks.org/mbostock/1804919

		/** 
		 * loop through all the detector functions, testing if they call another detector in GBP.
		 * differentiate between a function call and a property call
		 * store a lookup table on caller and callee to draw a directed graph later
		 * create an array with the order in which the detectors must be executed
		 */
		foreach($components_js_arr as $component_key => &$component)
		{
			//initialize the list of 'helpers' for a component in other components
			
			self::$components_helpers_arr[$component_key] = array();
			
			/** 
			 * check all the properties we included
			 */
			foreach($component as $prop_key => &$prop)
			{
				if(strpos($prop, 'function') !== false)
				{
					$num_detectors++;
					
					$prop_fn_arr[$prop_key]        = true;           //for all properties with detector functions
					$prop_component_arr[$prop_key] = $component_key; //create reverse-lookup property->component array
					
					/** 
					 * save the name functions that don't rewrite themselves
					 * these don't have to be executed in the dependency tree
					 * (need to ignore later when we write run() {} function callbacks)
					 */
					if(self::detector_no_rewrite($prop_key, $prop) === true)
					{
						$norun_fns_arr[$prop_key] = $prop_key;
					}
					
					/** 
					 * check each property's $prop code against all others find where other GBP detectors are called 
					 * the pairwise dependencies (e.g. dom1 depends on dom0)
					 */
					foreach($components_js_arr as $component_key2 => &$component2)
					{
						foreach($component2 as $prop_key2 => &$prop2)
						{
							if($prop2 === self::$UNDEFINED && strpos($prop, self::$HELPER.$prop_key2) !== false) 
							{
								/** 
								 * ERROR: we called %helper%.prop in a detector function, but the .prop is 'undefined' 
								 * in other words, no detector function was defined for prop2 in the database
								 */
								self::$ERROR[__METHOD__][] = "create_js_exe_array() - fn $prop_key can't reference undefined helper property '$prop_key2', VALUE:$prop2 ";
							}

							if(strpos($prop, 'this.'.$prop2) !== false)
							{
								/** 
								 * ERROR: prop function tries to use 'this.prop2' inside prop
								 * The prop can only refer to itself with 'this'.  All references 
								 * to another GBP detector go through helper.prop2
								 */
								self::$ERROR[__METHOD__][] = "create_js_exe_array() - fn $prop_key references a this.$prop_key2 instead of ".self::$HELPER.".".$prop_key2;	
							}
							
							/** 
							 * SEARCH for prop_key2 in $prop
							 * see if we can find $prop_key2 in $prop's function () {}
							 * and figure out how $prop_key2 is called by $prop_key. If prop_key2 is 
							 * called as a property, add to the dependency array
							 */
							$edge = self::real_internal_prop($prop, $prop_key2, $prop_key);

							/** 
							 * property can appear as a property, a function call, or 
							 * accidental string
							 */
							if($edge === self::$PROPERTY_CALLED_AS_PROP)
							{
								/** 
								 * prop is dependent on prop2, and prop2 is called as a property, not function
								 * since we have a detector function for prop2, it must have to 
								 * convert itself to a property
								 */
								if($prop_key !== $prop_key2)
								{
									/** 
									 * this creates redundant entries, but 
									 * we remove them later in our TOPOLOGICAL_SORT class
									 */
									$prop_fn_name_arr[] = $prop_key; //22222222222222222222222222222222222222222222222222222222222222222
									$prop_fn_name_arr[] = $prop_key2; //33333333333333333333333333333333333333333333333333333333333333
									$pairwise_dependencies_arr[] = array($prop_key2, $prop_key, "property");
									
									//stats on caller and callee
									
									if(isset($caller_prop_arr[$prop_key])) $caller_prop_arr[$prop_key] += 1;
									else $caller_prop_arr[$prop_key]  = 1;
									if(isset($callee_prop_arr[$prop_key2])) $callee_prop_arr[$prop_key2] += 1;
									else $callee_prop_arr[$prop_key2] = 1;
									/** 
									 * helper.prop_key2 to helpers list
									 */
									//self::$components_helpers_arr[$component_key][$component_key2] = $prop_key2;
									self::$components_helpers_arr[$component_key][$component_key2][$prop_key2] = true;
									
									//add to stats array
									
									self::$stats_arr['helper_callers'][] = "$component_key.$prop_key calls $component_key2.$prop_key2 as property .prop;";
									
								} //props should ignore references to themselves
								
								
							} //check_early_js_exe returns a pairwise dependency array
							
							else if ($edge === self::$PROPERTY_CALLED_AS_FN)
							{
								/** 
								 * prop2 is internally executed as a function in prop1, so 
								 * it must NOT be a 'function converting itself to a property'
								 * Add $prop_key2 to helpers object. 
								 * We also add to pairwise dependency array since prop2 may itself 
								 * call a prop function that needs to be converted to a property
								 */
								if($prop_key !== $prop_key2)
								{
									/** 
									 * this creates redundant entries, but we 
									 * remove them later in our TOPOLOGICAL_SORT class
									 */
									$prop_fn_name_arr[] = $prop_key; //22222222222222222222222222222222222222222222222222222222222222222
									$prop_fn_name_arr[]  = $prop_key2;
									$pairwise_dependencies_arr[] = array($prop_key2, $prop_key, "function");
									
									//stats on caller and callee
									
									if(isset($caller_prop_arr[$prop_key])) $caller_prop_arr[$prop_key] += 1;
									else $caller_prop_arr[$prop_key]  = 1;
									if(isset($callee_prop_arr[$prop_key2])) $callee_prop_arr[$prop_key2] += 1;
									else $callee_prop_arr[$prop_key2] = 1;
									
									//add helper.prop_key function to helpers list
									
									self::$components_helpers_arr[$component_key][$component_key2][$prop_key2] = true;
								 
									 //add to stats array
								 
									self::$stats_arr['helper_callers'][] = "$component_key.$prop_key calls $component_key2.$prop_key2 as function prop();";
								}
								 
							}
							else if($edge === self::$PROPERTY_NOT_PROP)
							{
								///not useful
							}
							
						} //inner  loop through all properties $prop_key2 for the detector function property

					} //inner component loop through whole property set, $component_key2 a second time
					
				} //end of filter for props, $prop_key whose value is a detector function

			} //outer property loop $prop_key
		
		} //outer component loop, $component
		
		
		self::$stats_arr['num_detector_functions'] = $num_detectors;

		//SAVE FOR DEBUG OUTPUT
		
		if(self::$DEBUG) 
		{
			self::$DEBUG_intermediate[__METHOD__]['prop_fn_arr'] = $prop_fn_arr;
			self::$DEBUG_intermediate[__METHOD__]['prop_fn_name_arr'] = $prop_fn_name_arr;
			self::$DEBUG_intermediate[__METHOD__]['pairwise_dependencies_arr'] = $pairwise_dependencies_arr;
			self::$DEBUG_intermediate[__METHOD__]['prop_component_arr'] = $prop_component_arr;
			self::$DEBUG_intermediate[__METHOD__]['caller_prop_arr'] = $caller_prop_arr;
			self::$DEBUG_intermediate[__METHOD__]['callee_prop_arr'] = $callee_prop_arr;
			self::$DEBUG_intermediate[__METHOD__]['norun_fns_arr'] = $norun_fns_arr;
		}
		
		/** 
		 * VISUALIZATION
		 * create a subroot array (which finds the detectors which are at the top 
		 * of a depedency tree, e.g. "dom0" for dom0->dom1->dom2
		 */
		self::create_topo_img_arr($prop_fn_name_arr, $pairwise_dependencies_arr, $caller_prop_arr, $callee_prop_arr);
		
		/** 
		 * ADD CODE TO DETECTORS
		 * add code to callee detector functions, which may need to do a callback
		 */
		foreach($callee_prop_arr as $prop_key => $val)
		{
			/** 
			 * replace string calls a small function executing the first parameter of 
			 * the JS arguments object as a function
			 * NOTE: this implies that any self-rewriting function with a parameter must provide parameter AFTER arguments[0]
			 * NOTE: We add this function to the overall js_run_str in the create_run() function
			*/
				$replace = "\nexeCallback (arguments);\n";
				
				/** 
				 * don't add a callback to helper functions. Otherwise the callback is 
				 * THE FIRST argument in the arguments object in the function. If a non-helper 
				 * function needs additional parameters, they must come after the callback
				 */
				if(!isset($norun_fns_arr[$prop_key])) //if this detector re-writes itself as a property
				{
					$detector = &$components_js_arr[$prop_component_arr[$prop_key]][$prop_key];
					$detector = substr_replace($detector, 
						$replace, 
						strrpos($detector, '}'), 
						0);
				}
		}
		
		/** 
		 * ORDER JS ARRAY FOR EXECUTION
		 * after generating the edge list of pairwise dependencies, call a topological sort algorithm to 
		 * put functions in the correct order of execution
		 * Class from:
		 * http://www.calcatraz.com/blog/php-topological-sort-function-384
		 */
		$topo_sort = new TOPOLOGICAL_SORT;
		self::$js_exe_arr = $topo_sort->tsort($prop_fn_name_arr, $pairwise_dependencies_arr, $norun_fns_arr);
		
		/** 
		 * BUILD DEPENDENCY TREE, AND TRANSLATE TO 
		 * A SET OF JS CALLBACKS FOR THE run() FUNCTION
		 */
		$tree = new GTree_JS($pairwise_dependencies_arr, $prop_component_arr, $norun_fns_arr);
		$tree->init_nodes();
		$tree->compute_children();
		$tree->build_trees();
		$tree->build_js($tree->GRoots);
		self::$js_run_arr = $tree->get_js_str_arr();
		
		/** 
		 * INDEPENDENT DETECTORS
		 * independent (no dependency) detectors execute independently
		 */
		if(is_array(self::$js_exe_arr)) 
		{
			if(self::$DEBUG) self::$DEBUG_intermediate[__METHOD__]['js_exe_arr_topo_sort'] = self::$js_exe_arr;
			
			/** 
			 * find detector functions that were in the original prop set, but 
			 * but are independent (neither calling or called)
			 */
			foreach($prop_fn_arr as $prop_key => $val)
			{
				if(!in_array($prop_key, self::$js_exe_arr))
				{
					self::$js_exe_arr[] = $prop_key;
				}
			}

			/** 
			 * write out the JavaScript object path to the property to execute in run()
			 */
			foreach(self::$js_exe_arr as &$prop)
			{
				/**
				 * get the detector, and look for a self-reference prop : function { this.prop = xxx; } 
				 */
				//////////////////////////////////////////////$detector = $components_js_arr[$prop_component_arr[$prop]][$prop];
				//if(strpos($detector, "this.$prop") !== false) //function tries to re-write itself
				//{
					//write correct function call, component.prop string
					
					$prop = $prop_component_arr[$prop].".".$prop;
				//}

			}

			if(self::$DEBUG) self::$DEBUG_intermediate[__METHOD__]['js_exe_arr_final'] = self::$js_exe_arr;
		}
		else
		{
			self::$ERROR[__METHOD__][] = "ERROR: Topological sort failed, pairwise dependencies follow:";
			self::$ERROR[__METHOD__][] = $pairwise_dependencies_arr;
		}

	}
	

	/** 
	 * ---------------------------------------------------------
	 * @method get_all_properties
	 * we get the properties, matched either with a JavaScript function or 
	 * a keycode telling gbp-bootstrap.php to fire the compileed PHP class of 
	 * required PHP functions (mostly in the server: component)
	 * @param {Boolean|String} $detector if present, add the detector function
	 * @returns {Array} the JavaScript array of values and detector functions
	 * ---------------------------------------------------------
	 */
	private function get_all_properties ($detector=false) 
	{
		/** 
		 * build a PHP array with the structure 
		 * $properties [component][property]
		 */
		$prop = array();

		/** 
		 * get the base property array, which was 
		 * joined to some other tables. We re-write 
		 * the array based on joined table values.
		 * puts value in self::$properties_arr
		 * TODO: GENERALIZE FOR DIFFERENT PROPERTY SOURCES
		 */
		self::db_get_properties(self::$SOURCE_GBP);

		//return is we haven't gotten the properties from the db

		if(!is_array(self::$properties_arr))
		{
			self::$ERROR[__METHOD__] = "No properties in properties array<br>";
			return false;
		}

		/** 
		 * we store the JS and php functions separately
		 */
		$components_js_arr = array();

		/** 
		 * make an array for the subset of total properties that 
		 * is NOT specified by the user. We use this to grab 
		 * helper functions
		 */
		$not_used_properties_arr = array();

		/** 
		 * loop through the array of all properties. we don't 
		 * need to access components since we got the component name
		 * in the database join, and can apply it when necessary (e.g. component.property)
		 */	
		foreach(self::$properties_arr as &$prop_arr)
		{
			/** 
			 * write into the global properties file. For 'static' 
			 * properties, get the data now, and pre-fill the array
			 * non-static properties will be filled in individual client-versions  
			 * on the server, or on the client using a JS test
			 * 
			 * NOTE: with the current implementation, 
			 * ONLY SOURCE ID:1000 IS RETRIEVED
			 */
			if(self::is_user_property($prop_arr['prop_name']))   //check to see if the user wants the property included
			{
				/** 
				 * detectors are classified by language in the database
				 * under the field 'detector_language'
				 */
				$fn = self::$UNDEFINED;	
				
				/** 
				 * DEBUG - write detector language for detectors
				 */
				if(self::$DEBUG && strlen($prop_arr['detector_language']) > 0) 
				{
					self::$DEBUG_detectors[$prop_arr['component_name']][$prop_arr['prop_name']] = $prop_arr['detector_language'];
				}

				switch(strtolower($prop_arr['detector_language']))
				{	
					case 'javascript':

						/** 
						 * JS functions are attached directly to their property
						 */
						if(!isset($components_js_arr[$prop_arr['component_name']]))
						{
							$components_js_arr[$prop_arr['component_name']] = array();
						}
						
						
						//substitute functions (strip comments, substitute %xxxx% strings in the function
						
						$fn = self::substitute_gbp_js_fn($prop_arr['prop_name'], $prop_arr['detector_code'], true);
						
						break;

					case 'vbscript':

						break;

					case 'php':

						/** 
						 * PHP functions are stored into an object as methods. The 
						 * methods are fired during runtime in gbp-bootstrap.php as 
						 * the GBP object is being assembled.
						 */
						if(!isset($components_php_arr[$prop_arr['component_name']]))
						{
							$components_php_arr[$prop_arr['component_name']] = array();
						}

						
						/** 
						 * we put a pseudo-property value here to 
						 * trigger gbp-bootstrap.php firing the PHP method for dynamic, server-side properties 
						 * in our compiled gbp PHP object instead. The compiler doesn't 
						 * attach the PHP directly; instead it writes it into a PHP class in 
						 * write_php_detectors()
						 */				
						$fn = self::$DETECTOR_SERVER_PHP.'-'.$prop_arr['prop_name'];
						self::$php_fns[$prop_arr['prop_name']] = str_replace(self::$DETECTOR_PROP_PHP_NAME, $prop_arr['prop_name'], $prop_arr['detector_code']);
						break;

					case 'python':
					
						/** 
						* TODO: python objects should be called by the PHP object written in 
						* write_php_detectors();
						*/
						$fn = self::$DETECTOR_SERVER_PYTHON.'-'.$prop_arr['prop_name'];
						self::$py_fns[$prop_arr['prop_name']] = str_replace(self::$DETECTOR_PROP_PYTHON_NAME, $prop_arr['prop_name'], $prop_arr['detector_code']);
						break;
						
					case 'ruby':
					
						/** 
						 * TODO: ruby objects should be called by the PHP object written in 
						 * write_php_detectors();
						 */
						$fn = self::$DETECTOR_SERVER_RUBY.'-'.$prop_arr['prop_name'];
						self::$rb_fns[$prop_arr['prop_name']] = $prop_arr['detector_code'];
						break;

					case 'config':

						/** 
						 * TODO:
						 * TODO:
						 * read value from GBP user-created config file. This implies that a 
						 * GBP install is like WordPress, with the ability to write a config
						 * via a web-based interface.
						 */
						break;

					default:
						//PHP value for detector language is NULL 
						
						$fn = self::$UNDEFINED;
						break;
				}

				$components_js_arr[$prop_arr['component_name']][$prop_arr['prop_name']] = $fn;

				/** 
				 * save the name of the property we included
				 */
				$components_js_names_arr[] = $prop_arr['prop_name'];

			} //end of test for user inclusion of property

			else 
			{
				/** 
				 * a property in the database that the user didn't include. Save the 
				 * component name and detector code in case it has to be added as a 'helper' 
				 * function later
				 */
				
				 $not_used_properties_arr[$prop_arr['prop_name']] = $prop_arr['detector_code'];

				 $not_used_properties_arr[$prop_arr['prop_name']] = array (
				 							'component_name' => $prop_arr['component_name'], 
											'detector_code' => $prop_arr['detector_code']
											);
			} //end of properties not specified by user (but may be 'helpers' which need to be added later)

		} //end of foreach for the complete list of global properties (including those not requested for compile)

		/** 
		 * look at full properties for JavaScript in GBP, and check if detector code 
		 * mentions any properties in our database that don't appear in our subset 
		 * of selected properties. Most commonly, these will be 'helper' functions 
		 * that aren't called directly, but used by GBP properties
		 */
		foreach($components_js_arr as $component_key => &$component)
		{	
			/** 
			 * check all the properties we included
			 */
			foreach($component as $prop_key => &$prop)
			{
				/** 
				 * if the property is actually a function
				 */
				if(strpos($prop, 'function ') !== false)
				{
					/** 
					 * see if an unused property is being called inside this function
					 */
					foreach($not_used_properties_arr as $unused_prop_key => $unused_prop)
					{
						/** 
						 * if the unused property is also a function
						 */
						if(substr_count($prop, $unused_prop_key) > 0) //unused property mentioned in function code
						{
							/** 
							 * an unused property, but it is called 
							 * by a used property. Include the detector
							 * code, along with the component (since the 
							 * component may be different)
							 */
							if($prop_key != $unused_prop_key)
							{	
								/**
								 * do substitutions
								 */
								
								
								/** 
								 * add the implicity called property to our array
								 */							
								//$components_js_arr[$unused_prop['component_name']][$unused_prop_key] = str_replace(self::$DETECTOR_PROP_JS_NAME, $unused_prop_key, $unused_prop['detector_code']);
								$components_js_arr[$unused_prop['component_name']][$unused_prop_key] = self::substitute_gbp_js_fn($unused_prop_key, $unused_prop['detector_code']);

///$components_js_arr['helper'][$unused_prop_key] = str_replace(self::$DETECTOR_PROP_JS_NAME, $unused_prop_key, $unused_prop['detector_code']);

								//flag the property as called by another property
	
							} //end of test so we don't call ourself 

							
						} //end of unused property is a function

						
					} //end of loop through unused properties

					
				} //end of property value is a function instead of a value

				
			} //end of loop through all properties


		} //end of loop through each component

		//return the JavaScript array of values and detector functions

		return $components_js_arr;
	}


	/** 
	 * ---------------------------------------------------------
	 * @method write_php_detectors
	 * server-side dynamic detectors
	 * given an array of strings compiling to functions, 
	 * write a php string that, when saved into an output 
	 * file will be valid PHP code creating an object with 
	 * those functions implemented as internal methods. Functions
	 * are stored as anonymous functions, and are rewritten as class 
	 * members during the conversion.
	 * 
	 * @param {Array} $fn_arr an array of strings, where the strings are 
	 * PHP function code serialized as a string
	 * @returns {String} the string corresponding to the PHP object
	 * ---------------------------------------------------------
	 */
	private function write_php_detectors ($fn_arr)
	{
		$export = "<?php \n\nclass GBP_SERVER_PHP_DETECTORS { \n\npublic function __construct() {}\n\n";

		foreach($fn_arr as $fn_name => $fn)
		{
			$fn = str_replace('function', "public function", $fn);
			$export .= "$fn\n\n";
		}

		/** 
		 * TODO:
		 * TODO: add the helper detector check_if_quotes_needed(), and make 
		 * any method that might return values that need to be quoted call 
		 * check_if_quotes_needed
		 */
		$export .= "\n};";

		return $export;
	}


	/** 
	 * ---------------------------------------------------------
	 * @method write_python_detectors
	 * write python server-side detector to an executable script
	 * 
	 * The python script returns a JSON object with the values of 
	 * all properties detected. It only runs once.
	 * 
	 * @param {Array} $fn_arr array of strings, where the strings are
	 * python functions serialized as a string
	 * @returns {String} the string corresponding to the complete Python script
	 * ---------------------------------------------------------
	 */
	private function write_python_detectors ($fn_arr)
	{
		$export = "#!/usr/bin/env python\n\n";

		foreach($fn_arr as $fn_name => $fn)
		{
			$fn = str_replace('function', "def  $fn_name:", $fn);
			$export .= "$fn\n\n";
		}
		
		$export .= "# ----------------------------\n# MAIN PROGRAM\n# ----------------------------\n\nif __name__ == \"__main__\":\n\n";

		$fn_num = count($fn_arr);
		$ct = 0;
		

		$export .= "\tserver = {\n";
		
		foreach($fn_arr as $fn_name => $fn)
		{
			$ct++;
			if($ct < $fn_num)
			{
				$comma = ",";
			}
			else
			{
				$comma = "";
			}
			$export .= "\t\t'$fn_name' : str(".$fn_name."())$comma \n";
		}
		
		$export .= "\t\t}\n";
		
		//now write a python json export 
		
		$export .= "\ttry:\n\t\timport json\n\t\tprint(json.dumps(server, ensure_ascii=False))\n\texcept (ImportError, AttributeError):\n\t\t print('{}')\n";
		
		return $export;
	}
	
	
	/** 
	 * @method write_run_callback
	 * run method using callbacks to control execution order
	 */
	private function create_run($full_properties)
	{
		$run_str = "";
		
		$callback_fn = "\t//function executing all callbacks\n\n\tfunction exeCallback (args) { \n\t\tvar arr = [].slice.apply(args);\n\t\tif(typeof arr[0] === \"function\") { \n\t\t\tarr[0](); \n\t\t} \n\t}\n\n";
		
		$run_str = $callback_fn;
		
		foreach(self::$js_run_arr as $run_key => $runs)
		{
			$run_str .= $runs;
		}
		
		$returned_object = self::write_returned_object($full_properties);
		
		
		$run_str = substr_replace($run_str, 
					$returned_object, 
					strrpos($run_str, '}'), //last occurence of bracket (current end of run() function)
					0);
		
		$run_str .= "\n\treturn run();\n";
		return $run_str;
	}
	
	
	/** 
	 * @method write_run_function
	 * write the run () {...} functions with sequentially 
	 * executes GBP detectors in the correct dependency order
	 */
	private function write_run_function()
	{
		$run_fn = "\tvar run = function () {\n\n\n";
		
		//write out the early execution methods, in the correct order
		
		$run_fn .= "\t\t//write early exe methods, in order of execution\n\n";
		
		/** 
		 * early execution functions, sorted for correct order of execution 
		 * TODO:
		 * TODO: MAKE THIS SUCKER RUN CALLBACKS FOR EACH FUNCTION!!!
		 * TODO: =============================================================!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
		 */
		$len = count(self::$js_exe_arr);
		$rn_fn = "";
		
		/** 
		 * make a debug array with the names of the functions only. When the JavaScript compiles, it 
		 * will store a reference to the functions in the object, rather than their name. The array 
		 * resulting will execute quickly. 
		 * Have to write a separate "debug" array with the names as string literals
		 */
		
		$debug_run_arr = "\t\tvar runDebug = [\"";
		
		$run_arr = "\t\tvar runArr = [";
		
		$run_list = "";
		
		for($i = 0; $i < $len; $i++)
		{
			if(strpos(self::$js_exe_arr[$i], "helper") === false)
			{
				$run_list .= "\t\t".'if(typeof '.self::$js_exe_arr[$i].' == "function") '.self::$js_exe_arr[$i].'()'."\n";
				
				//RUN ARRAY
				$run_arr .= self::$js_exe_arr[$i];
				$debug_run_arr .= self::$js_exe_arr[$i];
				if($i < $len - 1)
				{
					$run_arr .= ', ';
					$debug_run_arr .= '", "';
				}
				else
				{
					//nothing
				}
			}
			else
			{
				//helper function (does not re-write itself to a property)
			}
		}
		
		$run_arr .= "];\n";
		
		if(self::$DEBUG)
		{
			$debug_run_arr .= "\"];\n";
			$run_arr .= $debug_run_arr;
		}
		
		/** 
		 * performance tests show a simple 'typeof' here is 
		 * the fastest check
		 * http://jsperf.com/alternative-isfunction-implementations/4
		 * http://javascriptweblog.wordpress.com/2011/08/08/fixing-the-javascript-typeof-operator/
		 * 
		 * TODO: replace this list with a call to a Promise-like async module like
		 * https://github.com/FuturesJS/sequence
		 * https://github.com/masylum/funk
		 */
		
		
		$run_arr .= "\t\tfor(var i in runArr) {
			if(typeof runArr[i] == \"function\") { 
			console.log(\"executing...\" + runDebug[i]);
			runArr[i]();
			}
		};\n\n";
		
		
		//$run_fn .= "\n$run_arr\n";
		$run_fn .= "\n$run_list\n";
		
		/** 
		 * finish writing the run() function, then write a 
		 * line causing its immediate execution on the client-side
		 */
		$run_fn .= "\n\t} //end of run function\n\n\t//executing run\n\n\trun();\n\n";
		
		//$run_fn .= $run_arr;
		
		return $run_fn;
	}

	/** 
	 * @method write_debug_arrays
	 * if DEBUG, create arrays for client-reporting
	 * inserted into the constructor function for the GBP 
	 * object, and returned if they are present.
	 * possible debug arrays
	 * - Detector language
	 */
	private function write_debug_arrays()
	{
		if(self::$DEBUG)
		{
			//the detectors array - holds the language of the detector function 
			
			if(count(self::$DEBUG_detectors) > 0) 
			{
				$detector_types = "\n\t\t//debug detector type\n\t\t var detectorTypes = {\n";
				
				$component_count = 0;
				$num_components = count(self::$DEBUG_detectors);
				foreach(self::$DEBUG_detectors as $component_key => &$component)
				{
					$detector_types .= "\t\t\t$component_key : {\n";
					
					$prop_count = 0;
					$num_props = count($component);
					foreach($component as $prop_key => &$prop)
					{
						$detector_types .= "\t\t\t\t$prop_key : \"$prop\"";
						
						$prop_count++;
						if($prop_count < $num_props)
						{
							$detector_types .= ",\n";
						}
						else
						{
							$detector_types .= "\n";
						}
					}
					
					$detector_types .= "\t\t\t}";
					
					$component_count++;
					if($component_count < $num_components) 
					{
						$detector_types .= ",\n";
					}
					else
					{
						$detector_types .= "\n";
					}
				}
				
				$detector_types .= "\t};\n\n";
				return $detector_types;
			}
			
			//have an error, add nothing
		
			self::$ERROR[__METHOD__][] = "Error: no values in DEBUG_detectors array";
			
		}
		
		return "";
		
	}
	

	/** 
	 * @method write_returned_object
	 * write the return {component:component_value}; of values which form the 
	 * final GBP object returned by its constructor function.
	 * @param {Array} $full_properties the complete GBP property list. 
	 * We return the names of the components
	 * @returns {String} the corresponding JavaScript string creating the return
	 */
	private function write_returned_object($full_properties)
	{
		$returned_obj = "//returned object\n\n\treturn {\n";
		
		$len = count($full_properties);

		foreach($full_properties as $component_key => &$component)
		{
			$returned_obj .= "\t\t$component_key : $component_key,\n";
		}
		
		//DEBUG - export the debug arrays, which are not part of GBP itself
		
		if(self::$DEBUG)
		{
			$returned_obj .= "\t\tdetectorTypes : detectorTypes,\n";    //write detectorTypes (type of detector code)
		}
		
		//add the run() function to the export list, which is not part of GBP properties
		
		$returned_obj .= "\t\trun : run\n\t\t}; //end of returned object\n\n\t";
		
		return $returned_obj;
	}


	/**
	 * ---------------------------------------------------------
	 * @method compile_gbp 
	 * the top-level compile function for GBP
	 * @param {Boolean} $check_user if true, look at the $_POST or $_GET array 
	 * to restrict the collection of clients and properties
	 * @returns {Boolean} returns status of compile, ok/not ok
	 * ---------------------------------------------------------
	 */
	public function compile_gbp ($check_user=true)
	{
		//compile environment (checked in constructor)
		
		if(!self::$COMPILE_ENV_OK)
		{
			return false;
		}
		
		//start the timer

		self::$stats_arr['compile_start'] = self::$util->microtime_float();
		self::$stats_arr['compile_end']   = 0;

		/** 
		 * get the client-versions-properties from our SQL database, joined with 
		 * additional data including our detector functions 
		 * and property fields
		 */
		self::db_get_clients_versions_properties();

		/** 
		 * get all the properties, and substitute in detector functions if 
		 * they are available. Otherwise, put in an 'undefined'
		 * 
		 * For each property, we check:
		 * 1. configuration file (of current GBP install)
		 * 2. client database for each browser client-version
		 * 3. server-side sniff using a PHP or other server detector. The 
		 *    server detectors can find data that is:
		 *    - directly defined by the user-agent
		 *    - incoming data in HTTP headers, $_GET, $_POST defined for the individual user
		 *    - incoming data in HTTP header, $_GET, $_POST, defined for the client browser
		 *      + standard browser headers
		 *      + gbp cookie data
		 * 4. client-side sniff using JavaScript
		 */
		$full_properties = self::get_all_properties(true);

		/** 
		 * sort properties so detector functions that need to fire first will do so
		 * and only those who are "functions" at the start. Helper functions are left 
		 * alone (and not fired separately)
		 * creates:
		 * self::$js_exe_arr[]
		 */
		self::create_js_exe_array($full_properties);
		
		if(!is_array(self::$js_exe_arr))
		{
			self::$ERROR[__METHOD__][] = "ERROR - unable to create early execution array";
			return;
		}
		
		/** 
		 * add the 'helpers' object to our array
		 * with links from sub-objects (components) to the 
		 * 'helpers' object
		 */
		self::create_helpers($full_properties);

		/** 
		 * since browser names may change, we need to delete
		 * any that aren't in the new compile. We do that by
		 * deleting all the client files in the JS directory that aren't listed 
		 * in the new build
		 */
		$old_client_file_arr = preg_grep('~\.(php)$~', scandir(self::$GBP_CLIENT_JS_DIR));

		foreach($old_client_file_arr as &$old_client_file)
		{
			unlink(self::$GBP_CLIENT_JS_DIR.$old_client_file);
		}

		/** 
		 * write the PHP serverside functions to a single object file 
		 * (created when get_all_properties() ran above
		 * unfortunately, we can't use functions in the object/array returned by 
		 * json_encode easily, so we write the PHP detector class directly
		 */
		if(count(self::$php_fns) > 0)
		{
			$handle = self::open_client_file(self::$GBP_SERVER_PHP_FNS_FILE, self::$GBP_SERVER_PHP_DIR);
			$export = self::write_php_detectors(self::$php_fns);
			$bytes_written = fwrite($handle, $export);
			self::$stats_arr['client'] = "wrote $bytes_written bytes to ".self::$GBP_SERVER_PHP_DIR.self::$GBP_SERVER_PHP_FNS_FILE;
			self::close_client_file($handle);
			$handle = null;	
		}
		else
		{
			$fl = self::$GBP_SERVER_PHP_DIR.self::$GBP_SERVER_PHP_FNS_FILE;
			unset($fl); //remove, won't handle inline concatenation of filename
		}
		
		/** 
		 * write any python serverside function to the python directory
		 * TODO:
		 * TODO: catch Python errors in gbp-bootstrap
		 * TODO:
		 */
		if(count(self::$py_fns) > 0)
		{
			$handle = self::open_client_file(self::$GBP_SERVER_PY_FNS_FILE, self::$GBP_SERVER_PY_DIR);
			$export = self::write_python_detectors(self::$py_fns);
			$bytes_written = fwrite($handle, $export);
			self::$stats_arr['client'] = "wrote $bytes_written bytes to ".self::$GBP_SERVER_PY_DIR.self::$GBP_SERVER_PY_FNS_FILE;
			self::close_client_file($handle);
			$handle = null;				
		}
		else
		{
		}

		/** 
		 * TODO: 
		 * write Perl scripts called by PHP to the perl directory
		 * TODO:
		 */

		/** 
		 * TODO:
		 * write ruby called by PHP to the ruby directory
		 * TODO: NOT COMPLETED
		 */
		 
		/** 
		 * TODO:
		 * write csharp called by PHP to csharp directory
		 * TODO:
		 */
		
		
		
		/** 
		 * create a "run" function that executes
		 * GBP properties that are determined dynamically on the client. We also make a local reference 
		 * to the GBP object via "this" and use it to execute detectors in different sub-objects in GBP
		 * 
		 * ------IMPORTANT------
		 * multiple tests showed that the module pattern was superior to the object-literal 
		 * pattern in terms of speed (~2x) for a very large object like GBP in both old and modern browsers
		 *
		 */
		 
		 self::$js_run_str = self::create_run($full_properties); ////////////////////
		 $run = self::$js_run_str;
		 
		 //OLD, NON-CALLBACK RUN
		////////@@@@@@@@@@@@@@@@@@@@@@@@@/////////////////////////////////////////////////////////////////////////////////$run = self::write_run_function();
		
		
		/** 
		 * DEBUG - write special debugging arrays to GBP object outside the main GBP area
		 */
		$run .= self::write_debug_arrays();
		
		/** 
		 * now create the returned GBP object via the module pattern
		 * outside and below run();
		 * return { component_key1 : component1, component_key2 : component2...}
		 */
		 
		//OLD, NON-CALLBACK RUND
		///@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@///////////////////////////////////////////////////////////////////////////////////$run .= self::write_returned_object($full_properties);
		
		/** 
		 * attach the run() function, plus debug arrays, 
		 * plus the returned_object code to the GBP object 
		 * (which at this point is a huge PHP array). This is 
		 * a simple array value (string), and NOT compatible with the 
		 * component::property structure of the rest of the GBP object
		 */		  
		$full_properties['js_exe_arr'] = $run;
		
		/** 
		 * attach a debug flag from compiler to bootstrap
		 * like run() it is a simple array, and NOT compatible 
		 * with the component::property of the rest of the GBP object. 
		 * It is NOT exported by the JavaScript object
		 */
		$full_properties['debug'] = self::$DEBUG;
		
		/////////////////////////////self::print_array($full_properties, "FULL PROPERTIES");
		if(self::$DEBUG)
		{
			self::$DEBUG_intermediate[__METHOD__]['full_properties'] = $full_properties;	
		}
		
		
		/** 
		 * get the full list of properties, and write them to a separate file.
		 * This file also contains ALL JavaScript detector functions. Individual browser 
		 * files only contain properties derived from the database, not dynamic server or 
		 * client functions.
		 */
		$handle = self::open_client_file(self::$GBP_BROWSER_PROPERTIES_FILE, self::$GBP_CLIENT_JS_DIR);
		
		
		/** 
		 * write the GBP object to the properties file. It includes
		 * the detectors, helper detectors, and the run() function executing
		 * functions which rewrite themselves as properties
		 * TODO: write natively, instead of via JSON, for increased 
		 * speed in gbp-bootstrap
		 */
		$bytes_written = fwrite($handle, json_encode($full_properties));
		
		self::$stats_arr['server'] = "wrote $bytes_written bytes to ".self::$GBP_CLIENT_JS_DIR.self::$GBP_BROWSER_PROPERTIES_FILE;

		self::close_client_file($handle);

		$handle = null;

		//need a default to to enter the loop below

		$old_client_name = '';

		//array to hold one or more client data collections

		$client_db_arr = array();

		/** 
		 * Write client-side properties from SQL database into the GBP client database.
		 *
		 * loop through our list, assuming that clients and versions are PRE-SORTED in SQL. That is, 
		 * a client-version can't appear within a list of another client. We do this to avoid 
		 * looping through the entire client_version_property_arr for each client.
		 *     client1
		 *     client1
		 *     ...
		 *     client2
		 *     client2
		 *     ...
		 *     client3
		 *     ...
		 */
		foreach(self::$clients_versions_properties_arr as &$client_version_property_arr)
		{
			if(self::is_user_property($client_version_property_arr['prop_name']) &&      //check to see if the user wants the property included
				self::is_user_client($client_version_property_arr['client_name']))   //check to see if the user wants the client included
			{
				//add the client to the global array if necessary
			
				$curr_client_name = $client_version_property_arr['client_name'];
			
				//we're starting a new client
			
				if($curr_client_name !== $old_client_name)
				{
					if($handle) 
					{
						//write the old file, if present
						
						$bytes_written = fwrite($handle, json_encode($client_db_arr));
						
						//close the file
						
						self::close_client_file($handle);
						$handle = null;
						
						//reset the array
						
						$client_db_arr = array();
						
						$key = array_search($client_version_property_arr['client_name'].'.php', $old_client_file_arr);
						
						//we CAN get null or false keys, so don't unset something that isn't there
						
						if($key != 0)
							
						{
							unset($old_client_file_arr[$key]);
						}

					}

					$handle = self::open_client_file($client_version_property_arr['client_name'].'.php', self::$GBP_CLIENT_JS_DIR);
				}

				//add the version to the global array if necessary

				//////////////////////////////////////////////////////////////////////////$new_version_num = self::$util->generate_php_version_key($client_version_property_arr['version_num']);
				$new_version_num = self::$analyze->numberize_version($client_version_property_arr['version_num'], 100);

				if(!isset($client_db_arr[$new_version_num]))
				{	
					$client_db_arr[$new_version_num] = array();
				}

				/** 
				 * make the client-property listing
				 * 
				 * NOTE: this writes flags into the 'undefined'
				 * properties 
				 * 
				 * NOTE: only defined properties show up in the versions
				 * so our integrator program needs to "fuse" the JS and PHP 
				 * at runtime
				 * 
				 * 1. PHP dynamic tests - global
				 * 2. JS dynamic tests - global
				 * 3. database values - local to version
				 */
				if($client_version_property_arr['prop_name'] !== self::$PROPERTIES_COMMENTS)

				{
					$client_db_arr[$new_version_num][$client_version_property_arr['component_name']][$client_version_property_arr['prop_name']] = $client_version_property_arr['prop_value'];
				}

				//keep track of what client name was last processed

				$old_client_name = $curr_client_name;
			}
		}
		
		if(self::$DEBUG)
		{
			self::$DEBUG_intermediate[__METHOD__]['client_db_arr'] = $client_db_arr;
		}

		//finish writing out stats on performance

		self::$stats_arr['compile_end']  = self::$util->microtime_float();
		self::$stats_arr['compile_time'] = (self::$stats_arr['compile_end'] - self::$stats_arr['compile_start'])." seconds";

		return self::$stats_arr['status'];
		
		/////////////////return self::$stats_arr;

	} //end of compile_gbp
	
	
	/**
	 * --------------------------------------------------------- 
	 * RENDERING AND OUTPUT FUNCTIONS
	 * ---------------------------------------------------------
	 */

	/** 
	 * ---------------------------------------------------------
	 * @method create_topo_img_arr
	 * create a topo sort array that can be visualized by a tree-building 
	 * routine
	 * ---------------------------------------------------------
	 */
	private function create_topo_img_arr($prop_fn_name_arr, $pairwise_dependencies_arr, $caller_prop_arr, $callee_prop_arr)
	{
		
		foreach($callee_prop_arr as $prop_key => &$num)
		{
			if(!isset($caller_prop_arr[$prop_key])) //the called also calls others
			{
				//subroot array (detectors that don't call any other detectors for self::output_tree_graph
				
				self::$subroot_arr[] = $prop_key;
			}
			
		} 
		/* 
		ob_start();
		imagepng($png);
		// Capture the output
		$imagedata = ob_get_contents();
		// Clear the output buffer
		ob_end_clean();
		*/
		
		//[0] is parent, [2] is child, [2] is child, [3] is subroot flag

		foreach($pairwise_dependencies_arr as $pair)
		{
			self::$topo_img_arr[] = array($pair[0], $pair[1], $pair[2]);
		}
		
	}


	/** 
	 * ---------------------------------------------------------
	 * @method output_tree_graph using d3.js "force layout"
	 * convert the dependency array into a visual graph
	 * uses d3 JS visualization library from example
	 * http://bl.ocks.org/mbostock/1153292
	 * @param {Number} $width width of output graph
	 * @param {Number} $height height of output graph
	 * @param {String} $css_selector if present, define the css 
	 * id=xxx value where we should insert this graph. Otherwise, 
	 * just append to the <body> of the document
	 * ---------------------------------------------------------
	 */
	public function output_tree_graph($width=0, $height=0, $css_selector='')
	{		
		$tree_script = "<script>\n window.onload = function () {\n";
		
		//add a utility in_array() equivalent into the JS
		//adapted from http://phpjs.org/functions/in_array/
		
		$tree_script .= '
		function in_array(needle, haystack, argStrict) {
  var key = "",
    strict = !! argStrict;

  //we prevent the double check (strict && arr[key] === ndl) || (!strict && arr[key] == ndl)
  //in just one for, in order to improve the performance 
  //deciding wich type of comparation will do before walk array
  if (strict) {
    for (key in haystack) {
      if (haystack[key] === needle) {
        return true;
      }
    }
  } else {
    for (key in haystack) {
      if (haystack[key] == needle) {
        return true;
      }
    }
  }

  return false;
}'."\n";
		
		if(is_array(self::$topo_img_arr))
		{
			$tree_script .= "var links = [\n";
						
			foreach(self::$topo_img_arr as $node)
			{
				$tree_script .= '{source: "'.$node[1].'", target: "'.$node[0].'", type: "'.$node[2].'"},'."\n";	
			}
			
			
  			$tree_script .= "];\n";

			//add a subroot array (detectors that don't call any other detectors)
			
			$len = count(self::$subroot_arr);
			$subroot = "var subroot = [";
			
			for($i = 0; $i < $len; $i++)
			{
				$subroot .= '"'.self::$subroot_arr[$i].'"';
				if($i < $len-1)
				{
					$subroot .= ", ";
				}
				else
				{
				}
			}
			$subroot .= "];\n";
			
			$tree_script .= $subroot;

			//start the javascript
			
			$tree_script .= 'var nodes = {};

//compute the distinct nodes from the links

links.forEach(function(link) {
  link.source = nodes[link.source] || (nodes[link.source] = {name: link.source});
  link.target = nodes[link.target] || (nodes[link.target] = {name: link.target});
});


var width = 1260,
    height = 800,
    distance = 150,  //distance between nodes
    nodeRadius = 12; //node radius
    
    //define root node(s) with a fixed position from our "subroot" calcs (callees that are not callers)
    
    d3.values(nodes).forEach(function (node) {
	   if(in_array(node.name, subroot)) {
		   node.fixed = true;
		   node.x = width/2;
		   node.y = nodeRadius + 5;
	   }
    	}
    );
    

var force = d3.layout.force()
    .nodes(d3.values(nodes))
    .links(links)
    .size([width, height])
    .gravity(0.05)
    .linkDistance(distance)
    .charge(-300)
    .on("tick", tick)
    .start();

//ATTACH TO PAGE
';

//use supplied height, or defaults is none
if(!$width) $width = self::$TREE_GRAPH_WIDTH;
if(!$height) $height = self::$TREE_GRAPH_HEIGHT;

//append to element defined by id, or <body> if missing
	
if(strlen($css_selector) > 0)
{
	$tree_script .= 'var svg = d3.select("#'.$css_selector.'").append("svg")';
}
else
{
	$tree_script .= 'var svg = d3.select("body").append("svg")';
}


$tree_script .= '
    .attr("width", '.$width.')
    .attr("height", '.$height.');

//per-type markers, as they dont inherit styles. dependency, property, function

svg.append("defs").selectAll("marker")
    .data(["dependency", "property", "function"])
    .enter().append("marker")
    .attr("id", function(d) { return d; })
    .attr("viewBox", "0 -5 10 10")
    .attr("refX", function (d) {
	return 2 * nodeRadius; //changed so we can vary circle sizes
    })
    .attr("refY", -1.5)
    .attr("markerWidth", 6)
    .attr("markerHeight", 6)
    .attr("orient", "auto")
    .append("path")
    .attr("d", "M0,-5L10,0L0,5");

var path = svg.append("g").selectAll("path")
    .data(force.links())
    .enter().append("path")
    .attr("class", function(d) { return "link " + d.type; })
    .attr("marker-end", function(d) { return "url(#" + d.type + ")"; })
    .attr("rad", nodeRadius);

var circle = svg.append("g").selectAll("circle")
    .data(force.nodes())
    .enter().append("circle")
    .attr("r", nodeRadius)
    .call(force.drag);

var text = svg.append("g").selectAll("text")
    .data(force.nodes())
    .enter().append("text")
    .attr("x", 16)
    .attr("y", ".4em")
    .text(function(d) { return d.name; });

//use elliptical arc path segments to doubly-encode directionality.

function tick(e) {
  path.attr("d", linkArc);
  circle.attr("transform", transform);
  text.attr("transform", transform);
}

function linkArc(d) {
  var dx = d.target.x - d.source.x,
      dy = d.target.y - d.source.y,
      dr = Math.sqrt(dx * dx + dy * dy)*2; //reduce this for more circular arc
  return "M" + d.source.x + "," + d.source.y + "A" + dr + "," + dr + " 0 0,1 " + d.target.x + "," + d.target.y;
}

function transform(d) {
  return "translate(" + d.x + "," + d.y + ")";
}';

		}
		
		$tree_script .= "}\n</script>\n";
		return $tree_script;
}


	/**
	 * ---------------------------------------------------------
	 * @method print_array 
	 * debugging utility for checking the current 
	 * configuraiton of an array
	 * @param {Array} $arr array to print out
	 * @param {String} $title (optional) title for printed array
	 * @param {String} $style (optional) style for printout
	 * ---------------------------------------------------------
	 */
	public function print_array (&$arr)
	{
		echo "<pre>\n";
		print_r($arr);
		echo "</pre>\n";	
	}
	
	
	public function print_accordion_item(&$arr, $title='', $color='')
	{
		echo "<div class=\"accordion-item hide\">\n<h3 style=\"color:".$color.";\">$title</h3>\n";
		self::print_array($arr);
		echo "</div>\n";
			
	}
	

	/** 
	 * ---------------------------------------------------------
	 * @method print_compile_results
	 * print out the values of the $status and $ERROR arrays
	 * ---------------------------------------------------------
	 */
	public function print_compile_results ()
	{
		echo "<article class=\"accordion\" id=\"accordion-compile\">\n";
		
		$results_color = 'green';
		
		if(count(self::$ERROR) > 0)
		{
			self::print_accordion_item(self::$ERROR, "COMPILE ERRORS", 'red');	
			$results_color = 'red';
			self::$stats_arr['status'] = 'compile failed';
		}
		
		self::print_accordion_item(self::$WARNING, "COMPILE WARNINGS", $results_color);
		self::print_accordion_item(self::$stats_arr, "COMPILE STATS", $results_color);
		
		if(self::$DEBUG)
		{
			foreach(self::$DEBUG_intermediate as $arr_key => $intermediate_arr)
			{
				self::print_accordion_item($intermediate_arr, $arr_key, 'black');
			}
			
			self::print_accordion_item(self::$DEBUG_detectors, "DETECTOR TYPES", 'black');
			self::print_accordion_item(self::$topo_img_arr, 'TOPO IMAGE ARRAY', 'black');
		}
		
		
		///////////////////JS OUTPUT
		self::print_accordion_item(self::$js_run_arr, "JS RUN ARR", 'black');
		self::print_accordion_item(self::$js_run_str, "RUN STRING", 'black');
		
		//end of accordion

		echo "</article>\n";
		
		//create a visual tree graph showing detector dependencies
		
		echo "<article id=\"".self::$TREE_GRAPH_ELEMENT."\" class=\"accordion-item show\" ><h3>Detector Nodes</h3>\n";
		echo self::output_tree_graph(self::$TREE_GRAPH_WIDTH, self::$TREE_GRAPH_HEIGHT, self::$TREE_GRAPH_ELEMENT);
		echo "</article>\n";
	}
	

	
}; //end of class



/** 
 * ---------------------------------------------------------
 * PROGRAM EXECUTION
 * create a new GBP_COMPILE object, and 
 * run it 
 * ---------------------------------------------------------
 */
$gbp_compiler = new GBP_COMPILE;
$stats_arr = $gbp_compiler->compile_gbp(true);

?><!doctype html>
<html>
        <head>
        	<meta charset="utf-8">
                <title>GBP Compile</title>
		
		<style>
		
			/* d3 SVG styles */
			
			#d3-tree-graph {
				background-color:#fff;
			}
			
			
			.link {
				fill: none;
 				stroke: #666;
				stroke-width: 1.5px;
			}

			#property {
				fill: green;
			}

			.link.property {
				stroke: green;
			}

			.link.function {
				stroke-dasharray: 0,2 1;
			}

			circle {
				fill: #ccc;
				stroke: #333;
				stroke-width: 1.5px;
			}

			text {
				font: 10px sans-serif;
				pointer-events: none;
				text-shadow: 0 1px 0 #fff, 1px 0 0 #fff, 0 -1px 0 #fff, -1px 0 0 #fff;
			}
			
			/* page styles */
			
			body, #wrapper {
				margin:0;
				padding:0;
				background-color:#309113;
			}
			
			header {
				margin-top:0;
				color:#fff;
				background-color:#167012;
			}
			
			header h1 {
				margin-top:0;
			}
			
			section h2 {
				color:white;
			}
			
			/* accordion styles */
			
			.accordion-item h3 { 
				margin: 0; font-size: 1.1em; 
				padding: 0.4em; 
				color:#A7361B;
				background-color:#FEBA59;
				border-bottom: 1px solid #66d; 
			}
			
			.accordion-item h3:hover { 
				cursor: pointer; 
				background-color:#F2BC1A;
			}
			
			.accordion-item pre {
				 margin: 0; 
				 padding: 1em 0.4em; 
				 background-color: #eef; 
				 border-bottom: 1px solid #66d; 
			}
			
			.accordion-item.hide h3 {
				 background-color: :#FEBA59; 
			}
			
			.accordion-item.hide pre {
				 display: none; 
			}
			
			.accordion-item.show pre {
				display: block;
			}
			
		</style>
                
		<!--d3 visualization-->
		<script src="http://d3js.org/d3.v2.js?2.9.1"></script>
                
	</head>
	<body>
		<div id="wrapper">
			<header>
				<h1>GBP</h1>
			</header>
			<section>
				<h2>GBP Object and Database Compiler</h2>
				<?php 
					$stats_arr = $gbp_compiler->compile_gbp(true);
					$gbp_compiler->print_compile_results(); 
				?>
			</section>
			<footer>
				<p>Green Boilerplate</p>
			</footer>
		</div>
		
		<!--accordion script-->
		
		<script>
			var hideTagName = "pre";
			var accordionCompile = document.getElementById("accordion-compile");
			
			var accordionItems  = accordionCompile.getElementsByTagName(hideTagName);
				
			accordionCompile.onclick = function (e) {
				
			//hide all items
			
				for (var i = 0; i < accordionItems.length; i++) {
					accordionItems[i].className = 'accordion-item hide';
				}
				
				var target = e ? e.target : window.event.srcElement;
				
				var itemClass = this.parentNode.className;
				
				if(target.parentNode.className.indexOf('hide') != -1) {
					target.parentNode.className = 'accordion-item show';	
				}
				else {
					target.parentNode.className = 'accordion-item hide';		
				}
				
			}
		</script>
	</body>
</html>
