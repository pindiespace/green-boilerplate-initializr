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
		$SYSTEM_BROWSER = $prop->get_system_id_by_name('browser');
	}
	
	//get the values for no record (delete all) and new record
	
	$NONE       = $prop->get_none();
	$NO_RECORD  = $prop->get_no_record_id();
?>

<!--header for inserted form-->

<header class="form-header">
	
        <div id="err-list">
		<?php
			echo "<strong>ERROR:</strong>";
			print_r($clt->get_error());
		?>
		<!--we add an (invisible) foreground spinner image here. Background CSS animated 
		spinners don't always appear immediately-->

	</div>	
   
	<h2 class="clearfix">GBP Select Client and Version:</h2>
	
</header>

<!--section containing form (in a section)-->

<section class="clearfix">

<!--form routes to a processing script, redirects back to this page-->

	<form id="gbp-client-property-history-form" method="post" action="<?php echo 'index.php?state=clientupdatehistory&status=updateclientpropertyhistory'; ?>">

	<fieldset id="property-list-box">
		
<!--in practice, usually upgrading only one component-->
		
		<fieldset id="gbp-component" class="highlight-panel">
			
			<legend>Components:</legend>
			
			<label for="component">Component:</label>
			<!--<select id="component" name="component" onchange="ajaxChangePropertyByComponent();">-->
			<select id="component" name="component">
			<?php
				//we're forcing this only to browser-related components, so we query the GBP_ object
				
				$components = $prop->get_all_components($SYSTEM_BROWSER);
				
				foreach($components as $component) 
				{
					if(isset($COMPONENT_PRIMARY) && $COMPONENT_PRIMARY == $component['id'])
					{
						echo '<option value="'.$component['id'].'" selected="selected">'.$component['title'].'</option>'."\n";
					}
					else
					{
						echo '<option value="'.$component['id'].'">'.$component['title'].'</option>'."\n";
					}
				}
			?>
			</select>
            
            
<!--get the source databases-->
			
			<label for="source">Source Database:</label>
			<!--<select id="source" name="source" onchange="ajaxChangePropertyBySource();">-->
			<select id="source" name="source">
			<?php
				$sources = $prop->get_all_sources();
				
				foreach($sources as $source)
				{
					if(isset($SOURCE_PRIMARY) && $SOURCE_PRIMARY == $source['id'])
					{
						echo '<option value="'.$source['id'].'" selected="selected">'.$source['title'].'</option>'."\n";
					}
					else
					{
						echo '<option value="'.$source['id'].'">'.$source['title'].'</option>'."\n";
					}
				}
			
			?>
			</select>
			
		</fieldset>
		
<!--primary client list -->
		
		<fieldset id="gbp-client-sel" class="highlight-panel">
		
			<legend>Select a Client:</legend>
			
			<p id="component-left">
				
				<label for="client" accesskey="d">Client Name (group)</label>
				<!--<select id="client" name="client" onchange="ajaxChangeBrowser();">-->
				<select id="client" name="client">	
				<?php
					$clients = $clt->get_all_clients();
					$ct = 0;
					foreach($clients as $client) 
					{
						if($client['id'] == $CLIENT_PRIMARY)
						{
							echo '<option value="'.$client['id'].'" selected="selected">'.$client['title'].'</option>'."\n";
						}
						else
						{
							echo '<option value="'.$client['id'].'">'.$client['title'].'</option>'."\n";
						}
						$ct++;
						}
				?>
				</select>
				<span id="client-status"><?php echo "($ct)"; ?></span><!--this can't be changed on this page, so just list them once-->
			</p>
			
			
			
		</fieldset>

<!--list all the GBP properties in a list. Clicking on a property takes us to the entry form, with everything filled in-->
		
		<fieldset class="highlight-panel">
			
			<legend class="block-visible">Select a Property to Update:</legend>
			
			<select id="property" name="property">		
			<?php
				$prop_arr = $prop->get_all_properties($SOURCE_PRIMARY, $COMPONENT_PRIMARY);
				
				//remove the 'browser' name from the list
				
				$component_browser_id = $clt->get_component_id_by_name('browser');
				if($component_browser_id == $COMPONENT_PRIMARY)
				{
					$bad_name = 'browser';
				}
				else
				{
				$bad_name = false;
				}
				
				$ct = 0;
				foreach($prop_arr as $id => $property) 
				{
					if($bad_name == false || ($property['name'] != $bad_name))
					{
						echo '<option value="'.$property['id'].'">'.$property['title'].'</option>'."\n";
						$ct++;
					}
				}
			?>   
			</select>
			
			<span id="property-status"><?php echo "($ct)"; ?></span>
			
		</fieldset>
		
	</fieldset>
		
