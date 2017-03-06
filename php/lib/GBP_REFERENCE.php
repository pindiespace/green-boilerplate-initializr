<?php

/**
 * -------------------------------------------------------------------------
 * GBP_REFERENCE.php
 * class for manipulating reference data, multiple tables
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 2013
 * @version 1.0
 *
 * -------------------------------------------------------------------------
 */

class GBP_REFERENCE extends GBP_BASE {
	
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
	 * utility to sort reference by date, used to order reference lists
	 * @param {&Array} reference to multi-dimensional array to sort
	 * @return Array is returned by reference
	 */
	public function row_sort_by_date(&$arr)
	{
		usort($arr, function($a, $b) {
		$sortable = array(strtotime($a['ref_date']),strtotime($b['ref_date']));
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
	 * @method get_all_references
	 * @param {Boolean} $list_only if true, leave out 'description' column
	 * @return {Array|false} if ok, return array of all possible search groups, otherwise false
	 */
	public function get_all_references($list_only=true)
	{
		if($list_only === true)
		{
			return self::get_all(self::$table_names['references'], array('id','url','title'));
		}
		else
		{
			return self::get_all(self::$table_names['references']);
		}
		
	}
	

	/**
	 * @method get_reference_list
	 * get a set of references associated with a specific table and item within the
	 * edit field. For example, on table 'preoperties, the item can be the property itself,
	 * or a dependency of the property.
	 * @param {String} $ref_table_name string name of table we are referencing
	 * @param {Number} $ref_table_item_id id of item (e.g. a property_id)
	 * @returns {Array|false} if ok, return array with reference records, else false
	 */
	public function get_reference_list($ref_table_name, $ref_table_item_id)
	{
		$table_name = self::$table_names['references'];
		
		$references = self::get_records_by_value_array($table_name,
				array('ref_table_name', 'ref_table_item_id'),
				array($ref_table_name, $ref_table_item_id),
				false,
				array('ref_date')
				);
		
		if(is_array($references) && count($references) > 0)
		{
			return $references;	
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
	 * -------------------------------------------------------------------------
	 * INSERT TYPE FUNCTIONS
	 * add new reference
	 * -------------------------------------------------------------------------
	 */

	
	/**
	 * @method insert_new_reference
	 * insert a new reference record
	 * @param {Number} $reference_id (normally self::$NO_RECORD)
	 * @param {String} $table_name name of table associated with the reference
	 * @param {Number} $table_record_id Id of row in the table associated with the reference
	 * @param {String} $url address of reference
	 * @param {Enum} $location which location the reference is stored (http://, ftp://)
	 * @param {String} $site_name name of website or other resource "site"
	 * @param {String} $title title of resource (e.g. article title)
	 * @param {String} $description description of resource (e.g. why it is relevant)
	 * @return {Array|false} if inserted, return array id of inserted record, else falses
	 */
	public function insert_new_reference($ref_table_name, $ref_table_item_id, $ref_date, $url, $title, $description)
	{
		$table_name = self::$table_names['references'];
		
		//check if we already have a reference along this line
		
		$rows = self::get_records_by_value_array($table_name,
				array('ref_table_name', 'ref_table_item_id', 'url', 'ref_date'),
				array($ref_table_name, $ref_table_item_id, $url, $ref_date),
				false, 
				false);
		
		
		if(is_array($rows))
		{
			return array('reference_id' => self::$DUPLICATE_RECORD, 'ref_table_name' => $ref_table_name, 'ref_table_item_id' => $ref_table_item_id, 'url' => $url, 'ref_date' => $ref_date);
		}
		
		else
		{
			//we have a unique record, so do the insert
			
			$result = self::insert_record_by_value_array($table_name,
				array('ref_table_name', 'ref_table_item_id', 'url', 'ref_date', 'title', 'description'),
				array($ref_table_name, $ref_table_item_id, $url, $ref_date, $title, $description)
				);
		}
		
		//return the id of the insert so we can add the new client to our client-side list
		
		if($result && self::$last_insert_id)
		{
			return array('reference_id' => self::$last_insert_id, 'duplicate' => "false", 'column_value' => self::$last_insert_id);
		}
		else
		{
			self::$ERROR[__METHOD__][] = "insert error:last_insert_id:".self::$last_insert_id;
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
	 * @param {Number} $reference_id id of record in 'clients' table
	 * @param {String} $field_name name of column we want to update in a row
	 * @param {String} $field_value value we want to insert into the column
	 * @raturn {Boolean} if ok, return true, else return false
	 */
	public function update_reference($reference_id, $field_name, $field_value)
	{
		$table_name = self::$table_names['references'];
		
		if(isset($field_value) && $field_value !== '')
		{
			
			return self::update_row_column_value($table_name, $reference_id, $field_name, $field_value);
		}
		
		return false;
	}
	

	/**
	 * -------------------------------------------------------------------------
	 * DELETE TYPE FUNCTIONS
	 * delete a reference with associated tables, clients_properties, and clients_versions records
	 * -------------------------------------------------------------------------
	 */

	
	/**
	 * @method delete_reference
	 * delete a reference, and all its versions and associated properties. This completely wipes out
	 * all references to the client (no 'orphan' properties)
	 * @param {Number} $reference_id the id of the client we want to delete
	 * @return {Boolean} if deleted, return true, else false
	 */
	public function delete_reference($reference_id)
	{
		return self::delete_records_by_column_value(self::$table_names['references'], 'id', $reference_id);
	}

	
	/**
	 * ADDITIONAL UTILITIES
	 * These functions aren't standard create, update, delete but perform a task specific to
	 * the specific database tables accessed by this class.
	 */
	
	
	/**
	 * @method delete_all_references
	 * delete all references associated with a specific record in another table, e.g.,
	 * all reference associate with a specific property, client, client-version. Since we are
	 * checking TWO columns, we don't use GBP_BASE
	 * @param {String} $ref_table_name name of the table with the record our references are associated with
	 * @param {Number} $ref_table_item_id id of the record in $ref_table_name associated with our references
	 */
	public function delete_all_references($ref_table_name, $ref_table_item_id)
	{
		foreach(get_defined_vars() as $key => $val){ self::$util->clean($val); } //heavy-handed security, clean anything that comes in
		
		if(isset(self::$table_names[$ref_table_name]))
		{
			$db = self::get_pdo();
			
			$table_name = self::$table_names['references'];
			
			$statement = $db->prepare("DELETE FROM `".$table_name."` WHERE ref_table_name=:tbl AND ref_table_item_id=:id");
			
			try {
				$result = $statement->execute(array('tbl' => $ref_table_name, ':id' => $ref_table_item_id));	
				return $result;
			} 
			catch(Exception $e) { 
				self::$ERROR[__METHOD__][] = $e->getMessage()." for table $table_name, column_name=$column_name, column_value=$column_value"; //return exception 
			}
		}
		else
		{
			self::$ERROR[__METHOD__][] = "delete reference:Invalid ref_table_name provided";
		}
		
		return false;
	}
	

}; //end of class
