<!--history of one property for a given client, all versions-->

<?php 
	//init.php must have been included earlier
	
	if(class_exists('GBP_CLIENT'))
	{
		$clt = new GBP_CLIENT;
	}
	if(class_exists('GBP_PROPERTY'))
	{
		$prop = new GBP_PROPERTY;
	}
	
	//get the values for no record (delete all) and new record
	$NONE       = $prop->get_none();
	$NO_RECORD  = $prop->get_no_record_id();
	$NEW_RECORD = $prop->get_new_record_id();
	//$SOURCE_MODERNIZR_ID  = $prop->get_source_id_by_name($SOURCE_MODERNIZR);
	//$SOURCE_CANIUSE_ID    = $prop->get_source_id_by_name($SOURCE_CANIUSE);
	
	//defined widths of table fields
	
	$prop_column_width_arr = $prop->get_column_widths('properties', array('name', 'title', 'description'));
	
	//we need this array for dependency states
	
	$depend_state_arr = $prop->get_all_dependency_states();
	
?>

<!--header for inserted form-->

<header class="form-header">
	
        <!--we add an (invisible) foreground spinner image here. Background CSS animated 
        spinners don't always appear immediately-->
	
        <div id="err-list">
		<?php
			$arr = $prop->get_error();
			if(isset($arr[0])) 
			{
				echo "<strong>ERROR:</strong>";
				print_r($arr);
			}
			else {
				echo "&nbsp;"; //just keeps the field open
			}
		?>
	</div>

	<h2 class="clearfix">GBP Create and Edit Properties:</h2>
	
</header>

<!--section containing form (in a section)-->

<section class="clearfix">

<!--form routes to a processing script, redirects back to this page-->

	<form id="gbp-property-form" method="post" action="<?php echo 'index.php?state=clientupdatehistory&status=updateproperty'; ?>">
        
<!--Specify the property for editing by component and source-->

	<fieldset id="property-list-box">
		
<!--left hand box, with list of properties-->			
<!--right hand box, with component and source-->
		
		<fieldset id="gbp-component" class="highlight-panel">
			
			<legend>Component and Source:</legend>
			
<!--source list-->
			<label for="source">Source Database:</label>
			<select id="source" name="source"> <!--onchange=ajaxChangePropertyBySource-->
           	 	<?php
				
				$sources = $prop->get_all_sources(); //no restrictions
				
				$ct = 0;
				foreach($sources as $source)
				{
					if(isset($SOURCE_PRIMARY) && $SOURCE_PRIMARY == $source['id'])
					{
						echo '<option value="'.$source['id'].'" selected="selected">'.$source['title'].'</option>'."\n";
						$ct++;
					}
					else
					{
						echo '<option value="'.$source['id'].'">'.$source['title'].'</option>'."\n";
						$ct++;
					}
				}
			?>
			</select>
			<span id="source-status" class="form-note"><?php echo "($ct)"; ?></span>
			
<!--component list-->
			<label for="component">Component:</label>
			<select id="component" name="component"> <!--onchange=ajaxChangePropertyByComponent-->
				<?php
					$components = $prop->get_all_components(); //no restrictions
					$ct = 0;
					foreach($components as $component) 
					{
						if(isset($COMPONENT_PRIMARY) && $COMPONENT_PRIMARY == $component['id'])
						{
							echo '<option value="'.$component['id'].'" selected="selected">'.$component['title'].'</option>'."\n";
							$ct++;
						}
						else
						{
							echo '<option value="'.$component['id'].'">'.$component['title'].'</option>'."\n";
							$ct++;
						}
					}
				?>
				
			</select>
			<span id="component-status" class="form-note"><?php echo "($ct)"; ?></span>
			
		</fieldset>
		
		
		<fieldset id="select-property" class="highlight-panel">
			
			<legend>Property List:</legend>
			
			<label for="property">Current List:</label>
			
			<select id="property" name="property" tabindex="1" size="20"> <!--onchange= -->
				<option value="<?php echo $NO_RECORD; ?>">New Property</option>
				<?php
				$ct = 0;
				$prop_arr = $prop->get_all_properties($SOURCE_PRIMARY, $COMPONENT_PRIMARY, true);
				foreach($prop_arr as $id => $property) 
				{
					//NOTE: use htmlentities to make sure <select> displays properly
					
					echo '<option value="'.$property['id'].'">'.htmlentities($property['title']).'</option>'."\n";
					$ct++;
				}
				?>   
			</select>
			
			<span id="property-status" class="form-note"><?php echo "($ct)"; ?></span>
			
		<div id="delete-property">
			<input type="button" name="property-delete" id="property-delete" class="subb" value="delete selected property">
		</div>
		
		</fieldset>
		
        </fieldset>
       
<!--the property form-->

       <fieldset id="property-box">
		
<!--primary property entry fields for an individual GBP property. Other non-GBP sources can't be entered here, only referenced -->
		
		<fieldset id="gbp-property" class="highlight-panel">
			
			<legend><span id="property-title">New Property</span></legend>
			
<!--primary (JavaScript) name for property-->
			
			<label for="name" accesskey="g">GBP Primary Name:</label>
			<input id="name" name="name" type="text" placeholder="GBP Name" tabindex="2" size="40" maxlength="<?php echo $prop_column_width_arr['name']; ?>" required autofocus 
				value="<?php if(isset($update_prop_arr) && isset($update_prop_arr['name'])) { echo $update_prop_arr['name']; } ?>" >
			
			<div id="options">
				
<!--is the property locked for a specific component? Some tables like 'clients_versions' can't tolerate a
change in the 'name' field of the property-->
				
				<div id="lock" class="underline">
					<label for="component-lock">Lock this Name?</label>
					<input type="checkbox" name="component-lock" id="component-lock"><span id="component-lock-status" class="mini-highlight">unlocked</span>
				</div>
				
<!--does GBP require a particular state of this property in order to function?-->
				
				<div id="gbp-required" class="underline">
					
					<label for="gbp-exe-lock">Required for GBP?</label>
					<input type="checkbox" name="gbp-exe-lock" id="gbp-exe-lock"><span id="gbp-exe-lock-status" class="mini-highlight">no</span>
					
					
					&nbsp;&nbsp;<label for="gbp-exe-lock-priority">Priority:</label>
					<?php
						$arr = $prop->get_enums('properties', 'exe_lock_priority');
						echo "<select name=\"gbp-exe-lock-priority\" id=\"gbp-exe-lock-priority\" disabled=\"disabled\">\n";
						
						$ct = count($arr); $ct2 = $ct - 1;
						for($i = 0; $i < $ct; $i++)
						{
							$value = $arr[$i];
							if($i != $ct2)
							{
								echo "<option value=\"$value\">$value</option>\n";	
							}
							else
							{
								echo "<option value=\"$value\" selected=\"selected\">$value</option>\n";
							}
						}
						
						echo "</select>\n";
						echo "<span class=\"form-note\">(High = 1, Low = $ct)</span>";
					?>
				</div>
				
				
				
			</div>
			
<!--auto-generate CSS name and PHP name into the next fields via JavaScript-->
			
			<label for="title" accesskey="t">Title (descriptive name):</label>
			<input id="title" name="title" type="text" placeholder="title" tabindex="3" size="40" maxlength="<?php echo $prop_column_width_arr['title']; ?>" required 
				value="<?php if(isset($update_prop_arr) && isset($update_prop_arr['title'])) { echo $update_prop_arr['title']; } ?>" >
			
<!--long description of property-->
			
			<label for="description" accesskey="d">Description: <span class="mini-highlight"><a id="property-reference" class="greyed-link" href="#">ref</a></span></label>
            <!-- onclick="ajaxGetPropertyReference('properties', GBPInitializr.getCurrentProperty());"-->
			<textarea id="description" name="description" rows="7" cols="40" tabindex="4" maxlength="<?php echo $prop_column_width_arr['description']; ?>"><?php if(isset($update_prop_arr) && isset($update_prop_arr['description'])) { echo $update_prop_arr['description']; } ?></textarea>
			<div class="underline"></div>
			
<!--datatype of property-->
			
			<label for="datatype" accesskey="d">Datatype</label>    
			<select name="datatype" id="datatype" tabindex="5">
				<?php
					$datatypes = $prop->get_all_datatypes();
					foreach($datatypes as $row)
					{
						echo '<option value="'.$row['id'].'">'.$row['title'].'</option>'."\n";
					}
				?>
			</select>
			
