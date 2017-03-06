<?php

/**
 * -------------------------------------------------------------------------
 * GBP_IMPORT_FULLTESTS.php
 * class for importing feature test results
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 2013
 * @version 1.0
 *
 * -------------------------------------------------------------------------
 */

class GBP_IMPORT_FULLTESTS extends GBP_BASE {
	
	/**
	 * variables
	 */
	static private $UA_ANALYZE_FILE = "../../gbp/php/gbp/ua-analyze.php";
	
	static private $MAX_MATCHES            = 3;
	static private $VERSION_MULTIPLIER     = 100;
	
	//static private $UPDATE_NONE            = "update_none";
	//static private $UPDATE_CONFIDENCE_ONLY = "update_confidence";
	//static private $UPDATE_VALUE           = "update_value";
	//static private $UPDATE_INSERT_VALUE    = "insert_value";
	//static private $UPDATE_DELETE_VALUE    = "delete_value";
	
	static private $UPDATE = array( 
					'UPDATE_NONE'             => 'UPDATE_NONE',
					'UPDATE_CONFIDENCE_ONLY'  => 'UPDATE_CONFIDENCE_ONLY',
					'UPDATE_VALUE'            => 'UPDATE_VALUE',
					'UPDATE_INSERT_VALUE'     => 'UPDATE_INSERT_VALUE',
					'UPDATE_DELETE_VALUE'     => 'UPDATE_DELETE_VALUE',
					'UPDATE_INVALID'          => 'UPDATE_INVALID',
					'CMD_CLEARED'             => 'CMD_CLEARED' //special clearstring for erased client-version object in import_fulltests()
				);

	
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
	 * @method parse_ua
	 * given a user-agent string, parse and try to find it in the database.
	 * NOTE: this implies that we have to save the headers of each user-agent event!
	 */
	public function parse_ua($user_agent)
	{
		
	}
	
	
	/**
	 * @method scan_client_db
	 * check the 'clients' database in initializr to see if we can match a client from
	 * gbp-bootstrap with one already in our clients database
	 */
	public function scan_client_db($browser)
	{
		$table_name = self::$table_names['clients'];
		
		//if the browser is unknown, return
		
		if($browser[0] == self::$UNDEFINED)
		{
			return false;
		}
		
		$client_id = self::get_record_id_by_name($browser[0], $table_name);
		if($client_id !== false)
		{
			//try to find the closest version
			
			$table_name = self::$table_names['clients_versions'];
			$version_arr = self::get_records_by_column_value($table_name, 'clients_id', $client_id, array('id', 'version', 'versionname'));
			if(is_array($version_arr) && count($version_arr) > 0)
			{
				$vers_sort = array();
				foreach($version_arr as $key => $vers)
				{
					if(is_numeric($browser[1]) && is_numeric($vers['version']))
					{
						/**
						 * compute distance as an inverse function of the version in 'clients'
						 * relative to the browser version number
						 */
						$percent = $browser[1] / $vers['version'];
						if($percent != 0) {
							if($percent > 1.0) {
							$percent = 1.0/$percent;
							}
						}
					}
					else
					{
						$percent = 0;
					}
					
					$version_arr[$key]['simscore'] = $percent;
				}
				
				//sort the array
				
				usort($version_arr, function($a, $b) {
    				if ($a['simscore'] == $b['simscore']) return 0;
    				return ($a['simscore'] < $b['simscore']) ? 1 : -1;
				});
				
				return $version_arr;
			}
			
		}
		else
		{
			self::$ERROR[__METHOD__][] = "client:".$browser[0]." not found";
		}

		return false;
	}
	
