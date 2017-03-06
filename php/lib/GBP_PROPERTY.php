<?php


/** 
 * -------------------------------------------------------------------------
 * GBP_PROPERTY.php
 * 
 * PROPERTY MANIPULATION CLASS
 * This object uses the GBP Properties database to create and manipulate a memory 
 * object representing one or more properties, as a multi-dimensional array
 * Array
    [id]     => primary key
    [name]   => JavaScript property name
    [title]  => Longer name (shown on forms)
    [source] => Array
            [id]          => primary key for source database
            [name]        => JavaScript name of source database
            [title]       => Longer name (shown on forms)
            [description] => green boilerplate database
     [component] => Array
            [id]          => primary key for component table (HTML, browser, user, ISP)
            [name]        => JavaScript name of component
            [title]       => Longer name (shown on forms)
            [description] => full description of component
    [description] => full description of property
    [dependency]  => Array
            [1210] => Array
				Full record in a recursive list.
				The property that must/must not present for this propery to be valid (e.g. hasHTML -> hasHTML5)
    [discovery] => Array
            [property_id] => primary key of discovery state
            [state]       => discovery mode (client, server, both, by user inspection)
            
 * -------------------------------------------------------------------------
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 2013
 * @version 1.0
 * 
 * ------------------------------------------------------------------------- 
 */
class GBP_PROPERTY extends GBP_BASE {

	/** 
	 * our property array is a multi-dimensional array, assembled
	 * in a specific pattern
	 */
	private static $property_values = array();
	
	/** 
	 * we run a running tally of properties, since we have parents. When recovering a 
	 * property, we recursively grab parent properties until we reach the end of the 
	 * property chain. This allows recursive loops to form, we we define an array 
	 * global to the recursive process that keeps track of how many times we've 
	 * recursed to a parent property. We also confirm the we haven't looped back on 
	 * ourselves.
	 */
	 private static $chain = array();
	
	
	/** 
	 * constructor
	 */
	public function __construct()
	{
		parent::__construct();
		
	}

	
	/**
	 * ------------------------------------------------------------------------- 
	 * UTILITIES
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/**
	 * -------------------------------------------------------------------------
	 * SELECT TYPE FUNCTIONS - SIMPLE
	 * basic queries with one table
	 * -------------------------------------------------------------------------
	 */

	
	/**
	 * note: get_component_id_by name is in GBP_BASE (shared among several modules)
	 */


	/** 
	 * @method get_component
	 * get information about the GBP component for a specific component id
	 * NOTE: we assume source_id has been validated
	 * @param {Number} $component_id id for component record
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return if record found, return an array with the record, otherwise, return false
	 */	 
	public function get_component($component_id, $list_only=true)
	{
		$table_name = self::$table_names['components'];
		if($list_only === true)
		{
			return self::get_record_by_id($table_name, $component_id, array('id','system_id','datatype_id','name','title'));
		}
		else
		{
			return self::get_record_by_id($table_name, $component_id);			
		}
	}
	
	
	/** 
	 * @method get_all_components
	 * @param {Number} $system_id restrict components only belonging to a particular GBP system, e.g.
	 * 1. cloud
	 * 2. network
	 * 3. client
	 * 4. user
	 * 5. designer
	 * 6. stakeholder (non-designer)
	 * @param {Boolean} $list_only if true, return a subset of columns,
	 * otherwise, all columns returned
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return {Array} an array of rows with all defined components
	 * 
	 */
	public function get_all_components($system_id = 0, $list_only=true)
	{
		$table_name = self::$table_names['components'];
		
		if($system_id == 0)
		{
			return self::get_all(self::$table_names['components'], false, array('title'));
		}
		else
		{
			return self::get_records_by_column_value($table_name, 'system_id', $system_id, false, array('title'));
		}	
	}
	
	
	/**
	 * note: get_datatype_id_by name is in GBP_BASE (shared among several modules)
	 */

	
	/** 
	 * @method get datatype of property
	 * get a property datatype record from 'datatypes'
	 * NOTE: we don't try to order these
	 * @param {Number} $datatype_id record_id of datatype
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 */
	public function get_datatype($datatype_id, $list_only=true)
	{
		$table_name = self::$table_names['datatypes'];
		if($list_only === true)
		{
			return self::get_record_by_id($table_name, $datatype_id, array('id','name','title'));
		}
		else
		{
			return self::get_record_by_id($table_name, $datatype_id);
		}
	}

	
	/** 
	 * @method get_all_datatypes for properties
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return {Array} an array of rows with all defined datatypes
	 */
	public function get_all_datatypes($list_only=true)
	{
		$table_name = self::$table_names['datatypes'];
		
		if($list_only)
		{
			return self::get_all($table_name, array('id', 'name', 'title'));	
		}
		else
		{
			return self::get_all($table_name); //list_only = false, also load description
		}
	}


