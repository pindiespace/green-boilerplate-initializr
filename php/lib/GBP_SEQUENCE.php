<?php

/** 
 * GBP_SEQUENCE
 * Given a list of GBP detector function dependencies, create a Node-style structure allowing 
 * dependency-dependent execution of the detectors. Also write the structure as JavaScript code 
 * providing nested callbacks to implement dependency-dependent-execution in the simplest way 
 * (i.e., no Promise object required)
 */


/** 
 * @class GNode 
 * implement a DOM-style Node structure in PHP, with parents and children. Each Node is a Detector 
 * function, and is associated with a parent GNode, and child GNodes.
 * A GNode with no parent is "unrooted"
 * GNodes are hooked into trees, and we also define a "Level of execution" in the trees
 * 
 *           GNode1                GNode2                <<< GLEVEL 0
 *          __ | __                  | 
 *      GNode3    GNode4           GNode5                <<< GLEVEL 1
 *        |          |               | 
 *      GNode6     GNode 7         GNode8                <<< GLEVEL 2
 * 
 */
class GNode {
	
	private $name = "";               //name of node
	public $component = "";           //component (parent object) of dectctor function corresponding to node
	public $parents  = array();       //parent nodes this node depends on for execution
	public $body     = array();       //special code in node body
	public $children = array();       //child nodes dependent on this node
	public $type = "";                //property, function, helper
	public $level    = 0;             //horizontal level in the tree (0 = unrooted, 1 = first horizontal level...)
	public $instance = 0;             //unique number for this node in the overall node collection
	
	//static counter for all nodes cloned from this object
	
	public static $instances = 0;
	
	
	/** 
	 * constructor
	 */
	public function __construct ($name) 
	{
		$this->name = $name;
	}
	
	
	/** 
	 * COPY CONSTRUCTOR
	 */
	public function __clone() {
		$this->instance = ++self::$instances;
	}
	
	
	/** 
	 * @method add_component
	 * add a string corresponding to the component (its name) to 
	 * the GNode
	 * @param {String} $component component name
	 */
	public function add_component($component) {
		$this->component = $component;
	}
	
	
	/** 
	 * @method add_parent
	 * add a parent object to the GNode
	 * @param {String} $name the name of the parent
	 * @param {GNode|NULL} $obj the parent object, or a string placeholder
	 * for an empty object
	 */
	public function add_parent($name, $obj=NULL) {
		if($obj != NULL)
		{
			$this->parents[$name] = $obj;
		}
		else
		{
			$this->parents[$name] = "placeholder";
		}
	}
	
	
	/** 
	 * @method add_body
	 * add the GNode body (the detector code) to the GNode
	 * @param {String} $code the code, as a text string
	 */
	public function add_body($code) {
		$this->body = $code;
	}
	
	
	/** 
	 * @method add_child
	 * add a child GNode to a GNode
	 * @param {String} $name the name of the child
	 * @param {GNode|NULL} the child GNode object, or a string placeholder
	 */
	public function add_child($name, $obj=NULL) 
	{
		if($obj != NULL)
		{
			$this->children[$name] = $obj;
		}
		else
		{
			$this->children[$name] = "placeholder";
		}
	}
	
	
	/** 
	 * @method add_type
	 * add the type to the GNode (function, property, helper)
	 * @param {String} $type the type enum(function, property, helper)
	 * function is code
	 * property is a primitive (String, Number, Date as string)
	 */
	public function add_type($type) 
	{
		$this->type = $type;
	}
	
	
	/** 
	 * @method set_level
	 */
	public function set_level($level)
	{
		$this->level = $level;
	}
	
	
	/** 
	 * @method get_component
	 * get the component this GNode is assigned to
	 * @returns {String} the component name as a string
	 */
	public function get_component()
	{
		return $this->component;
	}
	
	
	/** 
	 * @method get_name
	 * get the name of this GNode
	 * @returns {String} the name
	 */
	public function get_name()
	{
		return $this->name;
	}
	
	
	/** 
	 * @method get_instance
	 * get the unique number assigned to this GNode in the overall GNode collection
	 * @returns {Number} the number assigned to this instance
	 */
	public function get_instance()
	{
		return $this->instance;
	}
	
	
	/** 
	 * @method get_level
	 * get the level in the dependency tree. GNodes follow a tree hierarchy, and 
	 * the top, unrooted node is level 0. Its immediate children are level 1, and 
	 * grandchildren are level 2. Useful for executing several GNode detector functions 
	 * at once
	 * @returns {Number} the level of the GNode in its tree
	 */
	public function get_level()
	{
		return $this->level;
	}
	
	
	/** 
	 * @method get_type
	 * get the type of GNode
	 * @returns {enum property, function, helper} GNode type
	 */
	public function get_type()
	{
		return $this->type;
	}
	
	
};


