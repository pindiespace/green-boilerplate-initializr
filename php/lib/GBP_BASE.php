<?php

/** 
 * GBP base class for MySQL database
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 2013
 * @version 1.0
 */

require_once('GBP_UTIL.php');
 
class GBP_BASE {
	
	/**
	 * basic text equivalents of values used in JavaScript
	 */
	protected static $UNDEFINED             = 'undefined';
	protected static $TRUE                  = 'true';
	protected static $FALSE                 = 'false';
	protected static $NULL                  = 'null';
	
	/** 
	 * database features, errors and non-values
	 */
	protected static $NO_RECORD             =  0;         //all IDs in our database are > 0
	protected static $NEW_RECORD            =  0;         //new record ID in lists
	protected static $DUPLICATE_RECORD      = -1;         //record already exists in database
	protected static $NONE                  = "none";     //value of select menu that triggers delete
	
	/** 
	 * constants shared by initializr and compiler
	 */
	
	/**
	 * we count a browser old after 5 years, written in seconds (unix timestamp)
	 * Allows us to dynamically re-define "ancient" browsers
	 */
	protected static $FIVE_YEARS   = 1.578e+8;

	
	protected static $SECONDS_BETWEEN_QUERY = 5;     //time between re-submission of query (reduce DOS problems)
	
	/** 
	 * login info
	 */
	protected static $hostname  = 'localhost';
	protected static $username  = '############';
	protected static $password  = '############';
	protected static $db_name   = 'db51371_greenboilerplate';

	/** 
	 * names of all the tables in our MySQL db
	 */
	protected static $table_names = array(
		'clients'                  => 'clients',
		'clients_properties'       => 'clients_properties',
		'clients_versions'         => 'clients_versions',
		'components'               => 'components',
		'datatypes'                => 'datatypes',
		'dependency'               => 'dependency',
		'dependency_state'         => 'dependency_state',
		'devices'                  => 'devices',
		'devices_properties'       => 'devices_properties',
		'devices_versions'         => 'devices_versions',
		'discovery'                => 'discovery',
		'discovery_state'          => 'discovery_state',
		'import_fulltests'         => 'import_fulltests',
		'import_fulltests_results' => 'import_fulltests_results',
		'models'                   => 'models',
		'principles'               => 'principles',
		'properties'               => 'properties',
		'references'               => 'references',
		'search_group'             => 'search_group',
		'sources'                  => 'sources',
		'swap'                     => 'swap',
		'system'                   => 'system',
		'translations'             => 'translations'
		);

	
	/**
	 * names of GBP systems defining groups of components, mapped to their
	 * current record_id in the database. Systems are the major boundaries
	 * that might be made between components in an LCA analysis of Internet
	 * transactions.
	 */
	protected static $system_ids = array(
		'browser'         => '0',
		'device'          => '0',
		'user'            => '0',
		'cloud'           => '0',
		'designer'        => '0',
		'stakeholder'     => '0',
		'gbp'             => '0'
	);

	
	/**
	 * names of dependency states, mapped to their current record_id
	 * in the database
	 */
	protected static $dependency_state_ids = array(
	);

	
	/**
	 * names of common sources, mapped to their current record_id
	 * in the database
	 */
	protected static $source_ids = array(
	);

	
	protected static $component_ids = array(
	);
	
	
	protected static $datatype_ids = array(
	);
	
	protected static $discovery_ids = array(
	);

	
	/**
	 *  save the insert id for INSERT methods that return Booleans here
	 */
	protected static $last_insert_id = 0;
	
	
	/** 
	 * errors are stored as a sequential array with class and method name 
	 * along with the error or status message
	 */
	protected static $ERROR  = array();  //error messages
	
	/** 
	 * warnings, when not fatal
	 */
	protected static $WARNING = array(); //warnings
	
	
	/**
	 * we add the GBP_UTIL utility class as part of this class
	 */
	protected static $util;