	/**
	 * note: get_source_id_by name is in GBP_BASE (shared among several modules)
	 */

	
	/** 
	 * @method get_source
	 * get information about the database (GBP or other database for a particular source id
	 * NOTE: we assume source_id has been validated
	 * @param {Number} $component_id id for source record
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return if record found, return an array with the record, otherwise, return false
	 */	
	public function get_source($source_id, $list_only=true)
	{	
		$table_name = self::$table_names['sources'];
		if($list_only === true)
		{
			return self::get_record_by_id($table_name, $source_id, array('id','name','title'));	
		}
		else
		{
			return self::get_record_by_id($table_name, $source_id);			
		}
		
	}

	
	/** 
	 * @method get_all_sources
	 * get all the possible sources
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return Array with all possible sources currently in the database
	 */
	public function get_all_sources($list_only=true)
	{
		if($list_only === true)
		{
			return self::get_all(self::$table_names['sources'], array('id','name','title'));	
		}
		
		else
		{
			return self::get_all(self::$table_names['sources']);				
		}
	}

	
	/**
	 * note: get_discovery_id_by name is in GBP_BASE (shared among several modules)
	 */


	/**
	 * @method get_discovery_mode
	 * get the method of discovery for this property (client, server, both, user inspection)
	 * NOTE: 'discovery' is a link table, no description (so no $list_only option)
	 * @param {Number} $property_id id for property
	 * @return value from the discovery_states table associated with this property
	 */
	public function get_discovery_mode($property_id)
	{	
		$table_name = self::$table_names['discovery'];
		$row = self::get_records_by_column_value($table_name, 'property_id', $property_id);
		
		//discovery record is required, we only allow one discovery link record
			
		if(is_array($row) && count($row) > 0) //should get back exactly one array
		{
			$row = $row[0];
			
			//get the discovery state
			
			$table_name = self::$table_names['discovery_state'];
			$state_id   = $row['state_id'];
			$row2       = self::get_record_by_id($table_name, $state_id);
			
			//only one discovery state allowed
			
			if(is_array($row2) && count($row2) > 0) 
			{
				return array('property_id' => $row['id'], 'state_id' => $row['state_id'], 'state' => $row2['name']);
			}
			else
			{
				self::$ERROR[__METHOD__][] = "could not get discovery mode";
			}
		}
		else
		{
			self::$ERROR[__METHOD__][] = "discovery record not returned for property_id=$property_id)";
		}

		return false;	
	}
	
	
	/** 
	 * @method get_all_discovery_modes
	 * return all possible discovery values from the table 'discovery_state'
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return an array with all the discovery modes
	 */
	public function get_all_discovery_modes($list_only=true)
	{
		$table_name = self::$table_names['discovery_state'];
		
		if($list_only === true)
		{
			return self::get_all($table_name, array('id','name','title'));	
		}
		else
		{
			return self::get_all($table_name);	
		}
	}


	/**
	 * -------------------------------------------------------------------------
	 * SELECT TYPE FUNCTIONS - COMPLEX
	 * begin complex features of a property (requiring multiple queries or tables)
	 * -------------------------------------------------------------------------
	 */


	/**
	 * @method get_translation()
	 * given a property in one source database, return the same property from another source database
	 * NOTE: this is a link table, so we don't restrict the number of columns we return
	 * @param {Number} $property_id property we are checking for a alternate (parent) source
	 * @param {Number} $alternate_source_id id of the alternate (parent) source database
	 * @return if found, return the parent property, otherwise false
	 */	
	public function get_translation($property_id, $alternate_source_id)
	{
		$table_name = self::$table_names['translations'];
		 $row = self::get_records_by_value_array($table_name, 
		 	array('alt_source_id', 'property_id'), 
			array($alternate_source_id, $property_id), 
			false);

		//the above method returns and array of records. We want only the first one
		 
		 if(is_array($row))
		 {
			$ct = count($row);
			if($ct == 1)
			{
				if(isset($row[0]))
				{
					return $row[0];
				}
				else
				{
					self::$ERROR[__METHOD__][] = "THE ROW[0] DOES NOT EXIST";
				}
			}
			return $row;
		 }
		 
		 return false;
	}
	
	
	/** 
	 * @method get_translation_by_name
	 * given a unique name of a property, with optional source restriction, 
	 * get all the translations.
	 * @param {String} $property_name name field of property
	 * @param {String} $source_name name field in sources
	 * @return {Array|false}
	 */	
	public function get_translation_by_name($property_name, $source_name)
	{	
		$property_id = self::get_record_id_by_name($property_name, self::$table_names['properties']);
		$source_id   = self::get_record_id_by_name($source_name, self::$table_names['sources']);
		
		if(is_numeric($property_id) && is_numeric($source_id))
		{
			return self::get_translation($property_id, $source_id);
		}
		else
		{
			self::$ERROR[__METHOD__][] = "Failed to get record_id by name, property_name $property_name (property_id = $property_id) or source_name $source_name (source_id=$source_id)";
		}
				
		return false;
	}
	