<!--where we detect the property, discovery table-->   
			
			<label for="discovery" accesskey="y">Best Discovery (second choice if not in database):</label>
			<select name="discovery" id="discovery" tabindex="6">
				<option value="<?php echo $NO_RECORD; ?>"><?php echo $NONE; ?></option>
				<?php
					$disc_arr = $prop->get_all_discovery_modes();
					foreach($disc_arr as $row)
					{
						echo '<option value="'.$row['id'].'">'.$row['title'].'</option>'."\n";
					}
				?>           
			</select>
			
		</fieldset>
            
<!--equivalent property in Modernizr database-->
		
		<fieldset id="gbp-alt-source" class="highlight-panel">
            
		<legend>Alternate Sources</legend>
			
			<div id="alternate-libs">
				
				<label for="modernizr" accesskey="m">Modernizr Name:</label>
				<select id="modernizr" name="modernizr" tabindex="7">
					<option value="<?php echo $NO_RECORD; ?>"><?php echo $NONE; ?></option>
					<?php		
						$prop_arr  = $prop->get_all_properties_by_name($SOURCE_MODERNIZR, false, true);				
						foreach($prop_arr as $row)
						{
							echo '<option value="'.$row['id'].'">'.$row['name'].'</option>'."\n";
						}  
					?>       
				</select>
				
<!--equivalent JSON file in Caniuse database-->
				
				<label for="caniuse" accesskey="c" tabindex="8">CaniUse Name:</label>
				<select id="caniuse" name="caniuse">
					<option value="<?php echo $NO_RECORD; ?>" selected="selected"><?php echo $NONE; ?></option>
					<?php
						$prop_arr = $prop->get_all_properties_by_name($SOURCE_CANIUSE, false, true);
						$trans_arr = $prop->get_translation_by_name($update_prop_arr['name'], $SOURCE_CANIUSE);
						foreach($prop_arr as $row)
						{  				
							echo '<option value="'.$row['id'].'">'.$row['name'].'</option>'."\n";
						}  
					?>                      
				</select>
				
			</div> <!--end of alternate libs (show/hide) -->
			
			<span id="alternate-libs-status">Not available</span>
                   
		</fieldset><!--end of alternate libs fieldset-->
            
<!--dependency-->
		
		<fieldset id="gbp-dependency" class="highlight-panel">
			
			<legend>Dependencies</legend>
                                    
			<label for="dependency" accesskey="d">New Dependency:</label>
			<select name="dependency" id="dependency" tabindex="9">
				<option value="<?php echo $NO_RECORD; ?>" selected="selected"><?php echo $NONE; ?></option>
				<?php
					$depend_arr = $prop->get_all_properties($SOURCE_PRIMARY, 0, true);
						
					$depend_exclude = $update_prop_arr['dependency'];
					$keys = array_keys($depend_exclude);
					
					foreach($depend_arr as $id => $row)
					{	
						echo '<option value="'.$row['id'].'">'.htmlentities($row['title']).'</option>'."\n";
					}
					$depend_arr = array();
				?>                   
			</select>
			
			
<!--make a table showing current dependencies-->
			
			<table id="dependency-table">
				<thead>
					<tr>
						<th>Dependency</th>
						<th>Value</th>
						<th class="center">Action</th>
					</tr>
				</thead>
				
				<tbody id="dependency-value">
				
				</tbody>
				
			</table>
                     
		</fieldset><!--end of dependency fieldset-->
            
	</fieldset><!--end of fieldset for entire property form-->
  

<!--submit buttons moved into property fieldset-->

	</form><!--end of property form-->
        
</section><!--end of section containing form-->
       
<!--footer for form-->

<footer>
	<p>
		<!--footer for form-->
	</p>
</footer>

<!--form specific javascript-->
<!--when a browser choice is made, fetch title and description-->

