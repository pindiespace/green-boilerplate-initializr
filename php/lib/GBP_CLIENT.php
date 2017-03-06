<?php

/**
 * -------------------------------------------------------------------------
 * GBP_CLIENT.php
 * class for manipulating client data by version of the client
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 2013
 * @version 1.0
 *
 * -------------------------------------------------------------------------
 */

class GBP_CLIENT extends GBP_BASE {
	
	static private $PROP_OBJ = '';
	static private $REF_OBJ  = '';
	
	/**
	 * constructor
	 */
	public function __construct()
	{
		parent::__construct();
		
		self::$PROP_OBJ = new GBP_PROPERTY;
		self::$REF_OBJ  = new GBP_REFERENCE;
	}
	
	/**
	 * ------------------------------------------------------------------------- 
	 * UTILITIES
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/**
	 * utility to sort by release date, used for 'clients_versions' table
	 * @param {&Array} reference to multi-dimensional array to sort
	 * @return Array is returned by reference
	 */
	public function row_sort_by_date(&$arr)
	{
		usort($arr, function($a, $b) {
		$sortable = array(strtotime($a['releasedate']),strtotime($b['releasedate']));
		$sorted = $sortable;
		sort($sorted);
		
		//if the names have switched position, return -1. Otherwise, return 1.
		
		return ($sorted[0] == $sortable[0]) ? -1 : 1;		 
		});
	}

	
	/**
	 * -------------------------------------------------------------------------
	 * SELECT TYPE FUNCTIONS - SIMPLE
	 * basic queries with one table
	 * -------------------------------------------------------------------------
	 */
	
	
	/** 
	 * @method get_all_search_groups
	 * get all allowed search groups for clients. Search groups are used 
	 * to break the ultimate GBP php database into loadable sections, so common 
	 * browsers are scanned for without loading the whole PHP-based database arrays
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return {Array|false} if ok, return array of all possible search groups, otherwise false<?php include ("../../index.php");?>
	 */
	public function get_all_search_groups($list_only=true)
	{
		if($list_only === true)
		{
			return self::get_all(self::$table_names['search_group'], array('id','name','title'));
		}
		else
		{
			return self::get_all(self::$table_names['search_group']);
		}
		
	}
	

	/**
	 * @method get_client
	 */
	public function get_client($client_id, $list_only=true)
	{
		$table_name = self::$table_names['clients'];
		if($list_only === true)
		{
			$row = self::get_record_by_id($table_name, $client_id, array('id','name','title'));
		}
		else
		{
			$row = self::get_record_by_id($table_name, $client_id);
		}
		
		if(is_array($row))
		{
			return $row;	
		}
		
		return false;
	}
	
	
	/** 
	 * @method get_all_clients
	 * return all clients in the db
	 * @return {Array|false} if ok, return array of clients, otherwise false
	 */
	public function get_all_clients()
	{
		return self::get_all(self::$table_names['clients'],
			false,           //by default, return all columns
			array('title')); //order by 'title'
	}


	/** 
	 * @method get_all_clients_versions()
	 * get all possible versions for all clients
	 */
	public function get_all_clients_versions()
	{
		return self::get_all(self::$table_names['clients_versions']);	
	}
	
	
	/** 
	 * @method get_client_allowed_versions
	 * get the allowed versions for a client, specified int the 'clients_allowed_versions' stable
	 * @param {Number} $client_id id of client (browser)
	 * @return {Array|false} if ok, return all the versions defined for this client
	 */
	public function get_client_versions($client_id)
	{
		$table_name = self::$table_names['clients_versions'];
		
		if(self::is_valid_id($client_id, self::$table_names['clients']))
		{
			/*
			 * get the client versions, and order them by their release date
			 */
			$row = self::get_records_by_value_array($table_name, 
				array('clients_id'),    //array of columns we are searching against
				array($client_id),      //array of values we are looking for in those columns
				false,                  //check to see if columns exist
				array('releasedate'));  //use as a sort
			
			//should have a set of client records by version
				
			if(is_array($row) && count($row))
			{
				//most of our records have nearly unique values, but searchgroup is repetitive, so break out into another table
				
				foreach($row as $key => $vers)
				{
					$vers['searchgroup']   = self::get_record_by_id(self::$table_names['search_group'], $client_version['searchgroup_id'], array('id','name','title'));
				}
				
				return $row;
			}
			else
			{
				self::$ERROR[__METHOD__][] = "invalid num translation equivalents (".count($row).")";
				return false;
			}
		}
		else
		{
			self::$ERROR[__METHOD__][] = "invalid client_id $client_id for clients table";
		}

		return false;
	}
		

	/**
	 * -------------------------------------------------------------------------
	 * SELECT TYPE FUNCTIONS - COMPLEX
	 * begin complex features of client-property, client-property-versions (requiring multiple queries or tables)
	 * -------------------------------------------------------------------------
	 */
	