	/**
	 * levels of debugging
	 */
	protected static $check_columns = false; //true to check columns before SELECT
	
	
	/**
	 * @construct
	 * place an instance of our utilities class inside base
	 */
	public function __construct() 
	{
		//pass in a GBP_UTIL object
                
		if(class_exists('GBP_UTIL'))
		{
			self::$util = new GBP_UTIL;
		}

		//initialize our default database ids
		self::init_db_ids(self::$table_names['system'], self::$system_ids);
		self::init_db_ids(self::$table_names['dependency_state'], self::$dependency_state_ids);
		self::init_db_ids(self::$table_names['sources'], self::$source_ids);
		self::init_db_ids(self::$table_names['components'], self::$component_ids);
		self::init_db_ids(self::$table_names['datatypes'], self::$datatype_ids);
		self::init_db_ids(self::$table_names['discovery'], self::$discovery_ids);

	}
	
	
	/**
	 * @method init_system_ids
	 * utility for creating PHP arrays with record ids mapped to the 'name' field in a db
	 * @param {String} $table_name name of table in db
	 * @param {&Array} reference to array to be filled with this information
	 */
	protected static function init_db_ids($table_name, &$arr)
	{	
		$loc = self::get_all($table_name);
		
		foreach($loc as $rec)
		{
			if(isset($rec['id']))
			{
				$arr[$rec['name']] = $rec['id'];
			}
		}
	}
	
	
	/**
	 * @method get_system_id
	 * given a name for a system, return its record_id
	 * @param {String} $system_name name of system
	 * @return {Number} if true, return record_id, else $NO_RECORD
	 */
	public static function get_system_id_by_name($system_name)
	{
		if(isset(self::$system_ids[$system_name]))
		{
			return self::$system_ids[$system_name];
		}
		else
		{
			return self::$NO_RECORD;
		}
	}
        
	
	public static function get_component_id_by_name($component_name)
	{
		if(isset(self::$component_ids[$component_name]))
		{
			return self::$component_ids[$component_name];
		}
		else
		{
			return self::$NO_RECORD;
		}		
	}
	
	
	/**
	 * @method get_dependency_state_id
	 * given a name for a dependency, return its record_id
	 * @param {String|Boolean} $dependency_state_name name of dependency_state, or true/false
	 * @return {Number|Number} if true, return record_id, else 'DEPENDENCY_FALSE'

	 */
	public static function get_dependency_state_id_by_name($dependency_state_name)
	{
		if(isset(self::$dependency_state_ids[$dependency_state_name]))
		{
			return self::$dependency_state_ids[$dependency_state_name];
		}
		else if($dependency_state_name === "true" || $dependency_state_name === true)
		{
			self::$ERROR[__METHOD__][] = "selecting true as".self::$dependency_state_ids['DEPENDENCY_TRUE'];
			return self::$dependency_state_ids['DEPENDENCY_TRUE'];
		}
		else if($dependency_state_name === "false" || $dependency_state_name === false)
		{
			self::$ERROR[__METHOD__][] = "selecting true as".self::$dependency_state_ids['DEPENDENCY_TRUE'];
			return self::$dependency_state_ids['DEPENDENCY_FALSE'];
		}
		else
		{
			self::$ERROR[__METHOD__][] = "selecting true as".self::$dependency_state_ids['DEPENDENCY_TRUE'];
			return self::$dependency_state_ids['DEPENDENCY_TRUE'];
		}
	}
	
	
	/**
	 * @method get_source_id
	 * get the record_id matching 'name' field (done without accessing the database)
	 * @param {String} string matching 'name' field in 'source' table record
	 * @return {Number|false} current record_id for that name
	 */
	public static function get_source_id_by_name($source_name)
	{
		if(isset(self::$source_ids[$source_name]))
		{
			return self::$source_ids[$source_name];	
		}
		return false;
	}
	
	
	/**
	 * @method get_datatype_id_by_name
	 * get the record_id matching 'name' field (done without accessing the database)
	 * @param {String} string matching 'name' field in 'datatype' table record
	 * @return {Number|false} current record_id for that name
	 */
	public static function get_datatype_id_by_name($datatype_name)
	{
		if(isset(self::$datatype_ids[$datatype_name]))
		{
			return self::$datatype_ids[$datatype_name];	
		}
		return false;
	}
	
	
	/**
	 * @method get_discovery_id_by_name
	 * get the record_id matching 'name' field (done without accessing the database)
	 * @param {String} string matching name field in 'discovery' table record
	 * @return {Number|false} current record_id for that name
	 */
	public static function get_discovery_id_by_name($discovery_name)
	{
		if(isset(self::$discovery_ids[$discovery_name]))
		{
			return self::$discovery_ids[$discovery_name];	
		}
		return false;
	}
	
	
	/**
	 * @method get_none()
	 * get "none" (used to trigger delete in select fields)
	 * @return "none"
	 */
	public static function get_none()
	{
		return self::$NONE;
	}
	
	
	/**
	 * @method get_no_record_id
	 * get value used for $NO_RECORD (currently == 0)
	 * @return 0
	 */
	public static function get_no_record_id()
	{
		return self::$NO_RECORD;
	}
	
	
	/**
	 * @method get_new_record_id
	 * get value used for $NEW_RECORD (currently == 0)
	 * @return 0
	 */
	public static function get_new_record_id()
	{
		return self::$NEW_RECORD;
	}
	
	
	/** 
	 * @method init_err
	 * reset error array
	 */
	protected static function init_error()
	{
		self::$ERROR = array();
	}
	
	
	/**
	 * @method init
	 * initialize the error array
	 */
	public static function init()
	{
		self::init_error();
		self::$last_insert_id = 0;
	}
        
	
	/**
	 * database-specific utilities
	 * @method count_real_rows
	 * count number of array elements, return 0 if the array is empty, or
	 * the variable is not an array
	 * @param {Array} the array to count
	 * @return the number of array elements, or 0 is the array is empty or the variable
	 * returned is not an array (count("false") would return a 1)
	 */
	public static function count_real_rows($arr)
	{
		if(is_arr($arr))
		{
			$ct = count($arr);
			
			if($ct > 0)
			{
				return $ct;
			}
		}
		
		return 0;
	}
       
