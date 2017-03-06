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
	
	//get widths of clients fields (in the static html)
	
	$client_column_width_arr = $clt->get_column_widths('clients', array('name', 'title', 'description'));	

?>

<!--header for inserted form-->

<header class="form-header">
	
        <div id="err-list">
		<?php
			echo "no errors";
			print_r($clt->get_error());
		?>
	</div>    
	<h2 class="clearfix">GBP Create Clients and Versions:</h2>

</header>

<!--section containing form (in a section)-->

<section class="clearfix">

<!--form routes to a processing script, redirects back to this page-->

	<form id="gbp-client-edit-form" method="post" action="<?php echo 'index.php?state=clientcreate&status=updateclient'; ?>">
	
<!--select from the list of clients -->
		
		<fieldset id="gbp-client" class="highlight-panel">
			
			<legend id="client-title">New/Edit Client:</legend>
			
			<div id="client-fields-left">
				
				<div id="client-subfields-left">	
					<label for="gbp_client" accesskey="d">Client Name (group)</label>
					<select id="client" name="client" data-gbp-type="client" tabindex="1"> <!-- onchange="ajaxChangeBrowser(this);" -->
					<option value="<?php echo $NEW_RECORD; ?>">New Client</option>
					<?php
						$clients = $clt->get_all_clients();
						
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
						}
						
						?>
						</select>
				</div>
				
<!--when we have a client selected, show its information to edit-->
				
				<div>
					<label for="name">Name:</label>
					<input type="text" name="name" id="name" tabindex="2" maxlength="<?php echo $client_column_width_arr['name']; ?>">
					
					<label for="title">Title:</label>
					<input type="text" name="title" id="title" tabindex="3" maxlength="<?php echo $client_column_width_arr['title']; ?>">
				</div>
				
			</div>
			
			<div id="client-fields-right">
				<div>
					<label for="description">Description: <span class="mini-highlight"><a id="client-reference" href="#">ref</a></span></label>
					<textarea name="description" id="description" rows="7" cols="40" tabindex="4" maxlength="<?php echo $client_column_width_arr['description']; ?>"></textarea>
				</div>
				
				<div id="delete-property">
					<input type="button" name="delete" class="subb" id="client-delete" value="delete client">
				</div>	
			</div>
			
<!--delete for client, plus associated version data-->
			
		</fieldset>
		
		<fieldset id="gbp-client-version-list" class="highlight-panel">
			
			<legend id="client-property-breadcrumb">Version List</legend>
			
			<div id="table-bkgnd">
				
			<table id="client-version-table">
				<thead>
					<tr>
						<th>Curr. Version</th>
						<th>Version</th>
						<th>Common Name</th>
						<th>Released</th>
						<th>Search</th>
						<th>Comments</th>
						<th class="center">Action</th>
					</tr>
				</thead>
				
				<tbody id="client-version-value">
					<tr>
						<td>&nbsp;</td><!--TODO: ADD A NEW FIELD BUTTON HERE-->
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
					</tr>
				</tbody>
				
			</table>
			
			</div>
			
		</fieldset>
		
        </form>
    
</section><!-- end of section containing form-->


<footer><!-- footer for form-->
    <p>
	
    </p>
</footer>

<!--form specific javascript-->

