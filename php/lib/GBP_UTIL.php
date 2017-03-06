<?php

/**
 * -------------------------------------------------------------------------
 * GBP_UTIL.php
 * useful, non-database utilities
 *
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 2013
 * @version 1.0
 *
 * -------------------------------------------------------------------------
 */
class GBP_UTIL {

	public function __construct() 
	{
		
	}
	
	
	/**
	 * =================================================================
	 * @method clean_str()
	 * remove hacker stuff from form fields prior to processing
	 * SHARED
	 * ==================================================================
	 */
	 public static function clean_str(&$str)
	 {
		//remove excess whitespace characters and lower-case
		
		$oldlen = strlen($str);
		
		$str = trim(strtolower($str));
		
		if(!strlen($str) && $oldlen > 0)
		{
			self::print_error(__METHOD__, "ERROR: empty string from:'$oldstr' ");
			return false;
		}
		
		/**
		 * note - this is "two-tier". For ideal screening, we would also implement
		 * Jeff Starr's 'blacklist' Apache rewrite rules
		 * strip out NULL  and other characters starting with %0 inserted between keywords
		 * null  %00 - %07
		 * bsp   %08
		 * tab   %09
		 * \n    %0A
		 * null  %0B
		 * null  %0C
		 * \r    %0D
		 * @see http://perishablepress.com/5g-blacklist-2012/
		 * @see http://psoug.org/snippet/XSS-Sanitizer-Function_17.htm
		 */
		$str = preg_replace('/\0+/', '', $str);
		$str = preg_replace('/(\\\\0)+/', '', $str);
		
		/**
		 * zap stuff written as character entities
		 * for example: mozilla/5.0 %77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D
		 */
		$str = preg_replace("/%u0([a-z0-9]{3})/i", '', $str);
		
		if(empty($str))
		{
			return true; //an empty string is not evil
		}
		
		//blast anything we missed
		
		$str = filter_var($str, FILTER_UNSAFE_RAW);
		
		//check for explicit evil tags in the url
		
		if ((preg_match('~<[^>]*script*\"?[^>]*>~i', $str))    || (preg_match('~<[^>]*object*\"?[^>]*>~i', $str))
		|| (preg_match('~<[^>]*iframe*\"?[^>]*>~i', $str)) || (preg_match('~<[^>]*applet*\"?[^>]*>~i', $str))
		|| (preg_match('~<[^>]*meta*\"?[^>]*>~i', $str))   || (preg_match('~<[^>]*style*\"?[^>]*>~i', $str))
		|| (preg_match('~<[^>]*form*\"?[^>]*>~i', $str))   || (preg_match('~<[^>]*php*\"?[^>]*>~i', $str))
			)
		{
			self::print_error(__METHOD__, "ERROR: Evil string submitted!");
				return false;
		}
		
		return true;
	}
	
	
	/** 
	 * =================================================================
	 * @method clean
	 * clean an entire array, typically a $_GET, $_POST or $_REQUEST array
	 * @param {Array} &$arr array to have its strings sanitized (passed by reference)
	 * @returns if ok true, if not ok, then false.
	 * SHARED
	 * =================================================================
	 */
	public static function clean(&$arr)
	{		
		if(is_array($arr))
		{
			$len = count($arr);
			for($i = 0; $i < $len; $i++)
			{
				if(self::clean_str($arr[$i]) === false)
				{
					return false;
				}
			}
			
			return true;
		}
		else
		{
			return self::clean_str($arr);
		}
		
		return false;
	}
	

	/** 
	 * @method str_lreplace
	 * replace the last occurence of a substring
	 * used to strip trailing commas, etc when outputing server-side 
	 * code with PHP var_export()
	 * @param {String} $search search string
	 * @param {String} $replace replace string
	 * @param {String} $subject original string
	 */
	public static function str_lreplace($search, $replace, $subject)
	{
		$pos = strrpos($subject, $search);

		if($pos !== false)
		{
			$subject = substr_replace($subject, $replace, $pos, strlen($search));
		}

		return $subject;
	}