	/** 
	 * @method get_client_property_versions
	 * get a versions list for the client, with property values filled in if present
	 * @param {Number} $client_id the id the the client (browser)
	 * @param {Number} $property_id the id of the property
	 * @return {Array|false} if ok, return an array with all the version number records associated
	 * with this client, false otherwise.
	 */
	public function get_client_property_versions($client_id, $property_id) 
	{
		if(self::is_valid_id($client_id, self::$table_names['clients']))
		{	
			//find all the versions for a given client
			
			$table_name = self::$table_names['clients_properties'];
			
			self::$ERROR[__METHOD__][] = "clients_id:$client_id, property_id:$property_id";
			
			$row = self::get_records_by_value_array($table_name, 
				array('clients_id', 'properties_id'), 
				array($client_id, $property_id), 
				false); //note: there is no '$list_only' option for this db call
				
			/**
			 * it is OK if we don't have any records yet (count($row) = 0)
			 * 
			 * if we got a row, we now have a row containing all the defined
			 * client_properties tables (un-padded). This
			 * will be a subset of all the allowed versions for the client
			 * 
			 * so get the full client and property data rows
			 */
			$client   = self::get_record_by_id(self::$table_names['clients'], $client_id, array('id', 'name', 'title'));
			$property = self::get_record_by_id(self::$table_names['properties'], $property_id); //we want everything
 			$datatype = self::get_record_by_id(self::$table_names['datatypes'], $property['datatype_id'], array('id', 'name', 'title'));
			
			$client_versions = array();
			
			/**
			 * get ALL the clients_versions, and fill in their data
			 */
			$all_client_versions = self::get_client_versions($client_id, false);
			
			foreach($all_client_versions as $client_version)
			{
				$client_version_id = $client_version['id']; //version id
				$client_versions[$client_version_id]                   = $client_version;
				
				$client_versions[$client_version_id]['client']         = $client; //adds name, version, rekeasedate, comments, searchgroup_id
				$client_versions[$client_version_id]['property']       = $property;			
				$client_versions[$client_version_id]['datatype']       = $datatype;
				$client_versions[$client_version_id]['sort']           = $client_version['releasedate'];
				$client_versions[$client_version_id]['search_group']   = self::get_record_by_id(self::$table_names['search_group'], $client_version['searchgroup_id'], array('id','name','title'));
				$client_versions[$client_version_id]['property_value'] = "undefined";
				
			}
			
			/**
			 * loop through all defined client_properties we got above, and update the 
			 * property_value if it is present
			*/
			if(is_array($row) && count($row) > 0) 
			{
				foreach($row as $defined_prop)
				{
					self::$ERROR[__METHOD__][] = $defined_prop;
				
					if(isset($client_versions[$defined_prop['clients_versions_id']]))
					{
						$client_versions[$defined_prop['clients_versions_id']]['property_value'] = $defined_prop['property_value'];
						$client_versions[$defined_prop['clients_versions_id']]['client_property_id'] = $defined_prop['id']; //needed for references only
					}
					else
					{
						self::$ERROR[__METHOD__][] = $defined_prop['clients_versions_id']." NOT in client_versions, row=$row, client_versions=$client_versions_set, client_id'=$client_id, property_id=$property_id";
						return false;
					}
				}
			
			}
			
			/**
			 * SORT BY RELEASE DATE
			 * we can't directly sort the constructed array in MySQL. The sort for $all_clients_versions is
			 * zapped when we construct the array later. So, sort it here by version
			 */
			$vers_keys = array();
			foreach ($client_versions as $key => $row)
			{
				$vers_keys[$key] = $row['sort'];
			}
			array_multisort($vers_keys, SORT_ASC, $client_versions);
			
			//return the sorted array
			
			return $client_versions;
		}
		
		return false;	
	}
	
	
	/** 
	 * @method get_client_properties
	 * get information about a property associated with a client-version, and its value
	 * @param {Number} $client_id id for client record
	 * @param {Number} $version_id id for the client-version
	 * @return {Array|false} if record found, return an array with the all the associated property records, otherwise false
	 */	 
	public function get_client_properties($client_id, $version_id)
	{
		$table_name = self::$table_names['clients_properties'];
		
		if(self::is_valid_id($client_id, self::$table_names['clients']) && 
			self::is_valid_id($version_id, self::$table_names['clients_versions']))
			{
				//get all the properties from 'clients_properties' for a specific 'clients_versions' row
				
				$row = self::get_records_by_value_array($table_name, 
				array('clients_id', 'clients_versions_id'), 
				array($client_id, $version_id), 
				false); //don't check columns or order
					
				//should have a set of rows of properties for a given client and version
				
				//TODO: if we return a "false" count() returns a 1 for the number of rows!
				//DO A CHECK HERE TO MAKE IT WORK PROPERLY!!!
				
				if(is_array($row) || self::count_real_rows($row))
				{
					/**
					 * we returned a set of client_property records of property
					 * values for a specific client-version.
					 * So get the client data from 'clients'.
					 */
					$client = self::get_record_by_id(self::$table_names['clients'], $client_id);
					
					$version = self::get_record_by_id(self::$table_names['clients_versions'], $version_id);
					
					//now get all the properties of that client from 'properties' table
					
					$client_properties = array();
					
					//$prop = new GBP_PROPERTY(); //TODO: Reconsider?
					
					//TODO: check code. We create a $client_properties empty array. If there is nothing in
					//the result, we should return $client_properties=false; = RUN A TEST TO SEE IF THIS IS OK
					
					foreach($row as $client_prop)
					{
						$client_prop['client']        = $client; //TODO: check, reciprocal ids should match!!! =========================
						$client_prop['version']       = $version; 
						$client_prop['property']      = self::$PROP_OBJ->get_property($client_prop['properties_id']);
						$client_prop['searchgroup']   = self::get_record_by_id(self::$table_names['search_group'], $client_prop['searchgroup_id']);	
						$client_prop['datatype']      = self::get_record_by_id(self::$table_names['datatypes'], $property['datatype']);
						
						/**
						 *assign the array, giving its key as the property record id. This makes
						 * assignments via property_id easier
						 */
						$client_properties[$client_prop['property']['id']]  = $client_prop;
					}
					
					return $client_properties;
				}
			}
			
		return false;
	}
	
	