	/** 
	 * GETTERS AND SETTERS
	 */
	
	
	/** 
	 * @method get_error
	 * get the error array
	 * @return {Array} return 2d error array. First dimension key is the function
	 * name where the error happened. In some functions, a traceback may be grafted
	 * to the error, or an Exception message.
	 */
	public static function get_error()
	{
		$ct = 0;
		foreach(self::$ERROR as $val)
		{
			$ct++;
		}
		if(!$ct)
		{
			return false;
		}
		else
		{
			return self::$ERROR;
		}
	}
	
	
	/** 
	 * set the error array. add api:false for
	 * our downstream JSON calls
	 * TODO: NOT USED YET
	 */
	public static function set_error($method, $err_str)
	{
		self::$ERROR[$method][] = $err_str;
		
		//find the function that called the function that the error happened in
		
		$trace = debug_backtrace();
		self::$ERROR[__METHOD__]['CALLING_FUNCTION'] = $trace[2]; //trace[0] is this function, trace[1] is the function with the error
	}
	
	
	/**
	 * @method get_table_names
	 * get the stored list of table names
	 * @return {Array} array mapping program table names to those used in the db
	 */
	public static function get_table_names()
	{
		return self::$table_names;
	}


	/** 
	 * get the PDO object
	 * @return {PDO} PDO data object
	 */
	public static function get_pdo()
	{
		self::$last_insert_id = 0;

		try {
			$dbh = new PDO('mysql:host='.self::$hostname.';dbname='.self::$db_name.';charset=UTF8', self::$username, self::$password);
			$dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		} catch (PDOException $e) {
			print "Error!: " . $e->getMessage() . "<br/>";
    		die();
		}

		return $dbh;	
	}
	
	
	/**
	 * commit the PDO object
	 * TODO: NOT USED YET, WILL IT SPEED THINGS UP?
	 */
	public static function commit_pdo($dbh)
	{
		$dbh->commit();
	}
	
	/** 
	 * close the PDO object by NULLing it
	 * normally closed when a PHP script ends
	 */
	public static function close_pdo(&$dbh)
	{
		$dbh = NULL;
	}


	/** 
	 * =================================================
	 * SELECT-STYLE FUNCTIONS
	 * =================================================
	 */


        /**
         * @method compare dates
         * run a search on a table to see if a MySQL date in a 'date' column
         * has a very close date to the current date. This prevents multiple submissions
         */
        protected function compare_dates($table_name, $date_column_name)
        {
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
                $db = self::get_pdo();
                
		//get current datetime
		
		$curr_date = date("Y-m-d H:i:s", (time() - self::$SECONDS_BETWEEN_QUERY));
		
		$select_list = 'SELECT * FROM `'.$table_name.'` WHERE '.$date_column_name.' > ('."'".$curr_date."'".')'; //worked correctly
		try {
			$statement = $db->prepare($select_list);
			$statement->execute();		
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetchAll();
			
			if(is_array($row) && count($row) > 0)
			{
				self::close_pdo($db);
				return true; //at least one record was created more recently than one second ago
			}
		} 
		catch(Exception $e) { 
			self::$ERROR[__METHOD__][] = $e->getMessage()." in compare_dates for table $table_name"; //return exception 
			$trace = debug_backtrace();
			self::$ERROR[__METHOD__][] = $trace[1];
		}
		self::close_pdo($db);
                return false;
        }
 
 
	/**
	 * @method get_column_names
	 * @param {String} $table_name name of table in database
	 * @returns {Array|Boolean} if ok, return an array with the column names, else false
	 */
	protected function get_column_names($table_name)
	{		
		$db = self::get_pdo();
		
		$sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = :table_name";
		
		try {
			$statement = $db->prepare($sql);
			$statement->bindValue(':table_name', $table_name, PDO::PARAM_STR);
			$statement->execute();
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetchAll();
			if(is_array($row) && count($row) > 0)
			{
				self::close_pdo($db);
				return $row;
			}
		}
		catch(Exception $e) {
			self::$ERROR[__METHOD__][] = $e->getMessage()."Could not get column names for table $table_name";
		}
		self::close_pdo($db);
		return false;
	}
	
	
	/**
	 * @method get column_widths
	 * get the width of a text field, column names supplied. If a non-text column_name
	 * is passed, returns a value of -1
	 * @param {String} $table_name name of table to check
	 * @param {Array} $column_arr column names to get the width for
	 * @returns {Array|false} if ok, return the column widths in an associative array, else false
	 */
	public static function get_column_widths($table_name, $column_arr)
	{		
		$db = self::get_pdo();
		
		$sql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = :table_name";
		
		try {
			$statement = $db->prepare($sql);
			$statement->bindValue(':table_name', $table_name, PDO::PARAM_STR);
			$statement->execute();
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetchAll();
			if(is_array($row) && count($row) > 0)
			{
				$cols = array();
				
				foreach($row as $col_data)
				{
					if(in_array($col_data['COLUMN_NAME'], $column_arr))
					{
						if($col_data['CHARACTER_MAXIMUM_LENGTH'] > 0)
						{
							$cols[$col_data['COLUMN_NAME']] = $col_data['CHARACTER_MAXIMUM_LENGTH'];
						}
						else
						{
							$cols[$col_data['COLUMN_NAME']] = "-1"; //not a text field
						}
					}
				}
				
				self::close_pdo($db);
				return $cols; //at least 1 column was text	
			}
		}
		catch(Exception $e) {
			self::$ERROR[__METHOD__][] = $e->getMessage()."Could not get column names for table $table_name";
		}
		
		self::close_pdo($db);
		return false;
	}
	
	
	/**
	 * @method check_column_list($column_arr)
	 * confirm that all the column names actually exist in the table
	 * @param {String} $table_name the name of the table to check
	 * @param {Array} the column we want to check
	 * @return {Boolean} if column names all exist in the table, return true, else false
	 */
	protected static function check_column_list($table_name, $column_arr)
	{
		if(is_array($column_arr))
		{
			$column_count = count($column_arr);
		
			for($i = 0; $i < $column_count; $i++)
			{
				if(!self::is_valid_column($table_name, $column_arr[$i]))
				{
					self::$ERROR[__METHOD__][] = "field ".$column_arr[$i]." not found in $table_name";
					return false;
				}
			}
		}
		
		return true;
	}
	
	
	/**
	 * @method make_column_list()
	 * make a string of columns suitable for a SELECT col1, col2
	 * if we have a column list defined, restrict the number of columns returned by the query.
	 * this makes things more efficient when we only need a few columns in the JSON output (don't
	 * have to unset())
	 * @param {Array} $column_arr list of columns we want to select
	 * @return {String|'*'} if there is a list, return it as a string, else return a wildcard string '*'
	 */
	protected static function make_column_list($column_arr)
	{
		if(is_array($column_arr))
		{
			$column_list = "";
			$num_columns = count($column_arr);
				
			for($i = 0; $i < $num_columns; $i++)
			{
				$column_list .= $column_arr[$i];
					
				if($i < $num_columns - 1)
				{
					$column_list .= ",";
				}
			}
		}
		else
		{
			$column_list = "*"; //SELECT * from table...
		}
		
		return $column_list;
	}

	
	/**
	 * @method make_order_by_list
	 * make a string of column names for ordering suitable for SELECT ... ORDER BY col1, col2
	 */
	protected static function make_order_by_list($order_by_arr)
	{
		$select_list = "";
		
		if(is_array($order_by_arr))
                {
                        $order_by_count = count($order_by_arr);
                        $select_list .= ' ORDER BY';
                        for($j = 0; $j < $order_by_count; $j++)
                        {
                                $select_list .= ' '.$order_by_arr[$j];
                                if($j < $order_by_count - 1)
                                {
                                        $select_list .= ', ';
                                }
                        }
                }
                
		return $select_list;
	}

