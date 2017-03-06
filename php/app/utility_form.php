<?php 
	//NOTE: init.php must have been included earlier
	
	if(class_exists('GBP_PROPERTY'))
	{
		$prop = new GBP_PROPERTY;
	}
	
	//get the values for no record (delete all) and new record
	
	$NONE       = $prop->get_none();
	$NO_RECORD  = $prop->get_no_record_id();
	$NEW_RECORD = $prop->get_new_record_id();
	
	
	//additional imports from the gbp/initializr/php/import directory
	
	/**
	 * JSON imports
	 * we only load the base, use subclasses for processing
	 */
	if(class_exists(GBP_CONVERT_BASE))
	{
		$convert_base = new GBP_CONVERT_BASE;
	}
	
	/**
	 * Feature test imports (ajaxed to us)
	 * non-JSON, in our database 'fulltests' table
	 */
	if(class_exists(GBP_CONVERT_FULLTESTS))
	{
		$import_fulltests = new GBP_CONVERT_FULLTESTS;
	}
	


?>

<!--header for inserted form-->

    	<header>
		
		<div id="err-list">
		<?php
			echo "<strong>ERROR:</strong>";
			print_r($prop->get_error());
		?>
		</div>       	
            
		<h2 class="clearfix">Utility Form</h2>

<!-- create a horizontal menu, not a tabbed list -->
		
		<nav class="horizontal-menu">
			<ul id="utility-menu" class="highlight-panel">
				<li><a href="#gbp-config">Configure</a></li>
				<li><a href="#gbp-import-fulltests">Import Feature Tests</a></li>
				<li><a href="#gbp-import">Import DB</a></li>
				<li><a href="#gbp-validate">Validate GBP</a></li>
			</ul>
		</nav>
			
    	</header>
        
<!--section containing form (in a section)-->

    	<section class="clearfix">

<!--since properties depend on each other, we do global validations (all sources and components)
		1. check for valid source and component assignments
        2. check for valid dependencies
        3. look for 'orphan' records not accessed above
        4. Print a relational grid, with GBP at the left, showing links and component links
-->

<!--set GBP configuration properties, specific to the install-->
		
		<div id="gbp-config" class="accordion-open accordion">
			
			<form method="post" id="gbp-config-form" action="php/set_gbp_config.php">
				
				<fieldset class="highlight-panel">
					
					<legend>Configure GBP:</legend>
					
<!--get a list of components specific to GBP configuration -->

				<?php
					$gbp_configs = $prop->get_all_properties($SOURCE_PRIMARY, $COMPONENT_GBP, false, true);
					foreach($gbp_configs as $config)
					{
						//print_r($config);
						echo '<div class="config-box clearfix">'."\n";
						echo '<div class="config-left">'."\n";
						echo $config['name'];
						echo "\n<br>";
						echo "component lock:".$config['component_lock'];
						echo "<br>\n";
						echo "exe lock:".$config['exe_lock'];
						echo "<br>\n";
						switch($config['datatype']['name']) 
						{
							case "boolean":
									echo '<input type="radio" name="'.$config['id'].'" id="'.$config['id'].'-true" value="true" class="inline"><label class="inline" for="'.$config['id'].'-true">True</label>'."\n";
									echo '<input type="radio" name="'.$config['id'].'" id="'.$config['id'].'-false" value="false" class="inline"><label class="inline" for="'.$config['id'].'-false">False</label>'."\n";
									break;
							case "string":
									echo '<input type="text" name="'.$config['id'].'" id="'.$config['id'].'" value="'.$config['id'].'" size="40">'."\n";
									break;
								
								default:
									break;
						}
						echo '</div>';
						echo '<div class="config-right">'."\n";
						echo $config['description'];
						echo "\n<br>";
						echo "</div>"; //config-right
						echo "</div>"; //config-box
						echo "<hr>\n";
					}
					?>
				</fieldset>
				
			</form>
			
		</div>
	 