<script>
	
	/** 
	 * -------------------------------------------------------------------------
	 * methods that get current form control values, filled in by the
	 * initial PHP load
	 * -------------------------------------------------------------------------
	 */
	
	
	/**
	 * get a pre-computed list of dependency states
	 * convert PHP array into JSON object
	 * @return {Object literal} JS object literal with all possible dependency states
	 */
	function getDependencyStates() {
		var response;
		<?php
			if(isset($depend_state_arr)) {
				
				echo 'response = '.json_encode($depend_state_arr).';'; //array with object literals
			}
		?>
		return response;
	}


	/**
	 * get alternative sources
	 * convert PHP array into JSON object
	 * @return {Object} JS object literal with all possible sources
	 */
	function getSources() {
		var response;
		<?php
			if(isset($sources)) {
				
				echo 'response = '.json_encode($sources).';'; //array with object literals
			}
		?>
		
		return response;
	}
	
	/**
	 * get component list
	 */
	function getComponents() {
		var response;
		<?php
			if(isset($components)) {
				
				echo 'response = '.json_encode($components).';'; //array with object literals
			}
		?>
		
		return response;
	}
	
	/**
	 * get allowed text field sizes for property text (name, title, description)
	 * these are computed in GBP_BASE directly from the table, so we can vary columns in the db as necessary
	 */
	function getPropertyFieldSizes() {
		var response = [];
		<?php
			//$prop_column_width_arr is set at the top of this page in PHP
			foreach($prop_column_width_arr as $key => $value)
			{
				echo "response['".$key."'] = $value; ";
			}
			echo "\n";
		?>
		
		return response;
	
	}
	
	/**
	 * getSourceId
	 * get the ID of a source record from the source name
	 * @return {Number|false} record_id of source
	 */
	function getSourceId(sourceName) {
		var sourceArr = getSources();
		for (var i in sourceArr) {
			console.log("getSourceId() - at position "+i);
			if (sourceArr[i].name == sourceName) {
				return sourceArr[i].id;
			}
		}
		
		return false;
	}
	
	/**
	 * get references for an data item on the page
	 */
	function ajaxGetPropertyReference (tableName, itemId) {
		
		console.log("in ajaxGetPropertyReference");
		
		if(itemId == <?php echo $NEW_RECORD; ?>) {
			return false;
		}
		
		//get the references for this property
		
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_reference_list&tnm=' + tableName + '&iid=' + itemId, function (response) {
			
			var JSONObject = GBPInitializr.processJSON(response, ["apiresult"]);
			
			if(JSONObject) {		
				console.log("returned a reference");
				var refArr = [];
				for(var i in JSONObject) {
					refArr[i] = JSONObject[i];
				}
				
				//get the name of the property reference, and don't show the window for unsaved properties
				
				console.log("ITEM ID IS:"+itemId + " and NEW RECORD is:"+<?php echo $NEW_RECORD; ?>);
				GBPInitializr.showMessage("opening property references");
				var selText = GBPInitializr.getCurrentSelectText(GBPInitializr.getElement("property"));
				return GBPInitializr.showRefWin(refArr, tableName, itemId, selText, 'reference-window');
			}
			else {
				console.log("no reference returned");
			}
			
		}, //end of callback
		
		'get'); //end of ajaxRequest()
	}
	
	/** 
	 * ------------------------------------------------------------------------- 
	 * trigger the change in the property list, based on data source
	 * ------------------------------------------------------------------------- 
	 */
	function ajaxChangePropertyBySource(val) {
		
		console.log("ajaxChangePropertyBySource() - entry");
		var sourceElem = GBPInitializr.getElement('component');
		var srcField   = GBPInitializr.getElement("alternate-libs");
		var srcStatus  = GBPInitializr.getElement("alternate-libs-status");
		
		//if we're not processing GBP, don't allow the alternate source to show
		
		var srcId = GBPInitializr.getCurrentSelect("source");
		
		//get the recordId for 'gbp' (primary data source
		
		if (srcId == getSourceId('gbp')) {
			srcField.style.display = "block";
			srcStatus.style.display = "none";
		}
		else {
			srcField.style.display = "none";
			srcStatus.style.display = "block";
		}
		
		return ajaxChangePropertyByComponent(sourceElem);
	}
	
	
	/** 
	 * ------------------------------------------------------------------------- 
	 * trigger the change the property list, based on component selection
	 * ------------------------------------------------------------------------- 
	 */
	function ajaxChangePropertyByComponent() {
		
		console.log("ajaxChangePropertyByComponent() - entry");
		
		var value = GBPInitializr.getCurrentComponent();
		var sid   = GBPInitializr.getCurrentSource();
		
		if(!value || !sid) {
			console.log("ERROR: ajaxChangePropertyByComponent() - error in reading select menu(s), component:"+value+" source:"+source);
			return;
		}
		
		/**
		 * if we select this control when on 'new property, warn the user 
		 */
		
		GBPInitializr.showSpinner('spinner-win');
		
		//clear the form
		
		clearPropertyForm();
		
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_property_by_component&sid=' + sid + '&cid=' + value, function (response) {
			
			var selPropElem = GBPInitializr.getElement('property');
			var selContents = '<option value="<?php echo $NEW_RECORD; ?>">New Property</option>\n';
			
			/** 
			 * this first level of the returned JSONObject does not have any predictable keys (they are id values)
			 * so we don't provide the third parameter to processJSON. if nothing comes back it is also OK - it just 
			 * means that the source doesn't have defined properties
			 *
			 * for this reaon we REMOVE ["apiresult"], so it doesn't interfere in the for...in loop
			 * we also send 'encodeHTMLChars()' into the select. The PHP script uses .htmlEntities() to encode the
			 * incoming property title so it is read properly. NOTE: the console() object in Opera and Google show
			 * what's been inserted into the <select> as <> symbols, not &lt; or &gt; - but the character entities are
			 * what is really in there
			 */
			var JSONObject = GBPInitializr.processJSON(response,["apiresult"]);
			if(JSONObject) {		
				var ct = 0;
				for (var key in JSONObject) {
					var obj = JSONObject[key];
					if(obj.id && obj.title) {
						selContents += '<option value="' + obj.id + '">' + obj.title.encodeHTMLChars() + '</option>\n';
						ct++;
					}
				}
				
				//(re) set the 'property' pulldown to default 'new' property
				
				var result = GBPInitializr.setSelOptions(selPropElem, selContents); //for IE compatibility
				
				//list property status in text field
				
				GBPInitializr.getElement("property-status").innerHTML = '('+ct+')';
				
				/**
				 * make a SECOND Ajax call to update the property list based on component
				 * so we DON'T hideSpinner() yet
				 */
				ajaxShowPropertyForm(selPropElem);
			}
			else {
				GBPInitializr.setSelOptions(selPropElem, selContents);
				GBPInitializr.getElement("property-status").innerHTML = "No properties defined";
				GBPInitializr.hideSpinner('spinner-win');
			}
			
			
		}, //end of callback
		
		'get'); //end of ajaxRequest()
		
	}
	
	
	/** 
	 * ------------------------------------------------------------------------- 
	 * display information for a property
	 * ------------------------------------------------------------------------- 
	 */
	function ajaxShowPropertyForm(selPropElem) {
		
		console.log("ajaxShowPropertyForm() - entry");
		
		//set the title of the property fieldset (we use encodeHTMLChars in case a <> slipped in) .encodHTMLChars()
		
		var gbpSelText = GBPInitializr.getCurrentSelectText("property");
		if (gbpSelText) {
			GBPInitializr.getElement("property-title").innerHTML = GBPInitializr.getCurrentSelectText("property").encodeHTMLChars();
		}
		
		var pid = GBPInitializr.getCurrentProperty();			
		var sid = GBPInitializr.getCurrentSource();
		
		console.log("ajaxShowPropertyForm() - property  title:"+GBPInitializr.getCurrentSelectText("property")+" id:"+pid);
		
		//check for a new property. Default selections in select pulldowns are always zero
		
		if(pid == <?php echo $NO_RECORD; ?>) {
			clearPropertyForm();     //new property?
			//document.getElementById("property").selectedIndex=<?php echo $NEW_RECORD; ?>;
			hidePropertyReference();    //reference for the property
			hidePropertyDeleteButton(); //button for deleting property to the db
			GBPInitializr.hideSpinner('spinner-win');
			return;
		}
		
		console.log("ajaxShowPropertyForm() - getting property by id:"+pid);
		
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_property_by_id&pid=' + pid, function (response) {
			
			GBPInitializr.hideSpinner('spinner-win');
			
			//parse the JSON response. Remove "apiresult" and confirm that "name" is a property of the returned object
			
			var JSONObject = GBPInitializr.processJSON(response,["apiresult"],["name"]);
			if(JSONObject) {
				
				//we got a property back in JSON. So start filling in datatype, name, title description
				
				GBPInitializr.setSelectByOptionValue("datatype", JSONObject.datatype.id);
				
				//fill in the text fields
				
				GBPInitializr.getElement("name").value           = JSONObject.name;
				GBPInitializr.getElement("title").value          = JSONObject.title;
				GBPInitializr.getElement("description").value    = JSONObject.description;
				
				//lock the name field if the name is reserved (e.g. used in 'clients_versions')
				
				lockNameField(JSONObject.component_lock);
				
				//lock or unlock the priority field, if property value required for GBP operation (e.g. try...catch)
				
				var exeElement = GBPInitializr.getElement("gbp-exe-lock");
				var exeStatus  = GBPInitializr.getElement("gbp-exe-lock-status");
				if (JSONObject.exe_lock == 0) {
					exeElement.checked  = false;
					console.log("updating status");
					exeStatus.innerHTML = "no";
				}
				else {
					exeElement.checked  = true;
					console.log("updating status");
					exeStatus.innerHTML = "yes";
				}
				
				//set the priority level dropdown menu
				
				var priorityElement = GBPInitializr.getElement("gbp-exe-lock-priority");
				GBPInitializr.setSelectByOptionValue(priorityElement, JSONObject.exe_lock_priority);
				if (exeElement.checked == true) {
					
					if (priorityElement !== "undefined") {
						priorityElement.disabled = false;
					}
					console.log("exe_lock_priority is:"+JSONObject.exe_lock_priority);
				}
				else {
					priorityElement.disabled = true;
				}
				
				//add dependency
				
				var tbl          = GBPInitializr.getElement('dependency-table');
				var tblBody      = GBPInitializr.getElement('dependency-value');
				var tblContents  = "";
				var depend1      = false;
				
				/**
				 * a fast click through the properties menu can stack up
				 */
				GBPInitializr.clearTableRows(tblBody);
				
				if(JSONObject.dependency) {
					
					console.log("ajaxShowPropertyForm() - evaluating dependencies");
					
					//list of allowed dependency states, as JS array
					
					var dependencyStateArr = getDependencyStates();
					
					//JSONObject.dependency is an Array, so create an object to pass to our table-maker
					
					var len = JSONObject.dependency.length;
					var dep = {};
					for(var i = 0; i < len; i++) {
						dep[i] = JSONObject.dependency[i];
					}
					
					//create the dependency table
					
					createDependencyTableColumns(dep, tbl, tblBody, 'row-highlight', len-1);
					
					if(len) { //only select if we have a value, otherwise "none"
						depend1 = JSONObject.dependency[len-1].id;
					}
					
				}
				
				//(re)set dependency
				
				GBPInitializr.setSelectByOptionValue("dependency", depend1);
				
				//set discovery
				
				GBPInitializr.setSelectByOptionValue("discovery", JSONObject.discovery.state_id);
				
				//Modernizr equivalent
				
				GBPInitializr.setSelectByOptionValue("modernizr", JSONObject.alt_source.modernizr.alt_property_id);
				
				//Caniuse equivalent
				
				GBPInitializr.setSelectByOptionValue("caniuse", JSONObject.alt_source.caniuse.alt_property_id);
				
				//allow the loaded property to be deleted, flag if dependencies
				
				showPropertyDeleteButton();
				showPropertyReference();
				
				GBPInitializr.showMessage("property:"+JSONObject.name);
				
			}
			else {
				clearPropertyForm();     //new property?
				hidePropertyReference();
				hidePropertyDeleteButton();
			}
			
			GBPInitializr.hideSpinner('spinner-win');
			
		}, //end of callback
		
	'get'); //end of ajaxRequest()
	
	}
	
	
	/**
	 * @method createDependencyTableColumns
	 * create the columns <td>'s for the dependency table
	 * as a string (ajaxShowPropertyForm) or as a row object (dynamic update with a new dependency)
	 * @param {DOMObject} obj object with properties for controls
	 * @param makeElement if true, create table dynamically
	 * @param row if makeElement is true, row should be a DOMElement table row attached to a table already
	 * @return {String|DOMTableObject} either a string representing the row, or the DOMObject for the rows
	 */
	function createDependencyTableColumns(tblObj, tbl, tblBody, highlightClass, highlightNum) {
		
		var tdStart, ct = 0;
		
		for (var i in tblObj) {
			
			var obj = tblObj[i];
			var dependencyStateArr = getDependencyStates();
			var dependency         = createDependencyStateSelect(dependencyStateArr, obj.id, obj.state.id);
			var delButt            = '<input type="button" class="subb" name='+obj.id+' id='+obj.id+' value="delete" onclick="ajaxDeleteDependency(this)">';
			
			var row = tblBody.insertRow(-1); //this insert will go into <tbody>, always last
			
			//title
			
			var cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass;
			}
			cell.innerHTML = obj.title;
				
			//dependency
				
			cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass;
			}
			cell.innerHTML = dependency;
				
			//delete button
				
			cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className += highlightClass + ' center';
			}
			else {
				cell.className += 'center';
			}
			cell.innerHTML = delButt;
			
			ct++;
		}
		
	}
	
	
	/**
	 * create a pulldown menu with dependency state for a parent property_id
	 * menu is created as pulldown, in text
	 * @param {Array} dependencyStateArr list of all allowed dependency states
	 * @param {Object} a dependency object returned by get_property()
	 */
	function createDependencyStateSelect(dependencyStateArr, propertyId, stateId) {
		
		//create the pulldown
		
		var dependencyState = '<select name="dependency-state" data-gbp-dependency-state="' + propertyId + '">';
		var len = dependencyStateArr.length;
		for(var i = 0; i < len; i++) {
			var id = dependencyStateArr[i].id;
			var title = dependencyStateArr[i].title;
			dependencyState += '<option value="' + id + '"';
			if(id == stateId) {
				dependencyState += ' selected="selected"';
			}
			dependencyState += '>' + title + '</option>\n';
		}
		
		dependencyState += '</select>';
		
		return dependencyState;
	}
	

	/**
	 * @method ajaxDeleteProperty()
	 * delete an existing property
	 * @param {DOMObject} delButt a 'delete input button
	 */
	function ajaxDeleteProperty(delButt) {
		console.log("ajaxDeleteProperty() - delButt is a " + delButt + " try get data-gbp-property ");
		console.log('ajaxDeleteProperty() - .getAttribute:' + typeof delButt.getAttribute);
		var id = delButt.getAttribute('data-gbp-property');
		console.log("ajaxDeleteProperty() - deleting current property, id:"+id);
		
		if(id) {
			
			var pid = GBPInitializr.getCurrentProperty();
			
			if(pid != id) {
				console.log("ERROR: ajaxDeleteProperty() - mismatch of property_ids in ajaxDeleteProperty");
				return false;
			}
			
			if (pid != <?php echo $NO_RECORD; ?>) {
				
				GBPInitializr.showSpinner('spinner-win');
				
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=delete_property&pid=' + pid, function (response) {
					
					console.log("ajaxDeleteProeprty() - resetting screen");
					
					var name = GBPInitializr.getElement('name');
					console.log("ajaxDeleteProperty() - name is "+ name.value);
					GBPInitializr.showMessage('deleted property ' + name.value);
					GBPInitializr.hideSpinner('spinner-win');
					
					GBPInitializr.deleteSelectOptionByValue('property', pid);
					
					//delete the select option (should reset to new)
					//update number
					
					var numProperties = GBPInitializr.getElement('property').length;
					numProperties -= 1; //don't count 'new property' in list
					
					console.log("ajaxDeleteProperty() - numProperties:" + numProperties);
					
					if (numProperties > 0) {
						var propertyStatus = GBPInitializr.getElement('property-status');
						propertyStatus.innerHTML = '('+numProperties+')'; //subtracted two above, ignore 'new property'
						hidePropertyReference();
						hidePropertyDeleteButton();
						GBPInitializr.setSelectByOptionValue('property', <?php echo $NO_RECORD; ?>);
						
					}
					else {
						console.log("ERROR: ajaxDeleteProperty() - deleted zero property");
						return false;
					}
					
					//clear the form
					
					clearPropertyForm();
				},
				
				'get'); //end of ajaxRequest()
				
			}
			else {
				console.log("ERROR: ajaxDeleteProperty() - tried to delete the 'no property' button, shouldn't be visible");
				return false;
			}
			
			return true;
			
		}
		
		return false;
	}
	
	
	/**
	 * @method ajaxDeleteDependency
	 * delete dependency just for 'delete' buttons
	 * @param {DOMObject button} delButt delete button as a dom object
	 */
	function ajaxDeleteDependency(delButt) {
		
		var pid  = GBPInitializr.getCurrentProperty();
		var prid = delButt.id; //value of parent dependency property_id
		var tbl_column = delButt.id;
		
		GBPInitializr.showSpinner('spinner-win');
		
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=delete_dependency&pid=' + pid + '&prid=' + prid, function (response) {
			
			var JSONObject = GBPInitializr.processJSON(response,["apiresult"]);
			if(JSONObject) {
				
				//now, delete the appropriate row in the table
				
				GBPInitializr.deleteTableRow(delButt);
				
				/**
				 * highlight the topmost dependency, and move the select
				 * element to this option. Otherwise, if there are no rows,
				 * set the select element to 'none'
				 */
				var tblBody  = GBPInitializr.getElement('dependency-value');
				if (tblBody.rows.length) {
					var oldRowButton = tblBody.rows[tblBody.rows.length-1].getElementsByTagName('input')[0];
					console.log("oldrowbutton:"+oldRowButton);
					GBPInitializr.unHighlightTableRows(tblBody, 'row-highlight', 'row-unhighlight');
					GBPInitializr.highlightTableRow(oldRowButton, 'row-highlight', 'row-unhighlight');
					GBPInitializr.setSelectByOptionValue('dependency', oldRowButton.name);
					GBPInitializr.showMessage('dependency deleted');
				} //end of tblBody.rows.length > 0
				else {
					//set select to "none"
					
					console.log("ajaxDeleteDependency() - setting to no dependencies");
					GBPInitializr.setSelectByOptionValue('dependency', '<?php echo $NONE; ?>');
					GBPInitializr.showMessage('no dependencies');
				}
				
			}
			else {
				console.log("ajaxDeleteDependency() - no JSON object returned");
				return false;
			}
			
			GBPInitializr.hideSpinner('spinner-win');
			
		}, //end of callback
		
		'get'); //end of ajaxRequest()
		
		return true;
	}
	

	/** 
	 * ------------------------------------------------------------------------- 
	 * update database on property change
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/** 
	 * read the value of the select field, and update the database. It may 
	 * be necessary to update other fields at the same time
	 */
	function getSelectFieldValue(target) {
		
		var pid  = GBPInitializr.getCurrentProperty();
		var cid  = GBPInitializr.getCurrentComponent();
		var sid  = GBPInitializr.getCurrentSource();
		var fldv = GBPInitializr.getCurrentSelect(target); //we use target, rather than target.name, since some elements don't have an id
		var tbl_column = false;
		
		GBPInitializr.showSpinner('spinner-win');
		
		switch(target.name) {
			
			case "component":
				console.log("getSelectFieldValue() - component changed")
				return ajaxChangePropertyByComponent();
				break;
			
			case "source":
				console.log("getSelectFieldValue() - source changed");
				return ajaxChangePropertyBySource();
				break;
			
			case "property":
				console.log("getSelectFieldValue() - redraw property")
				return ajaxShowPropertyForm();
				if (document.setActive) {
					GBPInitializr.getElement('name').setActive(); //set the first text field as active, without focus/blur events ONLY from here
				}
				break;
			
			case "gbp-exe-lock-priority":
				tbl_column = 'exe_lock_priority';
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_property_field&pid=' + pid + '&fldn=' + tbl_column + '&fldv=' + fldv, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response,[]["apiresult"]);
					
					//column_value returned is the record_id of the datatype record in the db
					
					if(GBPInitializr.validServerInsert(fldv, JSONObject.column_value)) {
						GBPInitializr.showMessage("updated required priority to:"+GBPInitializr.getCurrentSelectText(target));
					}
					else {
						console.log("ERROR: getSelectFieldValue() - required priority returned from server did not match,"+"fldv:"+fldv+", JSON:"+ JSONObject.column_value);
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
				}, //end of callback
				
				'get'); //end of ajaxRequest()
				
				break;
			 
			case "datatype":
				tbl_column = 'datatype_id';
				
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_property_field&pid=' + pid + '&fldn=' + tbl_column + '&fldv=' + fldv, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response,[]["apiresult"]);
					
					//column_value returned is the record_id of the datatype record in the db
					
					if(GBPInitializr.validServerInsert(fldv, JSONObject.column_value)) {
						GBPInitializr.showMessage("updated datatype to:"+GBPInitializr.getCurrentSelectText(target));
					}
					else {
						console.log("ERROR: getSelectFieldValue() - datatype returned from server did not match,"+"fldv:"+fldv+", JSON:"+ JSONObject.column_value);
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
				}, //end of callback
				
				'get'); //end of ajaxRequest()
				
				break;
				
			case "discovery":
				tbl_column = 'state_id';
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_discovery_field&pid=' + pid + '&fldn=' + tbl_column + '&fldv=' + fldv, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response,[],["apiresult"]);
					
					//column_value returned is the record_id of the discovery record assigned to this property
					
					if(GBPInitializr.validServerInsert(fldv, JSONObject.apiresult)) {	
						GBPInitializr.showMessage("updated detection to:"+GBPInitializr.getCurrentSelectText(target));
					}
					else {
						console.log("ERROR: getSelectFieldValue() - discovery mode returned from server did not match, "+"fldv:"+fldv+", JSON:"+ JSONObject.apiresult);
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
					}, //end of callback
					
				'get'); //end of ajaxRequest()
				
				break;
				
			case "modernizr":
				tbl_column = 'alt_property_id';
				var asid = getSourceId("modernizr");
				console.log("getSelectFieldValue() modernizr test, asid:"+asid);
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_translation_field&pid=' + pid + '&sid=' + sid + '&asid=' + asid + '&fldn=' + tbl_column + '&apid=' + fldv, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response,[],["apiresult"]);
					
					//column_value returned is the record_id of modernizr source, so we look at that rather than 'apiresult'
					
					if(GBPInitializr.validServerInsert(fldv, JSONObject.column_value)) {
						GBPInitializr.showMessage("updated alt-source modernizr to:"+GBPInitializr.getCurrentSelectText(target));
					}
					else {
						console.log("ERROR: getSelectFieldValue() - modernizr alt source returned from server did not match, "+"fldv:"+fldv+", JSON:"+ JSONObject.column_value);
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
					}, //end of callback
					
				'get'); //end of ajaxRequest()
				
				break;
				
			case "caniuse":
				tbl_column = 'alt_property_id';
				var asid = getSourceId("caniuse");
				console.log("getSelectFieldValue() - asid:"+asid);
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_translation_field&pid=' + pid + '&sid=' + sid + '&asid=' + asid + '&fldn=' + tbl_column + '&apid=' + fldv, function (response) {
					
					
					var JSONObject = GBPInitializr.processJSON(response,[],["apiresult"]);
					
					//column_value returned is the record_id of the caniuse source, so we look at that rather than 'apiresult'
					
					if(GBPInitializr.validServerInsert(fldv, JSONObject.column_value)) {
						GBPInitializr.showMessage("updated alt-source caniuse to:"+GBPInitializr.getCurrentSelectText(target));
					}
					else {
						console.log("ERROR: getSelectFieldValue() - caniuse alt source returned from server did not match, "+"fldv:"+fldv+", JSON:"+ JSONObject.column_value);
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
					}, //end of callback
					
				'get'); //end of ajaxRequest()
				
				break;
				
			case "dependency":
				console.log("getSelectFieldValue() - adding to dependency table");
				tbl_column = 'state_id';
				
				/**
				 * When we insert a new dependency, it should
				 * default default to 'true_if_parent_true', which means we need to grab the "TRUE"
				 * as a dependency default from the objects and apply it to dpv1 here. It was going to
				 * false, so it was zapping things when we tried to create a dependency.
				 */
				var dpvl = '<?php echo $prop->get_dependency_state_id_by_name(true); ?>';
				
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_dependency_field&pid=' + pid + '&prid=' + fldv + '&fldn=' + tbl_column + '&fldv=' + dpvl, function (response) {
					
					//note that we don't strip or restrict the result, since it should be an insert or update
					
					
					var JSONObject = GBPInitializr.processJSON(response,[],["apiresult"]);
					
					if(JSONObject.apiresult) { //in the case of an insert, we should get back the id of the record
						
						//make a new table row
						
						GBPInitializr.showMessage("added new dependency to property:"+GBPInitializr.getCurrentSelectText(target));
						console.log("getSelectFieldValue() - dependency updated, adding it to the table");
						
						var tbl     = GBPInitializr.getElement("dependency-table");
						var tblBody = GBPInitializr.getElement("dependency-value");
						
						//remove row-select, replace with row-unhighlight
						
						GBPInitializr.unHighlightTableRows(tblBody, 'row-highlight', 'row-unhighlight');
						
						//grab the "delete" button in the row, if it is present. If not, add a row
						
						var oldRowButton = GBPInitializr.getElement(fldv);
						if (!oldRowButton) {
							
							//row not present (post-load Ajax call), insert
							
							if (JSONObject.dependency) {
								var obj      = {};
								obj.id       = JSONObject.dependency.parent_id;
								obj.title    = JSONObject.title;
								obj.state    = JSONObject.dependency.state;
								
								//put this inside an object, so we can loop once in createDependencyTableColumns
								
								var tblObj = {'0':obj};
								createDependencyTableColumns(tblObj, tbl, tblBody, 'row-highlight', 0);
								
								//get the row button
								//get the last cell, and get the button in it
								
								GBPInitializr.showMessage('new dependency:'+obj.title+'('+obj.name+')');
							}
							else {
								//user set the menu to "none", all dependencies were erased
								
								GBPInitializr.setTBody(tbl, '<tr><td></td><td></td><td></td></tr>');
							}
						}
						else {
							//row already present (first load of property by selecting Property menu)
							console.log("getSelectFieldValue() - row already present, don't insert");
							
						} //end of oldRow test
						
					}
					else {
						console.log("ERROR: getSelectFieldValue() - error in dependency update, pid=" + pid + "prid="+ fldv + "fldn=" + tbl_column + "fldv=" + dpvl);
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
					}, //end of callback
					
				'get'); //end of ajaxRequest()
				break;
				
			case "dependency-state":
				
				tbl_column = 'state_id';
				
				//prid must be extracted from data- field
				
				var prid = target.getAttribute('data-gbp-dependency-state');
				console.log("getSelectFieldValue() - select dependency-state target is:"+prid);
				
				//fldv must be gotten from the select tag that put us here
				
				fldv = GBPInitializr.getCurrentSelect(target);
				
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_dependency_field&pid=' + pid + '&prid=' + prid + '&fldn=' + tbl_column + '&fldv=' + fldv, function (response) {
					
					//console.log("getSelectFieldValue() - dependency state changed:" + response);
					var JSONObject = GBPInitializr.processJSON(response,[],["apiresult"]);
					
					if (JSONObject) {
						GBPInitializr.showMessage('dependency state updated to:'+GBPInitializr.getCurrentSelectText(target));
						//can't do a GBPInitializr.validServerInsert() here since we can pass 'true' or 'false' instead of the actual dependency state names
						return true;
					}
					else {
						console.log("ERROR: getSelectFieldValue() - unable to update dependency-state, pid=" + pid + "prid=" + prid + "fldn=" + tbl_column + "fldv=" + fldv);
						return false;
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
					}, //end of callback
					
				'get'); //end of ajaxRequest()
				
				break;
			
			default:
				GBPInitializr.hideSpinner('spinner-win');
				GBPInitializr.showMessage("unknown field("+target.name+", not updated");
				console.log("ERROR: getSelectFieldValue() - unknown field name:" + target.name);
				tbl_column = false;
				break;
			
		} //end of switch
		
	
	} //end of function
	 
	 
	/**
	 * @method getTextFieldValue
	 * update the text field for the property. If we have a new property, and we have
	 * entered the minimum amount of information, insert a new record.
	 * We send to the server, and get back
	 * a result record with
	 * id: id of updated record
	 * column_name: name of column updated
	 * column_value: value of column updated
	 * test for mismatch, in case the server rejected the uploaded string
	 * @param {DOMObject}target the text field that fired an update message
	 * @param {Boolean} sync (optional) if true, run the Ajax call synchronously. This is used
	 * when a user left a 'dirty' field and tried to leave the page
	 */
	function getTextFieldValue(target, sync) {
		
		var pid  = GBPInitializr.getCurrentProperty();
		
		//remove whitespace from ends of all fields
		
		target.value = target.value.trim();
		
		//value of textfield, current id
		
		var fldv = target.value; 
		var tbl_column = false;
		var nm, title, desc;
		
		//sync flag, force to 'false' unless it is true
		
		if (!sync) {
			sync = false;
		}
		
		//check if no property defined
		
		var newProp = false;
		if(pid == <?php echo $NEW_RECORD; ?>) {
			newProp = true;
		}
		
		//switch through possible target fields
		
		switch(target.name) {
		
		case "name":
			tbl_column = "name";
			var val = GBPInitializr.processName(fldv);
			
			if (val) {
				if (fldv != val) {
					if(!GBPInitializr.getConfirm("The entered Name has to be altered to GBP standard. Do you accept?")) {
						GBPInitializr.showMessage("invalid name field");
						return false;
					}
					GBPInitializr.getElement('name').value = val;
				}
				fldv = val;
				GBPInitializr.showMessage("name ok");
			}
			else {
				if(newProp) {
					GBPInitializr.showMessage("no property defined");
				}
				else {
					GBPInitializr.showMessage("invalid name");
				}
				
				return false;
			}
			break;
			
		case "title":
			tbl_column = "title";
			var val = GBPInitializr.processTitle(fldv);
			if (val) {
				if (fldv != val) {
					if (!GBPInitializr.getConfirm("The entered title has to be altered to GBP standard. Do you accept?")) {
						GBPInitializr.showMessage("invalid title field");
						return false;
					}
					GBPInitializr.getElement('title').value = val;
				}
				fldv = val;
				GBPInitializr.showMessage("title ok");
			}
			else {
				if(newProp) {
					GBPInitializr.showMessage("no property defined");
				}
				else {
					GBPInitializr.showMessage("invalid title");
				}
				
				return false;
			}
			break;
			
		case "description":
			tbl_column = "description";
			var val = GBPInitializr.processDescription(fldv);
			if (val) {
				fldv = val;
				GBPInitializr.showMessage("description ok");
			}
			else {
				GBPInitializr.showMessage("invalid description");
				return false;
			}
			break;
			
		default:
			tbl_column = false;
			console.log("ERROR: getTextFieldValue() - text field name in getTextFieldValue() is:"+target.name);
			return false;
			break;
		}
		 
		/**
		 * if the updated field is OK, then check if we are ready to submit. We don't submit unless
		 * the minimum required fields are present:
		 * - name
		 * - title
		 * - description
		 * if the record id == 0, we insert a new record. otherwise, we update the existing record
		 * once we've done this, we can update other areas
		 */
		if(checkComplete()) {
			
			if (pid == <?php echo $NO_RECORD; ?> ) {
				
				if(!GBPInitializr.getConfirm("Are you sure you want to create a new property?")) {
					return false;
				}
				
				GBPInitializr.showMessage('inserting record');
				GBPInitializr.showSpinner('spinner-win');
				
				/**
				 * if the info has never been submitted (property_id=0) then submit the
				 * entire record for insertion
				 */
				var cid   = GBPInitializr.getCurrentComponent();
				var sid   = GBPInitializr.getCurrentSource();
				var dtyp  = GBPInitializr.getCurrentSelect('datatype');
				
				/**
				 * only one target among, name, title, description is updated. So check
				 * nm, title, desc, and update those that weren't defined yet
				 */
				nm    = GBPInitializr.getElement('name').value;
				title = GBPInitializr.getElement('title').value;
				desc  = GBPInitializr.getElement('description').value;
				
				//check for a disabled 'name' field. Enable it for submission
				
				var lastLock = GBPInitializr.getElement('name').disabled;
				GBPInitializr.getElement('name').disabled = false;
				
				//read property required and priority fields
				
				//ajax request
				
				GBPInitializr.showSpinner('spinner-win');
				
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=insert_new_property&pid=' + pid + '&cid=' + cid + '&sid=' + sid + '&dtyp=' + dtyp + '&nm=' + nm + '&title=' + title + '&desc=' + desc, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response,[],["apiresult"]);
					
					if (JSONObject) {
						
						//reset the name field
						GBPInitializr.getElement('name').disabled = lastLock;
						
						//if we tried to put in a duplicate record (name or title) it was
						//rejected by the server
						
						if (JSONObject.duplicate == 'name') {
							alert("duplicate name");
							GBPInitializr.showMessage("Error: duplicate property name already in database");
							console.log("ERROR: getTextFieldValue() checkComplete - duplicate name already in database");
							clearPropertyForm();
							GBPInitializr.hideSpinner('spinner-win');
						}
						else if (JSONObject.duplicate == 'title') {
							alert("duplicate title");
							GBPInitializr.showMessage("ERROR: getTextFieldValue() checkComplete - duplicate property title already in database");
							clearPropertyForm();
							GBPInitializr.hideSpinner('spinner-win');
						}
						else {
							console.log("getTextFieldValue() - successful property insert");
							
							if (JSONObject.column_value) {
								var newOption = GBPInitializr.insertSelectOption('property', JSONObject.column_value, title);
								GBPInitializr.sortSelect('property', <?php echo $NO_RECORD; ?>, <?php echo $NEW_RECORD; ?>);
								GBPInitializr.setSelectByOptionValue('property', newOption.value);
								
								//update number of properties
								
								var numProperties = GBPInitializr.getElement('property').length;
								numProperties--; //don't count 'new property' option
								var propertyStatus = GBPInitializr.getElement('property-status');
								propertyStatus.innerHTML = '('+numProperties+')';
								
								//update onscreen title
								
								document.getElementById('property-title').innerHTML = newOption.text;
								
								//highlight the property reference and delete
								
								showPropertyReference();
								showPropertyDeleteButton();
								
							}
							
						}
					}
					else {
						console.log("ERROR: getTextFieldValue() - server did not return a JSON response after inserting new property");
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
					}, //end of callback
					
				'get', sync); //end of ajax
			} //end of insert new record
			else if (tbl_column) {
				
				//update an old record, with a short delay before starting the spinner
				
				GBPInitializr.showMessage('updating record');
				GBPInitializr.delay(GBPInitializr.showSpinner, 700, 'spinner-win');
				
				/**
				 * otherwise, we have an existing property. We just update the
				 * form field that changed
				 */
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_property_field&pid=' + pid + '&fldn=' + tbl_column + '&fldv=' + fldv, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response,[],["apiresult"]);
					
					if(JSONObject) {
						
						console.log("getTextFieldValue() - using JSONObject to update select list with field "+ JSONObject.column_name + "=" + JSONObject.column_value + " id="+pid+" newid="+JSONObject.id);
						
						//sorting the select lists is too slow here...
						
						switch (JSONObject.column_name) {
							case "name":
								if(GBPInitializr.validServerInsert(fldv, JSONObject.column_value)) {
									GBPInitializr.showMessage("property name updated to:"+JSONObject.column_value);
								}
								else {
									GBPInitializr.showMessage("failed to update name:"+JSONObject.column_value); //.encodeHTMLChars()
									console.log("ERROR: getTextFieldValue() - name mismatch, original:'"+fldv+"' returned:"+JSONObject.column_value);
								}
								break;
								
							case "title":
								if(GBPInitializr.validServerInsert(fldv, JSONObject.column_value)) {
									var valTitle = JSONObject.column_value; //.encodeHTMLChars();
									console.log("getTextFieldValue() - encoding HTML characters, valTitle:"+valTitle);
									GBPInitializr.updateSelectOption(GBPInitializr.getElement('property'), JSONObject.id, valTitle);
									GBPInitializr.showMessage("property title updated to:"+valTitle);
								}
								else {
									GBPInitializr.showMessage("failed to update title:"+JSONObject.column_value);
									console.log("ERROR: getTextFieldValue() - title mismatch, original:'"+fldv+"' returned:"+JSONObject.column_value+" TITLE:"+valTitle);
								}
								break;
									
							case "description":
								if(GBPInitializr.validServerInsert(fldv, JSONObject.column_value)) {
									GBPInitializr.showMessage("property description updated");
								}
								else {
									GBPInitializr.showMessage("failed to update description");
									console.log("ERROR: getTextFieldValue() - description mismatch, original:'"+fldv+"' returned:'"+JSONObject.column_value+"'");
								}
								break;
								
							default:
								console.log("ERROR: getTextFieldValue() - attempted to update unknown field name:"+tbl_column);
								showMessage("unknown field name ("+tbl_column+"), not updated");
								break;
								
						} //end of switch
								
					} //end of if
					else { //no JSON object
						console.log("ERROR: getTextFieldValue() - updating one field in record, no JSONObject returned");
						return false;
					}
					
					GBPInitializr.hideSpinner('spinner-win');
					
				}, //end of update function
					
				'get', sync); //end of ajaxRequest()	
				
			}
			else {
				console.log("ERROR: record_id present but no tbl_column");
			}
		} //end of checkComplete, form is ready for submission
		else {
			GBPInitializr.showMessage("property data incomplete");
			console.log("getTextFieldValue() - form with new property not ready to submit");
		}
		
	} //end of function
	
	
	/**
	 * get a value from a checkbox
	 */
	function getCheckBoxValue(target) {
		
		console.log("getCheckBoxValue() - entered");
		if (checkComplete()) {
			
			switch (target.name) {
				
				case 'component-lock':
					
					GBPInitializr.showMessage('updating component-lock');
					GBPInitializr.showSpinner('spinner-win');
					
					var pid = GBPInitializr.getCurrentProperty();
					var tbl_column  = 'component_lock';
					var fldv = '0';
					
					var component_lock = GBPInitializr.getElement(target.name);
					if (component_lock.checked) {
						fldv = '1'; //Boolean true stored as 'tinyint' in MySQL
					}
					else {
						fldv = '0';
					}
					
					GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_property_field&pid=' + pid + '&fldn=' + tbl_column + '&fldv=' + fldv, function (response) {
						
						var JSONObject = GBPInitializr.processJSON(response,[]["apiresult"]);
						if (JSONObject) {
							
							lockNameField(JSONObject.column_value);
						}
						
						GBPInitializr.hideSpinner('spinner-win');
						
					}, //end of update function
					
					'get'); //end of ajaxRequest()	
					break;
				case 'gbp-exe-lock':
					
					GBPInitializr.showMessage('updating exe-lock');
					GBPInitializr.showSpinner('spinner-win');
					
					//if the box has just been checked, activate the priority select field,
					//and read it. Send both to the server
					
					var pid = GBPInitializr.getCurrentProperty();
					var tbl_column = 'exe_lock';
					var fldv = '0';
					
					var exe_lock = GBPInitializr.getElement(target.name);
					var exeStatus = GBPInitializr.getElement('gbp-exe-lock-status');
					var exe_lock_priority = GBPInitializr.getElement('gbp-exe-lock-priority');
					
					if (exe_lock.checked) {
						
						fldv = '1';
						
						//activate priority select
						exe_lock_priority.disabled = false;
						exeStatus.innerHTML = "yes";
					}
					else {
						fldv = '0';
						
						//deactivate priority select
						exe_lock_priority.selectedIndex = 0;
						exe_lock_priority.disabled = true;
						exeStatus.innerHTML = "no";
						
					}
					var lock_priority_value = GBPInitializr.getCurrentSelectText(exe_lock_priority);
					
					GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_exe_required_field&pid=' + pid + '&fldn=' + tbl_column + '&fldv=' + fldv + '&priorn=' + 'exe_lock_priority'+'&priorv=' + lock_priority_value, function (response) {
						
						var JSONObject = GBPInitializr.processJSON(response,[]["apiresult"]);
						if (JSONObject) {
							
						}
						
						GBPInitializr.hideSpinner('spinner-win');
						
					}, //end of update function
					
					'get'); //end of ajaxRequest()	
					break;
				default:
					break;
			}
		}
	}
	 
	 
	/**
	 * get a value from a radio button
	 */
	function getRadioSetValue(target) {
		console.log("getRadioSetValue() - entry");
		if (checkComplete()) {
			
			switch (target.id) {
				//case
			}
		}
	}
	 
	 
	/**
	 * get a value from a pushbutton
	 */
	function getButtonValue(target) {
		console.log("getButtonValue() - entry");
		switch (target.id) {
			//case
		}
		
	}


	/** 
	 * -------------------------------------------------------------------------
	 * toggle values based on state
	 * ------------------------------------------------------------------------- 
	 */

	
	/**
	 * message when field is disabled
	 */
	function disabledMsg() {
		GBPInitializr.showPoppup("this field must keep this name for the program to function correctly");
	}
	
	var lockCt = 0; //global for setting and unsetting 
	
	/**
	 * lock or unlock the 'name' field
	 * @param {Number} state if true, lock the field, otherwise unlock it
	 */
	function lockNameField(state) {
		
		if (state === 1 || state === "1" || state === true) {
			console.log("lockNameField() - setting lock to:"+state);
			if (lockCt > 0) {
				console.log("lockNameField() - removing extra event");
				GBPInitializr.removeEvent('name', 'click', disabledMsg, false);
			}
			GBPInitializr.getElement("component-lock").checked = true;
			GBPInitializr.getElement("component-lock-status").innerHTML = 'Locked for '+ GBPInitializr.getCurrentSelectText('component');
			GBPInitializr.getElement('name').disabled = true;
			GBPInitializr.getElement('name').className = 'disabled';
			GBPInitializr.showMessage('Name field locked for this property');
			lockCt++;
		}
		else {
			console.log("lockNameField() - setting lock to:"+state);
			GBPInitializr.getElement("component-lock").checked = false;
			GBPInitializr.getElement("component-lock-status").innerHTML = 'unlocked';
			GBPInitializr.getElement('name').disabled = false;
			GBPInitializr.getElement('name').className = 'enabled';
			GBPInitializr.showMessage('Name field unlocked for this property');
			lockCt--;
		}	
	}
	
	
	/**
	 * showPropertyDeleteButton
	 * make the property button visible, and set its data id to
	 * the current property_id
	 */
	function showPropertyDeleteButton() {
		var del = GBPInitializr.getElement("property-delete");
		del.style.display = "block";
		del.setAttribute('data-gbp-property', GBPInitializr.getCurrentSelect('property'));
	}
	
	
	function hidePropertyDeleteButton() {
		GBPInitializr.getElement("property-delete").style.display = "none";
	}
	
	/**
	 * showPropertyReference
	 * make the property reference visible
	 */
	function showPropertyReference() {
		var ref = GBPInitializr.getElement('property-reference');
		ref.className = 'active-link';
	}
	
	
	function hidePropertyReference() {
		var ref =  GBPInitializr.getElement('property-reference');
		ref.className = 'greyed-link';
	}
	
	
	/**
	 * if we're entering a new property, check to see if it has the minimal features before submitting
	 * field widths are written into the form via PHP from the database, so they match
	 * - name
	 * - title
	 * - description
	 * @returns {Boolean} if text is in the required fields return true, else return false
	 */
	function checkComplete() {
		
		console.log("checkComplete() - entry");
		
		if(GBPInitializr.getElement("name").value != "" && 
			GBPInitializr.getElement("title").value   != "" && 
			GBPInitializr.getElement("description").value  != "") {
			
			//TODO: CHECK FIELD WIDTHS
			
				return true;	
		}
		
		return false;
	}
	
	
	/** 
	 * ------------------------------------------------------------------------- 
	 * initialization
	 * ------------------------------------------------------------------------- 
	 */


	/** 
	 * we need to clear when resetting the component or the source
	 */
	function clearPropertyForm() {
		
		//required fields
		
		GBPInitializr.getElement("description").value = "";
		GBPInitializr.getElement("name").value        = "";
		GBPInitializr.getElement("title").value       = "";
		GBPInitializr.getElement("description").value = "";
		
		//locked fields should be unlocked
		
		lockNameField(false);
		
		//optional fields
		
		GBPInitializr.getElement("datatype").options[0].selected   = "selected";		
		GBPInitializr.getElement("discovery").options[0].selected  = "selected";
		GBPInitializr.getElement("modernizr").options[0].selected  = "selected";
		GBPInitializr.getElement("caniuse").options[0].selected    = "selected";
		GBPInitializr.getElement("dependency").options[0].selected = "selected";
		GBPInitializr.setTBody('dependency-value', '<tr><td></td></td><td></td><td></td></tr>');
		
		//set the select list to a new record, and get back the corresponding text
		
		document.getElementById("property").selectedIndex=<?php echo $NEW_RECORD; ?>;
		var newName = GBPInitializr.getCurrentSelectText('property');
		
		//apply the name to the property window
		
		GBPInitializr.getElement('property-title').innerHTML = newName;
		GBPInitializr.showMessage(newName);
	}
	
	
	/**
	 * add events to the property form (separate from dependency sub-table)
	 * note that this is different from the client-property-history delegate
	 */
	function addFormDelegateEvents(frmId) {
		
		var frm = GBPInitializr.getElement(frmId);
		
		//button clicks
		
		GBPInitializr.addEvent(frm, "click", function(e) {
			var target = GBPInitializr.getEventTarget(e);
			if (target.type) { //restrict to form fields
				if(target.type.indexOf('radio') !== -1) {
					getRadioSetValue(target); //control clicked on
				}
				else if (target.type.indexOf('checkbox') !== -1) {
					getCheckBoxValue(target);
				}
				else if (target.type.indexOf('button') !== -1) {
					//getButtonValue();
				}
				else if (target.type.indexOf('select') !== -1) {
					
					/**
					 * old IE and Opera don't work properly with 'change' events attached via addEvent,
					 * even when the event is attached. It DOES pass the 'click' event when we select a
					 * menu. So we detect the click event, and run select only
					 * if we are in old IE. The user may have to keep the mouse down during the
					 * menu selection.
					 */
					if (GBPInitializr.isOldIE()) {
						console.log("addFormDelegateEvents(), addEvent(click) - OldIE did 'click' target:"+target.type);
						getSelectFieldValue(target); //select control
					}
				}
			}
			console.log("addFormDelegateEvents(), addEvent('click') - form event for target:"+target.type);
			GBPInitializr.stopEvent(e); //stop propagation if it is a non-radio button click, since tbody or text field can get a click
			},
		false);
		
		//text fields
		
		/**
		 * we save the focus event for text fields, so, if we leave the screen, we can update
		 * any fields that were edited but not clicked out of (blur event)
		 */
		GBPInitializr.addEvent(frm, "focus", function (e) {
			var target = GBPInitializr.getEventTarget(e);
			if(target.type && target.type.indexOf('text') !== -1) {
				GBPInitializr.saveFocus(target); //we save the field with focus, overwrite our old focus
			}
			console.log("addFormDelegateEvents(), addEvent('focus') - form event for:"+target.type);
			GBPInitializr.stopEvent(e); //stop propagation			
			},
		true); //true=reverse bubbling to delegate onblur to a non-input element
		
		
		//for old IE blurs and selects
		
		if (GBPInitializr.isOldIE()) {
			
			console.log("addFormDelegateEvents(), we've got OLD IE");
			
			//old ie uses the 'focusout' event for blurred text
			
			GBPInitializr.addEvent(frm, "focusout", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('text') !== -1) {
					getTextFieldValue(target); //don't want <select> reacting to the blur event
				}
				console.log("addFormDelegateEvents(), .addEvent('focusout') text blur event for:"+target.type);
				GBPInitializr.stopEvent(e); //stop propagation	
				},
				
			true);
			
			//old ie uses 'propertychange' for selects and radio buttons
			
			GBPInitializr.addEvent(document.body, "propertychange", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				if (target.type) {
					if(target.type.indexOf('text') !== -1) {
						//getTextFieldValue(target); //don't want <select> reacting to the blur event
					}
					else if (target.type.indexOf('radio') !== -1) {
						//getRadioFieldValue(target);
					}
					else if(target.type.indexOf('select') !== -1) {
						getSelectFieldValue(target);
					}
				}
				console.log("addFormDelegateEvents(), .addEvent('propertychange') form event for:"+target.type);
				GBPInitializr.stopEvent(e); //stop propagation	
				},
				
			true);
		}
		else {
			GBPInitializr.addEvent(frm, "blur", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('text') !== -1) {
					
					getTextFieldValue(target); //don't want <select> reacting to the blur event
				}
				console.log("addFormDelegateEvents(), .addEvent('blur') form event for:"+target.type);
				GBPInitializr.stopEvent(e); //stop propagation			
				},
				
			true); //true=reverse bubbling to delegate onblur to a non-input element
		}
		
		//select menus
		
		GBPInitializr.addEvent(frm, "change", function(e) {
			var target = GBPInitializr.getEventTarget(e);
			if(target.type && target.type.indexOf('select') !== -1) {
				
				/**
				 * we have lots of <select>s in the form, both
				 * for selecting source, component, and property, discovery,
				 * datatype,dependency, equivalent modernizr and caniuse.
				 * However NONE OF THESE creates a html table with lists of
				 * similar stuff, so we DO NOT need the data-gbp-datatype-xxx to
				 * sort the selects properly. So, we send all our selects to
				 * getSelectFieldValue(), instead of processing the non-gbp-datatype
				 * here in the event function
				 * 
				 * NOTE: we don't save the focus for selects
				 * TODO: we should clear them!!!!!!!!!!!!!!!!!!!!!!!!!!!
				 */
				console.log("addFormDelegateEvents(), .addEvent('change') or select blur form event for:"+target.type);
				getSelectFieldValue(target); //select control
			}
			GBPInitializr.stopEvent(e); //stop propagation
			},
		true); //true=reverse bubbling to delegate onblur to a non-input element
		
		
		//delete button for global form (not dependencies)
		
		GBPInitializr.addEvent(GBPInitializr.getElement("property-delete"), "click", function (e) {
			
			GBPInitializr.stopEvent(e);
			
			if (GBPInitializr.getConfirm("Do you really want to delete?")) {
				
				//delete the property, and reset form to "New Property"
				
				ajaxDeleteProperty(this);
				
				return true;
			}
			else {
				console.log("addFormDelegateEvents(), we didn't choose to delete property....");
			}
			return false;	
			},
			
		false);
		
		//property reference (a hyperlink, so we return false)
		
		GBPInitializr.addEvent(GBPInitializr.getElement("property-reference"), "click", function (e) {
			
			ajaxGetPropertyReference('properties', GBPInitializr.getCurrentProperty());
			return false;
		},
		false);
		
		//dependency references are implemented as onclick='' in the reference window
		
	} //end of function
	
	
	/**
	 * @method finish
	 * method fired when a top-menu tab is clicked
	 * make sure everything is saved from input= form text fields
	 * before jumping to a new page. Our 'onblur' events and 'onfocusout'
	 * events already correctly save Ajax before jumping to the new page,
	 * so we don't need to do anything special here.
	 */
	function finish() {
		console.log("top menu clicked in property_edit.php")
		return true;
	}
	

	/**
	 * this is called in the parent index.php in a complete: function 
	 * in index.php
	 */
	function init() {
		
		//hide the alternate library status
		
		GBPInitializr.getElement("alternate-libs-status").style.display = "none";
		
		//add events to the <table> widget and <form>
		
		addFormDelegateEvents("gbp-property-form");
		
		//disable controls
		
		GBPInitializr.getElement("gbp-exe-lock-priority").disabled = true;
		
		hidePropertyReference();
		hidePropertyDeleteButton();
		
		GBPInitializr.showMessage("new record");
		
		//select "new" record in select field
		
		///////////document.getElementById("property").selectedIndex=<?php echo $NEW_RECORD; ?>;
		clearPropertyForm(); //otherwise, browsers will fill in cached data in form!
		
		console.log("page loaded");	
	}
	
</script>