	/**
	 * @method is_valid_id
	 * see if a supplied id is actually a primary key Id in the given table of our database
	 * @param {Number}  $id the numeric id of a record in the table
	 * @param {String} $table a table name (optional)
	 * @return {Number|false} if is matches primary key for record in table, return record id, else false
	 */
	protected static function is_valid_id($record_id, $table_name)
	{			
		$record = self::get_record_by_id($table_name, $record_id);
		if(is_array($record)) 
		{
			return $record['id']; //should be impossible not to have 'id'
		}
		else
		{
			self::$ERROR[__METHOD__][] = "invalid record_id $record_id in table $table_name";
			return false;
		}
	}
	
	
	/** 
	 * @method is_valid_table
	 * check schema table to make sure a table exists
	 * @param {String} $table_name name of table in our db
	 * @return {Boolean} true if valid table name, else false
	 */
	protected static function is_valid_table($table_name)
	{
		$db = self::get_pdo();
		
		$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = :tval";
		
		try {
			$statement = $db->prepare($sql); 
			$statement->execute(array(':tval' => $table_name));
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetchAll();
			
			if(is_array($row) && count($row) > 0)
			{
				self::close_pdo($db);
				return true;
			}
			
		} catch (Exception $e) { 
			self::$ERROR[__METHOD__][] = $e->getMessage(); //return exception 
        	}
		
		self::close_pdo($db);
		return false;
	}
	
	
	/** 
	 * @method is_valid_column
	 * check schema table to make sure a column exists before updating
	 * @param {String} $table_name name of table
	 * @param {String} $column_name name of column in table
	 * @return {Boolean} true if column exists, else false
	 */
	protected static function is_valid_column($table_name, $column_name)
	{
		$db = self::get_pdo();
		
		$select_list = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME =:tval AND COLUMN_NAME =:fval";
		
		try {
			$statement = $db->prepare($select_list);
			$statement->execute(array(':tval' => $table_name, ':fval' => $column_name));		
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetchAll();														
			if(is_array($row) && count($row) > 0)
			{
				self::close_pdo($db);
				return true;
			}
			else
			{
				self::$ERROR[__METHOD__][] = "invalid column $column_name in $table_name";
			}
		} 
		catch(Exception $e) { 
			self::$ERROR[__METHOD__][] = $e->getMessage()." in is_valid_column for table $table_name, column $column_name"; //return exception 
			$trace = debug_backtrace();
			self::$ERROR[__METHOD__][] = $trace[1];
		}
		
		self::close_pdo($db);
		return false;
	}