	/** 
	 * @method get_all_translations
	 * get all the translations
	 * NOTE: this is a link table, so we don't restrict columns
	 * @return {Array} list of all translation records
	 */
	public function get_all_translations()
	{
		$table_name = self::$table_names['translations'];
		
		return self::get_all($table_name);
	}


	/** 
	 * @method find_dependency_chains
	 * given a starting point in the dependency table, the dependency chain, and 
	 * flag if loops appear
	 * TODO: incomplete
	 * @param {Array} $dependency_arr dependency chain (current)
	 * @param {Array} $property_id the property we are back-tracking dependency for
	 * @param {Array} $dependency_id current dependency
	 */
	public function find_dependency_chains($dependency_arr, $property_id, $dependency_id)
	{
		self::$chain = array(); //starting point
	}

	
	/**
	 * note: get_dependency_state_id_by name is in GBP_BASE (shared among several modules)
	 */

	
	/** 
	 * @method get_dependency_state($dependency_id)
	 * get the dependency state user-readable name for a particular dependency_state_ie
	 * @param {Number} $dependency_state_id id of the record in 'dependency'
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return {Array|false} if ok, return the id and name, otherwise false
	 */
	public function get_dependency_state($dependency_state_id, $list_only=true)
	{
		$table_name = self::$table_names['dependency_state'];
		
		if($list_only === true)
		{
			$row = self::get_record_by_id($table_name, $dependency_state_id, array('id', 'datatype_id','name','title'));
		}
		else
		{
			$row = self::get_record_by_id($table_name, $dependency_state_id);	
		}
		
		//we should only get back one row, not wrapped in an array
			
		if(isset($row)) // && count($row) == 1)
		{
			//$row = $row[0];
			return array('id' => $row['id'], 'name' => $row['name']);
		}
		else
		{
			self::$ERROR[__METHOD__][] = "invalid num of dependency state modes set (".count($row).")";
		}
		
		return false;	
	}
	

	/** 
	 * @method get_all_dependency_states()
	 * get all the possible dependency states
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 */	
	public function get_all_dependency_states($list_only=true)
	{
		$table_name = self::$table_names['dependency_state'];
		if($list_only === true)
		{
			return self::get_all($table_name, array('id', 'datatype_id','name','title'));	
		}
		else
		{
			return self::get_all($table_name);	
		}
	}
	
	
	/** 
	 * @method get_dependency
	 * recursively get the dependencies for a particular property, and add them to the dependency array
	 * @param {Number} $property_id the id of the property
	 * @return multi-dimensional array of properties and the the properties they depend on joined as 
	 * array members to their 'dependency' key
	 */
	private function get_dependency($property_id, $recurse=false)
	{
		$table_name = self::$table_names['dependency'];
		
		/** 
		 * since we have dependencies in a chain, it is possible to 
		 * accidentally set a circular dependency. To prevent this, 
		 * we pass a chain of the sequence of dependencies we have gone through.
		 * If we hit a circular reference, or we go further than $MAX_DEPENDENCY_CHAIN,
		 * halt with an error
		 */
		$rows = self::get_records_by_column_value($table_name, 'property_id', $property_id);
			
		$dependency = array();
		
		/** 
		 * we can have MULTIPLE and RECURSIVE dependencies. For example, hasCanvasText might 
		 * be dependent on both hasCanvas and hasHTML5. These in turn, might be dependent
		 * on HTML.
		 *
		 * Get all the dependencies associated with property_id
		 */
		if(is_array($rows) && count($rows) > 0)
		{
			foreach($rows as $row2)
			{
				/**
				 * look for a circular reference whre a property -> depends on -> itself
				 * otherwise, we can get an endless recursion
				 */
				if($property_id == $row2['parent_id'])
				{
					self::$ERROR[__METHOD__][] = "CIRCULAR REFERENCE DETECTED: $property_id to $".$row2['parent_id'];
					self::delete_dependency($property_id, $row2['parent_id']);
					continue;
				}
				
				//this line makes the dependency search recursive.
				
				self::$ERROR[__METHOD__][] = "ABOUT TO ADD DEPENDENCY";
				
				if($recurse === true)
				{
					$dependency[$row2['parent_id']] = self::get_property_chain($row2['parent_id']);	
				}
				else
				{
					$dependency[$row2['parent_id']] = self::get_property($row2['parent_id'], false);
				}
				
				
				//get state
					
				$dependency[$row2['parent_id']]['state'] = self::get_dependency_state($row2['state_id']);
				
				//add a sorting column
				
				$dependency[$row2['parent_id']]['sort'] = $dependency[$row2['parent_id']]['title'];
			}
			
			if(count($dependency) > 0)
			{	
				$vers_keys = array();
				foreach ($dependency as $key => $row)
				{
					$vers_keys[$key] = $row['sort'];
				}
				array_multisort($vers_keys, SORT_ASC, $dependency);
				
				return $dependency;
			}
		}
		
		return false;	
	}
	