<script>
	
	/** 
	 * -------------------------------------------------------------------------
	 * methods that get current form control values, filled in by the
	 * initial PHP load
	 * -------------------------------------------------------------------------
	 */
	
	
	
	/** 
	 * -------------------------------------------------------------------------
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
	 * @returns Object JSONObject - JSON return string parsed to object with all 
	 * allowed search groups.
	 * -------------------------------------------------------------------------
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
	 * @method getClientFieldSizes
	 * get the allowed sizes of client fields
	 * 
	 */
	function getClientFieldSizes() {
		var response = [];
		<?php
			//$client_column_width_arr is set at the top of this page in PHP
			foreach($client_column_width_arr as $key => $value)
			{
				echo "response['".$key."'] = $value; ";
			}
		?>
		
		return response;
	}

	
	/**
	 * @method getClientVersionFieldSizes()
	 * get allowed text field sizes for client-version text (versionname, version)
	 * these are computed in GBP_BASE directly from the table, so we can vary columns in the db as necessary
	 * NOTE: we don't do this for the client fields, since they are static HTML
	 * @returns {Array} an associative array with 0 or more elements, from the database
	 */
	function getClientVersionFieldSizes() {
		var response = [];
		<?php
			$arr = $prop->get_column_widths('clients_versions', array('versionname', 'version', 'comments'));
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
	

	/**
	 * get a list of references associated with this client
	 * @param {String} tableName the name of the SQL table we are processing
	 * @param {Number} itemId the record of the row we want to get
	 * @returns {Boolean} if record already exists, return true, else false
	 */
	function ajaxGetClientReference(tableName, itemId) {
		
		console.log("in ajaxGetClientReference");
		
		if(itemId == <?php echo $NEW_RECORD; ?>) {
			return false;
		}
		
		//we have a defined client. So show the references associated with it
		
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_reference_list&tnm=' + tableName + '&iid=' + itemId, function (response) {
			
			var JSONObject = GBPInitializr.processJSON(response, ["apiresult"]);
			
			if(JSONObject) {		
				console.log("returned a client reference");
				var refArr = [];
				for(var i in JSONObject) {
					refArr[i] = JSONObject[i];
				}
				
				//get the name of the property reference, and don't show the window for unsaved properties
				
				GBPInitializr.showMessage("opening client references");
				var selText = GBPInitializr.getCurrentSelectText(GBPInitializr.getElement("client"));
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
	 * get reference associated with a particular client-version
	 */
	function ajaxGetClientVersionReference(tableName, itemId, selText) {
	
		console.log("in ajaxGetClientVersionReference");
		
		//we have a defined client-version. So show the references associated with it
		
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_reference_list&tnm=' + tableName + '&iid=' + itemId, function (response) {
			
			var JSONObject = GBPInitializr.processJSON(response, ["apiresult"]);
			
			if(JSONObject) {		
				console.log("returned a client-version reference");
				var refArr = [];
				for(var i in JSONObject) {
					refArr[i] = JSONObject[i];
				}
				
				//get the name of the property reference, and don't show the window for unsaved properties
				
				GBPInitializr.showMessage("opening client-version references");
				return GBPInitializr.showRefWin(refArr, tableName, itemId, selText, 'reference-window');
			}
			else {
				console.log("no reference returned");
			}
			
		}, //end of callback
		
		'get'); //end of ajaxRequest()
	}
	
	
	/**
	 * change the current client (browser)
	 */
	function ajaxChangeBrowser() {
		//var browserList = GBPInitializr.getElement('client');
		ajaxShowClientVersionValues();
	}
	
	
	/**
	 * display the current client, along with versions. Provide a button for
	 * adding a new version to the version table
	 * @returns {undefined} 
	 */
	function ajaxShowClientVersionValues() {
		
		var clid = GBPInitializr.getCurrentClient();
		
		/**
		 * only make an Ajax call if we are working with an existing client. If 'New Client' was selected,
		 * just clear the table and return
		 */
		if (GBPInitializr.getCurrentClient() == '<?php echo $NO_RECORD; ?>') {
			clearClientForm();
			hideClientReference();
			hideClientDeleteButton();
			GBPInitializr.hideSpinner('spinner-win');
			
			return; //new client
		}
		
		GBPInitializr.showSpinner('spinner-win');
		
		/**
		 * get information for editing client data (independent of property request)
		 */
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_client&clid=' + clid, function (response) {
			
			//we don't hide ajax spinner yet
			
			var JSONObject = GBPInitializr.processJSON(response,["apicall", "apiresult", "error"]);
			
			if(JSONObject) {
				GBPInitializr.getElement('name').value = JSONObject.name;
				GBPInitializr.getElement('title').value = JSONObject.title;
				GBPInitializr.getElement('description').value = JSONObject.description;
				GBPInitializr.showMessage('client:' + JSONObject.title);
				
				//change the title
				
				var gbpSelText = GBPInitializr.getCurrentSelectText("client");
				if (gbpSelText) {
					GBPInitializr.getElement("client-title").innerHTML = JSONObject.title;
				}
				
				//since we have a client, show the delete button
				
				showClientReference();
				showClientDeleteButton();
			}
			else {
				
			}
			
			}, //end of callback
			
		'get');	//end of ajaxRequest()
		
		
		/**
		 * second Ajax call
		 * get all client versions associated with this client, using client_id
		 */
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=get_client_versions&clid=' + clid, function (response) {
			
			//since we do a straight loop through the JSON results, we have to remove non-record properties
			//TODO: ALWAYS put error objects into a different object, JSONError
			
			var JSONObject = GBPInitializr.processJSON(response, ["apicall", "apiresult", "error"]);
			
			if (JSONObject) {
				
				/** 
				 * create a table with the existing client-versions. we pass in a -1 so that none of the 
				 * rows are highlighted
				 */
				var tbl = document.getElementById("client-version-table");
				var tblBody = document.getElementById("client-version-value");
				
				//clear the table
				
				GBPInitializr.deleteTableContent(tbl);
				
				//create a new client-version table
				
				//WHERE IS SEARCHGROUP
				
				createClientVersionTableColumns (JSONObject, tbl, tblBody, 'row-highlight', -1);
				
				/** 
				 * create an empty field at the bottom for inserting a new client. 
				 */
				var obj = {};
				obj[0] = {};
				obj[0].id = newClientVersion;
				obj[0].version = '';
				obj[0].versionname = '';
				obj[0].releasedate = '';
				obj[0].comments = '';
				createClientVersionTableColumns(obj, tbl,tblBody, 'row-highlight', 0); //highlight new row
				
			} //end of if JSONObject
			
			GBPInitializr.hideSpinner('spinner-win'); //significant lag in constructing tables
			
			}, //end of callback
			
		'get'); //end of ajaxRequest()
	}
	
	
	/** 
	 * constant ids for a new, empty row at the bottom of the table. To get these, we pass
	 * 'new-client-version' as our pseudo-id. This uniquely identifies the new client-verion
	 * row in the table.
	 */
		var newClientVersion              = 'new-client-version';
		var newClientSearchGroupId        = 'new-client-version-search-group';
		var newClientVersionNameId        = 'new-client-version-versionname';
		var newClientVersionVersionId     = 'new-client-version-version';
		var newClientVersionReleaseDateId = 'new-client-version-releasedate';
		var newClientVersionCommentsId    = 'new-client-version-comments';
	
	
	/**
	 * @method createClientVersionCells
	 * create form fields for a row in the client-version table
	 * @param {Object} obj a single object with properties (not an array of objects)
	 * @returns {Object} an object with rows corresponding to sub-objects, and columns properties
	 * inside those objects.
	 */
	function makeClientVersionCells(obj) {
		
		clCells = {};
		
		//construct version field (property in 'properties' = 'version')
		if(!obj.versionname || obj.versionname.length == 0) {
			clCells['name'] = 'New Client-version';
		}
		else { 
			clCells['name'] = obj.versionname;
		}
		
		clCells['version'] =  '<input data-gbp-type="number" type="text" name="' + obj.id + '-version" id="'+obj.id+'-version" value="' + obj.version+'" size="8" maxlength="'+getClientVersionFieldSizes()['version']+'">';
		
		//construct title field (BROWSER NAME)
		
		clCells['versionname'] = '<input data-gbp-type="string" type="text" name="'+obj.id+ '-versionname" id="' + obj.id + '-versionname" value="' + obj.versionname+'" maxlength="'+getClientVersionFieldSizes()['versionname']+'">';
		
		//construct release date field (MySQL format, YYYY-MM-DD, column name in db is 'releasedate')
		
		clCells['releasedate'] = '<input data-gbp-type="date" type="date" name="'+obj.id+ '-releasedate" id="'+obj.id+'-releasedate" value="'+obj.releasedate+'" size="10">';
		
		//construct search group field
		
		/**
		 * make the pulldown for search group. We use event delegation, and specifically look for 
		 * select fields with an id of the form
		 * 'client-property version key'-search-group
		 * NOTE: returns an Array
		 */
		var searchGroupArr = getSearchGroup();
		
		//if we're creating a new client row (not in db), set to a default searchgroup
		
		if (!obj.searchgroup_id || typeof parseInt(obj.searchgroup_id) != "number") {
			console.log("just picking the first value");
			obj.searchgroup_id = searchGroupArr[0].id; //just pick the first value
		}
		
		//if we are new, set the search group to 'common'
		
		var searchGroup = '<select data-gbp-type="searchgroup" name="' + obj.id + '-search-group" id="'+obj.id +'-search-group">\n';
		
		var len = searchGroupArr.length;
		
		for(var i = 0; i < len; i++) {
			var id = searchGroupArr[i].id;
			var name = searchGroupArr[i].name;
			searchGroup += '<option value="' + id + '"';
			if(id == obj.searchgroup_id) {
				searchGroup += ' selected="selected"';
			}
			searchGroup += '>' + name + '</option>\n';
		}
		
		searchGroup += '</select>\n';
		
		clCells['searchgroup'] = searchGroup;
		
		//construct comments field
		
		clCells['comments'] = '<input data-gbp-type="string" class="inline-block" type="text" name="'+obj.id + '-comments" id="'+obj.id+'-comments" value="'+obj.comments+'" size="40" maxlength="'+getClientVersionFieldSizes()['comments']+'">';
		
		//add a reference link
		
		if (clCells['name'] == 'New Client-version') {
			clCells['reflink'] = '';
			clCells['spinnerdiv'] = '<div id="spinner-div" class="subb spinner-div">';
			
		}
		else {
			//add a reflink
			
			clCells['reflink'] = '<span class="mini-highlight" ><a id="'+obj.id+'-client-version-reference" href="#">ref</a></span>';
			
			//add a delete button
			
			clCells['spinnerdiv'] = '<input data-gbp-type="delete" type="button" class="subb" name="'+obj.id+'-delete" id="'+obj.id+'-delete'+'" value="delete">';
		}
		return clCells;
	}
	
	
	/**
	 * @method createClientVersionTableColumns
	 * create the client-version table columns <td>'s, using the Javascript HTML table DOM. We don't use
	 * newer methods or setAttribute for max compatibility with all browsers
	 * @param {Object} tblObj the object with values for the table. It is an array of row objects, with
	 * column values as properties in the row objects.
	 * @param {DOMTableObject} tbl a <table>
	 * @param {DOMTableObject} tblBody <tbody>
	 * @param {String} highlightClass if present, the CSS class to highlight the table row with
	 * @param {Number} highlightNum the number of the APPENDED row. It should normally be ZERO, unless
	 * we are highlighting a row other than the one we are inserting.
	 */
	function createClientVersionTableColumns (tblObj, tbl, tblBody, highlightClass, highlightNum) {
		
		var tdStart, ct = 0;
		
		for (var i in tblObj) {
			
			var obj = makeClientVersionCells(tblObj[i]);
			
			var row = tblBody.insertRow(-1); //into <tbody>, always last
			
			//version name (plain text)
			
			cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass;
			}
			cell.innerHTML = obj.name;
			
			//version (field)
			
			var cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass;
			}
			cell.innerHTML = obj.version;
			
			//version name (field)
			
			cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass;
			}
			cell.innerHTML = obj.versionname; //name (field)
			
			//release date (date field)
			
			cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass;
			}
			cell.innerHTML = obj.releasedate; //release date (date field)
			
			//search group (pulldown menu)
			
			cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass;
			}
			cell.innerHTML = obj.searchgroup; //search group (pulldown menu)
			
			//comments and reflink (field, hyperlink)
			
			cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass;
			}
			cell.innerHTML = obj.comments + obj.reflink; //comments plus reflink
				
			cell = row.insertCell(-1);
			if (ct == highlightNum) {
				cell.className = highlightClass + ' center';
			}
			else {
				cell.className = 'center';
			}
			cell.innerHTML = obj.spinnerdiv; //small spinner
			
			ct++;
		}
	}
	
	
	/** 
	 * update client-version search group
	 * @param clvid client allowed-version id (with '-search-group' concatenated)
	 * @param sgid search group id chosen by the user
	 */
	function ajaxUpdateClientVersionSearchGroupValues(clvid, sgid) {
		
		/**
		 * the clvid allowed-version id comes through as 'client property version'-search-group. So strip the 
		 * gunk and grab just the record id
		*/
		clvid = clvid.split('-')[0]; //searchgroup_id is id + '-search-group' attached
		
		console.log("in ajaxUpdateClientVersionSearchGroupValues, updating search group in db, clvid=" + clvid + " clvid=" + sgid);
		
		GBPInitializr.showSpinner('spinner-win');
		
		var clid = GBPInitializr.getCurrentClient();
		
		/** 
		 * clid  = client_id
		 * clvid = client_version_id
		 * sgid  = searchgroup_id
		 */
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_client_search_group&clvid='+clvid+'&sgid='+sgid, function (response) {
			
			var JSONOBject = GBPInitializr.processJSON(response, [], ["apiresult"]);
			GBPInitializr.hideSpinner('spinner-win');
			GBPInitializr.showMessage("search group updated");
			
			}, //end of callback
			
		'get'); //end of ajaxRequest()
		
	} //end of function
	
	
	/**
	 * @method ajaxInsertNewClientVersion
	 * insert a new client-version. This assumes that the client exists, and that the
	 * new client-version row at the bottom of the client-version table is correctly filled out
	 */
	function ajaxInsertNewClientVersion () {
		
		if (checkCompleteClientVersion()) {
			
			GBPInitializr.showMessage('inserting record');
			GBPInitializr.showSpinner('spinner-win');
			
			/**
			 * if the info has never been submitted, then submit the
			 * entire record for insertion
			 */
			var clid    = GBPInitializr.getCurrentClient();
			
			//mini-spinner in the table row being created
			
			GBPInitializr.showMiniSpinner('spinner-div');
			
			//we can only come here if we're in a 'new' row
			
			var sgid    = GBPInitializr.getCurrentSelect(newClientSearchGroupId);
			var nm      = GBPInitializr.getElement(newClientVersionNameId).value;
			var vers    = GBPInitializr.getElement(newClientVersionVersionId).value;
			var relDate = GBPInitializr.getElement(newClientVersionReleaseDateId).value;
			var comm    = GBPInitializr.getElement(newClientVersionCommentsId).value;
			
			console.log("clid:"+clid+" sgid:"+sgid+" nm:"+nm+" vers:"+vers+" relDate:"+relDate+" comm:"+comm);
			
			GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=insert_new_client_version&clid=' + clid + '&sgid=' + sgid + '&nm=' + nm + '&vers=' + vers + '&reldate=' + relDate + '&comm=' + comm, function (response) {
				
				var JSONObject = GBPInitializr.processJSON(response,[],["apiresult"]);
				
				if (JSONObject) {
					if (JSONObject.duplicate == 'name') {
						alert("duplicate client-version name");
						GBPInitializr.showMessage("Error: duplicate client-version '"+nm+"' already in database");
						console.log("ERROR: duplicate name already in database");
						clearClientVersionNewForm();
						return false;
					}
					else if (JSONObject.duplicate == 'version') {
						alert("duplicate client version");
						GBPInitializr.showMessage("Error: duplicate client version '"+vers+"' already in database");
						clearClientVersionNewForm();
						return false;
					}
					else {
						//we successfully inserted the record...but we need to check the name and title,
						//and update our select by inserting the new property and selecting it
						
						console.log("successful client-version insert");
						
						if (JSONObject.column_value) {
							
							//here we have to insert a table row
							
							var tbl = GBPInitializr.getElement('client-version-table');
							var tblBody = GBPInitializr.getElement('client-version-value');
							var len = tblBody.rows.length;
							
							//delete the row
							
							tblBody.deleteRow(-1); // a -1 removes the last row in the table
							
							//create the equivalent of an JSONObject retrned from the db
							
							var obj = {};
							obj.id                = JSONObject.column_value;
							obj.version           = vers;
							obj.versionname       = nm;
							obj.releasedate       = relDate;
							obj.searchgroup_id    = sgid;
							obj.comments          = comm;
							
							var tblObj = {};
							tblObj[0] = obj;
							createClientVersionTableColumns (tblObj, tbl, tblBody, 'row-select', -1);
							
							//add a new blank field again
							
							obj = {};
							obj.id = newClientVersion;
							obj.version = '';
							obj.versionname = '';
							obj.releasedate = '';
							//obj.searchgroup_id = '';
							obj.comments = '';
							
							tblObj = {};
							tblObj[0] = obj;
							createClientVersionTableColumns (tblObj, tbl, tblBody, 'row-highlight', 0); //highlight new row
							
							GBPInitializr.showMessage('new client-version:'+name+'('+nm+')');

						}
						
					}
				}
				else {
					console.log("failed to insert new client-version record on server, check JSONError");
				}
				
				console.log("HIDING THE BLASTED SPINNERS!!!!!!!!!!!!");
				GBPInitializr.hideMiniSpinner('spinner-div');
				GBPInitializr.hideSpinner('spinner-win');
				
			}, //end of callback
			
			'get'); //end of ajax request
		}
		else {
			console.log("ajaxInsertNewClientVersion, incomplete new client-version")
		}

	}
	
	/**
	 * delete a client and its versions
	 * @param {Number} name the record id of the client to delete
	 */
	function ajaxDeleteClient(name) {
		console.log("in ajaxDeleteClient, deleting client and associated versions="+name);

		//do the delete
		
		var clid = GBPInitializr.getCurrentClient();
		
		if (clid != name) {
			console.log("wow, in ajaxDeleteClient() client name mismatch!");
		}
		
		GBPInitializr.showSpinner('spinner-win');
		
		/** 
		 * clid  = client_id
		 */
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=delete_client&clid='+clid, function (response) {
			
			var JSONObject = GBPInitializr.processJSON(response, [], ['apiresult']);
			
			//reset select and remove client from the select control
			
			GBPInitializr.deleteSelectOptionByValue('client', clid);
			
			//reset the client form fields
			
			GBPInitializr.sortSelect('client', <?php echo $NO_RECORD; ?>, <?php echo $NO_RECORD; ?>);
			GBPInitializr.setSelectByOptionValue('client', <?php echo $NO_RECORD; ?>);
			ajaxShowClientVersionValues();
			GBPInitializr.showMessage('client deleted');
			GBPInitializr.hideSpinner('spinner-win');
			}, //end of callback
			
		'get'); //end of ajaxRequest()
		
		
	} //end of function
	
	
	/**
	 * delete a client-version record
	 * @param {DOMElement} target the target of the event, with name = the data- property, with record_id at name.split('-')[1]
	 */
	function ajaxDeleteClientVersion(target) {
		var clvid = target.name.split('-')[0];
		var clid = GBPInitializr.getCurrentClient();
		
		console.log("in ajaxDeleteClientVersion, deleting record id="+clvid);
		
		GBPInitializr.showSpinner('spinner-win');
		
		//do the delete
		
		/** 
		 * clid  = client_id
		 * clvid = client_version_id
		 */
		GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=delete_client_version&clvid='+clvid, function (response) {
			
			var JSONObject = GBPInitializr.processJSON(response, [], ['apiresult']);
			
			//now we have to delete one row from the table (assuming our target is in the table)
			
			console.log("delete table row for del button id:"+target.id);
			GBPInitializr.deleteTableRow(target);
			GBPInitializr.hideSpinner('spinner-win');
			GBPInitializr.showMessage('client version '+clvid+' deleted');
			}, //end of callback
			
		'get'); //end of ajaxRequest()
		
		
	} //end of function
	
	
	/**
	 * ------------------------------------------------------------------------
	 * update the client datatype string fields
	 * this function gets the input field that changed
	 * used with blur events for text fields
	 * @param {String} fieldName name of column in 'clients' table
	 * @param {String} value data to put in row at column fieldName
	 * @returns {Boolean} if updated, return true, else false
	 * ------------------------------------------------------------------------
	 */
	function ajaxUpdateClientVersionStringValues(name, value) {
		
		/**
		 *  NOTE: unlike client_property_history.php, we have multiple fields of the same
		 *  datatype coming into this function. So, we initially store the name= in the control as
		 *  id-fieldName. We split off the name and fieldName
		 */
		var nameField = name.split('-');
		var clvid     = nameField[0];
		
		console.log("in ajaxUpdateClientVersionStringValues");
		console.log("=========NAME IS: "+name+" nameField[0]"+nameField[0]+" nameField[1]"+nameField[1]+" datatype:"+typeof parseInt(nameField[0]));
		
		//don't try to process if we are a new field being edited
		
		if (nameField[0] == 'new' || (typeof parseInt(nameField[0]) != "number")) {
			console.log("in new record field, don't do an update");
			
			ajaxInsertNewClientVersion(); //only works all fields are complete
			return;
		}
		
		if (nameField[1] == "version") {
			if (!GBPInitializr.isVersion(value)) {
				alert(value+" for "+nameField[1] +" is not a valid version for a browser");
				return;
			}
		}
		
		/**
		 * we don't fire ajax for field updates, we just do a showMessage
		 * TODO: add spinner if there is a long delay
		 */
		GBPInitializr.showMessage("updating field...");
		
		if (nameField.length > 1) {
			console.log("WENT IN WITH NAMEFIELD > 1");
			var fieldName = nameField[1];
			
			//update individual fields in the client-version defined by id=clvid
			
			console.log("in ajaxUpdateClientVersionStringValues (client-version, id-fieldName), fieldName="+fieldName+" and value="+value);
			
			GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_client_version_field&clvid='+clvid+'&fldn='+fieldName+'&fldv='+value, function (response) {
				
				var JSONObject = GBPInitializr.processJSON(response, [], ['apiresult']);
				
				console.log("=================CAME BACK FROM UPDATE");
				
				if (JSONObject.apiresult != false || JSONObject.apiResult != undefined) {
					console.log("in updateClientVersionStringValues, update of client-version ok");
					
					if (!GBPInitializr.validServerInsert(JSONObject.column_value, value)) {
						alert("server changed inserted input");
					}
					
					/**
					 * if we update the 'versionname' field, we also want to update the first column in the
					 * given row in this table. So grab, it, and change its innerHTML
					 */
					if (fieldName.indexOf('versionname') !== -1) {
						var tblBody = document.getElementById('client-version-table');
						var row = GBPInitializr.getTableRow(tblBody, document.getElementById(name));
						row.cells[0].innerHTML = value;
					}
					
					GBPInitializr.showMessage("update client-version ok");
				}
				else {
					console.log("Error client-version record not updated");
				}
				
			}, //end of callback
			
		'get'); //end of ajaxRequest()	
			
			
		} //end of client-version update
		else {
			console.log("unknown client-version field WITHOUT a datatype-");
		} //end of client update
		
	}
	
	
	/**
	 * update a client, or insert new client
	 */
	function ajaxUpdateClientStringValues(name, value) {
		
		//fields with non second value are just names of fields on the form
			
		var clid = GBPInitializr.getCurrentClient();
		var fieldName = name;
			
		//if the record exists, update. Otherwise, create the new client record
			
		console.log("in ajaxUpdateClientStringValues (client form), fieldName="+fieldName+" and value="+value);
			
		if (clid == <?php echo $NO_RECORD; ?> ) {
			
			console.log("CLIENT EMPTY, so insert new record for client");
			/**
			 * NOTE: we get two blur events if we use an alert box here
			 */
			var nm     = GBPInitializr.getElement('name').value;
			var title  = GBPInitializr.getElement('title').value.capitalize(); //added as String.prototype
			var desc   = GBPInitializr.getElement('description').value;
			
			GBPInitializr.showMessage('inserting record');
			GBPInitializr.showSpinner('spinner-win');
			
			console.log("ABOUT TO RUN THE AJAX REQUEST.....");
				
			GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=insert_new_client&clid='+clid+'&nm='+nm+'&title='+title+'&desc='+desc, function (response) {
				
				var JSONObject = GBPInitializr.processJSON(response, [], ["apiresult"]);
				
				//update the select to show our new client
				
				if (JSONObject) {
					
					if (JSONObject.duplicate == 'name') {
							alert("duplicate name");
							GBPInitializr.showMessage("Error: duplicate client name already in database");
							console.log("ERROR: duplicate client name already in database");
							clearClientForm();
							return false;
						}
						else if (JSONObject.duplicate == 'title') {
							alert("duplicate title");
							GBPInitializr.showMessage("Error: duplicate client title already in database");
							console.log("ERROR: duplicate client title already in database");
							clearClientForm();
							return false;
						}
						
						else {
							//we successfully inserted the record...but we need to check the name and title,
							//and update our select by inserting the new property and selecting it
							
											console.log("GOING TO UPDATE................."); ////////////////////////////////////////////////
											//TODO: OPERA REFUSES TO UPDATE ITS PULLDOWN MENU. SO FORCE-UPDATE BY re-creating the whole 
											//thing, and inserting it (in other words, make Opera like IE)
							
							var newOption = GBPInitializr.insertSelectOption('client', JSONObject.column_value, title);
							GBPInitializr.sortSelect('client', <?php echo $NO_RECORD; ?>, <?php echo $NEW_RECORD; ?>);
							GBPInitializr.setSelectByOptionValue('client', newOption.value);
							ajaxShowClientVersionValues();
							
						}
				}
				else {
					console.log("ERROR: server did not return a response for inserting new client");
				} //end of insert
				
				GBPInitializr.showMessage("inserted new client:"+title);
				GBPInitializr.hideSpinner('spinner-win');
				
				}, //end of callback
				
			'get');
		}
		else {	
			console.log("client exists, so update "+fieldName+" with "+value);
			GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=update_client_field&clid='+clid+'&fldn='+fieldName+'&fldv='+value, function (response) {
				
				var JSONObject = GBPInitializr.processJSON(response, [], ['apiresult']);
				
				if (JSONObject) {
					
				}
				else {
					console.log("ERROR: server did not return response for updating existing client");
				}
				
				if (JSONObject) {
					switch (fieldName) {
						case 'name':
							GBPInitializr.showMessage("updated client name with:"+JSONObject.column_value);
							break;
						case 'title':
							GBPInitializr.showMessage("updated client title with:"+JSONObject.column_value);
							break;
						case 'description':
							GBPInitializr.showMessage("updated client description");
							break;
						default:
							console.log("updated unknown field");
							break;
					}
				}
				
				GBPInitializr.hideSpinner('spinner-win');
				
				}, //end of callback
				
			'get'); //end of ajaxRequest()
			
			
		} //we had a client record, so update

	}
	
	
	/** 
	 * update client Boolean values
	 * since we store the strings "true" and "false", we just pass this to the string updater
	 * @param {Number} clvid client-version id in the database (for a specific client-verion-property combination)
	 * @param {String} value value of client-version property
	 */
	function ajaxUpdateClientBooleanValues(clvid, value) {
		console.log("in ajaxUpdateClientBooleanValues, updating Boolean id="+clvid+" with value="+value);
	}
	
	
	/**
	 * ------------------------------------------------------------------------- 
	 * this function gets the input field that changed
	 * selects and routes based on dataType defined in data-gbp-type form field
	 * used with blur events for text fields
	 * -------------------------------------------------------------------------
	 */	
	function getClientVersionTextFieldValue(target) {
		
		if(target) {
			
			console.log("blur event noted, just left text field name="+target.name+", value="+target.value);
			var nm  = target.name;
			var val = target.value;
			
			//datatype
			
			var attr = target.getAttribute('data-gbp-type');
			
			if (attr) {
				
				switch(attr.toLowerCase()) {
					
					case "string":
						console.log("string field blurred, value:" + val);
						ajaxUpdateClientVersionStringValues(nm, val);
						
						if (nm.indexOf("title") !== -1) {
							var tblBody  = GBPInitializr.getElement('client-version-value');
							var rowCells = GBPInitializr.getTableRow(tblBody, target).cells;
							rowCells[0].innerText = val;
						}
						break;
					
					case "number":
						console.log("number field blurred, value:"+val);
						var versHalf = nm.split('-')[1];
						if(versHalf == 'version' && GBPInitializr.isVersion(val)) {
							ajaxUpdateClientVersionStringValues(nm, val);
						}
						else if (GBPInitializr.isNumber(val)) {
							ajaxUpdateClientVersionStringValues(nm, val);
						}
						else {
							GBPInitializr.showMessage("non-numeric value in numeric field");
						}
						break;
					
					case "location":
						break;
					
					case "dimensions":
						break;
					
					case "date":
						console.log("date field blurred, value="+val);
						var currDate = new Date(val);
						if (!currDate) {
							console.log("value "+val+" can't be converted to a date");
							GBPInitializr.showMessage("value "+val+" can't be converted to a date");
						}
						else if (GBPInitializr.isDateRange(new Date('1991-08-01'), currDate)) { //date of beta release for WorldWideWeb on NeXT
							ajaxUpdateClientVersionStringValues(nm, val);
						}
						else {
							if (nm.indexOf(newClientVersion) == -1) {
								alert("invalid date for client-version:"+val);
								GBPInitializr.showMessage("invalid date for client-version");
								target.value = ''; //reset to undefined date
							}
						}
						break;
					
					case "timestamp":
						break;
					
					default:
						console.log("undefined data-gbp-type for control, name:"+nm+", value:"+val);
						break;
				}
			} //end of switch
			else {
				console.log("data-gbp-type not defined for this control, name:"+nm+", value:"+val);
			} //end of if data-gbp-type present
			
		} //end of target test
	}
	
	
	/**
	 * @method getClientVersionReferenceValue
	 * get the id value of a reference, and show any references associated with that client-version
	 * @param {DOMElement} target hyperlink for ref
	 */
	function getClientVersionReferenceValue(target) {
		
		console.log("in clientVersionReferenceValue");
		if (target) {
			
			var ref = target.id.split('-');
			if (ref && ref[0]) {
				console.log("REF0:"+ ref[0]);
				
				//call the modal dialog listing this kind of reference
				
				var row = GBPInitializr.getTableRow('client-version-table', target.parentNode); //we're inside a parentNode (<span>)
				var versText = row.cells[0].innerHTML;
				ajaxGetClientVersionReference('clients_versions', ref[0], versText); //NOTE: clients_versions is a mysql table name, not css id
			}
		}
	}
	
	
	/**
	 * @method getClientTextFieldValue
	 * specific fields by name in the 'client' form. This includes all
	 * the the fields in the client fieldset, but not fields for individual client-versions
	 * 
	 * client information independent of version (name, title, description)
	 */
	function getClientTextFieldValue(target) {
		console.log("target:"+target);
		if (target && checkCompleteClient()) {
			var nm  = target.name;
			var val = target.value;
			console.log("in getClientTextFieldValue, client-version data");
			switch (target.getAttribute('name')) {
				case 'name':
				case 'title':
				case 'description':
					console.log(nm, val)
					ajaxUpdateClientStringValues(nm, val);
					break;
					
				default:
					break;
			}
		}
		else {
			console.log("client form incomplete, can't submit")
		}
		
	}
	
	/**
	 * this function gets information about a button that was pressed
	 */
	function getClientVersionButtonValue(target) {
		console.log("client-version button click event noted");
		if (target) {
			console.log(target.getAttribute('data-gbp-type').toLowerCase());
			switch (target.getAttribute('data-gbp-type').toLowerCase()) {
				case "delete":
					console.log(target.name);
					ajaxDeleteClientVersion(target);
					break;
				
				default:
					break;
			} //end of switch
			
		} //end of target test
		
	} //end of function
	
	
	/**
	 * any buttons in the client form area (e.g. delete)
	 */
	function getClientButtonValue(target) {
		
		console.log("in getClientButtonValue, client button click event noted");
		if (target) {
		
			switch (target.name) {
				case "delete":
					console.log("in getClientButtonValue delete, deleting client");
					var clientId = GBPInitializr.getCurrentClient();
					if (GBPInitializr.getConfirm("Do you really want to delete "+GBPInitializr.getCurrentSelectText('client')+'?')) {
						ajaxDeleteClient(clientId);
					}
					else {
						console.log("delete cancelled in alert");
					}
					break;
				
				default:
					break;
			
			} //end of switch
			
		} //end of target test
		
	} //end of function

	
	/** 
	 * this function gets boolean or multiple-choice values from radio buttons
	 */
	function getRadioSetValue(target) {
		 console.log("radio click event noted");
		 if(target) {
			console.log(target.getAttribute('data-gbp-type').toLowerCase());
			switch(target.getAttribute('data-gbp-type').toLowerCase()) {
				
				case "boolean":
					if(target.value == "true" || target.value == true) {
						ajaxUpdateClientBooleanValues(target.name, "true");
					}
					else {
						ajaxUpdateClientBooleanValues(target.name, "false");
					}
					break;
					
				default:
				 	console.log("undefined data-gbp-type for control");
					break;
					
			} //end of switch
			
		} //end of target test
		 
	} //end of function

	
	/** 
	 * this function gets the value of an input field that changed
	 * in the <tbody> list of client-version combos, in particular
	 * for the search-group assigned to the client-version (common, rare, ancient)
	 */
	function getClientVersionSelectFieldValue(target) {
		console.log("onchange <select> event noted");
		if(target) {
			console.log(target.getAttribute('data-gbp-type').toLowerCase());
			switch(target.getAttribute('data-gbp-type').toLowerCase()) {
				case "searchgroup":
				 	//NOTE: target.name is the id + '-search-group' (must be split off)
					var nameField = target.name.split('-');
					var clvid     = nameField[0];
					if (nameField[0] == 'new') { //we're in the new field
						if (checkCompleteClientVersion()) {
							ajaxInsertNewClientVersion(); //only works all fields are complete
						}	
					}
					else {
						ajaxUpdateClientVersionSearchGroupValues(target.name, target.value);
					}
				 	break;
				 
				default:
					console.log("undefined data-gbp-type for control");
				 	break;
				 
			} //end of switch
			 
		} //end of target test
		 
	} //end of function



	/** 
	 * ------------------------------------------------------------------------- 
	 * toggle values based on state
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/**
	 * showPropertyDeleteButton
	 * make the property button visible, and set its data id to
	 * the current property_id
	 */
	function showClientDeleteButton() {
		var del = GBPInitializr.getElement("client-delete");
		del.style.display = "block";
		del.setAttribute('data-gbp-client', GBPInitializr.getCurrentSelect('client'));
	}
	
	
	function hideClientDeleteButton() {
		GBPInitializr.getElement("client-delete").style.display = "none";
	}
	
	
	/**
	 * showClientReference
	 * make the client reference visible
	 */
	function showClientReference() {
		var ref = GBPInitializr.getElement('client-reference');
		ref.className = 'active-link';
	}
	
	
	function hideClientReference() {
		var ref =  GBPInitializr.getElement('client-reference');
		ref.className = 'greyed-link';
	}
	
	
	function checkCompleteClient() {
		console.log("checkCompleteClient() - entry, title:"+GBPInitializr.getElement('title').value);
		if(GBPInitializr.getElement('name').value != "" && 
			GBPInitializr.getElement('title').value   != "" && 
			GBPInitializr.getElement('description').value  != "") {
			return true;	
		}
		
		return false;
	}
	
	/**
	 * check new client-version record to ensure that it is complete
	 * - versionname, version version, and releasedate must be defined
	 * - version must be a valid version
	 */
	function checkCompleteClientVersion() {
		console.log("checkCompleteClientVersion() - entry");
		
		var version = GBPInitializr.getElement(newClientVersionVersionId).value;
		var dat     = GBPInitializr.getElement(newClientVersionReleaseDateId).value;
		
		if (GBPInitializr.getElement(newClientVersionNameId).value == "") {
			GBPInitializr.showMessage("Client-version common name missing");
			//GBPInitializr.getElement(newClientVersionNameId).focus();
			return false;
		}
		else if (version == '') {
			GBPInitializr.shoMessage("Missing client-version");
			return false;
		}
		else if (!GBPInitializr.isVersion(version)) {
			GBPInitializr.showMessage("Invalid client-version");
			return false;
		}
		else if (dat == '') {
			GBPInitializr.showMessage("Missing release date");
			return false;
		}
		else if (!GBPInitializr.isDate(dat)) {
			GBPInitializr.showMessage("Invalid release date");
			return false;
		}
		
		return true;
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
		console.log("top menu clicked in client_edit.php");
	}


	/** 
	 * ------------------------------------------------------------------------- 
	 * initialization
	 * ------------------------------------------------------------------------- 
	 */
	
	/**
	 * @method clearClientForm
	 * clear the client fields for a new client
	 */
	function clearClientForm() {
		GBPInitializr.getElement('name').value = '';           
		GBPInitializr.getElement('title').value = '';          
		GBPInitializr.getElement('description').value = '';
		GBPInitializr.setTBody('client-version-value', '<tr>\n<td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>\n');
		
		//set the client pulldown to 'new' record
		
		document.getElementById('client').selectedIndex=<?php echo $NEW_RECORD; ?>;
		var gbpSelText = GBPInitializr.getCurrentSelectText("client");
		if (gbpSelText) {
			GBPInitializr.getElement("client-title").innerHTML = GBPInitializr.getCurrentSelectText("client");
		}
		
	}
	
	
	/**
	 * @method clearClientVersionNewForm
	 * destroy the row that we enter new client data. Needed after a new client has been inserted, and
	 * we need to append that data to the table, and add a new empty client-version field at the bottom of the form.
	 * SPECIFIC USE: when a duplicate entry is found for a newly-entered client-version in the database
	 */
	function clearClientVersionNewForm() {
		GBPInitializr.getElement(newClientVersionNameId).value = '';
		GBPInitializr.getElement(newClientVersionVersionId).value = '';
		GBPInitializr.getElement(newClientVersionReleaseDateId).value = '';
		GBPInitializr.getElement(newClientVersionCommentsId).value = '';
	}

	
	/** 
	 * we use event delegation on the <tbody> of our table. We read the events
	 * from controls in table cells
	 * 1. to use event delegation on tbody we need to reverse 
	 * the order of event bubbling, otherwise we won't pick up the event
	 * http://www.quirksmode.org/blog/archives/2008/04/delegating_the.html
	 * 2. we make this event specific to text fields by checking the element.type
	 * NOTE: <table> cells don't have a 'type', so confirm for element.type exists before testing
	 * using list of events mapped to form elements at: http://www.w3schools.com/jsref/dom_obj_event.asp
	 * IE can pass almost any event from any form element
	 * @param {DOMElement tbody} tBody table body from table widget
	 */
	function addTableDelegateEvents(tBodyId) {
		
		var tBody = GBPInitializr.getElement(tBodyId); //can accept id or element itself
		
		if (tBody) {
			
			GBPInitializr.addEvent(tBody, "blur", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('text') !== -1) {
					getClientVersionTextFieldValue(target); //don't want <select> reacting to the blur event
				}
				else if (target.type && target.type.indexOf('date') !== -1) {
					console.log("addTableDelegateEvents('blur') event, date as plain text field");
					getClientVersionTextFieldValue(target); //for compatibility when HTML5 input type=date not supported
				}
				GBPInitializr.stopEvent(e); //stop propagation
				
				},
			true); //true=reverse bubbling to delegate onblur to a non-input element
			
				/** 
				 * we make this event specific to radio buttons by check the element.type
				 * NOTE: <table> cells don't have a type, so confirm element.type exists before testing
				 */
			GBPInitializr.addEvent(tBody, "click", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				if (target.type) {
					
					if(target.type.indexOf('radio') !== -1) {
						GBPInitializr.stopEvent(e);
						getRadioSetValue(target); //control clicked on
					}
					else if (target.type.indexOf('button') !== -1) {
						GBPInitializr.stopEvent(e);
						getClientVersionButtonValue(target);
					}
					else {
						console.log("addTableDelegateEvents('click') event, in table body");
						GBPInitializr.stopEvent(e);
					}
					
				}
				else if (target.id.indexOf('-client-version-reference') !== -1) { //reference hyperlink
					GBPInitializr.stopEvent(e);
					getClientVersionReferenceValue(target);
				}
					
				/**
				 * highlight the row we've clicked in, unless it is in the 'new' client-version row at
				 * the bottom of the table. All these fields have 'new-client' in their 'name' property
				 */
				if (target.name) {
					if(target.name.indexOf(newClientVersion) === -1) {
						GBPInitializr.unHighlightTableRows(tBody, 'row-select', 'row-unselect', 'row-highlight'); //unhighlight everyone, leaving out 'new' row
						GBPInitializr.highlightTableRow(target, 'row-select', 'row-unselect');   //highlight our row
					}
					else {
						GBPInitializr.unHighlightTableRows(tBody, 'row-select', 'row-unselect', 'row-highlight'); //unhighlight all non-'new' rows
					}
				}
				
				},
			false);
			
			/** 
			 * we make this event specific to <select>s in our <table> by check the element.type
			 * NOTE: <table> cells don't have a type, so confirm element.type exists before testing
			 */
			GBPInitializr.addEvent(tBody, "change", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('select') !== -1) {
					
					if (target.name == 'client') {
						//no selects currently in the client
					}
					else {
						getClientVersionSelectFieldValue(target); //control clicked on
					}
				}
				else if (target.type && target.type.indexOf('date') !== -1) {
					console.log("addTableDelegateEvents('change') event, date as select");
					//TODO: polyfill for everyone except opera?
					//this works GREAT in Opera's date field, but craps out in Google's date field
					//update when we switch out of the field
					////////////////////////////////////getClientVersionTextFieldValue(target);
				}
				GBPInitializr.stopEvent(e); //stop propagation
				},
			false);
			
			if (GBPInitializr.isOldIE()) {
				
				console.log("addTableDelegateEvents() isOldIE, we've got OLD IE");
				
				//old ie uses the 'focusout' event for blurred text
				GBPInitializr.addEvent(tBody, "focusout", function (e) {
					var target = GBPInitializr.getEventTarget(e);
					if(target.type && target.type.indexOf('text') !== -1) {
						getClientVersionTextFieldValue(target); //don't want <select> reacting to the blur event
					}
					else if (target.type && target.type.indexOf('date') !== -1) {
						console.log("addTableDelegateEvents('focusout') event, date as plain text field");
						getClientVersionTextFieldValue(target); //for compatibility when HTML5 input type=date not supported
					}
					GBPInitializr.stopEvent(e); //stop propagation
					
					//special highlight for 'new client-version row at bottom of client-version table
					
					if(target.name.indexOf('new-client') !== -1) {
						GBPInitializr.highlightTableRow(target, 'row-highlight');
					}
					
					},
					
				true);
				
				
			}
			
		} //end of valid tBody
	}
	
	
	/**
	 * additional delegate events for a form
	 */
	function addFormDelegateEvents(frmId) {
		
		var frm = GBPInitializr.getElement(frmId);
		
		//current client form fields
		
		/**
		 * click for button for deleting a client
		 */
		GBPInitializr.addEvent(frm, "click", function(e) {
			var target = GBPInitializr.getEventTarget(e);
			if (target.type) {
				
				if(target.type.indexOf('radio') !== -1) {
					getRadioSetValue(target); //control clicked on
				}
				else if (target.type.indexOf('checkbox') !== -1) {
					//getCheckBoxValue()
				}
				else if (target.type.indexOf('button') !== -1) {
					getClientButtonValue(target);
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
						switch (target.name) {
							case 'client':
								ajaxChangeBrowser();
								break;
							default:
								break;
						}
						console.log("addFormDelegateEvents('click') event, target:"+target.type);
					}
				}
				console.log("addFormDelegateEvents('click') form event, target:"+target.type);
			}
			GBPInitializr.stopEvent(e); //stop propagation
			},
		true);
		
		
		/**
		 * we save the focus event for text fields, so, if we leave the screen, we can update
		 * any fields that were edited but not clicked out of (blur event)
		 */
		GBPInitializr.addEvent(frm, "focus", function (e) {
			var target = GBPInitializr.getEventTarget(e);
			if(target.type) {
				if(target.type.indexOf('text') !== -1) {
					GBPInitializr.saveFocus(target); //we save the field with focus
				}
				else if (target.type.indexOf('date') !== -1) {
					GBPInitializr.saveFocus(target);
				}
			}
			console.log("addFormDelegateEvents('focus') form event, target:"+target.type);
			GBPInitializr.stopEvent(e); //stop propagation			
			},
		true); //true=reverse bubbling to delegate onblur to a non-input element
		
		
		if (GBPInitializr.isOldIE()) {
			
			console.log("addFormDelegateEvents() isOldIE, we've got OLD IE");
			
			//old ie uses the 'focusout' event for blurred text
			
			GBPInitializr.addEvent(frm, "focusout", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('text') !== -1) {
					
					if (!GBPInitializr.hasSpinner('spinner-win')) { //don't re-fire this event while processing
						getClientTextFieldValue(target); //don't want <select> reacting to the blur event
					}
				}
				console.log("addFormDelegateEvents('focusout') form event, target:"+target.type);
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
						ajaxChangeBrowser();
					}
				}
				console.log("addFormDelegateEvents('propertychange') form event, target:"+target.type);
				GBPInitializr.stopEvent(e); //stop propagation	
				},
				
			true);
		}
		else {
			GBPInitializr.addEvent(frm, "blur", function (e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('text') !== -1) {
					/**
					 * an alert() generates a second "blur" event. Don't fire if we are
					 * already processing the first blur event
					 */
					if (!GBPInitializr.hasSpinner('spinner-win')) { //don't re-fire this event while processing
						getClientTextFieldValue(target); //don't want <select> reacting to the blur event
					}
				}
				console.log("addFormDelegateEvents('blur') event, target:"+target.type);
				GBPInitializr.stopEvent(e); //stop propagation			
				},
				
			true); //true=reverse bubbling to delegate onblur to a non-input element
			
			//select menus
			
			GBPInitializr.addEvent(frm, "change", function(e) {
				var target = GBPInitializr.getEventTarget(e);
				if(target.type && target.type.indexOf('select') !== -1) {
					
					switch(target.name)
					{
						case 'client':
							ajaxChangeBrowser();
							break;
						default:
							console.log("unknown select in form");
							break;
					}
					console.log("addFormDelegateEvents('change') event, target:"+target.type); //GETTING THE SELECT
				}
				GBPInitializr.stopEvent(e); //stop propagation
				},
			true); //true=reverse bubbling to delegate onblur to a non-input element
		}
		
		
		//add reference event for the client (a hyperlink, so we return false to kill default link behavior)
		
		GBPInitializr.addEvent(GBPInitializr.getElement("client-reference"), "click", function (e) {
			
			ajaxGetClientReference('clients', GBPInitializr.getCurrentClient());
			return false;
		},
		false);
		
	}

	
	/** 
	 * this is called in the parent index.php in a complete: function 
	 * in index.php
	 */
	function init() {
		
		//add events to the <table> widget and <form>
		
		addTableDelegateEvents("client-version-value");
		addFormDelegateEvents("gbp-client");
		
		/**
		 * set the default browser $CLIENT_PRIMARY (an id value in the 'clients' table)
		 */
		ajaxChangeBrowser();
	}
	
</script>