	/** 
	 * @method_check_value_in_record
	 * check a value in a record. We use this in clients_properties because we list both clients_id and clients_version_id, 
	 * in clients_versions, and clients_properties.client_version_id depends on clients.clients_id
	 * @param {String} $table_name name of the table
	 * @param {Number} $record_id record primary key in table
	 * @param {String} $column_name name of the field
	 * @param {Mixed} $column_value value in $column_name column
	 * @return {Boolean} if column exists, and value matches supplied value, return true, otherwise false
	 */
	protected static function check_value_in_record($table_name, $record_id, $column_name, $column_value)
	{
		$db = self::get_pdo();
		
		$record = self::get_record_by_id($table_name, $record_id);
		if(isset($record) && isset($record[$column_name]) && $record[$column_name] === $column_value)
		{
			self::close_pdo($db);
			return true;
		}
		else
		{
			self::$ERROR[__METHOD__][] = "value $column_value for $column_name not found in record at $record_id in $table_name";
		}
		
		self::close_pdo($db);
		return false;
	}
	
	
		
	/**
	 * @method get_enum_list
	 * get the enumerated values of a column via the schema
	 * MySQL specific!
	 * http://jadendreamer.wordpress.com/2011/03/16/php-tutorial-put-mysql-enum-values-into-drop-down-select-box/
	 * @param {String} $table_name name of table with enumerated column
	 * @param {String} $column_name name of colum with enumerated list
	 * @returns {Array|false} if found, return array of enums, else false
	 */
	public static function get_enums($table_name, $column_name)
	{
		$db = self::get_pdo();
		
		$select_list = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME =:tval AND COLUMN_NAME =:fval";
		
		try {
			$statement = $db->prepare($select_list);
			$statement->execute(array(':tval' => $table_name, ':fval' => $column_name));		
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetchAll();														
			if(is_array($row) && count($row) > 0)
			{
				$row = $row[0];
				$enum_list = explode(",", str_replace("'", "", substr($row['COLUMN_TYPE'], 5, (strlen($row['COLUMN_TYPE'])-6))));
				self::close_pdo($db);
				return $enum_list; //array of records
			}
			else
			{
				self::$ERROR[__METHOD__][] = "enums not found in $table_name for column_name=$column_name)";
			}
		} 
		catch(Exception $e) { 
			self::$ERROR[__METHOD__][] = $e->getMessage()." in get_enums() for table $table_name"; //return exception 
			$trace = debug_backtrace();
			self::$ERROR[__METHOD__][] = $trace[1];
		}
		
		self::close_pdo($db);
		return false;
	}
	
	
	/** 
	 * @method get_all()
	 * get all the records from a table. useful for tables which are really 
	 * enumerated lists of states
	 * @param {String} $table_name name of table in database
	 * @param {Array} $column_arr if present, select only the list of column names in this array
	 * @param {Array} $order_by_arr if present, order the results by the columm names listed
	 * @return {Array|false} if ok, return Array(records as associative arrays), else false
	 */
	protected function get_all($table_name, $column_arr=false, $order_by_arr=false)
	{
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
		$db = self::get_pdo();

		$column_list = self::make_column_list($column_arr);
		
		$select_list = "SELECT $column_list FROM `".$table_name."`";
		
		
		//if desired, sort the results by one or more columns
		
		$select_list .= self::make_order_by_list($order_by_arr);
		
		try {
			$statement = $db->prepare($select_list);	
			$statement->execute();	
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetchAll();
			
			//multiple rows come back, an array of rows
			
			if(is_array($row) && count($row) > 0)
			{
				self::close_pdo($db);
				return $row;
			}
		}
		catch (Exception $e) {
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table $table_name, query:SELECT $column_list FROM $table_name";
		}

		self::close_pdo($db);
		return false;
	}	
	
	
	/** 
	 * @method get_record_by_id
	 * given a primary key, return a record
	 * @param {String} $table_name name of table in db
	 * @param {Number} $record_id primary key we are searching for in table
	 * @param {Array} $column_arr if present, restrict results to column names listed
	 * @return {Array|false} if record found, return an array with the record, otherwise, return false
	 */
	protected static function get_record_by_id($table_name, $record_id, $column_arr=false)
	{
		//if we get a 'zero' id record, don't process, just return
		
		if($record_id == self::$NO_RECORD)
		{
			return false;
		}
		
		$records =  self::get_records_by_column_value($table_name, 'id', $record_id, $column_arr);
		
		//record is an array of arrays
			
		if(is_array($records))
		{
			$record = $records[0];
			if(is_array($record))
			{
				return $record;
			}
		}
		else
		{
			self::set_error(__METHOD__, "could not find record using $record_id in $table_name");
		}
		return false;
	}
	