/** 
 * @class GTree
 * build a tree of GNodes, based on their dependency relationships
 * the dependency is supplied as a list of GNode name paires in $dependency_arr
 */
class GTree {
	
	public $dependency_arr = array();       //external dependency list
	public $prop_component_arr = array();   //component for a property
	public $GNodes  = array();              //basic set of depedencies
	public $GRoots  = array();              //standalone trees, starting with an unrooted node
	public $GLevels = array();              //horizontal level of execution in trees
	
	
	/** 
	 * @constructor
	 
	 * @param {Array} $dependency_arr, 
	 * Dependency array is an array of sub-arrays with the following features:
	 * assume parent = dependency[0]
	 * child = dependency[1]
	 * type of call = dependency[2]
	 * Generated by GBP_COMPILE
	 
	 * @param {Array} $prop_component_arr an array which reverse-maps each property to 
	 * its component in the final GBP object. Needed since, in order to execute a detector 
	 * function we need to specify its component
	 * var result = component.property
	 * var res = events.domready();
	 *Generated by GBP_COMPILE
	 */
	public function __construct ($dependency_arr, $prop_component_arr=array()) 
	{
		$this->dependency_arr = $dependency_arr;
		
		if(count($prop_component_arr) > 0)
		{
			$this->prop_component_arr = $prop_component_arr;
		}
		
		//look for circular dependencies
		
		$this->detect_circular();
	}
	
	
	/** 
	 * @method detect_circular
	 * find circular dependencies in the dependency array
	 * the dependency_arr is an array of arrays, each sub-array
	 * defining a parent, then child
	 * @returns {Boolean} if a circular dependency is found, stop testing and return false, 
	 * else if all are ok in the dependency_arr return true
	 */
	public function detect_circular()
	{
		foreach($this->dependency_arr as &$arr)
		{
			$child = $arr[0];
			$len = count($arr);
				
				//look at defined parents
				
			for($i = 1; $i < $len; $i++)
			{
				$parent = $arr[$i];
				if($parent == $child)
				{
					echo "ERROR: child names itself as a parent";
					return false;
				}
				
				//now look for any arrays that start with the child
				
				foreach($this->dependency_arr as &$arr2)
				{
					if($arr2[0] == $parent) 
					{
						$len2 = count($arr2);
						for($j = 1; $j < $len2; $j++)
						{
							if($arr2[$j] == $child)
							{
								echo "ERROR: indirect circular dependency";
								echo "<pre>";
								print_r($arr);
								print_r($arr2);
								echo "</pre>";
								return false;
							}
						}
					}
				}
			}
			
		}
		
		return true;
	}
	
	
	/** 
	 * @method init_nodes
	 * initialize GNodes based on dependency array, reading the passed-in dependency 
	 * array generated by GBP_COMPILe
	 */
	public function init_nodes()
	{
		//assume parent = dependency[0], child = dependency[1], type of call = dependency[2]
		
		//create an array of the children first
		
		foreach($this->dependency_arr as &$dependency)
		{
			$this->GNodes[$dependency[1]] = new GNode($dependency[1]);
			$this->GNodes[$dependency[1]]->add_parent($dependency[0]);
			$this->GNodes[$dependency[1]]->add_component($this->prop_component_arr[$dependency[1]]);
			
			//echo "COMPONENT:".$this->prop_component_arr[$dependency[1]]."<br>";
			$this->GNodes[$dependency[1]]->add_type($dependency[2]);
		}
		
		//look for nodes that have no parent (only listed in parent field)
		
		foreach($this->dependency_arr as &$dependency)
		{
			if(!isset($this->GNodes[$dependency[0]])) 
			{
				$this->GRoots[$dependency[0]] = new GNode($dependency[0]);
				$this->GRoots[$dependency[0]]->add_component($this->prop_component_arr[$dependency[0]]);
				$this->GRoots[$dependency[0]]->add_type($dependency[2]);
				//no children
			}
		}
	}
	
	
	/** 
	 * @method compute_children
	 * based on passed-in dependency_arr, give each GNode its 
	 * required children
	 */
	public function compute_children()
	{
		foreach($this->dependency_arr as &$dependency)
		{
			if(isset($this->GNodes[$dependency[0]]))
			{
				$this->GNodes[$dependency[0]]->add_child($dependency[1]);
			}
			else if(isset($this->GRoots[$dependency[0]]))
			{
				$this->GRoots[$dependency[0]]->add_child($dependency[1]);
			}
			else
			{
				echo "ERROR:dependency not set for:".$this->GNodes[$dependency[0]]->get_name();
			}
		}
	}
	
	
	/** 
	 * @method build_tree
	 * starting with unparented root, build local tree
	 * RECURSIVE
	 * @param {GNode&} $root the GNode to add children to
	 */
	public function build_tree(&$root)
	{
		if(is_object($root)) 
		{
			foreach($root->children as $key => &$child)
			{
				$child = clone $this->GNodes[$key];
				$child->set_level($root->get_level() + 1); //set our horizontal level
				$this->build_tree($child);
			}
		}
		else
		{
			echo "ERROR: Invalid GNode passed to build_tree";
		}
	}
	
	
	/** 
	 * @method build_trees
	 * build all the trees for all unrooted GNodes. There may be more than one 
	 * "starter" GNodes. Some GNodes may be represented in multiple trees.
	 * RECURSIVE
	 */
	public function build_trees()
	{
		foreach($this->GRoots as $key => $root)
		{
			$this->build_tree($root);
		}
	}
	
	
	/** 
	 * @method build_level
	 * add individual nodes to the horizontal list of GNodes. In addition to the 
	 * GNode tree, we also maintain a list of all GNodes at each level in the tree. 
	 * This can be used to start detector execution for an entire level, and wait for 
	 * all detectors to finish before advancing to the next level
	 * RECURSIVE
	 * @param {GNode} $root an unrooted GNode (no parent node)
	 */
	public function build_level($root)
	{
		if(is_object($root))
		{
			foreach($root->children as $key => &$child)
			{
				$child_level = clone $child;
				$level = $child_level->get_level(); //set our horizontal level
				$this->GLevels[$level][$key] = $child_level;
				$this->build_level($child_level);
			}
			
		}
		else
		{
			echo "Not a child, $root in build_level";
		}
	}
	
	
	/** 
	 * @method build_levels
	 * build the global "level of execution" across all the GNode trees
	 * so we can start an entire level at once across multiple GNode trees
	 * RECURSIVE
	*/
	public function build_levels()
	{
		$this->GLevels = array();
		
		foreach($this->GRoots as $key => $root)
		{
			//the first node is computed before recursion
			
			$node = clone $root;
			$level = $node->get_level();
			$this->GLevels[$level][$key] = $node;
			
			//recursive build
			
			$this->build_level($root);
		}
	}
	
	
	/** 
	 * @method print_linear_tree
	 * pretty-print out all the GNodes we are working with
	 */
	public function print_linear_tree()
	{
		echo "<hr><h3>Linear Tree</h3><pre>\n";
		print_r($this->GNodes);
		echo "</pre>\n";
	}
	
	
	/** 
	 * @method print_roots
	 * pretty-print out all the unrooted GNodes (no parent)
	 */
	public function print_roots()
	{
		echo "<hr><h3>Roots</h3><pre>\n";
		print_r($this->GRoots);
		echo "</pre>\n";
	}
	
	
	/** 
	 * @method print_levels
	 * pretty-print out the GNodes at a particular horizontal level of 
	 * execution in the GNode trees
	 */
	public function print_levels()
	{
		echo "<hr><h3>Levels</h3><pre>\n";
		print_r($this->GLevels);
		echo "</pre>\n";
	}
};