	/**
	 * =================================================================
	 * @method generate_php_version_key
	 * generate a GBP-compatible version key from the actual browser version
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
	 * SHARED
	 * =================================================================
	 */
	public function generate_php_version_key ($raw_key)
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
	 * @method sort_by_title
	 * sorts a multi-dimensional array rows by a column in the row
	 * NOTE: Requires PHP 5.3
	 * @param {Array&} $arr incoming array, sorted in-place
	 */
	public static function row_sort_by_title(&$arr) 
	{
		usort($arr, function($a, $b) {
		$sortable = array(strtolower($a['title']),strtolower($b['title']));
		$sorted = $sortable;
		//sort($sorted);
		natsort($sorted);
		
		//if the names have switched position, return -1. Otherwise, return 1.
		
		return ($sorted[0] == $sortable[0]) ? -1 : 1;		 
		});
	}
	
	
	/**
	 * TODO: determine if we actually use this
	 */
	public static function title_sort(&$arr)
	{
		$tmp = Array(); 
		foreach($arr as &$ma)
		{
			$tmp[] = &$ma['title'];
		}
		array_multisort($tmp, $arr);
		
	}
	
	
	/**
	 * TODO: determine if we actually use this function
	 */
	public static function version_sort(&$arr)
	{
		$tmp = Array(); 
		foreach($arr as &$ma)
		{
			$tmp[] = &$ma['version'];
		}
		array_multisort($tmp, $arr);
		
	}
	
	
	/** 
	 * @method sort_by_name
	 * sorts a multi-dimensional array rows by a column in the row
	 * NOTE: Requires PHP 5.3
	 * @param {Array&} $arr incoming array, sorted in-place
	 */
	public static function row_sort_by_name(&$arr)
	{
		usort($arr, function($a, $b) {
		$sortable = array(strtolower($a['name']),strtolower($b['name']));
		$sorted = $sortable;
		natsort($sorted);
		
		//if the names have switched position, return -1. Otherwise, return 1.
		
		return ($sorted[0] == $sortable[0]) ? -1 : 1;		 
		});
		
	}
	
	
	/** 
	 * neat_r - print output from a php-style array in a javascript object format
	 */
	public function neat_r($arr, $arr_name, $quote_symbol) {
		$out = array();
		$oldtab = "    ";
		$newtab = "  ";
		
		$lines = explode("\n", print_r($arr, true));
		
		if(!isset($quote_symbol))
		{
			$quote_symbol = "'";
		}
		
		$ct  = count($lines);
		$ctr = 0;
		
		foreach ($lines as $line)
		{
	 		
			if($ctr == 0)
			{
				$line = preg_replace('/Array/', '$'.$arr_name.' = array', $line);
			}
			
			$line = preg_replace('/[\[]([a-zA-Z0-9]*)[\]](\s*=>\s*)([a-z]*)/', "'$1'$2".$quote_symbol."$3".$quote_symbol.',', $line);
			
			$line = str_replace("'',Array", 'array', $line);
			
			$line = str_replace(')', '),', $line);
			
			//$out[] = $indent . trim($line);
			$out[] = $line;
			
			$ctr++;
		}
		
		//add a 'php' declaration
		
		//array_unshift($out, '<?php');
		
		array_pop($out);
		array_pop($out);
		array_push($out, ');');
		
		$out = implode("\n", $out) . "\n";	
		
		return $out;
	}
	

	/**
	 * @function write_db_file
	 * write a database file to the disk
	 * write the output as one or more $USER_AGENT files. We have to have premission for
	 * a GBP directory in which we can write the database.
	 * @param {String} $DATA_STR data to be written
	 * @param {String} $db_dir relative path on website to the database directory for the GBP distribution
	 * @param {String} $db_filename name of output file
	 * @return {Boolean} if successful, return true, else false
	 */
	