	/** 
	 * @method get_record_id_by_name
	 * look in a table under the 'name' field for a match, return the id of the record
	 * @param {String} $name name value of 'name' field in the table
	 * @param {String} $table_name name of table in db
	 * @param {Array} $column_arr if present, restrict results to column names listed
	 * @return {Number|false} if record found, return the id (primary_key) of record, else false
	 */
	protected static function get_record_id_by_name($name_value, $table_name)
	{
		$records = self::get_records_by_column_value($table_name, 'name', $name_value, false);
		
		//records is an array of arrays
		
		if(is_array($records))
		{
			if(count($records) != 1)
			{
				self::$ERROR[__METHOD__][] = "warning: multiple records returned when one record expected (".count($records).")";
			}
			$record = $records[0];
			return $record['id'];
		}
		else
		{
			self::$ERROR[__METHOD__][] = "could not find record using name $name_value in $table_name";
		}
		
		return false;
	}
	
	
	/**
	 *  @method get_record_id_by_title
	 *  look in a table under the 'title' field for a match, return id of the record
	 *  @param {String} $title_value search text for title
	 *  @param {String} $table_name name of table
	 *  @param {Array} $column_arr if present, restrict results to column names listed
	 *  @return {Number|false} if record found, return id (primary key) of record, else false
	 */
	protected static function get_record_id_by_title($title_value, $table_name, $column_arr=false)
	{
		$records = self::get_records_by_column_value($table_name, 'title', $title_value, $column_arr);

		if(is_array($records))
		{
			if(count($records) != 1)
			{
				self::$ERROR[__METHOD__][] = "warning: multiple records returned when one record expected";
			}
			$record = $records[0];
			return $record['id'];
		}
		else 
		{
			self::$ERROR[__METHOD__][] = "could not find record using title $title_value in $table_name";
		}
		
		return false;
	}
	
	/**
	 * @method get_unique_property_by_name
	 * get a property from 'properties' for a particular source and component
	 * under source and/or component, properties might have similar names, e.g., 'version' for
	 * component::browser and component::device, with a similar case for 'sources'
	 * @param {String} $name value of 'name' field in 'properties' table
	 * @param {Number} $component_id component constraint on property name
	 * @param {Number} $source_id source constraint on property name
	 * @return {Array} if ok, return the property array (1d), else false
	 */
	protected static function get_unique_property_by_name($name, $component_id, $source_id)
	{
		$property = self::get_records_by_value_array(self::$table_names['properties'],
				array('name', 'component_id', 'source_id'),
				array($name, $component_id, $source_id)
				);
		
		//we should only get back one record
				
		if(is_array($property))
		{
			if(count($property) == 1)
			{
				return $property[0];
			}
			else if(count($property) == 0)
			{
				self::$ERROR[__METHOD__][] = "ZERO property records found with same name:$name, source:$source_id, and component:$component_id!";
			}
			else
			{
				self::$ERROR[__METHOD__][] = "MULTIPLE property records found with same name:$name, source:$source_id, and component:$component_id!";
			}
		}
		else
		{
			self::$ERROR[__METHOD__][] = "NO UNIQUE PROPERTY RECORDS FOUND, name:$name, component_id:$component_id, source_id:$source_id";
		}
		
		return false;
	}
	

	/** 
	 * @method get_record_by_column_value
	 * find a record(s) by a value in a  single column, restricting the number of columns returned
	 * return the record(s)
	 * @param {String} $table_name name of table
	 * @param {String} $column_name name of column with value
	 * @param {String} $column_value  value of field matching record(s)
	 * @param {Array|false} $column_arr if an array, only SELECT the column names in the array, otherwise SELECT *
	 * @param {Array|false} $order_by_arr if an array, sort results according to columns listed in array
	 * @return {Array|False} if ok, return the Array(of records as associative arrays), otherwise false
	 */
	protected static function get_records_by_column_value($table_name, $column_name, $column_value, $column_arr=false, $order_by_arr=false)
	{
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
		if(self::is_valid_column($table_name, $column_name)) //table has this column
		{						
			$db = self::get_pdo();
			
			/**
			 * RESTRICT COLUMNS RETURNED
			 * if we have a column list defined, restrict the number of columns returned by the query.
			 * this makes things more efficient when we only need a few columns in the JSON output (don't
			 * have to unset())
			 */
			$column_list = self::make_column_list($column_arr);
			
			$select_list = "SELECT $column_list FROM `".$table_name."` WHERE $column_name=:fval";
			
			if(is_array($order_by_arr))
			{
				$select_list .= self::make_order_by_list($order_by_arr);
			}
			
			$statement = $db->prepare($select_list);
			
			try {
					$statement->execute(array(':fval' => $column_value));		
					$statement->setFetchMode(PDO::FETCH_ASSOC);
					$row = $statement->fetchAll();														
					if(is_array($row) && count($row) > 0)
					{
						self::close_pdo($db);
						return $row; //array of records, numbered by system (not the record_id)
					}
					else
					{
						self::$ERROR[__METHOD__][] = "record not found in $table_name for column_name=$column_name and column_value=$column_value)";
					}
			} 
			catch(Exception $e) { 
				self::$ERROR[__METHOD__][] = $e->getMessage()." for table $table_name"; //return exception 
				$trace = debug_backtrace();
				self::$ERROR[__METHOD__][] = $trace[1];
			}   
		}
		else
		{
			self::$ERROR[__METHOD__][] = "invalid column name '$column_name' for table ($table_name)";
		}
		
		self::close_pdo($db);
		return false;
	}
	