/** 
 * @class GTree_Js
 * create JavaScript code implementing the dependency relationships in 
 * a GNode tree
 */
class GTree_JS extends GTree {
	
	public $js_str_arr     = array();
	public $norun_fns_arr  = array();
	public $used_nodes_arr = array(); //nodes already fired in callback hierarchy
	
	
	/** 
	 * @constructor
	 * @param {Array} $dependency_array an array of parwise parent, child dependencies
	 * @param {Array} $prop_component_array reverse-mapps each property in a GNode back to its 
	 * @param {Array} $norun_fns_arr array with non-rewriting functions. We don't execute them, 
	 * or write their children in a callback (just execute them)
	 * component in the final GBP object
	 */
	public function __construct ($dependency_arr, $prop_component_arr=array(), $norun_fns_arr=array()) 
	{
		parent::__construct($dependency_arr, $prop_component_arr);
		$this->norun_fns_arr = $norun_fns_arr;
	}
	
	private function make_tabs($num)
	{
		return str_repeat("\t", intval($num) + 1);
	}
	
	
	/** 
	 * @method walk_js()
	 * generate a series of nested callbacks for GBP detector functions
	 * matching the dependencies of a GNode tree
	 * RECURSIVE
	 * @param {GNode&} reference to the current GNode
	 * @param {String&} $str reference to the growing JS code string
	 * 
	 */
	public function walk_js(&$root, &$str)
	{
		if(is_object($root)) 
		{
			/** 
			 * get information about the current GNode, which 
			 * contains JS detector code
			 */
			$instance = $root->get_instance();
			$level = $root->get_level();
			$name = $root->get_name();
			$component = $root->get_component();
			
			//compute the number of tabs to format code
			
			$tabs = $this->make_tabs($level);
			
			/* 
			 * see if we've already fired the GNode detector function. If so, it should 
			 * have converted to a property
			 */
			if(in_array($name, $this->used_nodes_arr))
			{
				$used = true;
			}
			else
			{
				$used = false;
			}
			
			//only list the function once (outer) assuming it will resolve by the time we reach inner loops
			
			if(!$used)
			{
				
				/** 
				 * to build our callback tree, we need to strip any dependencies which rely on a helper that 
				 * doesn't re-write itself being executed, which are listed in $norun_fns_arr
				 * In other words, we don't have to execute this helper, and then supply a callback.
				 *
				 * Therefore, we don't write the function call, but we DO write the children, so they execute
				 * in the correct order
				 */
				if(!isset($this->norun_fns_arr[$name]))
				{
					$str .= $tabs.$component.".".$name." (callback".$instance.");\n";
					$str .= $tabs."function callback".$instance." () {\n";
				}
				
			}
			//record the firing so we don't duplicate later
			
			$this->used_nodes_arr[] = $name;
			
			foreach($root->children as $key => &$child)
			{
				//write the children, and include any special code required in the callback independent of detectors
				
				$tabs."\t".$this->walk_js($child, $str);
			}
			
			if(!$used) 
			{
				if(!isset($this->norun_fns_arr[$name]))
				{
					$str .= $tabs."};\n";
				}
			}
			//echo "Adding to string, now $str<br>";
		}
		else
		{
			echo "Not a child, $root in build_tree";
		}
	}
	
	
	/** 
	 * @method build_js
	 * build the complete "run" function for the GBP object
	 * Calls walk_js
	 * @param {GNode} $root the current GNode object we are reading in the GTree
	 */
	public function build_js($root)
	{
		//build the call
		
		$run_callbacks = "";
		
		//build the callbacks
		$count = 0;
		
		foreach($this->GRoots as $key => $root)
		{
			$this->js_str_arr[$key] = '';
			$this->js_str_arr[$key] .= "function run".$count." (callback) {\n";
			$this->js_str_arr[$key] .= "console.log(\"in run$count\");\n";
			$this->walk_js($root, $this->js_str_arr[$key]);
			$this->js_str_arr[$key] .= "};\n";
			$count_next = $count + 1;
			$run_callbacks .= "\trun".$count."();\n";
			$count++;
		}
		
		$this->js_str_arr["run"]  = "function run () {\n";
		$this->js_str_arr["run"] .= "\n\t//run dependent detectors with callbacks\n\n";
		$this->js_str_arr["run"] .= $run_callbacks;
		

		//////////////////////////////////////////
		//HANDLE UNROOTED PROPERTIES THAT DON'T HAVE ANY DEPENDENCIES
		//TODO:
		//TODO:
		//TODO:
		//TODO:
		//TODO: NEED TO CHECK IF WE HAVE A DETECTOR, SO WE DON'T DO UNNECESSARY TYPEOFS
		//TODO:
		//TODO: undefineds don't actually have to be written
		//TODO:
		//TODO: don't include undefineds when a dependency test fails
		//TODO:
		$this->js_str_arr["run"] .= "\n\t//run independent detectors\n\n";
		$unrooted = array();
		$props = array_keys($this->prop_component_arr);
		foreach($props as $prop)
		{
			if(!in_array($prop, $this->used_nodes_arr))
			{
				$unrooted[$prop] = $prop;
			}
		}
		foreach($unrooted as $un)
		{
			$prop_call = $this->prop_component_arr[$un].".".$un;
			$this->js_str_arr["run"] .= "\tif(typeof $prop_call == \"function\") $prop_call();"."\n";
		}
				
		$this->js_str_arr["run"] .= "\n\t};//end of primary run function\n\n";
		//$this->js_str_arr["run"] .= "\treturn run();\n";
		
	}
	
	
	public function get_js_str_arr()
	{
		return $this->js_str_arr;
	}
	
	/** 
	 * @method print_used_nodes
	 * pretty-print the nodes which are part of a GTree
	 */
	public function print_used_nodes()
	{
		echo "<hr><h3>USED_NODES</h3><pre>\n";
		print_r($this->used_nodes_arr);
		echo "</pre>\n";
	}
	
	
	/** 
	 * @method print_js_strs
	 * pretty-print the array with each unrooted GTree JavaScript output
	 */
	public function print_js_strs()
	{
		echo "<hr><h3>FINAL_JS_STRING</h3><pre>\n";
		print_r($this->js_str_arr);
		echo "</pre>\n";
	}
};