<!--import data from another database into GBP -->
		
		<div id="gbp-import" class="accordion-closed accordion">
			
			<form method="post" id="gbp-import-form">
				
				<fieldset class="highlight-panel">
					
					<legend>Import from another Database:</legend>
					
					<p>
						<select name="alt-db-sources" id="alt-db-sources">
						<?php
							$sources_arr = $convert_base->get_convert_sources(array('gbp'));
							$ct = count($sources_arr);
							$pos = 0;
							
							if($ct > 0)
							{
								foreach($sources_arr as $source)
								{
									if($pos == 0)
									{
										echo '<option value="'.$source['name'].'" selected="selected">'.$source['title'].'</option>'."\n";
										$selected_source_name = $source['name']; //NOTE: we get name instead of Id
									}
									else
									{
										echo '<option value="'.$source['name'].'">'.$source['title'].'</option>'."\n";
									}
									$pos++;
									
								}
							}
						?>
						</select>
						
						<select name="alt-db-file" id="alt-db-file">
						<?php	
							$alt_db_files = $convert_base->scan_for_files($dir.'/import/'.$selected_source_name.'/');
							$ct = count($alt_db_files);
							if($ct > 0)
							{
								if($ct > 1)
								{
									echo '<option value="all">Convert All</option>'."\n";
								}
								
								foreach($alt_db_files as $db_file)
								{
									echo '<option value="'.$db_file['name'].'">'.$db_file['name'].'</option>'."\n";
								}
							}
						
						?>
						</select>
						<span id="alt-db-file-num"><?php echo '('.$ct.')'; ?></span>
						
						<!--submit for conversion-->
						<input style="display:block;" type="submit" name="subb" id="alt-db-file-subb" class="subb" value="Commit Changes to DB">
					</p>
				   
				</fieldset>
				
			</form>
		  
		</div>
	
<!-- import feature detects from running GBP installations into GBP database -->

	<div id="gbp-import-fulltests" class="accordion-closed accordion">
		
		<!-- code should read import tables, allow edits, connect to likely client. First read should
		load the imports. Second should Ajax details and edit fields-->
		
		<form method="post" id="gbp-import-fulltests-form" action="php/merge_uatests_process.php">
			
			<fieldset class="highlight-panel">
				
			<legend>Import Feature Tests</legend>
			
			<!--this is added dynamically to keep from slowing things down-->
			<table id="import-fulltests-table">
				<thead>
					<tr>
						<th>Date</th>
						<th>Referer</th>
						<th>User-Agent</th>
						<th>Client</th>
						<th>Version</th>
						<th>Matched</th>
						<th>Num Props</th>
						<th class="center">Action</th>
					</tr>
				</thead>
				
				<tbody id="import-fulltests-value">
				
				</tbody>
				
			</table>
			
			
			</fieldset>
		</form>

	</div>

<!--validate the database-->

	<div id="gbp-validate" class="accordion-closed accordion">
		
        	<form method="post" id="gbp-validate-form" action="php/validate_form_process.php">
			
			<fieldset class="highlight-panel">
			
                	<legend>Validate the Database:</legend>
				
				<table class="fullwidth">
                			<tbody>
						<tr>
							<td>
								<input type="checkbox" name="valid-names" id="valid-names">
								<label for="valid-names">Valid Property Names</label>
							</td>
							
							<td>
								<input type="checkbox" name="locked-names">
								<label for="locked-names">Generate Locked Names List</label>
							</td>
							</tr>
						<tr>
							<td>
								<input type="checkbox" name="valid-source" id="valid-source">
								<label for="valid-source">Valid Source Assignments</legend>
							</td>
							<td>
								<input type="checkbox" name="valid-dependency" id="valid-dependency">
								<label for="valid-depenency">Valid Dependencies</legend>
							</td>
						</tr>
						<tr>
							<td>
								<input type="checkbox" name="orphan-records" id="orphan-records">
								<label for="orphan-records" name="orphan-records">Orphan Records</legend>
							</td>
							<td>
								<input type="checkbox" name="naming-conventions" id="naming-conventions">
								<label for="naming-conventions">Naming Conventions</legend>
							</td>
           					</tr>
						<tr>
							<td>
								<input type="checkbox" name="valid-records" id="valid-records">
								<label for="valid-records">Valid Records</label>
							</td>
							<td>
								<input type="checkbox" name="required-properties" id="required-properties">
								<label for="required-properties">Required Properties are Defined</label>
							</td>
						</tr>
					    
					</tbody>
				    
				</table>
				
				<input type="submit" name="subb" class="subb" value="Validate The Database">
			
			</fieldset>
		
		</form>
		
	</div><!--end of validate-->


   </section><!--end of section containing form-->
       