	/**
	 * @method get_records_by_value_array
	 * select using values for multiple columns in a table, with ALL COLUMNS RETURNED
	 * @param {String} $table_name name of table
	 * @param {Array} $column_arr array of columns we are searching against
	 * @param {Array} $value_arr array of values for the columns that must match
	 * @param {Boolean} $check_columns if true, check if columns exist, otherwise, don't check
	 * @param {Array} $order_by_arr, use column values to sort results
	 * @return {Array|false} if ok, return Array(of records as associative arrays), otherwise false
	 */
	protected static function get_records_by_value_array($table_name, $column_arr, $value_arr, $check_columns=false, $order_by_arr=false)
	{
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
		//arrays must match
		
		if(!is_array($column_arr) || !is_array($value_arr) || (count($column_arr) != count($value_arr)))
		{
			self::$ERROR[__METHOD__][] = "invalid field and or value arrays";
			return false;
		}
		
		$column_count = count($column_arr);
		
		//check if columns exist
		
		if(self::$check_columns)
		{
                        self::check_column_list($column_arr);
		}
		
		//we are looking in the right table
		
		$db = self::get_pdo();
		
		/**
		 * SPECIFY WHICH COLUMNS TO USE IN SEARCH
		 * construct our select list, we get ALL THE COLUMNS. $column_array specifies
		 * the search, NOT the results
		*/
		$select_list = 'SELECT * FROM `'.$table_name.'` WHERE ';
		$execute_arr = array();
		$j = 0;
		
		for($i = 0; $i < $column_count; $i++)
		{
			$select_list .= $column_arr[$i].'=:'.$column_arr[$i];
			if($i < $column_count - 1)
			{
				$select_list .= ' AND ';
			}
			
			$execute_arr[':'.$column_arr[$i]] = $value_arr[$i];
		}
                
                //if the ORDER BY array is present, add this to the select list and execute array
		
		$select_list .= self::make_order_by_list($order_by_arr);
		
		$statement = $db->prepare($select_list);
		try {
				$statement->execute($execute_arr);		
				$statement->setFetchMode(PDO::FETCH_ASSOC);
				$row = $statement->fetchAll();														
				if(is_array($row) && count($row) > 0)
				{
					self::close_pdo($db);
					return $row; //array of records
				}
				else
				{
					self::$ERROR[__METHOD__][] = "record not found in $table_name with the following lists";
					self::$ERROR[__METHOD__][] = $column_arr;
					self::$ERROR[__METHOD__][] = $value_arr;
				}
		} 
		catch(Exception $e) { 
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table $table_name, SQL string was $select_list, $execute_arr was ".implode(',', $execute_arr); //return exception 
		}
		
		self::close_pdo($db);
		return false;	
		
	} //end of function
	
	
	/** 
	 * @method update_row_column_value
	 * given an id for a row in a table, and a specific field, update that field only
	 * if the record doesn't exist, return an error
	 * @param {String} $table_name name of table with row to update
	 * @param {Number} $record_id the id of the record to update
	 * @param {String} $column_name the name of the column to update
	 * @param {String} $column_value the value to update the row cell to
	 * @return {Array|false} if update ok, return Array(record_id, column_name, column_value), else false
	 */
	protected static function update_row_column_value($table_name, $record_id, $column_name, $column_value) 
	{
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
		$db = self::get_pdo();
		
		if(self::is_valid_id($record_id, $table_name))
		{
			try {
				$statement = $db->prepare("UPDATE `$table_name` SET $column_name = :flv WHERE $table_name.id = :rid");
				
				$result = $statement->execute(array(':flv'=>$column_value, ':rid'=>$record_id));
				if($result)
				{	
					self::close_pdo($db);
					return array("id" => $record_id, "column_name" => $column_name, "column_value" => $column_value);
				}
				else
				{
					self::$ERROR[__METHOD__][] = "unable to update $table_name in $column_name with value $column_value";
					self::close_pdo($db);
					return false;
				}
			} 
			catch(Exception $e) { 
				self::$ERROR[__METHOD__][] = $e->getMessage()." for table $table_name"; //return exception 
			}
		}
		else
		{
			self::$ERROR[__METHOD__][] = "invalid_id for $table_name, with id=$record_id and $column_name=$column_value";
		}
		
		self::close_pdo($db);
		return false;
	}
	
	
	/**
	 * @method update_row_by_value_array
	 * update a record with the supplied arrays. We construct the query list from a list of
	 * field names and field values.
	 * @param {String} $table_name name of table
	 * @param {Number} $record_id record we want to update
	 * @param {Array} $column_arr columns in record to update
	 * @param {Array} $value_arr values to use in update
	 * @returns {Boolean} if ok, true, else false
	 */
	protected static function update_row_by_value_array($table_name, $record_id, $column_arr, $value_arr)
	{
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
		//confirm we set things up correctly 
		
		if(is_array($column_arr) && is_array($value_arr) && (count($column_arr) != count($value_arr)))
		{
			self::$ERROR[__METHOD__][] = "invalid arrays supplied for $table_name";
			return false;
		}
		
		//get the db object
		
		$db = self::get_pdo();
		
		//construct the query string
		
		$column_count = count($column_arr);
		
		$update_list = 'UPDATE `'.$table_name.'` SET ';
		$execute_arr = array();
		$j = 0;
		
		for($i = 0; $i < $column_count; $i++)
		{
			$update_list .= $column_arr[$i].'= :'.$value_arr[$i];
			if($i < $column_count - 1)
			{
				$update_list .= ', ';
			}
			
			$execute_arr[':'.$column_arr[$i]] = $value_arr[$i];
		}
		
		$execute_arr[':rid'] = $record_id;
		
		$update_list .= ' WHERE $table_name.id = :rid';
		
		$statement = $db->prepare($update_list);
			
		try {
			$result = $statement->execute($execute_arr);
			if($result)
			{
				self::close_pdo($db);
				return array("id" => $record_id, "values" => implode(',', $value_arr));
			}
			else
			{
				self::$ERROR[__METHOD__][] = "unable to update $table_name in $column_name with value $column_value";
				self::close_pdo($db);
				return false;
			}
		} 
		catch(Exception $e) { 
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table $table_name, SQL query was ".$update_list." value_array was ".implode(',', $value_arr);
		}
		
		self::close_pdo($db);
		return false;
	}
	
	
	/** 
	 * @method insert_record_by_value_array
	 * insert using PDO with supplied arrays. We construct the query from a list of 
	 * field names and field values
	 * @param {String} $table_name name of the table
	 * @param {Array] $column_arr a list of column names as strings
	 * @returns {Boolean} if insert ok, return true, else false
	 * we also save the returned record_id for inserted record under $last_insert_id
	 */
	protected static function insert_record_by_value_array($table_name, $column_arr, $value_arr)
	{
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
		$db = self::get_pdo();
		
		//confirm we set things up correctly 
		
		if(is_array($column_arr) && is_array($value_arr) && (count($column_arr) != count($value_arr)))
		{
			self::$ERROR[__METHOD__][] = "invalid arrays supplied for $table_name";
			self::close_pdo($db);
			return false;
		}
		
		$column_count = count($column_arr);
		
		$insert_list = 'INSERT INTO `'.$table_name.'` (';
		$execute_arr = array();
		$j = 0;
		
		for($i = 0; $i < $column_count; $i++)
		{
			$insert_list .= $column_arr[$i];
			if($i < $column_count - 1)
			{
				$insert_list .= ', ';
			}
		}
			
		$insert_list .= ') values(';
			
		for($i = 0; $i < $column_count; $i++)
		{
			$insert_list .= ':'.$column_arr[$i];
			if($i < $column_count - 1)
			{
				$insert_list .= ', ';
			}
				
			$execute_arr[':'.$column_arr[$i]] = $value_arr[$i];
		}
			
		$insert_list .= ')';
			
		$statement = $db->prepare($insert_list);
		try {
			$result = $statement->execute($execute_arr);
			self::$last_insert_id = $db->lastInsertId(); //store separately so we can return a "true" for this insert
			self::close_pdo($db);
			return $result;
		} 
		catch(Exception $e) { 
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table $table_name"; //return exception
			self::$ERROR[__METHOD__][] = "INSERT_LIST:$insert_list";
			self::$ERROR[__METHOD__][] = $value_arr;
		}
		
		self::close_pdo($db);
		return false;
	}


