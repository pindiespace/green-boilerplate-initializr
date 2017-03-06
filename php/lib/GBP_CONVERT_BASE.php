<?php

    class GBP_CONVERT_BASE extends GBP_BASE {
    

	/** 
	 * constructor
	 */
	 public function __construct()
	{
	 	parent::__construct();
		
		
	}
	
	 
	/** 
	 * @method scan_for_files()
	 * scan a directory, and return all files matching an extension, or all
	 * @param {String} $path directory path
	 * @param {String} $ext optional file extension ('.jpg'. '.txt', '.json')
	 * @return {Array} array of filenames
	 */
	public static function scan_for_files($path, $ext = '*', $limit=500)
	{
		
		if(is_dir($path)) //the directory containing all alt databases
		{
			$file_arr = glob($path."*.".$ext); //can restrict to extensions like .json, .xls...
			
			$file_list = array();
			$set_limit = 0;
			
			foreach($file_arr as $key => $file)
			{
				if($set_limit == $limit)
				{
					self::$ERROR[__METHOD__][] = "exceeded limit for files, $limit";
					break;
				}
				
				$file_list[$key]['name'] = basename($file);
				$file_list[$key]['size'] = filesize($file);
				$file_list[$key]['date'] = date('Y-m-d G:i:s', filemtime($file));
				$set_limit++;
			}
			
			if(!empty($file_list))
			{
				return $file_list;
			}
	 
		} //end of path exists
		
		return false;
	 }
	 
	 
	 /**
	  * @method scan_for_directories whose name contains a keyword
	  * scan for all the directories in an array
	  */
	 public static function scan_for_named_directories($path, $keyword)
	 {
		$named_dirs = array();
	    
		foreach(glob($path, GLOB_ONLYDIR) as $dir)
		{
			if(substr($dir, 0, 1) != ".")
			{
				if(strpos($dir, $keyword) !== false)
				{
				$named_dirs[] = $item;
				}
			}
		}
	    
		//return directories with matching substrings in their name
	    
		if(count($named_dirs) > 0)
		{
			return $named_dirs;
		}
	    
		return false;
	 }
	 
	 /**
	  * @method get_sources
	  * get available sources for output, limiting by providing ids for
	  * excluded sources that won't be returned
	  * @param {Array} $exclude_names if present, contains an list of names
	  * of the disallowed sources. Must match 'name' field in table 'sources'
	  * @returns {Array|false} if ok, return source records, else false
	  */
	 public static function get_convert_sources($exclude_names = false)
	 {
		$table_name = self::$table_names['sources'];

		$sources_arr = self::get_all($table_name, 
			  array('id', 'name', 'title'),
				array('title')
				);
	    
		$convert_sources_arr = array();
	    
		foreach($sources_arr as $source)
		{
			if(is_array($exclude_names))
			{
			//exclude sources with names in $exclude_names
		    
			foreach($exclude_names as $source_name)
			{
				if(isset(self::$source_ids[$source_name])) //make sure valid name
				{
				if($source['name'] !== $source_name)
				{
					$convert_sources_arr[] = $source;
				}
				}
				else
				{
				self::$ERROR[__METHOD__][] = "invalid source name $source_name";
				}
			}
		    
			}
			else
			{
			$convert_sources_arr[] = $source;
			}
		
		}
	    
		if(count($convert_sources_arr > 0))
		{
			return $convert_sources_arr;
		}

		return false;
	 }
	
	
	/**
	 * @method get_convert_translation
	 * get the properties associated with a given source, along with their
	 * translation
	 * @param {Number} $source_id id of primary data source (usually 'gbp')
	 * @param {Number} $alt_source_id id of secondary data source to import
	 * @returns {Array|false} if ok, return a translation table mapping GBP property names to
	 * the comparable property names in the alt_source
	 */
	public static function get_convert_translation($source_id, $alt_source_id)
	{
		//clean the incoming data
		
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in

		$table_name = self::$table_names['translations'];
		
		$db = self::get_pdo();
	
		$select_list = "SELECT $column_list FROM `".$table_name."` WHERE source_id=:sval AND alt_source_id=:aval";
		
		$statement = $db->prepare($select_list);
			
			try {
				$statement->execute(array(':sval' => $source_id, ':aval' => $alt_source_id));		
				$statement->setFetchMode(PDO::FETCH_ASSOC);
				$row = $statement->fetchAll();														
				if(is_array($row) && count($row) > 0)
				{
					$table_name = self::$table_names['properties'];
					$translation = array();
					
					foreach($row as $key => $rec)
					{
						$prop     = self::get_record_by_id($table_name, $rec['property_id'], array('id', 'name'));
						$alt_prop = self::get_record_by_id($table_name, $rec['alt_property_id'], array('id', 'name'));
						$translation[$prop['name']] = $alt_prop['name'];
						
					}
					if(count($translation))
					{
						return $translation; //return translation table
					}
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
			
		return false;
	}
	
	
	/**
	 * @method get_json
	 * get a JSON file, and convert it to a PHP multidimensional associative array
	 * @param {String} $json_file path to JSON file on server
	 * @returns {Array|false} if ok, return JSON as multi-dimensional array, else false
	 */
	protected static function get_json($json_file)
	{
	    	$file_str = file_get_contents($json_file);
		
		if($file_str === false) //read into array
		{
			self::print_error(__METHOD__, "could not load JSON file at ".$json_file);
			return false;
		}
		
			
		//decode JSON
		
		$json_arr[] = json_decode($file_str, true);
		
		if($json_arr === false)
		{
			self::print_error(__METHOD__,"could not decode JSON file at ".$json_file);
			return false;
		}
		
		return $json_arr;
	}
	
	
	/** 
	 * @method get_clients
	 * get all gbp clients, and return in an array with client and version
	 * @returns {Array | false} if ok, return array[client] = client version, else false
	 */
	 protected static function get_clients() 
	 {
		 return false;
	 }

	

};