	public function write_db_file($DATA_STR, $db_dir, $db_filename, $remove_whitespace = false)
	{
		//if compression option is active, remove whitespace
		
		if($remove_whitespace)
		{
			$DATA_STR = self::compress_str($DATA_STR);
		}
		
		//get the correct directory, confirm we have a leading slash and a trailing slash
		
		if(substr($str, 0, 1) !== '/')
		{
			$db_dir = '/'.$db_dir;
		}
		
		if(substr($str, -1) !== '/')
		{
			$db_dir .= '/';
		}
		
		$dir = $_SERVER['DOCUMENT_ROOT'].$db_dir;
		
		//$dir_contents = scandir($dir);
		//echo "<pre>DIR CONTENTS:";
		//print_r($dir_contents);
		//echo "</pre>";
		
		//echo "DIR:".$dir."<br>";
		
		if(!file_exists($dir))
		{
			echo "dir ($dir) does not exist<br>";
			return false;
		}
		
		
		if(is_file($dir)) //we can't use a file as a directorys
		{
			echo "Specified write directory ($dir) is actually a file<br>";
			return false;
		}
		
		if(!is_writable($dir)) //directory has wrong write permissions
		{
			echo "Unable to write to specified directory ($dir)<br>";
			return false;
		}
		
		//write the output file
		
		////////echo "DB FILENAME IS A $db_filename<br>";
		
		$db_filename = $dir.$db_filename;
		
		$handle = fopen($db_filename, 'w') or die('Cannot open file:  '.$db_filename."<br>"); //open file for writing ('w','r','a')...
		$bytes_written = fwrite($handle, $DATA_STR);
		fclose($handle);
		
		if($bytes_written !== false)
		{
			return true;
		}
		
		echo "Failed to write to directory with $db_filename<br>";
		
		return true;
	}



	/**
	 * @function write_to_zip
	 * Alternately, we can write to memory, and ZIP the data into a GBP distribution.
	 * @param {String} $db_dir relative path on website to the database directory for the GBP distribution
	 * @param {String} $db_filename name of output file
	 * @return {Boolean} if successful, return true, else false
	 */
	public function write_to_zip($USER_AGENTS_STR, $db_filename)
	{
	
	}
	
	
	/**
	 * @method compress_str
	 * rip out whitespace
	 * put in a ' ' to make it slightly more readable
	 * @param {String} $str incoming string needing compression
	 * @param {String} $spacer what to replace ripped-out whitespace with, typically nothing
	 * @return {String} if ok, recurn whitespace-free string, otherwise return false
	 */
	public function compress_str($str, $spacer='')
	{
		if(is_string($str))
		{
			return preg_replace('/\s+/', $spacer, $str);
		}
		else
		{
			return false;
		}
	}
	
	public function microtime_float()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
	
	/**
	 * @method get_error
	 * get and set the last status and error messages as a $_SESSION value. We need to do 
	 * this because our processing cycle is:
	 * 1. Load the form (with possible errors)
	 * 2. Run separate processing script
	 * 3. use header() directive to load a new form
	 * @return {Array} errors, written to a $_SESSION array
	 */
	public function get_error()
	{
		return $_SESSION['gbp_error'];
	}
	
	public function get_status()
	{
		return $_SESSION['gbp_status'];
	}
	
	public function set_error(&$obj)
	{
		if(method_exists($obj, "get_error"))
		{
			$_SESSION['gbp_error'] = $obj->get_error();
		}
	}
	
	public function set_status(&$obj)
	{
		if(method_exists($obj, "get_status"))
		{
			$_SESSION['gbp_statu'] = $obj->get_status();
		}
	}
		
	/** 
	 * errors
	 */
	private function print_error($fn, $msg)
	{
		echo "ERROR in $fn: $msg";	
	}	


};