<!--form for client-property lists-->
            
        <fieldset id="client-property-form" class="highlight-panel">
		
		<legend id="client-property-breadcrumb">Client-Property Form</legend>
		<table id="client-property-table">
			<thead>
				<tr>
					<th>Version</th>
					<th>Value</th>
					<th>Search</th>
				</tr>
			</thead>
			
			<tbody id="client-property-value">
				<tr>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
					<td>&nbsp;</td>
				</tr>
			</tbody>
			
		</table>
		
	</fieldset>

	</form>

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
	 * methods that write PHP output as JavaScript JSON, variables, code
	 * -------------------------------------------------------------------------
	 */
	

	/** 
	 * get the list of allowed search groups (common, rare, market share)
	 * we only need to do this once, and use it to create pulldown menus so the 
	 * property can be adjusted. Because we only need it once, we DO NOT use 
	 * Ajax to load - instead we insert a JSON object at the PHP level which we can
	 * convert into a search group
	 * NOTE: since we are using the JSON object directly, there is a potential security 
	 * issue, if someone manages to inject the PHP $arr.
	 * NOTE: we DO NOT NEED A JSON PARSE here. by assigning the output of json_encode 
	 * to a JavaScript variable, it turns directly into an object. If we try to 
	 * use JSON.parse() we will get an error
	 * @return Object JSONObject - JSON return string parsed to object with all 
	 * allowed search groups.
	 */
	function getSearchGroup() {
		var response, JSONObject;
		<?php
			$arr = $clt->get_all_search_groups();
			if(isset($arr)) {
				echo 'response = '.json_encode($arr).";\n"; //array with object literals
			}
		?>
		
		return response;
	}
	
	
	/**
	 * get allowed text field sizes for client-property text values (typically 255 chars)
	 * these are computed in GBP_BASE directly from the table, so we can vary columns in the db as necessary
	 */
	function getPropertyValueFieldSize() {
		var response = [];
		<?php
			$arr = $prop->get_column_widths('clients_properties', array('property_value'));
			foreach($arr as $key => $value)
			{
				echo "response['".$key."'] = $value; ";
			}
			echo "\n";
		?>
		
		return response;
	
	}

	
	/** 
	 * ------------------------------------------------------------------------- 
	 * ajax methods
	 * ------------------------------------------------------------------------- 
	 */
	
	function ajaxGetClientPropertyReference(tableName, itemId, selText) {
		
		console.log("in ajaxGetPropertyClientReference");
		
		//we have a defined client. So show the references associated with it
		
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_reference_list&tnm=' + tableName + '&iid=' + itemId, function (response) {
			
			var JSONObject = GBPInitializr.processJSON(response, ["apiresult"]);
			
			if(JSONObject) {		
				console.log("returned a client-property reference");
				var refArr = [];
				for(var i in JSONObject) {
					refArr[i] = JSONObject[i];
				}
				
				//get the name of the property reference, and don't show the window for unsaved properties
				
				GBPInitializr.showMessage("opening client-property references");
				
				return GBPInitializr.showRefWin(refArr, tableName, itemId, selText, 'reference-window');
			}
			else {
				console.log("no reference returned");
				return false;
			}
			
		}, //end of callback
		
		'get'); //end of ajaxRequest()
	}


	/** 
	 * ------------------------------------------------------------------------- 
	 * trigger the change in the property list when we change 
	 * the browser 
	 * ------------------------------------------------------------------------- 
	 */
	function ajaxChangeBrowser() {
		ajaxShowClientPropertyValues(false);
		GBPInitializr.showMessage("changed component to:"+GBPInitializr.getCurrentSelectText(GBPInitializr.getElement('component')));
	}

	
	/** 
	 * ------------------------------------------------------------------------- 
	 * trigger the change in the property list, based on data source
	 * ------------------------------------------------------------------------- 
	 */
	function ajaxChangePropertyBySource() {	
		
		ajaxChangePropertyByComponent();
		
		//show an end-user message
		
		GBPInitializr.showMessage("changed source to:"+GBPInitializr.getCurrentSelectText(GBPInitializr.getElement('source')));
	}
	
	
	/** 
	 * ------------------------------------------------------------------------- 
	 * trigger the change in the property list, based on component selection
	 * ------------------------------------------------------------------------- 
	 */
	function ajaxChangePropertyByComponent() {
		
		var value = GBPInitializr.getCurrentComponent();
		var sid   = GBPInitializr.getCurrentSource();
		
		/**
		 * FORBIDDEN PROPERTY
		 * we need to to restrict the browser::name property from showing up in our property list.
		 * browser-versions are OK, but browser base (client) name in the 'clients' DB table is NOT.
		 *
		 * Other features of the client (releasedate, version, version common name) can be edited in
		 * either 'client' or 'client-properties.' The 'searchgroup' only forms a <select> in 'clients'
		 */
		var forbiddenPropId = "<?php echo $prop->get_property_id_by_name('name'); ?>";
		
		if(!value || !sid) {
			console.log("ERROR: ajaxChangePropertyByComponent() - error in reading select menu(s), component:"+value+" source:"+source);
			return false;
		}
		
		GBPInitializr.showSpinner('spinner-win');
		
		/**
		 * @param params parameters to the request.
		 * @param url url of the request
		 * @param func callback function
		 * @param connType GET or POST	 
		 */	
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_property_by_component&sid=' + sid + '&cid=' + value + '&list_only=true', function (response) {
			
		var JSONObject = GBPInitializr.processJSON(response,["apicall", "apiresult"]);
		
		if(JSONObject) {
			
			var selPropElem = GBPInitializr.getElement('property');
			var selContents = "";
			var ct = 0;
			for (var key in JSONObject) {
				var obj = JSONObject[key];
				
					/**
					 * forbiddenProperty is a property which CANNOT be edited on this form
					 */
					if (!forbiddenPropId || forbiddenPropId != obj.id) {
						selContents += '<option value="' + obj.id + '">' + obj.title + '</option>\n';
						ct++;
					}
					
				}
				
			
			//selPropElem.innerHTML = selContents;
			var result = GBPInitializr.setSelOptions(selPropElem, selContents); //for IE compatibility
			
			//update the number of properties for this component
			
			document.getElementById('property-status').innerHTML = "("+ct+")";
			
			/*
			 * since we've shifted display, the saved lastFocus object may not
			 * exist anymore. So remove our data referencing it
			 */
			
			GBPInitializr.clearFocus();
			
			/**
			 * make a SECOND Ajax call to update the property list based on component
			 * so we DON'T hideSpinner() yet
			 */
			ajaxShowClientPropertyValues(false);
			
		}
		else {
			/**
			 * we have nothing defined for this source and component. So just empty 
			 * the table and indicate the lack to the user
			 */
			var breadcrumb = GBPInitializr.getElement('client-property-breadcrumb');
			breadcrumb.innerHTML = "Source has no Defined Properties for Component";
			var tblBody = GBPInitializr.getElement('client-property-value');
			tblBody.innerHTML = "<tr><td>&nbsp;</td><td>&nbsp</td></tr>";
			GBPInitializr.hideSpinner('spinner-win');
			GBPInitializr.showMessage("no properties assigned for component:"+GBPInitializr.getCurrentSelectText(GBPInitializr.getElement('component')));
		} //end of empty table
		
			
		}, //end of callback
		
		'get'); //end of ajaxRequest()
		
		return true;
	}


	/** 
	 * ------------------------------------------------------------------------- 
	 * if the property selected changes, update the list of 
	 * property values for all the versions of the client
	 * @param {Boolean} doFocus if true, set using the saved GBPInitializr.saveFocus()
	 * @return undefined
	 * ------------------------------------------------------------------------- 
	 */
	function ajaxShowClientPropertyValues(doFocus) {
		
		GBPInitializr.showSpinner('spinner-win');
		
		/**
		 * getSearchGroup is one of our weird functions that is filled with data by PHP. We use it 
		 * later to create pulldowns with the search option
		*/
		var searchGroupArr = getSearchGroup();
		
		//property, soruce, client
		
		var pid  = GBPInitializr.getCurrentProperty();
		var sid  = GBPInitializr.getCurrentSource();
		var clid = GBPInitializr.getCurrentClient();
		console.log("pid:"+pid+" sid:"+sid+" clid"+clid);
		
		/**
		 * get client-property data
		 */
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_client_property_versions&clid=' + clid + '&pid=' + pid, function (response) {
			
			/** 
			 * this function is sensitive to any object that doesn't follow our expected pattern. 
			 * so delete our housekeeping properties
			 */
			var JSONObject = GBPInitializr.processJSON(response,["apicall", "apiresult", "error"]);
			GBPInitializr.hideSpinner('spinner-win');
			if(JSONObject) {
					
				var tblContents = "";
				var count = 0;
				
				for(var key in JSONObject) {
					
					var obj = JSONObject[key];
					
					if(count == 0) {
						var browser  = obj.client.title;
						var property = obj.property.title;
						count = 1;
					}
					
					/**
					 * our key is numeric in the for(in) loop, even though in the JSON output, it is 
					 * the version key. Get the right 
					 * key value from the version:{} array
					 */
					key = obj.id;
					
					
					//switch on datatype
					
					var ctlString = "";
					
					switch(obj.datatype.name)
					{
						case "string":
							
							//certain properties have enums. List them here
							//TODO: add an 'enum' flag to 'properties', or change 'searchgroup' datatype to
							//'enum'
							
							if (obj.property.name == 'searchgroup') {
								
								//<select> enum
								
								var len = searchGroupArr.length; //an array
								ctlString += '<select data-gbp-type="string" name="' + key + '" id="' + key + '-str">';
								for (var j=0; j < len; j++) {
									ctlString += '<option value="'+searchGroupArr[j].id+'"';
									
									if (searchGroupArr[j].id == obj.search_group.id) {
										ctlString += ' selected="selected"';
									}
									
									ctlString += '">'+searchGroupArr[j].name+'</option>';
								}
								ctlString += '</select>';
							}
							else {
								//plain text field, with size determined by current size in database
								
								ctlString += '<input data-gbp-type="string" type="text" name="' + key + '" id="' + key + '-str"';
								if(obj.property_value != "undefined") {
									ctlString += ' value="' + obj.property_value + '" maxlength="'+getPropertyValueFieldSize()['property_value']+'">';
								}
								else {
									ctlString += ' value="" maxlength="'+getPropertyValueFieldSize()['property_value']+'">';
								}
							}
							break;
							
						case "boolean":
							ctlString  += '<label for="' + key + '">True</label>\n<input data-gbp-type="boolean" type="radio" name="' + key + '" id="' + key + '-true" value="true"';
							if(obj.property_value == "true") {
								ctlString += ' checked="checked">';
							}
							else {
								ctlString += '>';
							}
							ctlString += '&nbsp;&nbsp;';
							ctlString += '<label for="' + key + '">False</label>\n<input data-gbp-type="boolean" type="radio" name="' + key + '" id="' + key + '-false" value="false"';
							if(obj.property_value == "false") {
								ctlString += ' checked="checked">';
							}
							else {
								ctlString += '>';
							}
						break;
						
						case "number":
							ctlString += '<input data-gbp-type="number" type="text" name="' + key + '" id="' + key + '-num" value="'+obj.property_value+'">';
							break;
						
						case "undefined":
							break;
							
						case "null":
							break;
						
						case "location":
							ctlString += '<span rel="geo">';
							ctlString += '<label for="' + key + '-lat">Latitude</label>';
							ctlString += '<input data-gbp-type="latlong" type="text" name="' + key + '-lat" id="' + key + '-lat" value="'+obj.property_value.latitude+'">';
							ctlString += '<label for="' + key + '-long">Longitude</label>';
							ctlString += '<input data-gbp-type="latlong" type="text" name="' + key + '-long" id="' + key + '-long" value="'+obj.property_value.longitude+'">';
							ctlString += '<label for="">N/S E/W</label>';
							ctlString += '<input data-gbp-type="latlong" type="radio" name="' + key + '-north" id="' + key + '-north" value="north">';
							ctlString += '<input data-gbp-type="latlong" type="radio" name="' + key + '-south" id="' + key + '-south" value="south">';
							ctlString += '<input data-gbp-type="latlong" type="radio" name="' + key + '-east" id="' + key + '-east" value="east">';
							ctlString += '<input data-gbp-type="latlong" type="radio" name="' + key + '-west" id="' + key + '-west" value="west">';						
							ctlString += '</span>';
							break;
						
						case "dimensions":
							ctlString += '<span rel="dim">';
							ctlString += '<label for="">Width(px)</label>';
							ctlString += '<input data-gbp-type="dimensions" type="text" name="' + key + '-width" id="' + key + '-width" value="'+obj.property_value.width+'">';
							ctlString += '<label for="">Width(px)</label>';
							ctlString += '<input data-gbp-type="dimensions" type="text" name="' + key + '-height" id="' + key + '-height" value="'+obj.property_value.height+'">';						
							ctlString += '</span>';
							break;
						
						case "date":
							ctlString += '<input data-gbp-type="date" type="date" name="' + key + '" id="' + key + '-date" value="'+obj.property_value+'">';
							break;
						
						case "timestamp":
							break;
						
						case "na":
							break;
						
						case "key":
							break;
						
						default:
							break;
						
					}
					
					//search group value
					
					var searchGroup = obj.search_group.name;
					
					//reference link
					
					/**
					 * the key is the client-property id, NOT the client-version-property id. To get that, we need
					 * the client_property id, which is only defined when the client-version-property record exists
					 */
					var refLink;
					if (obj.client_property_id) {
						refLink = '<span class="mini-highlight" ><a id="'+obj.client_property_id+'-client-property-reference" href="#">ref</a></span>';
					}
					else {
						refLink = '';
					}
					
					//finally, construct the table columns
					
					tblContents += "<tr>\n<td>" + obj.versionname + "</td>\n<td>" + ctlString + refLink + "</td>\n<td>"+searchGroup+"</td>\n</tr>\n";
					
				} //end of for-in loop
				
				//write information about the client and version to the screen
				
				var breadcrumb = GBPInitializr.getElement('client-property-breadcrumb');
				
				if(browser && property) {
					breadcrumb.innerHTML = browser + ":" + property;
				}
				else {
					breadcrumb.innerHTML = "Client has no defined versions (therefore no properties)";
				}
				
				/**
				 * change the table <tbody>.
				 * Because IE < 10 and FF < 4 have problems inserting a new <tbody> using
				 * the .innerHTML property, we call a specialized function which makes it
				 * work in these older browsers.
				 * Because we are using event delegation, we can 
				 * change the table rows at will, and still catch the click and blur events
				 */
				var hasDelegates = GBPInitializr.setTBody('client-property-value', tblContents);
				if (!hasDelegates) {
					console.log("ajaxShowClientPropertyValues() - adding table delegates for Old IE");
					var tblBody = GBPInitializr.getElement('client-property-value');
					addTableDelegateEvents(tblBody);
				}
				
				/**
				 * if we need the 'date' polyfill, use it. This function adds it to any input type='date' field
				 * in the table
				 */
				GBPInitializr.addCalendarWidget('client-property-value');
				
				console.log("ajaxShowClientPropertyValues() - reset tBody");
				
				if (doFocus) {
					/**
					 * (re)set the focus. Even though we have the element, its reference may not be valid after
					 * redrawing the page with ajaxShowClientPropertyvalues(). use the id value to re-get the element.
					 * however, we ONLY want to set the focus for a 'blur' event
					 */
					var lastFocus = GBPInitializr.getLastFocus();
					if (lastFocus.name) {
						if (lastFocus.type && lastFocus.type == 'text') {
							GBPInitializr.getElement(lastFocus.id); //we redrew, so saved element reference is probably wrong
							GBPInitializr.saveFocus(lastFocus);
							
							//using setActive keeps the page from scrolling
							
							if (document.setActive) {
								GBPInitializr.getLastFocus().setActive();
							}
							
							GBPInitializr.clearFocus();
						}
					}
					
				} //end of doFocus flag
				
			} //end of JSONObject
			
			
			
			}, //end of callback 
			
		'get');	//end of ajaxRequest()
	}


	/** 
	 * ------------------------------------------------------------------------- 
	 * update a client-property record in the database. We create a new record, 
	 * then either update the existing record, or make a new one. If a value is 
	 * undefined, no record exists.
	 * @param {String} clvid id of client, specific allowed version (from the client version table, not the client-property table)
	 * @param {String} value what was typed in the text field (simple string)
	 * ------------------------------------------------------------------------- 
	 */
	function ajaxUpdateClientPropertyStringValues(clvid, value) {
		
		GBPInitializr.showSpinner('spinner-win');
		
		var sid  = GBPInitializr.getCurrentSource();
		var clid = GBPInitializr.getCurrentClient();
		var pid  = GBPInitializr.getCurrentProperty();
		
		console.log("ajaxUpdateClientPropertyStringValues() - updating string value, pid="+pid+" clvd="+clvid+" value="+value);
		
		/** 
		 * call update_client_property()
		 * the update function takes
		 * sid    = datasource (gbp, modernizr, caniuse)
		 * clid   = client id
		 * clvid  = client version id
		 * pid    = property id
		 * pval   = value of field
		 */		
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_client_property_value&clid='+clid+'&clvid='+clvid+'&pid='+pid+'&pval='+value, function (response) {
			
				var JSONObject = GBPInitializr.processJSON(response, []);
				
				/**
				 * we re-run this to update the client-property table, which may change
				 * based on our update. For example, if we select 2 consecutive versions and
				 * give them the same property value,update_client_property_values() will remove the
				 * redundant more recent version property, making it 'undefined'
				 */
				ajaxShowClientPropertyValues(true); //reset focus only for text fields, not if we click on a <select>
				
				GBPInitializr.showMessage("updated client:"+GBPInitializr.getCurrentSelectText(GBPInitializr.getElement('client'))+" property:"+GBPInitializr.getCurrentSelectText(GBPInitializr.getElement('property')));
				
			}, //end of callback
			
		'get'); //end of ajaxRequest()
		
	}
	

	/** 
	 * update Boolean values associated with a client-version property combo
	 * since we store the strings "true" and "false", we just pass this to the string updater
	 * @param {Number} clvid client-version id in the database (for a specific client-verion-property combination)
	 * @param {String} value value of client-version property
	 */
	
	function ajaxUpdateClientPropertyBooleanValues(clvid, value) {
		
		console.log("ajaxUpdateClientPropertyBooleanValues() - updating Boolean in db with value="+value);
		ajaxUpdateClientPropertyStringValues(clvid, value);
		
		GBPInitializr.showMessage("updated client:"+GBPInitializr.getCurrentSelectText(GBPInitializr.getElement('client'))+" property:"+GBPInitializr.getCurrentSelectText(GBPInitializr.getElement('property')));

	}
	
	/**
	 * update values associated with an <select> enum with a client-version property combo
	 * the results are just strings, we don't have info for datatype
	 */
	function ajaxUpdateClientPropertySelectValues(clvid, value) {
		console.log("ajaxUpdateClientPropertySelectValues() - updating select option in db with value="+value);
		ajaxUpdateClientPropertyStringValues(clvid, value);
	}

	//TODO: Special functions needed for location and dimensions data units
	
	
	function getClientPropertyReferenceValue(target) {
		
		console.log("in clientPropertyReferenceValue");
		
		var ref = target.id.split('-');
		if (ref && ref[0]) {
			console.log("REF0:"+ ref[0]);
			
			//call the modal dialog listing this kind of reference
			
			var row = GBPInitializr.getTableRow('client-property-table', target.parentNode); //we're inside a parentNode (<span>)
			
			console.log("row is:"+row);
			var versText = row.cells[0].innerHTML;
			
			ajaxGetClientPropertyReference('clients_properties', ref[0], versText); //NOTE: clients_versions is a mysql table name, not css id
		}
	}
	
	/**
	 * ------------------------------------------------------------------------- 
	 * this function gets the input field that changed
	 * used with blur events for text fields
	 * -------------------------------------------------------------------------
	 */
	function getTextFieldValue(target) {
		
		console.log("getTextFieldValue() - blur event noted for text field");
		
		/**
		 * in the case of text fields, we want to save the focus. document.ActiveElement
		 * should give us the answer, but for old browser compatibility (e.g. old Safari)
		 * we just save the active element
		 * WE SAVED FOCUS IN THE EVENT HANDLER
		 */
		if(target) {
			
			/**
			 * client-property features
			 */
			var attr = target.getAttribute('data-gbp-type');
			
			if (attr) {
				
				console.log("getTextFieldValue() - ATTR:"+attr);
				
				switch(attr.toLowerCase()) {
					
					case "string":
						console.log("getTextFieldValue() - string field blured ("+target.value+")");					
						ajaxUpdateClientPropertyStringValues(target.name, target.value);
						break;
					
					case "number":
						console.log("getTextFieldValue() - number field blured ("+target.value+")");
						if (GBPInitializr.isNumber(target.value)) {
							ajaxUpdateClientPropertyStringValues(target.name, target.value);
						}
						else {
							console.log("ERROR: getTextFieldValue() - number expected, didn't get one("+target.value+")");
						}
						break;
					
					case "location":
						//TODO: find parent, find other input fields
						break;
						
					case "dimensions":
						//TODO: find parent, find other input fields
						break;
						
					case "date":
					
						/**
						 * if we are running the calendar polyfill, it writes a semaphore into the <input type=date...> field.
						 * this is because the calendar is a separate window, and closing it after selecting a date will
						 * trigger an 'onblur' event BEFORE the calendar updates the text field. In other words, the 'onblur'
						 * event was queued when the calendar mini-window opened.
						 *
						 * So, we have the calendar object write a special 'noBlur' attribute into the <input> tag. This is messing
						 * with the defined input types, but since it only operates in the narrow scope of the calendar polyfill it
						 * is probably ok.
						 *
						 * The calendar object, having prevented the blur event from Ajaxing, is now responsible for firing a 'blur'
						 * event when its window closes. It stores the input fields it is attached to in order to do this.
						 */
						
						if (target.noBlur === "true") {
							
							console.log("getTextFieldValue() - noblur present, don't execute update on our text field");
							
							//figure out if we are inside our calendar div
							
							if(calendar && calendar.isVisible) {
								console.log("getTextFieldValue() - calendar visible");
								if (!calendar.mouseIsOver) {
									console.log("getTextFieldValue() - mouse outside, hiding");
									calendar.hideCalendar();
								}
								else {
									console.log("getTextFieldValue() - mouse not over");
								}
								
							}
							else {
								console.log("getTextFieldValue() - calendar not visible");
							}
							
							return; //don't run the blur event!!!!!! we have a calendar polyfill
						}
						else {
							console.log("getTextFieldValue() - noblur absent, execute update");
						}
						
						//confirm we received a date back
						
						if (GBPInitializr.isDate(target.value)) {
							ajaxUpdateClientPropertyStringValues(target.name, target.value);
						}
						else {
							console.log("ERROR: getTextFieldValue() - date expected, didn't get one ("+target.value+")");
						}
						break;
							
					case "timestamp":
						if (GBPInitializr.isTimeStamp(target.value)) {
							ajaxUpdateClientPropertyStringValues(target.name, target.value);
						}
						else {
							console.log("ERROR: getTextFieldValue() - timestamp expected, didn't get one ("+target.value+")");
						}
						break;	
					
					default:
						console.log("ERROR: getTextFieldValue() - undefined data-gbp-type for input field control, check for specific fields");
						break;
					
				} //end of switch
			
			} //end of if data-gbp-type present
			else {
				console.log("ERROR: getTextFieldValue() - a text field was found without data-gbp-type present");
			} //end of no data-gbp-type processing
			
		} //end of target test
		
	} //end of function
	 
	 
	/**
	 * @method getRadioSetValue()
	 * this function gets boolean or multiple-choice values from radio buttons
	 * @param target the DOM element that was the target of the event
	 */
	function getRadioSetValue(target) {
		console.log("getRadioSetValue() - radio click event noted");
		if(target) {
			switch(target.getAttribute('data-gbp-type').toLowerCase()) {
							
				case "boolean":
					if(target.value == "true" || target.value == true) {
						ajaxUpdateClientPropertyBooleanValues(target.name, "true");
					}
					else {
						ajaxUpdateClientPropertyBooleanValues(target.name, "false");
					}
					break;
					
				default:
				 	console.log("ERROR: getRadioSetValue() - undefined data-gbp-type for radio control");
					break;
					
			} //end of switch
			
		 } //end of target test
		 
	} //end of function
	 
	 
	/**
	 * @method getSelectFieldValue()
	 * this function gets the value of an input field that changed
	 * in the <tbody> list of client-property-version combos, in particular
	 * for the search-group assigned to the client-version (common, rare, ancient)
	 * @param target the DOM element that was the target of the event
	 */
	function getSelectFieldValue(target) {
		console.log("getSelectFieldValue() - onchange event noted target.name:"+target.name+" and target.value:"+target.value);
		if(target) {
			console.log(target.getAttribute('data-gbp-type').toLowerCase());
			switch(target.getAttribute('data-gbp-type').toLowerCase()) {
				case 'string':
					ajaxUpdateClientPropertyStringValues(target.name, target.value);
					break;
				case 'number':
					if (GBPInitializr.isNumber(target.value)) {
						ajaxUpdateClientPropertyStringValues(target.name, target.value);
					}
					else {
						console.log("ERROR: getSelectFieldValue() - number expected, didn't get one("+target.value+")");
					}
					break;
				case 'date':
					if (GBPInitializr.isDate(target.value)) {
						ajaxUpdateClientPropertyStringValues(target.name, target.value);
					}
					else {
						console.log("ERROR: getSelectFieldValue() - date expected, didn't get one ("+target.value+")");
					}
					break;
				case 'timestamp':
					if (GBPInitializr.isTimeStamp(target.value)) {
						ajaxUpdateClientPropertyStringValues(target.name, target.value);
					}
					else {
						console.log("ERROR: getSelectFieldValue() - timestamp expected, didn't get one ("+target.value+")");
					}					
					break;
				default:
				 	console.log("ERROR: getSelectFieldValue() - undefined data-gbp-type for select control");
				 	break;
				 
			} //end of switch
			 
		} //end of target test
		 
	} //end of function
	 
	 
	 
	/** 
	 * we use event delegation on the <tbody> of our table. We read the events
	 * from controls in table cells
	 * 1. to use event delegation on tbody we need to reverse 
	 * the order of event bubbling, otherwise we won't pick up the event
	 * http://www.quirksmode.org/blog/archives/2008/04/delegating_the.html
	 * 2. we make this event specific to text fields by checking the element.type
	 * NOTE: <table> cells don't have a type, so confirm for element.type exists before testing
	 * using list of events mapped to form elements at: http://www.w3schools.com/jsref/dom_obj_event.asp
	 * IE can pass almost any event from any form element
	 * @param {DOMElement tbody} tBody table body from table widget
	 */
	function addTableDelegateEvents(tBodyId) {
		
		var tBody = GBPInitializr.getElement(tBodyId);
		
		if (tBody) {
			
			GBPInitializr.addEvent(tBody, "blur", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('text') !== -1) {
					getTextFieldValue(target); //don't want <select> reacting to the blur event
					GBPInitializr.saveFocus(target);
				}
				else if(target && target.type && target.type.indexOf('date') !== -1)
				{
					//no special processing
					getTextFieldValue(target);
				}
				
				console.log("addTableDelegateEvents('blur') - event, target:"+target.type);
				GBPInitializr.stopEvent(e); //stop propagation
				},
			true); //true=reverse bubbling to delegate onblur to a non-input element
			
			
			/**
			 * we save the focus event for text fields, so, if we leave the screen, we can update
			 * any fields that were edited but not clicked out of (blur event)
			 */
			GBPInitializr.addEvent(tBody, "focus", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('text') !== -1) {
					GBPInitializr.saveFocus(target); //we save the text field with focus
				}
				else if(target && target.type && target.type.indexOf('date') !== -1)
				{
					//no special processing
				}
				
				console.log("addTableDelegateEvents('focus') - event, target:"+target.type);
				GBPInitializr.stopEvent(e); //stop propagation
				},
			true); //true=reverse bubbling to delegate onblur to a non-input element
			
			
			/** 
			 * we make this event specific to radio buttons by check the element.type
			 * NOTE: <table> cells don't have a type, so confirm element.type exists before testing
			 * NOTE: propagate event improves radio button appearance (redraws the button), so don't use .stopEvent() here
			 */
			GBPInitializr.addEvent(tBody, "click", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				
				if (target && target.type) {
					
					if(target.type.indexOf('radio') !== -1) {
						getRadioSetValue(target); //radio control clicked on
						GBPInitializr.saveFocus(target);
					}
					else if(target.type.indexOf('date') !== -1) {
						//no special work
					}
					else {  
						GBPInitializr.stopEvent(e); //stop propagation if it is a non-radio button click, since tbody or text field can get a click
					}
					
					console.log("addTableDelegateEvents('click') event, target:"+target.type);
				}
				else  if (target.id.indexOf('-client-property-reference') !== -1) { //reference hyperlink
					GBPInitializr.stopEvent(e);
					getClientPropertyReferenceValue(target);
				}
			},
			false);
			
			
			/** 
			 * we make this event specific to selects in our <table> by check the element.type
			 * NOTE: <table> cells don't have a type, so confirm element.type exists before testing
			 */
			GBPInitializr.addEvent(tBody, "change", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('select') !== -1) {
					getSelectFieldValue(target); //select control clicked on
					GBPInitializr.saveFocus(target);
				}
				else if (target.type && target.type.indexOf('date') !== -1) {
					//getTextFieldValue(target); //select, but also dates with select-like controls
				}
				console.log("addTableDelegateEvents('change') event, target:"+target.type);
				GBPInitializr.stopEvent(e); //stop propagation
				},
			false);
			
			
			
			if (GBPInitializr.isOldIE()) {
				
				console.log("addTableDelegateEvents() - isOldIE, tBody delegate for OLD IE");
				
				//old ie uses the 'focusout' event for blurred text
				GBPInitializr.addEvent(tBody, "focusout", function (e) {
					var target = GBPInitializr.getEventTarget(e);
					if (target.type) {
						if(target.type.indexOf('text') !== -1) {
							getTextFieldValue(target); //don't want <select> reacting to the blur event
						}
						else if (target.type.indexOf('date') !== -1) {
							console.log("addTableDelegateEvents('focusout') date as plain text field");
							getTextFieldValue(target); //for compatibility when HTML5 input type=date not supported
						}
					}
					GBPInitializr.stopEvent(e); //stop propagation
					},
					
				true);
				
				
				GBPInitializr.addEvent(tBody, "propertychange", function (e) {
					var target = GBPInitializr.getEventTarget(e);
					console.log(" addTableDelegateEvents('propertychange') event, "+target.type);
					if(target.type && target.type.indexOf('radio') !== -1) {
						console.log(" addTableDelegateEvents('propertychange') radio event");
						getRadioSetValue(target);
					}
					GBPInitializr.stopEvent(e); //stop propagation
					},
				
				true);
				
			}
			
		} //end of valid tBody
	}
	
	/**
	 * additional delegate events for the form. in this ui, all the form elements which are not
	 * part of a client-property are <select> elements
	 */
	function addFormDelegateEvents(frmId) {
		
		var frm = GBPInitializr.getElement(frmId);
		
		//save text fields for focus events
		
		//for old IE blurs and selects
		
		if (GBPInitializr.isOldIE()) {
			
			console.log("addFormDelegateEvents() - isOldIE() entry");
			
			GBPInitializr.addEvent(frm, "click", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				if (target.type) { //restrict to form fields
					if(target.type.indexOf('radio') !== -1) {
						getRadioSetValue(target); //control clicked on
					}
					else if (target.type.indexOf('checkbox') !== -1) {
						//getCheckBoxValue(target);
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
							console.log("addFormDelegateEvents() - isOldIE, form 'click' target:"+target.type);
							//getSelectFieldValue(target); //select control
							if (target.type && target.type.indexOf('select') !== -1) {
								switch(target.name) {
									case 'component':
										ajaxChangePropertyByComponent();
										break;
									case 'source':
										ajaxChangePropertyBySource();
										break;
									case 'client':
										ajaxChangeBrowser();
										break;
									case 'property':
										ajaxShowClientPropertyValues(false);
										break;
									default:
										console.log("ERROR: addFormDelegateEvents() - unrecognized select field in form!");
										break;
								}	
							}
						}
					}
					console.log("addFormDelegateEvents('click'), form event for target:"+target.type);
					GBPInitializr.stopEvent(e); //stop propagation if it is a non-radio button click, since tbody or text field can get a click
				}
			},
			false);
			
			//old ie uses 'propertychange' for selects and radio buttons
			
			GBPInitializr.addEvent(frm, "propertychange", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				
				if(target.type && target.type.indexOf('text') !== -1) {
					//GBPInitializr.saveFocus(target);
					//getTextFieldValue(target); //don't want <select> reacting to the blur event
				}
				else if(target.type && target.type.indexOf('select') !== -1) {
					switch(target.name)
					{
						case 'component':
							ajaxChangePropertyByComponent();
							break;
						case 'source':
							ajaxChangePropertyBySource();
							break;
						case 'client':
							ajaxChangeBrowser();
							break;
						case 'property':
							ajaxShowClientPropertyValues(false);
							break;
						default:
							console.log("ERROR: addFormDelegateEvents() - unrecognized select field in form!");
							break;
					}
					GBPInitializr.saveFocus(target);
				}
				console.log("addFormDelegateEvents('propertychange') for form, "+target.type);
				GBPInitializr.stopEvent(e); //stop propagation	
				},
				
			true);
			
		}
		else {
			//non IE, we only need to support <select> at the form level
		
			GBPInitializr.addEvent(frmId, "change", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('select') !== -1) {
					switch(target.name)
					{
						case 'component':
							ajaxChangePropertyByComponent();
							break;
						case 'source':
							ajaxChangePropertyBySource();
							break;
						case 'client':
							ajaxChangeBrowser();
							break;
						case 'property':
							ajaxShowClientPropertyValues(false);
							break;
						default:
							console.log("ERROR: addFormDelegateEvents() - unrecognized select field in form!");
							break;
					}
					GBPInitializr.saveFocus(target);
				}
				console.log("addFormDelegateEvents() - change event for form, target:"+target)
				GBPInitializr.stopEvent(e); //stop propagation
				},
				
			false);	
		}
		
		
	}

	
	/**
	 * @method finish
	 * method fired when a top-menu tab is clicked
	 * make sure everything is saved from input= form text fields
	 * before jumping to a new page. Our 'onblur' events and 'onfocusout'
	 * events already correctly save Ajax before jumping to the new page,
	 * so we don't need to do anything special here.
	 */
	function finish() {
		console.log("top menu clicked in client_property_history.php");
		return true;
	}
	
	
	/** 
	 * this is called in the parent index.php in a complete: function 
	 * in index.php
	 */
	function init() {
		
		//add events to the <table> widget and <form>
		
		addTableDelegateEvents("client-property-value");
		addFormDelegateEvents("gbp-client-property-history-form");
		
		/**
		 * set the default browser $CLIENT_PRIMARY (an id value in the 'clients' table)
		 */
		ajaxChangeBrowser();
		
	}
	
</script>