	/**
	 * -------------------------------------------------------------------------
	 * SELECT - MAIN PROPERTY METHODS
	 * includes recursive property select along with related tables, bundled into PHP array
	 * -------------------------------------------------------------------------
	 */
		
		
	/** 
	 * @method get_next_property()
	 * PRIVATE, we use this when recursively grabbing properties and dependencies. We call 
	 * get_property() to start. Recursions through properties we are dependent on (parent properties) 
	 * are handled by jumping back and forth between get_next_property() and get_dependency()
	 * @param {Number} $property_id id value for property we are looking for	 
	 * @return if found, return multi-dimensional array with property data, otherwise, return false
	 */
	private function get_next_property($property_id)
	{
		$table_name = self::$table_names['properties'];
			
		/** 
		 * $chain is initialized in get_property, and we add the property_id of every 
		 * property as we recurse up the dependency tree
		 */
		if(in_array($property_id, self::$chain)) 
		{
			//we've run into ourself (which implies a big circular dependency), so kill the process
			
			self::$ERROR[__METHOD__][] = "CIRCULAR DEPENDENCY:".implode('->', self::$chain)."->$property_id";
			return false;     //don't continue
		}
		
		//assign our current property_id for the next iteration
		
		self::$chain[] = $property_id;				
		
		$property = self::get_property_only($property_id);
		if(is_array($property))
		{	
			$property['dependency']  = self::get_dependency($property['id'], true); //NOTE: RECURSIVE
			
			//return the completed property
			
			return $property;			
		}
		
		return false;
	}
	
	
	/** 
	 * @method get_property_chain
	 * recursively call get_property to get all properties in the dependency chain
	 * @param {Number} $property_id primary key for property record
	 * @return {Array|false} if true, return property record as associative array, false otherwise
	 */
	public function get_property_chain($property_id)
	{
		self::$chain = array(); //initialize our test for circular dependencies
		return self::get_next_property($property_id);
	}
	
	
	/**
	 * @method get_property (non-recursive)
	 * get a property, plus first-order dependencies, but don't recurse
	 * when we call get_dependency with a 'false', 
	 */
	function get_property($property_id, $get_dependency = true)
	{
		$property = self::get_property_only($property_id);
		
		if(is_array($property))
		{	
			if($get_dependency === true)
			{
				$property['dependency']  = self::get_dependency($property_id, false); //NOTE: NON-RECURSIVE
			}
			else
			{
				$property['dependency']  = false;
			}
			
			//return the completed property
			
			return $property;			
		}
		
		return false;
	}

	
	/**
	 * @method get_property_id_by_name
	 * given a property name, get the id
	 */
	public function get_property_id_by_name($property_name)
	{
		return self::get_record_id_by_name($property_name, self::$table_names['properties']);
	}
	