	/**
	 * -------------------------------------------------------------------------
	 * INSERT TYPE FUNCTIONS
	 * add new client, clients_properties, and clients_versions records
	 * -------------------------------------------------------------------------
	 */

	
	/**
	 * @method insert_new_client
	 * insert a new client record
	 * @param {Number} $client_id (normally self::$NO_RECORD)
	 * @param {String} $name name of client
	 * @param {String} $title extended name of client
	 * @param {String} $description description of client
	 * @return {Array|false} if inserted, return array id of inserted record, else falses
	 */
	public function insert_new_client($client_id, $name, $title, $description)
	{
		$table_name = self::$table_names['clients'];
		
		$row = self::get_record_by_id($table_name, $client_id);
		
		if(is_array($row))
		{
			self::$ERROR[__METHOD__][] = "duplicate client record id id=$client_id";
			return array('property_id' => self::$DUPLICATE_RECORD, 'duplicate' => 'id', 'column_name' => $client_id);
		}
		else
		{
			//insert a new client record
			
			//first, check if there is not already another client with the same name or title, if so, don't insert
			
			$result = self::get_record_id_by_name($name, $table_name);
			if($result)
			{
				self::$ERROR[__METHOD__][] = "Name ($name) already exists in client table";
				return array('property_id' => self::$DUPLICATE_RECORD, 'duplicate' => 'name', 'column_value' => $name);
			}
			
			$result = self::get_record_id_by_title($title, $table_name);
			if($result)
			{
				self::$ERROR[__METHOD__][] = "Title ($title) already exists in  client table";
				return array('property_id' => self::$DUPLICATE_RECORD, 'duplicate' => 'title', 'column_value' => $title);
			}
			
			//we have a unique record, so do the insert
			
			$result = self::insert_record_by_value_array($table_name,
				array('name', 'title', 'description'),
				array($name,  $title,  $description)
				);
			
			//return the id of the insert so we can add the new client to our client-side list
			
			if($result && self::$last_insert_id)
			{
				return array('client_id' => self::$last_insert_id, 'duplicate' => "false", 'column_value' => self::$last_insert_id);
			}
			else
			{
				self::$ERROR[__METHOD__][] = "insert error:last_insert_id:".self::$last_insert_id;
			}
		}
		
		return false;
	}
	
	
	/**
	 * @method insert_new_client_version
	 * insert a version description for a client
	 * @param {Number} $client_version_id a client_version_id, if it exists (catch duplicates)
	 * @param {Number} $client_id the client we are inserting a value for
	 * @param {Number} $searchgroup_id the searchgroup value (e.g. ancient, common)
	 * @param {DateString} $releasedate the release date, as a date string in utc format
	 * @param {String} $version_common_name name of client-version (really a number like '2.2')
	 * @param {String} $title longer name for client-version,('chrome beta')
	 * @param {String} $desc full description
	 * insert a new client-version combo
	 */
	public function insert_new_client_version($client_version_id, $client_id, $searchgroup_id, $releasedate, $version_common_name, $version, $comments)
	{
		$table_name = self::$table_names['clients_versions'];
		
		self::$ERROR[__METHOD__][] = "client_version_id:$client_version_id, client_id:$client_id, searchgroup_id:$searchgroup_id, releasedate:$releasedate, name:$version_common_name, version:$version, comments:$comments";
		
		$row = self::get_record_by_id($table_name, $client_version_id);
		
		if(is_array($row))
		{
			self::$ERROR[__METHOD__][] = "duplicate client-version record id id=$client_id";
			return array('client_id' => self::$DUPLICATE_RECORD, 'duplicate' => 'id', 'column_name' => $client_id);
		}
		else
		{
			/**
			 * insert a new client-version record
			 * first, check if there is not already another client with the same name. if so, don't insert
			 * since the record depends on client AND $version_common_name or $title, we have to check using get_records_by_value_array
			 * Otherwise, we would block having 'Chrome 0.8' and 'FF 0.8' in the table at the same time
			 */
			
			//'name' is the name of the versions, e.g., Netscape 1
			
			$result = self::get_records_by_value_array(
				$table_name,
				array('clients_id', 'versionname'),
				array($client_id, $version_common_name)
				);
			if(is_array($result) && count($result) > 0)
			{
				self::$ERROR[__METHOD__][] = "Name ($version_common_name) already exists in client-version table";
				return array('client_version_id' => self::$DUPLICATE_RECORD, 'duplicate' => 'versionname', 'column_value' => $version_common_name);
			}
			
			/**
			 * we insert the data TWICE, in order to enable a 'sparse database table' in clients-properties. If we inserted into
			 * clients-properties and back-linked, we would have to define the back-like for every version. Often, we will only
			 * have two client-property instances, e.g. MSIE1 HTML5 support = false, MSIE9 HTML5 support = true. We don't want to
			 * create the intermediate 'false' records.
			 *
			 * So, we insert into client_versions, AND insert into client_properties.
			 * the version name is 'versionname' in 'clients_versions', but is 'name' in clients_properties'
			 */
			$result = self::insert_record_by_value_array($table_name,
				array('clients_id', 'version', 'versionname', 'releasedate', 'searchgroup_id', 'comments'), 
				array($client_id,   $version,  $version_common_name,  $releasedate,  $searchgroup_id,  $comments)
				);
			
			//return the id of the insert so we can add the new client-version to our client-side list
			
			if($result && self::$last_insert_id)
			{
				/**
				 * since some of the columns we associate with client-versions are actually properties, create and insert
				 * last_insert_id is the id of the client_versions_record. We do this for several reasons
				 * It is possible that a column in clients_versions could be an ID referencing an enum table
				 * We want the GBP generator to create its output by just looking at one table.
				 * We want any user-content to only modify the clients_properties table (Admin creates all client_versions)
				 */
				
				$last_insert_id = self::$last_insert_id;
				
				//we can have identical property names in different component groups
				
				$component_id = self::get_record_id_by_name('browser', self::$table_names['components']);
				
				//the records in client_versions can ONLY use the GBP as a source.
				
				$source_id    = self::get_record_id_by_name('gbp', self::$table_names['sources']);
				
				//insert versionname into clients-properties-versions
				
				$property = self::get_unique_property_by_name('versionname', $component_id, $source_id);
				if(is_array($property))
				{
					$result = self::insert_record_by_value_array(self::$table_names['clients_properties'],
						array('components_id', 'clients_id', 'clients_versions_id', 'properties_id', 'property_value'),
						array($component_id, $client_id, $last_insert_id, $property['id'], $version_common_name)
						);
				}
				
				self::$ERROR[__METHOD__][] = $property;
				self::$ERROR[__METHOD__][] = "FIELD NAME: 'versionname', NAME: $version_common_name, PROPERTY_ID:".$property['id'].", RESULT:$result";
				
				//insert  version into clients-properties-versions
				
				$property = self::get_unique_property_by_name('version', $component_id, $source_id);
				if(is_array($property))
				{
					$result = self::insert_record_by_value_array(self::$table_names['clients_properties'],
						array('components_id', 'clients_id', 'clients_versions_id', 'properties_id', 'property_value'),
						array($component_id, $client_id, $last_insert_id, $property['id'], $version)
						);
				}
				
				self::$ERROR[__METHOD__][] = $property;
				self::$ERROR[__METHOD__][] = "FIELD NAME: 'version', VERSION: $version, PROPERTY_ID:".$property['id'].", RESULT:$result";
				
				//insert release date
				
				$property = self::get_unique_property_by_name('releasedate', $component_id, $source_id);
				if(is_array($property))
				{
					$result = self::insert_record_by_value_array(self::$table_names['clients_properties'],
						array('components_id', 'clients_id', 'clients_versions_id', 'properties_id', 'property_value'),
						array($component_id, $client_id, $last_insert_id, $property['id'], $releasedate)
						);
				}
				
				self::$ERROR[__METHOD__][] = $property;
				self::$ERROR[__METHOD__][] = "FIELD NAME: 'releasedate', RELEASE DATE: $releasedate, PROPERTY_ID:".$property['id'].", RESULT:$result";
				
				/**
				 * insert searchgroup (literal, not the id) - this demonstrates why we duplicate data in these two
				 * tables. The other data is too unique to have an id linking to an enum table
				 */
				
				$searchgroup = self::get_record_by_id(self::$table_names['search_group'], $searchgroup_id);	
				$property = self::get_unique_property_by_name('searchgroup', $component_id, $source_id);
				if(is_array($property))
				{
					
					$result = self::insert_record_by_value_array(self::$table_names['clients_properties'],
						array('components_id', 'clients_id', 'clients_versions_id', 'properties_id', 'property_value'),
						array($component_id, $client_id, $last_insert_id, $property['id'], $searchgroup['name'])
						);
				}
				
				self::$ERROR[__METHOD__][] = $property;
				self::$ERROR[__METHOD__][] = "FIELD NAME:'name', 'SEARCH_GROUP: ".$searchgroup['name'].", PROPERTY_ID:".$property['id'].", RESULT:$result";
				
				//insert comments
				
				$property = self::get_unique_property_by_name('comments', $component_id, $source_id);
				if(is_array($property))
				{
					$result = self::insert_record_by_value_array(self::$table_names['clients_properties'],
						array('components_id', 'clients_id', 'clients_versions_id', 'properties_id', 'property_value'),
						array($component_id, $client_id, $last_insert_id, $property['id'], $comments)
						);
				}
				
				self::$ERROR[__METHOD__][] = $property;
				self::$ERROR[__METHOD__][] = "FIELD NAME: 'comments' COMMENTS: $comments, PROPERTY_ID:".$property['id'].", RESULT:$result";
				
				
				return array('client_version_id' => self::$last_insert_id, 'duplicate' => "false", 'column_value' => $last_insert_id);
			}
			else
			{
				self::$ERROR[__METHOD__][] = "insert error: invalid last insert id";
			}
		}
		
		return false;
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * UPDATE TYPE FUNCTIONS
	 * adjust existing client, clients_properties, and clients_versions records
	 * -------------------------------------------------------------------------
	 */


	/**
	 * @method update_client()
	 * UPDATE a field in the client description
	 * @param {Number} $client_id id of record in 'clients' table
	 * @param {String} $field_name name of column we want to update in a row
	 * @param {String} $field_value value we want to insert into the column
	 * @raturn {Boolean} if ok, return true, else return false
	 */
	public function update_client($client_id, $field_name, $field_value)
	{
		$table_name = self::$table_names['clients'];
		
		if(isset($field_value) && $field_value !== '')
		{
			
			return self::update_row_column_value($table_name, $client_id, $field_name, $field_value);
		}
		
		return false;
	}
	
	
	/**
	 * @method update_client_search_group()
	 * update_client_property_search_group
	 * change just the search group for the 'clients_versions' table
	 * NOTE: we should only update, never insert
	 * @param {Number} $client_version_id id or record in 'client_versions' table
	 * @param {Number} $searchgroup_id id for the search group from 'search_groups' table
	 * @raturn {Boolean} if true, return true, otherwise false
	 */
	public function update_client_search_group($client_version_id, $searchgroup_id)
	{
		return self::update_client_versions($client_version_id, 'searchgroup', $searchgroup_id);
		
		//return self::update_row_column_value(self::$table_names['clients_versions'], $client_version_id, 'searchgroup_id', $searchgroup_id);
	}
	
	
	/**
	 * @method update client_versions
	 * update a specific field for a row in the 'client-versions' table
	 * NOTE: update and insert are possible
	 * @param {Number} $client_version_id id or record in 'client_versions' table
	 * @param {String} $field_name field name to update
	 * @param {String} $field_value value of row for column in $field_name
	 * @raturn {Boolean} if true, return true, otherwise false
	 */
	public function update_client_versions($client_version_id, $field_name, $field_value)
	{
		self::$ERROR[__METHOD__][] = "CLIENT_VERSION_ID:$client_version_id, FIELD_NAME:$field_name, FIELD_VALUE:$field_value";
		
		/**
		 * NOTE THAT 'version', 'clientname',, 'clientversion', 'clientversionname' 'releasedate', and 'comments' and 'searchgroup' are assumed to be defined properties
		 * in the 'properties' table. Changing their names in 'properties' would zap this
		 */
		$property_name = $field_name;
		
		self::$ERROR[__METHOD__][] = "UPDATING CLIENT-PROPERTY, name:$property_name, value:$field_value";
		
		/**
		 * clients_versions are RESTRICTED to only one source (GBP) and only one component class ('browser'),
		 * in contract 'clients_properties' can be any source and component. Since we may have the name 'name'
		 * value in properties for different sources and components (e.g. 'version' could refer to Modernizr or
		 * GBP, and could be in 'browser' or 'device') we restrict it here.
		 * get the property_id, for the source="gbp" and component="browser"
		 */
		
		//get the required source and component ids for 'clients_versions' entries
		
		$source_id    = self::get_record_id_by_name('gbp', self::$table_names['sources']);
		$component_id = self::get_record_id_by_name('browser', self::$table_names['components']);
		
		//get the named property id for the specific source and component
		
		$table_name = 'properties';
		
		$property = self::get_records_by_value_array($table_name,
				array('name', 'component_id', 'source_id'),
				array($field_name, $component_id, $source_id)
				);
		
		if(is_array($property))
		{
			$property_id = $property[0]['id'];
				
			$table_name = 'clients_versions';
			$client_version = self::get_record_by_id($table_name, $client_version_id);
			if($client_version['clients_id'] !== self::$NO_RECORD)
			{
				//update 'clients_properties' only if a 'clients_version' record also exists
				//NOTE: The routine we're calling ALSO updates 'clients_versions'
				
				self::$ERROR[__METHOD__][] = "****Updating client_property_versions, property_id:$property_id, property_value:$field_value";
				$result = self::update_client_property_versions($client_version['clients_id'], $client_version_id, $property_id, 'property_value', $field_value);
				return $result;
			}
		}
		else
		{
			self::$ERROR[__METHOD__][] = "property_id not found for name:$field_value, source_id:$source_id, component_id:$component_id";
		}
		
		return false;
	}
	
	
	/** 
	 * @method update_client_property_versions()
	 * UPDATE a single client-property-version, or INSERT a new one if not already present
	 * @param {Number} $client_id client (browser)
	 * @param {Number} $client_version_id one of the allowed versions for this client
	 * @param {Number} $property_id
	 * @param {String] $field name of field we are updating
	 * @param {Mixed} $value the value for that property (number, string, object)
	 * @return {Array|false} if ok, return array with results for JSON conversion in api.php
	 */
	public function update_client_property_versions($client_id, $client_version_id, $property_id, $field_name, $field_value)
	{
		$client_property_id = "false";
		
		self::$ERROR[__METHOD__][] = "entering update_client_property_versions, clients_id=$client_id, clients_version_id=$client_version_id, property_id=$property_id, field=$field_name, value=$field_value";
		
		//check for errors in the record
		
		$table_name = self::$table_names['clients_versions'];
		
		if(!self::check_value_in_record($table_name, $client_version_id, 'clients_id', $client_id))
		{
			self::$ERROR[__METHOD__][] = "value not found, clients_id=$client_id, clients_version_id=$client_version_id, property_id=$property_id, field=$field_name, value=$field_value";
			return false;
		}
		
		//don't update if there is no value. Records either don't exist, or they exist and have a value
		
		$table_name = self::$table_names['clients_properties'];
		$last_id = self::$NO_RECORD;
		
		//only insert a non-empty value into the db
		
		if(isset($field_value))// && $field_value !== '')
		{
			/**
			 * update 'clients_versions', if necessary
			 * if the $field_name is one of the 'special' ones duplicated in 'clients_versions',
			 * update clients_versions
			 * NOTE: We only use the GBP source, with the 'browser' component to update the 'clients_versions' table, since it duplicates just a few of
			 * the many possible values in the 'clients_properties' table.
			*/
			$property  = self::get_record_by_id(self::$table_names['properties'], $property_id, array('name', 'component_id', 'source_id'));
			$source    = self::get_record_by_id(self::$table_names['sources'], $property['source_id'], array('name'));
			$component = self::get_record_by_id(self::$table_names['components'], $property['component_id'], array('name'));
			
			/**
			 * to update 'clients_versions' we need to look at the property name. If it matches the property column name in 'clients_versions'
			 * (which is a naming convention) we update in 'clients_versions' AND 'clients_properties. It is inefficienty database programming,
			 * but makes the GBP output scripts much simpler. Other tables similar to this include 'device_versions'
			 */
			if($source['name'] == 'gbp' && $component['name'] == 'browser')
			{
				$property_name = $property['name'];
				
				switch($property_name)
				{
					case 'searchgroup':
						//we store the actual group here ('ancient', 'modern'), but need the searchgroup_id in clients_versions
						self::$ERROR[__METHOD__][] = "WARNING: updating searchgroup in a text field processor using $field_value";
						$result = self::update_row_column_value(self::$table_names['clients_versions'], $client_version_id, 'searchgroup_id', $field_value);
						break;
					case 'version':
					case 'versionname': //in 'properties' and 'clients_versions' we use 'versionname' but in 'clients_properties' we use 'name'
					case 'releasedate':
					case 'comments':
					default:
						$result = self::update_row_column_value(self::$table_names['clients_versions'], $client_version_id, $property_name, $field_value);
						break;
				
				}
				
				/**
				 * we can't consistently update the browser name from this level, we have multiple fields to update, but one
				 * browser has many properties, with many values. The client_version 'versionname' can change with each version,
				 * but the client browser 'name' cannot. So, we just exit if we somehow get 'name' here. The client-side
				 * ui is supposed to prevent this.
				 */
				if($property_name == 'name') //this would be client-name (browser-name), NOT client-version-name
				{
					self::$ERROR[__METHOD__][] = 'tried to update client (not client-version) name with $field_value';
				//	return true;
				}	
				
			}
			
			/**
			 * now, update 'clients_properties'
			 * get all the 'clients_properties' records
			 */
			
			/**
			 * ----------------------------------------------------------------------------------------
			 * ****************************************************************************************
			 * we have one remapping. versionname needs to be changed to 'name' in 'clients_properties'
			 * the property version is called 'versionname' in client_edit.php, but we need to insert
			 * this value into the 'property_value' field of 'clients_properties'
			 * ****************************************************************************************
			 * ----------------------------------------------------------------------------------------
			 */
			if($field_name == 'versionname')
			{
				$field_name = 'name';
			}
			
			self::$ERROR[__METHOD__][] = "client_id=$client_id, clients_version_id=$client_version_id, property_id=$property_id, field_name=$field_name, field_value=$field_value";
			
			$table_name = self::$table_names['clients_properties'];
			
			$row = self::get_records_by_value_array($table_name, 
				array('clients_id', 'clients_versions_id', 'properties_id','components_id'), 
				array($client_id, $client_version_id, $property_id, $property['component_id']), 
				false);
			
			if(is_array($row))
			{
				$num_records = count($row); 
				
				if(is_array($row) && $num_records == 1)
				{
					//update existing record
					self::$ERROR[__METHOD__][] = "UPDATE EXISTING id:".$row[0]['id'];
					
					/**
					 * we insert the record, then calculate which other records need to be removed
					 * in the normalize() function below
					 */
					$result = self::update_row_column_value($table_name, $row[0]['id'], 'property_value', $field_value);
					$last_id = $row[0]['id']; //save to row to normalize() from later
				}
				else
				{
					self::$ERROR[__METHOD__][] = "wrong count for client properties (should be one, found $ct)";
					return false;				
				}
			}
			else
			{
				/**
				 * insert a new 'clients_properties' record
				 */
				self::$ERROR[__METHOD__][] = "INSERTING NEW:";
				
				$result = self::insert_record_by_value_array($table_name,
					array('clients_id', 'clients_versions_id', 'properties_id', 'components_id', $field_name), 
					array($client_id, $client_version_id, $property_id, $property['component_id'], $field_value)
					);
				$last_id = self::$last_insert_id; //save the row to normalize() from later
				
			}
			
			/**
			 * In both INSERT and UPDATE operations, check the altered client-property array for
			 * redundant records, and delete redundant records if necessary. Redundant records aer
			 * adjacent versions with exactly the same value (e.g. both IE5 and IE6 support HTML, so
			 * we delete the IE6 reference) 
			 */
			self::$ERROR[__METHOD__][] = "ID IS NOW: $last_id";
			
			$redundant = self::normalize_client_properties($client_id, $property_id, $last_id);
			
			//delete records that are redundant
			
			if(is_array($redundant))
			{
				foreach($redundant as $id)
				{
					self::$ERROR[__METHOD__][] = "DELETING $id from clients_properties";
				
					self::delete_record_by_id($table_name, $id);
				
					//delete any associated references with this client-property
				
					self::$REF_OBJ->delete_all_references(self::$table_names['clients_properties'], $id);	
				}
			}
			
			return $result;
			
		}
		
		return false;
	}
	
	
	/**
	 * @method normalize_client_properties()
	 * if we have a client-property assigned to a version, we should avoid redundant values. So, if
	 * MSIE didn't support HTML5 in version 1.0, and started supporting it in version 9.0, we should
	 * only store the 1.0 and 9.0 values - we shouldn't redundantly store 'false' for versions 2.0-8.0.
	 * This algorithm analyzes the current client-property-version matrix, using the last property either
	 * inserted or updated. Then, it scans up and down from this position for redundant entries, and deletes
	 * them if they exist. If a property changes, back and forth, those changes aren't deleted.
	 * @param {Number} $client_id the id of the client in 'client_properties'
	 * @param {Number} $property_id the id of the property in 'client_properties'
	 * @param {Number} $client_version the version for which we want to insert a property value
	 * @param {Mixed} $last_client_property_id
	 */
	public function normalize_client_properties($client_id, $property_id, $last_client_property_id)
	{
		$table_name = self::$table_names['clients_properties'];
		
		//get all the clients-properties records
		
		$clients_properties = self::get_records_by_value_array($table_name, 
					array('clients_id', 'properties_id'), 
					array($client_id, $property_id), 
					false); //check columns
		
		if(is_array($clients_properties))
		{
			
			//we have a set of client-versions for a specific property for the client. So make an
			//array in memory to sort and analyze
			
			$num_records = count($clients_properties);
			
			//get the name (property = name for browser name)
			//get the property_id for 'name' as a property
			
			$name_property_id     = self::get_record_id_by_name('name', self::$table_names['properties']);      //TODO: browserName
			$version_property_id  = self::get_record_id_by_name('version', self::$table_names['properties']);   //TODO: browserVersion
			$released_property_id = self::get_record_id_by_name('releasedate',self::$table_names['properties']);
			//$comments_property_id = self::get_record_id_by_name('comments', self::$table_names['properties']); //TODO: browserComments	
			
			if($num_records > 0)
			{
				/**
				 * get each client-version record for which a value was defined, returning the
				 * 'name' and 'released' date. Sort on the released date
				 */
				$table_name = self::$table_names['clients_versions'];
				
				foreach($clients_properties as $key => $client_property)
				{
					if($client_property['properties_id'] == $name_property_id)
					{
						$name = $client_property['property_value'];
					}
					else if($client_property['properties_id'] == $version_property_id)
					{
						$version = $client_property['property_value'];
					}
					else if($client_property['properties_id'] == $released_property_id)
					{
						$released = $client_property['property_value'];
					}
				}
				
				//now, augment the array
				
				foreach($clients_properties as $key => $client_property)
				{
					////$clients_properties[$key]['name']     = $name;
					$clients_properties[$key]['name']  = $version;
					$clients_properties[$key]['releasedate'] = $released;
					////$clients_properties[$key]['comments'] = $comments;
				}
				
				/**
				 * we can't directly sort the constructed array in MySQL. So sort it here by version
				 */
				$vers_keys = array();
				
				foreach ($clients_properties as $key => $row)
				{
					$vers_keys[$key] = $row['released'];
				}
				array_multisort($vers_keys, SORT_ASC, $clients_properties);
				
				//re-key the index of the 2d arrays
				
				$clients_properties = array_values($clients_properties);
				
				self::$ERROR[__METHOD__][] = "last insert or update id was $last_client_property_id";
				self::$ERROR[__METHOD__][] = "FINAL LIST:";
				foreach($clients_properties as $key => $arr)
				{
					self::$ERROR[__METHOD__][] = $arr['id'].' '.$arr['released'];
				}
				
				//create an array to hold the list of deletions
				
				$del_ids = array();
				
				if($num_records > 1)
				{
					$pos     = -1;
					$new_val = '';
					
					//find our current numerical index in the array
					
					for($i = 0; $i < $num_records; $i++)
					{
						if($clients_properties[$i]['id'] == $last_client_property_id) //what we just inserted or updated
						{
							$pos = $i;
							$new_val = $clients_properties[$i]['property_value'];
						}
						self::$ERROR[__METHOD__][] = $clients_properties[$i]['id'];
					}
					
					self::$ERROR[__METHOD__][] = "POS:".$pos.' NEW VAL:'.$new_val;
					
					//TODO: if $last_client_property_id == 0
					//TODO: ????????????????????????????????
					
					if($pos >= 0)
					{
						$new_val = $clients_properties[$pos]['property_value'];
					 	
						for($i = $pos+1; $i < $num_records; $i++)
						{
							$curr_val = $clients_properties[$i]['property_value'];
							if($curr_val == $new_val)
							{
								$del_ids[] = $clients_properties[$i]['id'];
								break;
							}
							else
							{
								$new_val = $curr_val;
							}
						} //end of scan up to last (most recent) value
						
						$new_val = $clients_properties[$pos]['property_value'];
						
						for($i = $pos-1; $i >= 0; $i--)
						{
							$curr_val = $clients_properties[$i]['property_value'];
							if($curr_val == $new_val)
							{
								$del_ids[] = $clients_properties[$i]['id'];
							}
							else
							{
								$new_val = $curr_val;
							}
							
						} //end of scan down to zeroth (oldest) version
						
					} //end of scans in either direction from new value
					
				} //end of $num_records > 0
				
				self::$ERROR[__METHOD__][] = $del_ids;
				
				return $del_ids;
				
			}
		}
		
		return false; //remember to use '===' operator!
	}
	

	/**
	 * -------------------------------------------------------------------------
	 * DELETE TYPE FUNCTIONS
	 * delete a client with associated tables, clients_properties, and clients_versions records
	 * -------------------------------------------------------------------------
	 */

	
	/**
	 * @method delete_client
	 * delete a client, and all its versions and associated properties. This completely wipes out
	 * all references to the client (no 'orphan' properties)
	 * @param {Number} $client_id the id of the client we want to delete
	 * @return {Boolean} if deleted, return true, else false
	 */
	public function delete_client($client_id)
	{		
		//we don't have to do this if the related records are set to CASCADE
		//////self::delete_records_by_column_value(self::$table_names['clients_properties'], 'clients_id', $client_id); //all the property values for all client-versions
		//////self::delete_records_by_column_value(self::$table_names['clients_versions'], 'clients_id', $client_id);   //all the client-versions
		
		//we DO have to delete client references manually
		
		self::$REF_OBJ->delete_all_references(self::$table_names['clients'], $client_id);
		
		//delete all clients_versions references. There may be multiple versions, so a loop is needed
		
		$clients_versions_arr = self::get_records_by_value_array(self::$table_names['clients_versions'], 
				array('clients_id'),    //array of columns we are searching against
				array($client_id),      //array of values we are looking for in those columns
				false,                  //check to see if columns exist
				false);                 //sort field
		
		if(is_array($clients_versions_arr))
		{
			//we don't call delete_client_version since we are only deleting references directly. Versions delete due to
			//the CASCADE in the database itself
			
			foreach($clients_versions_arr as $client_version)
			{
				self::$REF_OBJ->delete_all_references(self::$table_names['clients_versions'], $client_version['id']);
				
				$clients_properties_arr = self::get_records_by_value_array(self::$table_names['clients_properties'],
					array('clients_versions_id'),
					array($client_version['id']),
					false,
					false);
				
				if(is_array($clients_properties_arr) && count($clients_properties_arr) > 0)
				{
					foreach($clients_properties_arr as $client_property)
					{
						self::$REF_OBJ->delete_all_references(self::$table_names['clients_properties'], $client_property['id']);
					}
				}
				
			}
		}
		
		//delete the client itself
		
		return self::delete_records_by_column_value(self::$table_names['clients'], 'id', $client_id);             //the client itself
	}

	
	/**
	 * @method delete_client_version
	 * delete a version number for a client
	 * @param {Number} $client_version_id the client-version we want to delete
	 * @return {Boolean} if deleted, return true, else false
	 */
	public function delete_client_version($client_version_id)
	{
		//we don't have to do this if the related records are set to CASCADE
		///////self::delete_records_by_column_value(self::$table_names['clients_properties'], 'clients_versions_id', $client_version_id);
		
		//we DO have to delete references manually
		//TODO: NEED A FOREACH FOR ALL CLIENTS_PROPERTIES
		
		self::$REF_OBJ->delete_all_references('clients_versions', $client_version_id);
		
		//delete any references in client_properties referencing this client-version
		
		$clients_properties_arr = self::get_records_by_value_array(self::$table_names['clients_properties'],
						array('clients_versions_id'),
						array($client_version_id),
						false,
						false);
		
		if(is_array($clients_properties_arr) && count($clients_properties_arr) > 0)
		{
			
			foreach($clients_properties_arr as $client_property)
			{
				self::$REF_OBJ->delete_all_references(self::$table_names['clients_properties'], $client_property['id']);
			}
		
			//delete the client-version itself
		
			return self::delete_records_by_column_value(self::$table_names['clients_versions'], 'id', $client_version_id);
		}
		
		return false;
	}
	
	/**
	 * ADDITIONAL UTILITIES
	 * These functions aren't standard create, update, delete but perform a task specific to
	 * the specific database tables accessed by this class.
	 */
	
	
	/**
	 * find the most recent search group for a collection of client-versions. This allows us to put wholly
	 * ancient clients in a separate file at burn-time
	 * @param {Number} $client_id id value for the client
	 * @return {String} search_group array found to be most recent for the client. 'common' and 'mobile' are
	 * assumed to be of the same rank.
	 * ---------------------------------
	 * ancient = no versions after 2007
	 * user-agents-ancient.php
	 * edge    = may be updated, but very rare in the wild (both desktop and mobile included here)
	 * user-agents-edge.php
	 * common  = significant (>1%) of browser share
	 * user-agents-common.php
	 * mobile  = significant (>1%) of mobile market share
	 * user-agents-mobile.php
	 * future  = beta or predicted browser
	 * part of user-agents-common.php or user-agents-mobile.php
	 * ---------------------------------
	 * ORDER OF RECENT-NESS: "future"->("common"|"mobile")->"edge"->"ancient"
	 */
	public function most_recent_search_group($client_id)
	{
		//get all the client-versions
		
		$client_version_arr  = self::get_client_versions($client_id);
		
		if(self::count_real_rows($client_version_arr) > 0)
		{
			$table_name = self::$table_names['search_group'];
			
			$most_recent = 'ancient';
			
			//recover the search groups into an array
			
			foreach($client_version_arr as $client_version)
			{
				//scan the array, putting the most recent search group array into output
				
				$search_group = self::get_record_by_id($table_name,
								       $client_version['id'],
								       array('name')
								       );
				switch($search_group['name'])
				{
					case 'future':
						$most_recent = 'future'; //future trumps everything
						break;
					
					case 'common':
						if($most_recent != 'future')
						{
							$most_recent = 'common'; //generally if one is 'common', there should be no 'mobile'
						}
						break;
					
					case 'ancient':
						//already pre-set
						break;
					
					case 'mobile':
						if($most_recent != 'future')
						{
							$most_recent = 'mobile'; //generally if one is 'mobile', there should be no 'common'	
						}
						break;
					
					case 'edge':
						if($most_recent != 'common' && $most_recent != 'mobile' && $most_recent != 'future')
						{
							$most_recent = 'edge';	
						}
						break;
					
					default:
						break;
				}
				
			
			//return the most recent search group array found
			
			}
		}
		
		
	}

	
};