	/** 
	 * @method delete_record_by_id
	 * delete a record by its id value
	 * @param {String} $table_name the table name
	 * @param {Number} $record_id the primary key of the record
	 * @return {Boolean} if delete ok, return true, else false
	 */
	protected static function delete_record_by_id($table_name, $record_id)
	{
		return self::delete_records_by_column_value($table_name, 'id', $record_id);
	}
	
	
	/** 
	 * @method delete_records_by_column_value
	 * delete record(s) with a matching column value
	 * @param {String} $table_name the name of the table
	 * @param {String} $column_name the name of the column
	 * @param {String} $column_value the column value triggering a delete
	 * @return {Boolean} if delete ok return true, else false
	 */
	protected static function delete_records_by_column_value($table_name, $column_name, $column_value)
	{
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
		$db = self::get_pdo();
		
		try {
			$statement = $db->prepare("DELETE FROM `".$table_name."` WHERE $column_name=:cval");
			$result = $statement->execute(array(':cval' => $column_value));	
			self::close_pdo($db);
			return $result;
		} 
		catch(Exception $e) { 
			self::$ERROR[__METHOD__][] = $e->getMessage()." for table $table_name, column_name=$column_name, column_value=$column_value"; //return exception 
		}
		
		self::close_pdo($db);
		return false;
	}

};