	/**
	 * get a property, without filling in dependencies
	 */
	private function get_property_only($property_id)
	{
		$table_name = self::$table_names['properties'];
		
		$row = self::get_record_by_id($table_name, $property_id);
		if(is_array($row))
		{
			$property = array();
			$property['id']               = $row['id'];
			$property['name']             = $row['name'];
			$property['title']            = $row['title'];
			$property['datatype']         = self::get_datatype($row['datatype_id']);
			$property['source']           = self::get_source($row['source_id']);
			$property['component']        = self::get_component($row['component_id']);
			$property['description']      = $row['description'];
			$property['component_lock']   = $row['component_lock'];
			$property['exe_lock']         = $row['exe_lock'];         //format for JSON
			$property['exe_lock_priority'] = $row['exe_lock_priority'];
			
			//we don't fill in dependency
			
			$property['dependency']  = false;
			
			//add sources
				
			$sources = self::get_all_sources();
			$alt_source = array();
				
			foreach($sources as $source)
			{
				$trans = self::get_translation($property_id, $source['id']);
				if(isset($trans))
				{
					$alt_source[$source['name']] = $trans;
				}
			}
				
			$property['alt_source'] = $alt_source;
				
			//add discovery
			
			$property['discovery']   = self::get_discovery_mode($row['id']);
			
			//return the completed property
			
			return $property;
		}
		
		return false;	
		
	}
	
	
	/** 
	 * @method get_all_properties
	 * get all the property records (usually to put into a form control)
	 * @param {Number or String} $source_id the id of the source database the property belongs to. If 
	 * we provide a source name, the method recovers its unique Id in the source table
	 * @param {Number} $component_id the id of the component, when we want to restrict the result 
	 * @param {String] $source_name name of the component in our globals. We rely on name because it is 
	 * more stable than the primary key id of the source database to a specific component
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @param {Boolean} $sort if true, do an array_multisort, otherwise don't sort
	 * @return {Array} if the properties are found, false otherwise
	 */
	public function get_all_properties($source_id=0, $component_id=0, $list_only=false, $sort=true)
	{		
		$db = self::get_pdo();
		$table_name = self::$table_names['properties'];
		
		$sel_arr = array();
		
		if($list_only == true)
		{
			$select_list = "SELECT id, name, title FROM `".$table_name."`";			
		}
		else
		{
			$select_list = "SELECT * FROM `".$table_name."`";
		}
		
		//branch based on which one (or both) of input ids are initialized
		
		if(self::is_valid_id($component_id, self::$table_names['components']))
		{
			$select_list .= " where component_id=:cid";
			$sel_arr[':cid'] = $component_id;
			if(self::is_valid_id($source_id, self::$table_names['sources']))
			{
				$select_list .= " and source_id=:sid";
				$sel_arr[':sid'] = $source_id;
			}
		}
		else if(self::is_valid_id($source_id, self::$table_names['sources']))
		{
			$select_list .= " where source_id=:sid";		
			$sel_arr[':sid'] = $source_id;	
			if(self::is_valid_id($component_id, self::$table_names['components']))
			{
				$select_list .= " and component_id=:cid";
				$sel_arr[':cid'] = $component_id;
			}
		}
		else
		{
			self::$ERROR[__METHOD__][] = "Both source_id and component_id are invalid ($source_id, $component_id)";
			return false;
		}
		
		//execute the query
		
		try {
			
			$statement = $db->prepare($select_list);
			$statement->execute($sel_arr);		
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetchAll();
			
			
			if(is_array($row) && count($row) > 0)
			{
				//create our array of all properties
				
				$vers_keys = array();
				
				foreach($row as $key => &$prop)
				{
					if($list_only === false)
					{
						$prop['datatype']       = self::get_datatype($prop['datatype_id']);
						$prop['source']         = self::get_source($prop['source_id']);
						$prop['component']      = self::get_component($prop['component_id']);
					}
					
					//if $sort == true, sort by title
					
					$vers_keys[] = strtolower($prop['title']);
				}
				
				/**
				 * we can't directly sort the constructed array in MySQL. So sort it here by title
				 */
				if($sort === true)
				{
					array_multisort($vers_keys, SORT_STRING, $row);
				}
				return $row;
				
			}
			else
			{
				self::$ERROR[__METHOD__][] = "No properties found for source_id=$source_id and component_id=$component_id";
			}
			
		}
		catch (Exception $e) {
			self::$ERROR[__METHOD][] = $e->getMessage()." for table $table_name";
		}
		
		return false;
	}


	/** 
	 * get all properties by source name
	 * get properties by name, with optional $source or $component restriction
	 * @param {String} $source_name name of property (which must be unique, even though it isn't an Id)
	 * @param {String} $component_name (optional) name of component, restring properties to that component class
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return {Array} if true, return all properties for a given source from 'sources' table
	 */
	public function get_all_properties_by_name($source_name, $component_name=false, $list_only=false)
	{
		//since we can provide a string instead of an source_id, translate before executing
		
		$source_id    = self::get_record_id_by_name($source_name, self::$table_names['sources']);
		
		if($component_name != false)
		{
			$component_id = self::get_record_id_by_name($component_name, self::$table_names['components']); 
		}
		else
		{
			$component_id = 0; //ignored value by get_all_properties()
		}
		
		if(is_numeric($source_id))
		{			
			return self::get_all_properties($source_id, $component_id, $list_only);
		}
		
		return false;
	}
	
	
	/**
	 * @method get_locked_properties
	 * get all the properties that are locked. Use to restrict
	 * editing in client-property-version data (e.g. we don't want to
	 * edit 'name' since this is locked)
	 * @raturns {Array|false} of ok, return property records with lock=1, otherwise false
	 */
	public function get_all_locked_properties()
	{
		$table_name = self::$table_names['properties'];
		return self::get_records_by_column_value($table_name, 'component_lock', "1", array('id', 'name', 'title'));
	}