	/**
	 * -------------------------------------------------------------------------
	 * SELECT TYPE FUNCTIONS - SIMPLE
	 * basic queries with one table
	 * -------------------------------------------------------------------------
	 */
	
	
	/** 
	 * @method get_all_imports
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @returns {Array|false} if ok, return array of all possible search groups, otherwise false
	 */
	public function get_all_fulltests($list_only=true)
	{
		//get all the fulltests to import
		
		$table_name = self::$table_names['import_fulltests'];
		
		if($list_only === true)
		{
			$row = self::get_all($table_name, array('id', 'user_agent', 'referer', 'date_test'));
		}
		else
		{
			$row = self::get_all($table_name);
		}
		
		$len = count($row);
		
		/**
		 * for each row:
		 * - check for a match in our UAAnalyze sniffer
		 * - check for a match in our client-version table
		 */
		if(is_array($row) && $len > 0)
		{
			//fire up the UA_ANALYZE class in GBP proper to sniff the user-agent
			
			if(file_exists(self::$UA_ANALYZE_FILE))
			{
				require(self::$UA_ANALYZE_FILE);
				if(class_exists(UAAnalyze))
				{
					$ua_analyze = new UAAnalyze(false);
					
					for($i = 0; $i < $len; $i++)
					{
						$browser = $ua_analyze->get_ua_data($row[$i]['user_agent'], true); //default user-agent, only return browser
						$row[$i]['client'] = $browser[0];
						$row[$i]['client_version'] = round($browser[1]/100, 2);
						$row[$i]['found_rows'] = self::get_fulltest_size($row[$i]['id']);
						
						//now see if the match found by UAAnalyze is also in our 'clients' table already
						
						$client_version_match = self::scan_client_db(array($browser[0], $row[$i]['client_version']));
						
						/**
						 * get the top matches, and add them to our returned object
						 */
						if(is_array($client_version_match) && count($client_version_match) > 0)
						{
							$max_width = 0;
							
							for($j = 0; $j < self::$MAX_MATCHES; $j++)
							{
								if(isset($client_version_match[$j]))
								{
									$row[$i]['matches'][$j] = $client_version_match[$j];
								}
							}
							
						}
						else
						{
							$row[$i]['matches'] = array();
						}
					}
					
					return $row;
				}
				else
				{
					self::$ERROR[__METHOD__][] = "Could not find UAAnalyze class";
				}
			
			}
			else
			{
				self::$ERROR[__METHOD__][] = "Could not find UA_ANALYZE file";
			}
		
			//if we couldn't load UAAnalyze, provide blank fields
			
			for($i = 0; $i < $len; $i++)
			{
				$rows[$i]['client'] = 'undefined';
				$rows[$i]['client_version'] = "0";
			}
			return $row;
		}
		/**
		 * now return the augmented row
		 */
		return false;
	}
	
	
	/**
	 * @method get_fulltest
	 * get a test, and all the property results
	 * @param {Number} $fulltest_id (normally self::$NO_RECORD)
	 * @returns {Number} the number of records (tested browse features) for the given fulltest
	 */
	public function get_fulltest_size($fulltest_id)
	{
		$table_name = self::$table_names['import_fulltests_results'];
		
		$records = self::get_records_by_column_value($table_name, 'fulltest_id', $fulltest_id, false);
		if(is_array($records) && count($records) > 0)
		{
			return count($records);
		}
		return 0;
	}
	
	
	/**
	 * @method get_fulltest_results
	 * get the list of GBP properties, and the results of tests on a browser
	 */
	public function get_fulltest_results($fulltest_id)
	{
		$table_name = self::$table_names['import_fulltests_results'];
		
		$rows = self::get_records_by_column_value($table_name, 'fulltest_id', $fulltest_id, false);
		if(is_array($rows) && count($rows  > 0))
		{
			return $rows;
		}
		
		return false;
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * SELECT TYPE FUNCTIONS - COMPLEX
	 * begin complex features of references, (e.g. complimentary references)
	 * -------------------------------------------------------------------------
	 */

	
	/**
	 * @method calc_client_property_versions
	 * scan old and more recent client-property-versions, and decide if some should be deleted. It works by
	 * altering the input $client_versions_arr, inserting in directives on how to alter the client-property-versions
	 * table.
	 * @param {Array} $client_versions_arr a sorted (by version) client-property-versions list, specific to one property_id
	 * array or records identical to 'clients_properties,' specific to a client_versions_id and a property_id.
	 * @param {Number} $client_version_id incoming client_version that we want to import property values for
	 * @returns {Array} modified with actions for each client-version for that given property:
	 * - directives for INSERT, UPDATE, DELETE or NO_CHANGE. for all the records in the passed client-property-versions
	 *   array.
	 * - any changes in the num of tests for the properties, and the confidence level values in the records.
	 */
	public function calc_client_property_versions(&$client_versions_arr, $client_version_version)
	{
		
		//define the name and value of the property
		
		$name_val = $client_versions_arr[$client_version_version]['property_name']."(".$client_version_version.")";
		
		/**
		 * get the value we want to import, and see if the same value is defined anywhere
		 * in the client_versions_arr for this client. Only identical values in the client_versions_arr
		 * are relevant, since if they are in nearest-neighbor versions, we either
		 * (1) do not insert our import, since an earlier version already has the value
		 * (2) insert our object and delete a later database record with the same value
		 */
		$new_value = $client_versions_arr[$client_version_version]['property_value_import'];
		
		$old_versions = array();
		
		foreach($client_versions_arr as $key => $vers)
		{
			//valid client_property_id (from 'clients_properties' db)
			
			if(isset($vers['client_property_id']) && $vers['client_property_id'] !== self::$UNDEFINED && $vers['client_property_id'] > self::$NO_RECORD)
			{
				/**
				 * if one of the client-version arrays has a property_value defined in the database, and if
				 * that value is the same as our prospective insert value, record its key (the version, a number like '560')
				 */
				if($vers['client_property_value'] == $new_value) 
				{
					$old_versions[] = $key; //one or more values for this property are already defined for this version in the db
				}
			}
		}
		
		$len = count($old_versions);
		$neighbors = array();
		
		if($len > 0) //we aren't alone, a client-versions above or below us has the same value
		{
			//this re-indexes the array so we can use a for() loop to find nearest neighbors
			
			asort($old_versions, SORT_NUMERIC);
			
			for($i = 0; $i < $len; $i++)
			{
				if($client_version_version != $old_versions[$i])
				{
					if($old_versions[$i] < $client_version_version)
					{
						$neighbors['before'] = $old_versions[$i];
					}
					else //must be greater
					{
						$neighbors['after'] = $old_versions[$i];
					}
				}
			}
			
			/**
			 * we are only looking at neighbors that have earlier or more recent client-versions
			 * with values defined in the database the same as our import, e.g. a value of "true"
			 * for IE3 for "activex" when we are importing IE6 and have a value of "true"
			 * for "activex"
			 * 'UPDATE_NONE',
			 * 'UPDATE_CONFIDENCE_ONLY',
			 * 'UPDATE_VALUE',
			 * 'UPDATE_INSERT_VALUE',
			 * 'UPDATE_DELETE_VALUE'
			 */
			
			if(count($neighbors) > 0)
			{
				if(isset($neighbors['before']))
				{
					/**
					 * the same value exists for an EARLIER client-version-property-value.
					 * - do NOT insert our import_value
					 * - leave the earlier value alone
					 * - in other words, do nothing
					 * - 'UPDATE_NONE'
					 */					
					$client_versions_arr[$client_version_version]['CMD'] = self::$UPDATE['UPDATE_NONE'];
					$client_versions_arr[$client_version_version]['ACTION'] = "<strong>No import</strong> for ".$name_val." due to earlier value in version (".$neighbors['before'].")";
					
					/**
					 * TODO: check for error where there is a record at our position, since it shouldn't be there
					 * if there is an earlier version with the same value
					 */
				}
				
				if(isset($neighbors['after']))
				{
					/**
					 * the same value exists in a later client-version-property value
					 * - our import value is redundant
					 * - write "delete" for the later definition in the database, and replace it with ours
					 */
					$client_versions_arr[$neighbors['after']]['CMD'] = self::$UPDATE['UPDATE_DELETE_VALUE'];
					$client_versions_arr[$neighbors['after']]['ACTION'] = "Import <strong>updates</strong> ".$name_val.", also <strong>deleting redundant value</strong> for latter version (".$neighbors['after'].")";
					
					/**
					 * -insert or update our import_value, which is replacing a later value in the db
					 * -if our import_value already exists as a client_version_id, update
					 * -if our import_value has a client_version_id of "undefined" insert
					 */
					if($client_versions_arr[$client_version_version]['client_property_id'] > self::$NO_RECORD) //there is an existing client-property-version in the db at our version number
					{
						$client_versions_arr[$client_version_version]['CMD'] = self::$UPDATE['UPDATE_VALUE'];
						$client_versions_arr[$client_version_version]['ACTION'] = "Import <strong>updates</strong> ".$name_val." since record already exists ".$neighbors['after']."NOTE: this is an db error, it shouldn't be there";
					}
					else
					{
						$client_versions_arr[$client_version_version]['CMD'] = self::$UPDATE['UPDATE_INSERT_VALUE'];
						$client_versions_arr[$client_version_version]['ACTION'] = "Import <strong>inserts</strong> value for ".$name_val.", <strong>deleting redundant value</strong> for later version (".$neighbors['after'].")";
					}
				}
			}
			else
			{
				/**
				 * no neighbors (in other words, if there is a neighbor, we found ourselves)
				 * if our import value is different from the current value, AND we aren't over-writing special
				 * values (e.g. column 'version' in 'clients_versions') then either update or insert
				 */
				if($client_versions_arr[$client_version_version]['client_property_id'] > self::$NO_RECORD)
				{
					if($client_versions_arr[$client_version_version]['client_property_value'] != $client_versions_arr[$client_version_version]['property_value_import'])
					{
						$client_versions_arr[$client_version_version]['CMD'] = self::$UPDATE['UPDATE_VALUE'];
						$client_versions_arr[$client_version_version]['ACTION'] = "No neighbors, import <strong>updates</strong> ".$name_val.", since import value is different than current db value";
					}
					else
					{
						$client_versions_arr[$client_version_version]['CMD'] = self::$UPDATE['UPDATE_NONE'];
						$client_versions_arr[$client_version_version]['ACTION'] = "No neighbors, <strong>no update needed</strong>, incoming value for ".$name_val." matches existing db value";
					}
				}
				else
				{
					$client_versions_arr[$client_version_version]['CMD'] = self::$UPDATE['UPDATE_INSERT_VALUE'];
					$client_versions_arr[$client_version_version]['ACTION'] = "No neighbors, <strong>inserting</strong> value for ".$name_val. " (no record in db)";
				}
			}
		}
		else
		{
			//no old versions, so we can just insert
			
			$client_versions_arr[$client_version_version]['CMD'] = self::$UPDATE['UPDATE_INSERT_VALUE'];
			$client_versions_arr[$client_version_version]['ACTION'] = "Import <strong>inserts</strong> new value for ".$name_val." (no client-versions have defined db values)";
		}
		return true;
	}
	
	
	/**
	 * @method compare_fulltests
	 * given a fulltest id, scan import_fulltest_results for all the
	 * individual feature tests. Find the equivalent client-properties-version records,
	 * and return a matched list of the properties to import, versus those already in
	 * the database.
	 * @param {Number} $fulltest_id the id of the import
	 * @param {Number} $client_version_id the id of the client-version, found using UAAnalyze
	 * @returns {Array|false} if ok, return a matched list of the client-property-versions to
	 * import, versus those already in the database
	 */
	public function compare_fulltests($fulltest_id, $client_version_id)
	{
		//get the fulltests
		
		$table_name = self::$table_names['import_fulltests_results'];
		$fulltests_arr = self::get_records_by_column_value($table_name, 'fulltest_id', $fulltest_id, false);
		
		if(!is_array($fulltests_arr) || count($fulltests_arr) < 1)
		{
			self::$ERROR[__METHOD__][] = "Fulltest error: no fulltest properties found to import";
			return false;
		}
		
		//initialize our results array
		
		$results = array();
		
		//get the client-version associated with this import, as well as the client
		
		$table_name = self::$table_names['clients_versions'];
		$client_version = self::get_record_by_id($table_name, $client_version_id);
		$results['client_version'] = $client_version;
		
		if(!is_array($client_version))
		{
			self::$ERROR[__METHOD__][] = "Client-version error: version not found for client-version id:".$client_version_id;
			return false;
		}
		
		//make an interger, sortable version out of the real version of the client
		
		$client_version_version = intval($client_version['version']*self::$VERSION_MULTIPLIER);
		
		//if the version is somehow invalid, exit
		
		if(!is_numeric($client_version_version))
		{
			self::$ERROR[__METHOD__][] = "Client-Version Error: version for ".$client_version['versionname']."(".$client_version['version'].") cannot be converted to numeric value";
			return false;
		}
		
		//get the parent client of all the client-versions
		
		$table_name = self::$table_names['clients'];
		$client = self::get_record_by_id($table_name, $client_version['clients_id']);
		
		//if there's no client, exit
		
		if(!is_array($client))
		{
			self::$ERROR[__METHOD__][] = "Client Error: client isn't in database for the client-version (".$client_versions['versionname'].")";
			return false;
		}
		$results['client'] = $client;
		
		//now, get all the client-versions, using the found client_id
		
		$table_name = self::$table_names['clients_versions'];
		$clients_versions_arr_raw = self::get_records_by_column_value($table_name, 'clients_id', $client['id'], array('id', 'version','versionname','releasedate','clients_id'));
		if(!is_array($clients_versions_arr_raw))
		{
			self::$ERROR[__METHOD__][] = "Client-version error: no client-versions returned, even though we did get client_version:".$client_version['versionname'];
			return false;
		}
		
		$clients_versions_arr = array();
		foreach($clients_versions_arr_raw as $vers)
		{
			$clients_versions_arr[intval($vers['version']*self::$VERSION_MULTIPLIER)] = $vers;
		}
		
		//tack on the current value for the property from clients-properties-versions
		
		$table_name = self::$table_names['clients_properties'];
		foreach($fulltests_arr as $key1 => $fulltest)
		{
			$table_name = self::$table_names['properties'];
			$property_id = self::get_record_id_by_name($fulltest['property'], $table_name);
			
			//loop through client_versions, and add client-property-version data
			
			if($property_id !== false)
			{
				$table_name = self::$table_names['clients_properties'];
				
				foreach($clients_versions_arr as $key2 => $client_version)
				{
					//since we are re-using the clients_versions array, clear any CMD or ACTION values
					
					$clients_versions_arr[$key2]['CMD']    = self::$UPDATE['CMD_CLEARED'];
					$clients_versions_arr[$key2]['ACTION'] = 'ACTION_CLEARED';
					
					//add the property name and id to the clients-versions record
					
					$clients_versions_arr[$key2]['property_name'] = $fulltest['property'];
					$clients_versions_arr[$key2]['property_id']   = $property_id;
					
					//if this client-version is the one we're updating add the import result to the client-version record
					
					if($client_version['id'] == $client_version_id) //this is the version we want to alter
					{
						$clients_versions_arr[$key2]['property_value_import'] = $fulltest['result']; //this column means the record is new
					}
					else
					{
						$clients_versions_arr[$key2]['property_value_import'] = self::$UNDEFINED; //client-version is not the one we're importing
					}
					
					//get all records in 'clients_properties' for this client-version and property
					
					$client_property_version_record = self::get_records_by_value_array($table_name,
						array('clients_versions_id', 'properties_id'),
						array($client_version['id'], $property_id),
						false,
						false
					);
					
					//if there are records in $client_property_version (a value for the property is present in clients_properties) process
					
					if(is_array($client_property_version_record))
					{
						//there should only be one
						
						if(count($client_property_version_record) > 1)
						{
							self::$ERROR[__METHOD__][] = "Error: 'client_properties' table, TOO MANY records for client_property_version_record for ".$fulltest['property']." for client-version".$client_version['versionnname'];
						}
						
						$client_property_version_record = $client_property_version_record[0]; //should NOT be more than one
						$client_property_version_record['property_name'] = $fulltest['property'];
						
						//if there is a predefined property in the database for this client-version, add the result to the client-version record
						
						$clients_versions_arr[$key2]['client_property_value'] = $client_property_version_record['property_value'];
						$clients_versions_arr[$key2]['client_property_id']    = $client_property_version_record['id'];
					}
					else
					{
						//no predefined property values in the database for any client-versions
						
						$clients_versions_arr[$key2]['client_property_value'] = self::$UNDEFINED; //no record for this client-version for this property exists in db
						$clients_versions_arr[$key2]['client_property_id']    = self::$UNDEFINED; //no record_id in the database for this combo of property and client-version
					}
					
				} //end of loop throught client-versions
				
				
				/*
				 * the modified clients-versions array has been built. Now check
				 * to see if the proposed addition will require altering other records in the 'clients_properties'
				 * table, or is redundant and shouldn't be done
				 */
				self::calc_client_property_versions($clients_versions_arr, $client_version_version);
				
			} //end of property_id was valid
			else
			{
				//$fulltests specified a property name which is NOT in our db, so clear our re-used clients_versions_arr
				
				foreach($clients_versions_arr as $key => $client_version)
				{
					$clients_versions_arr[$key]['property_name']         = $fulltest['property'];
					$clients_versions_arr[$key]['CMD']                   = self::$UPDATE['UPDATE_INVALID'];
					$clients_versions_arr[$key]['ACTION']                = $fulltest['property'].' not in our DB';
					$clients_versions_arr[$key]['property_id']           = self::$UNDEFINED;
					$clients_versions_arr[$key]['property_value_import'] = self::$UNDEFINED;
					$clients_versions_arr[$key]['client_property_value'] = self::$UNDEFINED; //no record for this client-version for this property exists in db
					$clients_versions_arr[$key]['client_property_id']    = self::$UNDEFINED; //no record_id in the database for this combo of property and client-version
					
				}
				self::$ERROR[__METHOD__][] = "No property defined in db for fulltest import property name:".$fulltest['property'];
			}
			
			$results[$fulltest['property']] = $clients_versions_arr;
			
		} //end of loop through fulltests
		
		return $results;
	}
	

	/**
	 * -------------------------------------------------------------------------
	 * INSERT TYPE FUNCTIONS
	 * -------------------------------------------------------------------------
	 */

	
	/**
	 * @method commit_fulltest_results
	 * given an import, commit to the full set of tests
	 * @param {Number} $fulltest_id id of import to pull into the db
	 * @param {Number} $client_version_id the specific client-version to import, as determined from the import form
	 * @param {Array} $ignore_arr if present, a list of properties to ignore. Process from $_REQUEST in api.php
	 * @returns {String|false} if ok, return a summary string reporting the results of the insert, else false
	 */
	public function commit_fulltest_results($fulltest_id, $client_version_id, $ignore_arr)
	{
		//re-compute the fulltests
		
		$results  = self::compare_fulltests($fulltest_id, $client_version_id);
		$table_name = self::$table_names['clients_versions'];
		$results2 = array();

		if(is_array($results))
		{
			foreach($results as $property_name => $client_version)
			{
				
				if($property_name !== 'client_version' && $property_name !== 'client') //remove non-property members of this object
				{
					//loop through the client-versions
					
					
					foreach($client_version as $version => $property_import)
					{
						/**
						 * if the property_id is listed in the ignore_arr, don't process. otherwise, switch through
						 * the commands and do the requested operation
						 */
						if(!in_array($property_import['property_id'], $ignore_arr))
						{
							$results_key = $property_name."-".$version;
							$results2[$results_key] = array();
							
							$from_to = " (From:".$property_import['client_property_value']." To:".$property_import['property_value_import'].")";
							
							switch($property_import['CMD'])
							{
								case self::$UPDATE['UPDATE_NONE']:
									//no change
									//$results2[$property_name."-".$version] = self::$UPDATE['UPDATE_NONE'].$from_to;
									break;
							
								case self::$UPDATE['UPDATE_CONFIDENCE_ONLY']:
									//increment the confidence value of the 'clients_properties' record (typically unnecessary)
									$results2[$results_key][] = self::$UPDATE['UPDATE_CONFIDENCE_ONLY'].$from_to;
									break;
							
								case self::$UPDATE['UPDATE_VALUE']:
									//update the property_value in the 'clients_properties' record
									$results2[$results_key][] = self::$UPDATE['UPDATE_VALUE'].$from_to." id:".$property_import['client_property_id'];
									$row = self::get_record_by_id($table_name, $property_import['client_property_id']);
									if(is_array($row)) //we have the record
									{
										$results2[$results_key][] = "found row, updating...";
										$num_tests = $row[0]['num_tests'] + 1;
										$confidence = $row[0]['confidence'];
										$results2[$results_key][] = "num_tests:".$num_tests.", confidence:".$confidence;
										if($row[0]['property_value'] != $property_import['property_value_import']) //value mismatch, reduce confidence
										{
											if($num_tests == 0)
											{
												self::$ERROR[__METHOD__][] = "ZERO value for confidence for property ".$property_import['property_name'];
											}
											$confidence -= (1/$num_tests); //reduce confidence by inverse function
											$results2[$results_key][] = "value mismatch, confidence reduced to ".$confidence;
										}
										/*
										$res = self::update_row_by_value_array($table_name,
											$row[0]['id'],
											array('clients_id', 'clients_versions_id', 'properties_id', 'property_value', 'num_tests', 'confidence'),
											array($property_import['clients_id'], $property_import['id'], $property_import['property_id'], $property_import['property_value_import'], $num_tests, $confidence)
											);
										if($res)
										{
											$results2[$results_key][] = "ok";
										}
										*/
									}
									else
									{
										self::$ERROR[__METHOD__][] = "ERROR: tried to update value for".$property_import['property_name']." into clients_properties, expected record does not exist";	
									}
									break;
							
								case self::$UPDATE['UPDATE_INSERT_VALUE']:
									//insert a new 'clients_properties' record
									$results2[$results_key][] = self::$UPDATE['UPDATE_INSERT_VALUE'].$from_to;
									$row = self::get_record_by_id($table_name, $property_import['client_property_id']);
									if(is_array($row)) //we have the record
									{
										//ERROR, it shouldn't be there!
										
										self::$ERROR[__METHOD__][] = "tried to insert value for ".$property_import['property_name']." into clients_properties, but record already exists";
									}
									else
									{
										$results2[$results_key][] = "new row, inserting...";
										/*
										$res = self::insert_record_by_value_array($table_name,
											array('clients_id', 'clients_versions_id', 'properties_id', 'property_value'),
											array($property_import['clients_id'], $property_import['id'], $property_import['property_id'], $property_import['property_value_import'])
											);
										
										if($res)
										{
											$results2[$results_key][] = "ok";
										}
										*/
									}
									break;
							
								case self::$UPDATE['UPDATE_DELETE_VALUE']:
									//delete the 'clients_properties' record specified
									$results2[$results_key][] = self::$UPDATE['UPDATE_DELETE_VALUE'].$from_to." id:".$property_import['client_property_id'];
									$row = self::get_record_by_id($table_name, $property_import['client_property_id']);
									if(is_array($row)) //we have the record
									{
										$results2[$results_key][] = "found row, deleting...";
										/*
										$res = self::delete_record_by_id($table_name, $property_import['client_property_id');
										if($res)
										{
											$results2[$results_key][] = "ok";
										}
										*/
									}
									else
									{
										self::$ERROR[__METHOD__][] = "could not find record for ".$property_import['property_name'].", expected 'clients_properties' record id:".$property_import['client_property_id'];
									}
									break;
							
								case self::$UPDATE['UPDATE_INVALID']:
									//update is invalid, don't do it
									//$results2[$results_key][] = self::$UPDATE['UPDATE_INVALID'].$from_to;
									break;
							
								case self::$UPDATE['CMD_CLEARED']:
									//$results2[$results_key][] = self::$UPDATE['CMD_CLEARED'].$from_to;
									break;
								default:
									$results2[$results_key][] = 'UPDATE_UNKNOWN'.$from_to;
								//unknown command, do nothing
									break;
							}
							
							
							//if($property_name != $property_import['property_name'])
							//{
							//	$results2['BOGUS'][] =  "TOP NAME:".$property_name." INTERIOR NAME:".$property_import['property_name']." ID:".$property_import['property_id']." VERSION:".$version." INTERIOR VERSION:".$property_import['version']." CMD:".$property_import['CMD'];	
							//}
							//$results2[$version][$property_import['property_id']] = "TOP NAME:".$property_name." ID:".$property_import['property_id']." INTERIOR NAME:".$property_import['property_name']." VERSION:".$version." INTERIOR VERSION:".$property_import['version']." CMD:".$property_import['CMD'];
						}
					}
				}
			}
		}
		return $results2;
	}


	/**
	 * @method insert_new_fulltest
	 * insert browser results into 'import_fulltests' from a user-accessed form
	 * (not part of initializr). A way of getting full browser test input into
	 * initialzr. This functions does NOT work with the standard GBP test, only
	 * the full JavaScript test suite.
	 * @param {String} $user_agent user agent recorded for the test
	 * @param {String} $referer site referring the test
	 * @param {date} $date_test date and time of test
	 * @param {Array} $property_arr the array with the results of the fulltest
	 * @returns {Array|false} if inserted, return array id of inserted record, else falses
	 */
	public function insert_new_fulltest($user_agent, $referer, $date_test, $property_arr)
	{
		$table_name = self::$table_names['import_fulltests'];
		
		
		$dt = self::compare_dates($table_name, 'date_test');
		
		//if our request was too recent, according to the lag in self::$SECONDS_BETWEEN_QUERY, don't submit
		
		if($dt === true)
		{
			return false;
		}
		
		$result = self::insert_record_by_value_array($table_name,
			array('user_agent', 'referer', 'date_test'),
			array($user_agent, $referer, $date_test)
			);
		
		//return the id of the insert so we can add the new client to our client-side list
		
		$ct = 0;$badct = 0;
		
		if($result && self::$last_insert_id)
		{
			$table_name = self::$table_names['import_fulltests_results'];
			$fulltest_id = self::$last_insert_id;
			
			foreach($property_arr as $key => $value)
			{
				$result = self::insert_record_by_value_array($table_name,
					array('fulltest_id', 'property', 'result'),
					array($fulltest_id, $key, $value)
					);
				$ct++;
				if($result === false)
				{
					self::$ERROR[__METHOD__][] = "import insert error:last_insert_id:self::$fulltest_id, property:$key, result:$value";
					$badct++;
				}
			}
			
			if($badct === 0)
			{
				return $ct; //number of values successfully uploaded
			}
			else {
				//delete the entire entry
				
				self::delete_record_by_id($table_name, $fulltest_id);
			}
		}
		else
		{
			self::$ERROR[__METHOD__][] = "import insert error:last_insert_id:".self::$last_insert_id;
		}
		
		return false;
	}
	
	
	/**
	 * @method delete_fulltest
	 * delete a fulltest record
	 * @param {Number} $fulltest_id the id of the fulltest
	 * @returns {Boolean} if ok, return true, else false
	 */
	public function delete_fulltests_results($fulltest_id)
	{
		$table_name = self::$table_names['import_fulltests'];
		return self::delete_record_by_id($table_name, $fulltest_id);
	}
	


}; //end of class