<!--footer for form-->

   <footer>
		<p>
<!--footer for form-->
		</p>
   </footer>

<!--local form javascript goes here-->

	<script>
		
		/** 
		 * -------------------------------------------------------------------------
		 * methods that get current form control values, filled in by the
		 * initial PHP load
		 * -------------------------------------------------------------------------
		 */

		
		/** 
		 * ------------------------------------------------------------------------- 
		 * get shared values
		 * ------------------------------------------------------------------------- 
		 */
		
		//get the divs that form visible or invisible folds of the accordion for the top horizontal menu
		
		var folds = document.getElementsByClassName("accordion");
		
		/**
		 * -------------------------------------------------------------------------
		 * utility functions
		 * -------------------------------------------------------------------------
		 */
		
		
		/**
		 * @method createFullTestCompareColumns
		 * create a table showing values to import, plus values already in the database, with
		 * checkboxes allowing user to turn some imports on or off
		 * @param {Object} obj object with sub-objects named for properties, inside them a
		 * description of current db values and the import value, plus changes that will happen
		 * if the import is committed
		 */
		function createFullTestCompareColumns(obj) {
			
			/**
			 * insert GBP_IMPORT_FULLTESTS values for 'CMD'
			 */
			
			//get the client-version info
			
			var versionIndex = parseInt(obj.client_version.version * 100);
			console.log("versionIndex:"+versionIndex);
			
			var tBodyStr = '';
			var updateNone           = '';
			var updateConfidenceOnly = '';
			var updateValue          = '';
			var updateInsertValue    = '';
			var updateDeleteValue    = '';
			var updateInvalidValue   = '';
			var updateUnknownValue   = '';
			var rowFrag              = ''
			var endRow               = "</td></tr>\n";
			var subHeader            =  '<tr><td style="background-color:#ddd;text-align:center;" colspan="5">';
			
			for(var i in obj) {
				
				if(i !== "client_version" && i !== 'client')  {
					
					var prop = obj[i][versionIndex];
					
					var row = '<tr><td><input type="checkbox" name="importx'+prop.property_id+'" value="import" checked="checked"></td><td><strong>'+i+'</strong></td><td>'+prop.client_property_value+'</td><td>'+prop.property_value_import+'</td><td>';
					row += prop.ACTION + endRow;
					
					switch(prop.CMD) {
						case 'UPDATE_NONE':
							updateNone += row;
							break;
						case 'UPDATE_CONFIDENCE_ONLY':
							updateConfidenceOnly += row;
							break;
						case 'UPDATE_VALUE':
							updateValue += row;
							break;
						case 'UPDATE_INSERT_VALUE':
							updateInsertValue += row;
							break;
						case 'UPDATE_DELETE_VALUE':
							updateDeleteValue += row;
							break;
						case 'UPDATE_INVALID':
							updateInvalidValue += row;
							break;
						default:
							updateUnknownValue = "UNKNOWN";
							break;
						
					} //end of switch
					
				} //end of leaving out client-version or client
				
			} //end of main loop
			
			//add the table rows for each import state
			
			if(updateNone.length > 1)           tBodyStr += subHeader + '<strong>No Updates</strong></td></tr>\n' + updateNone;
			if(updateConfidenceOnly.length > 1) tBodyStr += subHeader + '<strong>Confidence Level Only</strong></td></tr>\n' + updateConfidenceOnly;
			if(updateValue.length > 1)          tBodyStr += subHeader + '<strong>Update Existing Value</strong></td></tr>\n' + updateValue;
			if(updateInsertValue.length > 1)    tBodyStr += subHeader + '<strong>Insert New Values</strong></td></tr>\n' + updateInsertValue;
			if(updateDeleteValue.length > 1)    tBodyStr += subHeader + '<strong>Delete Existing Values</strong></td></tr>\n' + updateDeleteValue;
			if(updateInvalidValue.length > 1)   tBodyStr += subHeader + '<strong>Invalid Imports</strong></td></tr>\n' + updateInvalidValue;
			
			return tBodyStr;
		}
		
		
		/**
		 * @method ajaxImportFullTest
		 * import a test into the system for a user-agent, in import_fulltests, including all associated
		 * individual feature tests in import_fulltests_results
		 * @param {DOMFormObject} target the button that triggered the event, with
		 * id = the id of the import in import_fulltests
		 */
		function ajaxImportFullTest(target) {
			
			console.log("in ajaxImportFullTest");
			
			GBPInitializr.showSpinner('spinner-win');
			
			//fulltestId is in the name= field
			
			var ftid = target.name.split('-')[0];
			
			console.log("ftid:"+ftid);
			console.log("sel id:"+ftid+'-matched-versions');
			
			//client-version id is in the select for a specific client-version
			
			var sel = GBPInitializr.getElement(ftid+'-matched-versions');
			if (sel) {
				
				var clvid = GBPInitializr.getCurrentSelect(sel);
				var clvSelText = GBPInitializr.getCurrentSelectText(sel);
				
				console.log("clvid:"+clvid);
				
				//get new import and current client-property data, and compare
				
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=compare_fulltests_results&ftid=' + ftid + '&clvid=' + clvid, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response,["apicall", "apiresult", "error"]);
					
					if (JSONObject) {
						
						//create a caption to the table
						
						var captionStr = "<span>Import Status for id</span>";
						
						//create a table with old and new test results side-by-side
						
						var tblStr = '<table id="import-fulltests-commit-table">\n<thead>\n<th>Use</th>\n<th>Property</th>\n<th>Current Value</th>\n<th>Import</th>\n<th>Action That Will Be Taken</th></thead>\n';
						
						tblStr += "<tbody>\n";
						
						//create the table
						
						tblStr += createFullTestCompareColumns(JSONObject);
						
						tblStr += "</tbody>\n"
						
						tblStr += "</table>\n"
						
						GBPInitializr.showPoppup(tblStr, 740, 400, "Import A Browser Test for:"+clvSelText, function () {
							
							/*
							 * construct the array of unchecked boxes from the form. We don't use JSON, but
							 * instead create an array from part of the name= value defined for each checkbox
							 * in the format importxXXXX, where XXXX refers to the property_id we are setting a
							 * value for in this particular client-version. Output to Ajax is a comma-delimited
							 * list of numbers corresponding to the property_ids
							 */
							var idArr = [];
							var inputs = document.getElementById('import-fulltests-commit-table').getElementsByTagName('input');
							
							for(var i = 0; i < inputs.length; i++) {
								//console.log("inputs["+i+"]]");
								if (inputs[i].checked != true) {
									var propId = inputs[i].name.split('x');
									idArr.push(propId[1]);
								}
							}
							
							var idStr = idArr.toString();
							console.log("idStr for non-imported properties:"+idStr);
							
							//hide the poppup here, rather than at the end
							
							GBPInitializr.hidePoppup();
							
							//send the request
							
							GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=commit_fulltests_results&ftid=' + ftid + '&clvid=' + clvid + '&ign='+idStr, function (response) {
								var JSONObject = GBPInitializr.processJSON(response,["apicall", "apiresult", "error"]);
								if (JSONObject) {
									
								} //end of valid JSONObject in callback
								
							}, //end of callback for .ajaxRequest()
							
							'get');
							
							} //end of callback function for .showPoppup()
							
						); //end of showPoppup call
						
					}
					else {
						//invalid JSONObject (probably a false)
						alert("Import could not be set up for import id:"+ftid);
					}
					
					GBPInitializr.hideSpinner('spinner-win');
				
				}, //end of callback
				
			'get'); //end of ajaxRequest()	
			
			}
			
		}
		
		
		/**
		 * @method ajaxDeleteFullTest
		 * delete a feature test waiting to be imported into import_fulltests, including all associated
		 * individuals tests in import_fulltests_results
		 * @param {DOMFormObject} target the button that triggered the event, with an
		 * id = the id of the import in import_fulltests
		 * 
		 */
		function ajaxDeleteFullTest(target) {
			
			console.log("in ajaxDeleteFullTest");
			
			if (target.name) {
				
				var ftid = target.name.split('-')[0];
				
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=delete_fulltests_results&ftid=' + ftid, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response);
					
					if (JSONObject) {
						if (JSONObject.apiresult === "true") {
							
							//delete the appropriate table row
							
							GBPInitializr.deleteTableRow(target);
							GBPInitializr.showMessage("fulltest deleted");
						}
						else {
							GBPInitializr.showMessage("ERROR: fulltest failed to delete");
						}
					}
					
				}, //end of callback
				
				'get'); //end of ajaxRequest()
			
			}
			
		}
		
		
		/**
		 * @method createVersionsSelect
		 * create a <select> for the top matched versions
		 * @param {Number} id id of import record
		 * @param {Array} $matches - a set of matches
		 */
		function createVersionsSelect (id, client_version, max_width, matches)
		{
			var sel = 'none';
			
			if (matches && (matches.length > 0)) {
				
				//make the select
				var selId = id+'-matched-versions';
				
				sel = '<select name="'+selId+'" id="'+selId+'" style="width:8em">'+"\n";
				for(var i = 0; i < matches.length; i++) {
					
					/**
					 * option contains the client-version id
					 */
					var matchname = matches[i].versionname;
					
					sel += '<option value="'+matches[i].id+'">'+matchname+'</option>'+"\n";
				}
				sel += "</select>\n";
			}
			
			return sel;
		}
		
		
		/**
		 * @method createFullTestTableColumns
		 * create the columns <td>'s for the dependency table
		 * as a string (ajaxShowPropertyForm) or as a row object (dynamic update with a new dependency)
		 * @param {DOMObject} obj object with properties for controls
		 * @param makeElement if true, create table dynamically
		 * @param row if makeElement is true, row should be a DOMElement table row attached to a table already
		 * @return {String|DOMTableObject} either a string representing the row, or the DOMObject for the rows
		*/
		function createFullTestTableColumns(tblObj, tbl, tblBody, highlightClass, highlightNum) {
		
			var tdStart, ct = 0;
			
			for (var i in tblObj) {
				
				var obj = tblObj[i];
					var buttons = '<input type="button" class="subb" name="'+obj.id+ '-import" id="'+obj.id+'-import" value="import" onclick="ajaxImportFullTest(this)">' +
					'<input type="button" class="subb" name="'+obj.id+'-del"  id="'+obj.id+'-del" value="delete" onclick="ajaxDeleteFullTest(this)">';
					
				var row = tblBody.insertRow(-1); //into <tbody>, always last
				
				//date
				
				var cell = row.insertCell(-1);
				if (ct == highlightNum) {
					cell.className = highlightClass;
				}
				cell.innerHTML = obj.date_test; //date
				
				//referer
				
				cell = row.insertCell(-1);
				if (ct == highlightNum) {
					cell.className = highlightClass;
				}
				cell.innerHTML = obj.referer;
				
				//user-agent
				
				cell = row.insertCell(-1);
				if (ct == highlightNum) {
					cell.className = highlightClass;
				}
				cell.innerHTML = obj.user_agent;
				
				//client determined by sniffing user-agent (if UA_ANALYZE is available)
				
				cell = row.insertCell(-1);
				if (ct == highlightNum) {
					cell.className = highlightClass;
				}
				cell.innerHTML = obj.client;
				
				//client-version determiend by sniffing user-agent (if UA_ANALYZE is available)
				
				cell = row.insertCell(-1);
				if (ct == highlightNum) {
					cell.className = highlightClass;
				}
				cell.innerHTML = obj.client_version;
				
				//matched clients in a select list
				
				cell = row.insertCell(-1);
				if (ct == highlightNum) {
					cell.className = highlightClass;
				}
				cell.innerHTML = createVersionsSelect(obj.id, obj.client_version, obj.max_width, obj.matches);
				
				//numProps
				
				cell = row.insertCell(-1);
				if (ct == highlightNum) {
					cell.className = highlightClass;
				}
				cell.innerHTML = obj.found_rows;
				
				//add and delete buttons
					
				cell = row.insertCell(-1);
				if (ct == highlightNum) {
					cell.className = highlightClass + ' center';
				}
				else {
					cell.className = 'center';	
				}
				
				cell.innerHTML = buttons;
				
				ct++;
			}	
			
		}
		
		
		/** 
		 * -------------------------------------------------------------------------
		 * ajax methods
		 * -------------------------------------------------------------------------
		 */

		 
		 /**
		  * @method getRadioSetValue
		  */
		 function getRadioSetValue(frm, target) {
			console.log("in getRadioSetValue()");
			switch(frm.id) {
				
			}
		 }
		 
		 
		 /**
		  * @method getCheckBoxValue
		  */
		 function getCheckboxValue(frm, target) {
			console.log("in getCheckBoxValue()");
			switch(frm.id) {
				
			}
		 }
		 
		 
		 /**
		  * @method getButtonValue
		  */
		 function getButtonValue(frm, target) {
			console.log("in getButtonValue()");
			switch(frm.id) {
				
				case 'gbp-config-form':
					
					break;
				
				case 'gbp-import-fulltests-form':
					
					break;
				
				case 'gbp-import-form':
					//action="php/import_db.php"
					
					break;
				
				case 'gbp-validate-form':
				
					break;
				default:
					break;
			}
		 }
		
		
		/**
		 * -------------------------------------------------------------------------
		 * @method getSelectFieldValue
		 * get the value of a select field, and trigger changes
		 * @param {DOMElement} frm the form we are checking
		 * @param {DOMESelectField} the <select> in the form
		 * -------------------------------------------------------------------------
		 */
		function getSelectFieldValue(frm, target) {
			
			switch(frm.id) {
				
				case 'gbp-config-form':
					break;
				
				case 'gbp-import-form':
					
					switch(target.name) {
						
						case 'alt-db-sources':
							
							//get the value of the select field, which should be the name of the alt-source
							
							var fldv = GBPInitializr.getCurrentSelect(target); //value of select field
							
							//get import files associated with this alt_source
							console.log("fldv:"+fldv);
							
							GBPInitializr.showSpinner('spinner-win');
							
							GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=change_alt_db_source&fldv=' + fldv, function (response) {
								
								
								
								//remove everything except file list
								
								var JSONObject = GBPInitializr.processJSON(response,["apicall", "apiresult", "error"]);
								var selContents = '';
								var ct = Object.keys(JSONObject).length;
								
								if(ct > 1) {
									selContents = '<option value="all">Convert All</option>\n';
								}
								else if(ct == 0) {
									selContents = '<option value="<?php echo $NO_RECORD; ?>">No Records</option>\n';
								}
								
								for (var key in JSONObject) {
									var obj = JSONObject[key];
									if(obj.name) {
										selContents += '<option value="' + obj.name + '">' + obj.name.encodeHTMLChars() + '</option>\n';
									}
								}
								
								//(re) set the source import pulldown
								
								var selFileList = document.getElementById("alt-db-file");
								
								var result = GBPInitializr.setSelOptions(selFileList, selContents); //for IE compatibility
								
								//update length of select
								
								document.getElementById('alt-db-file-num').innerHTML = '('+ct+')';
								
								GBPInitializr.hideSpinner('spinner-win');
								
								}, //end of callback
							
							'get'); //end of ajaxRequest()
							
							break;
						
						default:
							break;
					}
					break;
				
				case 'gbp-import-fulltests-form':
					break;
				
				case 'gbp-validate-form':
					break;
				
				default:
					break;
			}
			
		}
	
	
		/**
		 * ------------------------------------------------------------------------
		 * @method getListValue
		 * show the item referenced by the given <ul> list, and hide the others
		 * used for the top horizontal menu on this form
		 * ------------------------------------------------------------------------
		 */
		function getListValue(list, target) {
			var currHashTag = target.href.split('#')[1];
			console.log("currHashTag:"+currHashTag);
			for (var i in folds) {
				fold = folds[i];
				
				if (fold) {
					
					if (fold.id == currHashTag) {
						if (GBPInitializr.hasClass(fold, "accordion-closed")) {
							GBPInitializr.removeClass(fold, "accordion-closed");
						}
						GBPInitializr.addClass(fold, "accordion-open");
						
						//do any initialization required when we reveal a panel
						
						switch(currHashTag) {
							
							//GBP configuration
							
							case 'gbp-config':
								break;
							
							//Import browser tests
							
							case 'gbp-import-fulltests':
								
								//load the current import list onscreen
								
								GBPInitializr.showSpinner('spinner-win');
								
								GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_fulltests_list', function (response) {
									
									GBPInitializr.hideSpinner('spinner-win');
									
									//remove everything except file list
									
									var JSONObject = GBPInitializr.processJSON(response,["apicall", "apiresult", "error"]);
									var selContents = '';
									if (JSONObject) {
										
										console.log("got back fulltests list");
										
										var ct = Object.keys(JSONObject).length;
										
										if (ct > 0) {
											var tbl          = GBPInitializr.getElement('import-fulltests-table');
											var tblBody      = GBPInitializr.getElement('import-fulltests-value');
											createFullTestTableColumns(JSONObject, tbl, tblBody, 'row-select', 0);
										}
									}
									else {
										console.log("getListValue() - JSONObject not returned");
									}
									
									}, //end of callback
								
								'get'); //end of ajaxRequest()
								
								break;
							
							//import data from another database
							
							case 'gbp-import':
								break;
							
							//validate the Initializr database
							
							case 'gbp-validate':
								break;
							
							default:
								break;
						}
					
					}
					else {
						if (GBPInitializr.hasClass(fold, "accordion-open")) {
							GBPInitializr.removeClass(fold, "accordion-open");
						
						}
						GBPInitializr.addClass(fold, "accordion-closed");
					}
				}
			}
		}
	
	
		/**
		 * @method getTableRowValue
		 * process a mouse click in a table row
		 * @param {DOMElement} target the actual target of the click
		 * @param {DOMElementArray} cells an array of the columns <td> in the table row
		 * @param {Number} rowNum the position of the row in the table
		 */
		function getTableRowValues(target, tbl, cells, rowNum) {
			console.log("in getTableRowValues, cells:"+cells+" rowNum:"+rowNum);
			
			//highlight the row
			
			var rows = tbl.rows;
			var len = cells.length;
			
			for(var i = 0; i < tbl.rows.length; i++) {
				if(i != rowNum) {
					var oldCells = rows[i].cells;
					for(j = 0; j < len; j++) {
						GBPInitializr.removeClass(oldCells[j], 'row-select');
						GBPInitializr.addClass(oldCells[j], 'row-unselect');
					}
				}
				else {
					for(j = 0; j < len; j++) {
						GBPInitializr.removeClass(cells[j], 'row-unselect');
						GBPInitializr.addClass(cells[j],'row-select');
					}	
				}
			}
			
			
			//TODO: process the target

		}
	
	
		/** 
		 * -------------------------------------------------------------------------
		 * initialization
		 * -------------------------------------------------------------------------
		 */
		
		/**
		 * @method addTableRowDelegateEvents
		 * add events to individual rows in an HTML table
		 */
		function addTableRowDelegateEvents(tbl) {
			
			GBPInitializr.addEvent(tbl, "click", function(e) {
				
				//this function makes the rows click-able independently of their elements
				
				var rows = tbl.rows; // or table.getElementsByTagName("tr");
				for (var i = 0; i < rows.length; i++) {
					rows[i].onclick = (function() { // closure
						var cnt = i; // save the counter to use in the function
						return function() {
							getTableRowValues(e.target, tbl, this.cells, cnt);
							//alert("row"+cnt+" data="+this.cells[0].innerHTML);
						}
					})(i);
				}
				GBPInitializr.stopEvent(e); //stop propagation
				},
			
			true);
		}

		
		/**
		 * @method addListDelegatEvents
		 * additional delegate events for a form
		 */
		function addListDelegateEvents(list) {
		
			GBPInitializr.addEvent(list, "click", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				
				if (target.href) {
				
					if(target.href.indexOf('#') !== -1) {
						getListValue(list, target); //control clicked on
					}
				}
				GBPInitializr.stopEvent(e); //stop propagation
				},
			true);
		
		}
		
		
		/**
		 * add select events to the forms(s) on the page
		 */
		function addFormDelegateEvents(frm) {
			
			//select elements
			
			GBPInitializr.addEvent(frm, "change", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('select') !== -1) {
					console.log("addSelectEvents(), .addEvent('change') or select blur form event for:"+target.type);
					getSelectFieldValue(frm, target); //select control
				}
				GBPInitializr.stopEvent(e); //stop propagation
				},
			true); //true=reverse bubbling to delegate onblur to a non-input element
			
			//click elements, and select in oldIE and old Opera
			
			GBPInitializr.addEvent(frm, "click", function(e) {
				
				if (target.type.indexOf('select') !== -1) {
				
					/**
					 * old IE and Opera don't work properly with 'change' events attached via addEvent,
					 * even when the event is attached. It DOES pass the 'click' event when we select a
					 * menu. So we detect the click event, and run select only
					 * if we are in old IE. The user may have to keep the mouse down during the
					 * menu selection.
					*/
					if (GBPInitializr.isOldIE()) {
						console.log("addFormDelegateEvents(), addEvent(click) - OldIE did 'click' target:"+target.type);
						getSelectFieldValue(frm, target); //select control in these old browsers
					}
				}
				else if (target.name) { //standard form controls
					
					if(target.type.indexOf('radio') !== -1) {
						getRadioSetValue(frm, target); //control clicked on
					}
					else if (target.type.indexOf('checkbox') !== -1) {
						getCheckBoxValue(frm, target);
					}
					else if (target.type.indexOf('button') !== -1) {
						getButtonValue(frm, target);
					}
					else if (target.type.indexOf('submit') !== -1) {
						getButtonValue(frm, target);
					}
				}
				
				GBPInitializr.stopEvent(e); //stop propagation
				},
			true); //true=reverse bubbling to delegate onblur to a non-input element
			
		} //end of addFormDelegateEvents
	
	
		/**
		 * @method finish
		 * method fired when a top-menu tab is clicked
		 */
		function finish() {
			console.log("top menu clicked in utility_form.php");
		}
	

		/**
		 * this is called by the parent index.php file for all forms in initializr
		 */
		function init() {
			console.log("initializing");
			addListDelegateEvents(document.getElementById("utility-menu"));
			addFormDelegateEvents(document.getElementById("gbp-import-form"));
			addTableRowDelegateEvents(document.getElementById("import-fulltests-value"));
		}
	
	
	
	</script>