	/** 
	 * get the swap table for two green ingredients
	 */
	private function get_swaps($property1_id, $property2_id)
	{
		self::$ERROR[__METHOD__][] = "function NOT COMPLETE!";
		
		return false;
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * INSERT, UPDATE, DELETE ROUTINES FOR PROPERTIES TABLES
	 * begin complex features of a property (requiring multiple queries or tables)
	 * -------------------------------------------------------------------------
	 */
	 
	 
	/**
	 * complete property update (or insert new property)
	 * this routine inserts or updates the minimum required fields to create a new property
	 * @param {Number} $property_id id for property
	 * @param {Number} $component_id id for component
	 * @param {Number} $source_id id for data source
	 * @param {Number} $datatype id for datatype
	 * @param {String} $name name of property
	 * @param {String} $title title of property
	 * @param {String} $description property description
	 * @param {Array|false} if inserted, return summary array with new record_id, else false
	 */
	public function insert_new_property($property_id, $component_id, $source_id, $datatype_id, $name, $title, $description)
	{
		$table_name = self::$table_names['properties'];
		
		$row = self::get_record_by_id($table_name, $property_id);
		
		if(is_array($row))
		{
			self::$ERROR[__METHOD__][] = "duplicate property record id id=$property_id";
			return array('property_id' => self::$DUPLICATE_RECORD, 'duplicate' => 'id', 'column_name' => $property_id);
		}
		else
		{
			//insert a new property record
			
			//first, check if there is not already another property with the same name. if so, don't insert
			
			$result = self::get_record_id_by_name($name, $table_name);
			if($result)
			{
				self::$ERROR[__METHOD__][] = "Name ($name) already exists in property table";
				return array('property_id' => self::$DUPLICATE_RECORD, 'duplicate' => 'name', 'column_value' => $name);
			}
			
			$result = self::get_record_id_by_title($title, $table_name);
			if($result)
			{
				self::$ERROR[__METHOD__][] = "Title ($title) already exists in property table";
				return array('property_id' => self::$DUPLICATE_RECORD, 'duplicate' => 'title', 'column_value' => $title);
			}
			
			//we have a unique record, so do the insert
			
			$result = self::insert_record_by_value_array($table_name,
				array('component_id', 'source_id', 'datatype_id', 'name', 'title', 'description'),
				array($component_id,  $source_id,  $datatype_id,  $name,  $title,  $description)
				);
			
			//return the id of the insert so we can add the new property to our client-side list
			
			if($result && self::$last_insert_id)
			{
				return array('property_id' => self::$last_insert_id, 'duplicate' => "false", 'column_value' => self::$last_insert_id);
			}
			else
			{
				self::$ERROR[__METHOD__][] = "invalid last_insert_id:".self::$last_insert_id;
			}
			
		}
		return false;
	}


	/** 
	 * @method update_property_field
	 * update an individual field in an existing property
	 * @param {Number} $property_id primary key for property record
	 * @param {String} $field_name name of field for updating
	 * @param {String} $field_value value to update field_name with
	 * @return {Boolean} if updated, return true, else false
	 */
	public function update_property_field($property_id, $field_name, $field_value)
	{
		$table_name = self::$table_names['properties'];
		
		if(self::is_valid_column($table_name, $field_name))
		{
			return self::update_row_column_value($table_name, $property_id, $field_name, $field_value);	
		}
		else
		{
			self::$ERROR[__METHOD__][] = "invalid column $field_name for property_id $property_id in properties table";
			return false;
		}
	}
	
	
	/**
	 * @method delete_property
	 * delete a property and its related records
	 * @param {Number} $property_id the id of the property we want to delete.
	 * deleting involves the following steps:
	 * - delete any discovery records
	 * - delete any client_properties records
	 * - delete parent dependencies
	 * - scanning the database for child dependencies depending on this record.
	 * - if they exist, they are also deleted
	 * - delete any discovery records
	 * - delete any translations for our property_id
	 * @return {Boolean} if ok, return true, else false
	 */
	public function delete_property($property_id)
	{
		//delete any discovery records
		
		self::$ERROR[__METHOD__][] = 'discovery:'.self::delete_records_by_column_value(self::$table_names['discovery'], 'property_id', $property_id);
		
		//delete any client_properties records
		
		self::$ERROR[__METHOD__][] = 'clients_properties:'.self::delete_records_by_column_value(self::$table_names['clients_properties'], 'properties_id', $property_id);
		
		//delete parent dependencies
		
		self::$ERROR[__METHOD__][] = 'dependency-parent:'.self::delete_records_by_column_value(self::$table_names['dependency'], 'parent_id', $property_id);
		
		//scan the database for child dependencies depending on this record, if they exist, delete
		
		self::$ERROR[__METHOD__][] = 'dependency-child:'.self::delete_records_by_column_value(self::$table_names['dependency'], 'property_id', $property_id);
		
		//delete any translations for our property_id
		
		self::$ERROR[__METHOD__][] = 'translations:'.self::delete_records_by_column_value(self::$table_names['translations'], 'property_id', $property_id);
		
		//finally, delete the property
		
		self::$ERROR[__METHOD__][] = 'property:'.self::delete_records_by_column_value(self::$table_names['properties'], 'id', $property_id);
		
		return true;
	}
	
	
	/** 
	 * @method update_discovery_field
	 * update a related discovery record for a given property, insert if 
	 * it doesn't exist yet. Required, so we can't delete the discovery reference.
	 * ignore self::$NONE, since we should always have a discovery method.
	 * @param {Number} $property_id the id of the property record
	 * @param {String} $field_name name of field in discovery table
	 * @param {String} $field_value value of field in discovery table
	 * @return {Array|Boolean}
	 */
	public function update_discovery_field($property_id, $field_name, $field_value)
	{			
		$table_name = self::$table_names['discovery'];
		
		//check if our field value is $NO_RECORD. If so, delete existing
		
		if($field_value == self::$NO_RECORD)
		{
			$discoveries = self::get_records_by_column_value($table_name, 'property_id', $property_id);	
			
			if(is_array($discoveries))
			{
				$num_records = count($discoveries); 
				if($num_records > 0)
				{
					foreach($discoveries as $discovery) //all dependency records
					{
						self::delete_record_by_id($table_name, $discovery['id']);
					}
				}
			}
			return true; //emptied, or already empty, done	
		}
		
		//look for records (there should be zero or one)
		
		$discovery = self::get_records_by_column_value($table_name, 'property_id', $property_id); //returns false if records don't exist
		
		//we don't delete here, since we ALWAYS have a discovery value
		
		if(is_array($discovery))
		{
			$num_records = count($discovery);
			
			if($num_records == 1)
			{
				if(isset($discovery[0]['id']))
				{
					$result = self::update_row_column_value($table_name, $discovery[0]['id'], $field_name, $field_value);
					return $result;
				}
				else
				{
					self::$ERROR[__METHOD__][] = "found record but no id field for table discovery";
					self::$ERROR[__METHOD__][] = $discovery;
					return false;
				}
			}
			else 
			{
				self::$ERROR[__METHOD__][] = "invalid number of discovery records ($num_records), property_id=$property_id ($num_records)";
			}
		}
		
		//we need to insert a new record if it is missing or never created
			
		return self::insert_record_by_value_array($table_name,
			array('property_id', $field_name), 
			array($property_id, $field_value)
			);
		
	}
	

	/** 
	 * @method update_dependency_field
	 * dependency optional, and a property may have multiple dependencies. However, we update them
	 * one at a time, so there should always only be one result here.
	 * If we select self::$NONE, we delete ALL dependencies, since they are optional.
	 * @param {Number} $property_id id of property record we want to update
	 * @param {Number} $parent_property_id parent property we want to associate with our property
	 * @param {String} $field_name name of field in dependency record we want to update (dependency_state)
	 * @param {String} $field_value value to update dependency field with (a dependency_state_id)
	 * @return {Boolean} if updated, return true, else false
	 */
	public function update_dependency_field($property_id, $parent_property_id, $field_name, $field_value)
	{
		//find the dependency field (could be multiple)
		
		$table_name = self::$table_names['dependency'];
		
		//If we selected "none" it implies we want to remove ALL dependencies
		
		if($parent_property_id === self::$NONE)
		{
			$dependencies = self::get_records_by_value_array($table_name, 
				array('property_id'), 
				array($property_id), 
				false);
			
			if(is_array($dependencies))
			{
				$num_records = count($dependencies); 
				
				if($num_records > 0)
				{
					foreach($dependencies as $depends) //all dependency records
					{
						self::delete_record_by_id($table_name, $depends['id']);
					}
				}
			}
			
			return true; //already empty or emptied, done
		}
		
		//insert, delete or update a dependency (there should only be one found with this SELECT)
		
		$dependency = self::get_records_by_value_array($table_name, 
			array('property_id', 'parent_id'), 
			array($property_id, $parent_property_id), 
			false);
		
		if(is_array($dependency))
		{
			$num_records = count($dependency);
				
			if($num_records == 1) //update record
			{
				if(isset($dependency[0]['id']))
				{
					$record_id = $dependency[0]['id'];
					
					/**
					 * look for a self-referential dependency, e.g. html4->html4. Since they might
					 * sneak in, delete as a matter of housekeeping when updating. Otherwise,
					 * just update the dependency_state
					*/
					if($property_id == $parent_property_id)
					{
						$ERROR[__METHOD__][] = "SELF-REFERENCE property lists itself as a dependency, DELETING";
						self::delete_record_by_id($table_name, $record_id);
						return false;
					}
					else
					{
						return self::update_row_column_value($table_name, $record_id, $field_name, $field_value);	
					}
					
				}
				else
				{
					self::$ERROR[__METHOD__][] = "found dependency record, NO id field, num_records is $num_records";
					self::$ERROR[__METHOD__][] = $dependency;
					return false;
				}
			}
			else
			{
				self::$ERROR[__METHOD__][] = "multiple records for a single propert->parent dependency, num_records is $num_records property_id=$property_id parent_property_id=$parent_property_id";
				self::$ERROR[__METHOD__][] = $dependency;
			}
		}
		
		//in case we didn't get an array, or we got an empty array, we need to insert a new dependency
		
		$result = self::insert_record_by_value_array($table_name,
			array('property_id', 'parent_id', $field_name), 
			array($property_id, $parent_property_id, $field_value)
			);
			
		/**
		 * if we insert a dependency, we need the new row to update the Ui. So, recover the
		 * id of the last inserted record, and return the record.
		 */
		if($result)
		{
			$depend = self::get_record_by_id($table_name, self::$last_insert_id);
			$depend['state'] = self::get_record_by_id(self::$table_names['dependency_state'], $depend['state_id']);
			$prop = self::get_property($parent_property_id); //TODO: we only need one, not the whole schebang
			$prop['dependency'] = $depend;
			return $prop;
		}
		
		return false;
	}
		
		
	/** 
	 * @method update_translation_field (modernizr, caniuse)
	 * add a translation, but REMOVE IT IF WE SELECT "NONE" in the pulldown menu (optional)
	 * Selecting self::$NONE should delete ALL translations associated with the source.
	 * @param {Number} $property_id id of primary property we are adding an equivalent property for in a another source
	 * @param {Number} $source_id the database source for the property
	 * @param {Number} $alt_source_id the other data source (e.g. modernizr)
	 * @param {String} $field_name name of field (alt_property_id) we want to update
	 * @param {String} $field_value the new value for alt_property_id
	 */
	public function update_translation_field($property_id, $source_id, $alt_source_id, $field_name, $field_value)
	{			
		$table_name = self::$table_names['translations'];
		
		$translation = self::get_records_by_value_array($table_name, 
			array('property_id', 'source_id', 'alt_source_id'), 
			array($property_id, $source_id, $alt_source_id), 
			false);
		
		//since linking of another translation database is optional, we can delete all
		
		
		if($field_value === self::$NONE)
		{
			if(is_array($translation))
			{
				$num_records = count($translation);
				
				if($num_records > 0)
				{
					foreach($translation as $trans) //all dependency records
					{
						self::delete_record_by_id($table_name, $trans['id']); //TODO:DELETED FIELD NOT SHOW UP!!!!!!!!!!!!!!
					}
				}
			}
			
			return true; //already empty or emptied, done
		}
		
		//insert or update
		
		if(is_array($translation))
		{
			$num_records = count($translation); 	
			
			if($num_records == 1) //update record
			{			
				if(isset($translation[0]['id']))
				{
					$record_id = $translation[0]['id'];
					
					//update
					
					return self::update_row_column_value($table_name, $record_id, $field_name, $field_value);
				}
				else
				{
					self::$ERROR[__METHOD__][] = "found translation record, NO id field, num_records is $num_records, $property_id, $source_id, $alt_source_id, $field_name, $field_value";
					self::$ERROR[__METHOD__][] = $dependency;
				}
			}
			
		}
		
		self::$ERROR[__METHOD__][] = "number of records in translation was not 1, ($num_records)";
		
		//we need to insert a new record
		
		return self::insert_record_by_value_array($table_name,
			array('property_id', 'source_id', $field_name, 'alt_source_id'), 
			array($property_id, $source_id, $field_value, $alt_source_id)
			);
		
		return false;
	}
	
	
	/**
	 * @method delete_dependency
	 * delete a dependency record, usually used with explicit 'delete' button
	 * @param {Number} $property_id id of property that is having a dependency removed
	 * @param {Number} $parent_property_id id of parent proprety with a dependency relationship
	 * @return {Boolean} if success, true, otherwise false
	 */
	public function delete_dependency($property_id, $parent_property_id)
	{
		$table_name = self::$table_names['dependency'];
		$dependency = self::get_records_by_value_array($table_name, 
			array('property_id', 'parent_id'), 
			array($property_id, $parent_property_id), 
			false);
		
		$num_records = count($dependency);
		//self::$ERROR[__METHOD__][] = "COUNT FOR DEPENDENCY IS $num_records";
		
		if($num_records == 1)
		{
			$dependency = $dependency[0]; //comes back as an array!
			$result = self::delete_record_by_id($table_name, $dependency['id']);
		}
		else
		{
			self::$ERROR[__METHOD__][] = "wrong count for dependency, expected 1, got $num_records for property_id=$property_id and parent_id=$parent_property_id";
		}
		
		return false;
	}

	
}; //end of class