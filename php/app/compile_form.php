<?php 
	/** 
	 * NOTE: init.php must have been included earlier
	 */
	
	if(class_exists('GBP_PROPERTY'))
	{
		$prop = new GBP_PROPERTY;
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

        	<h2>GBP Generate the Properties Table</h2>
            
        </header>
        
        <section>

            
		<form method="post" action="<?php echo 'php/app/compile_process.php'; ?>">
	        <!--list all the GBP properties in a list. Clicking on a property takes us to the 
	        entry form, with everything filled in-->
	        <?php 		
			$component_arr = $prop->get_all_components();
			
			foreach($component_arr as $component)
			{
				/** 
				 * rebuild the component array from the raw array returned by the database call 
				 * to the components database
				 */
				$prop_arr[$component['name']]               = array();
				$prop_arr[$component['name']]['name']       = $component['name'];
				$prop_arr[$component['name']]['title']      = $component['title'];
				$prop_arr[$component['name']]['id']         = $component['id'];
				$prop_arr[$component['name']]['user_edit']  = $component['user_edit'];
				
				/** 
				 * create a sub-array of all the properties which are associated with this component
				 */
				$prop_arr[$component['name']]['properties'] = $prop->get_all_properties($SOURCE_PRIMARY, $component['id'], true);
				
			}
			
			$component_arr = array();
	
	        ?>
        
        <!--write all the GBP native properties (source is always GBP). List them in tables broken down by component -->

	        <fieldset>
			
			<legend>Select Properties to Include:</legend>
			<?php
				$coltick = 0;
				
				/*
				 * special code to make checkbox non-editable, but not disabled (so it submits with $_POST)
				 */
				$required = 'checked="checked" onclick="this.checked=!this.checked;alert(\'required by gbp\');"';
				
				/** 
				 * outer loop, for each type of component
				 */
				foreach($prop_arr as $name => $component_group)
				{
					$coltick++;
					if($component_group['user_edit'] == true) //leave out references, which aren't included in the final GBP object
					{
						if($coltick == 1)
						{
							echo '<div class="component-row-div">'."\n";
						}
						
						//create the table header
						
						echo '<table class="component-table" id="table-'.$name.'">'."\n<thead>\n";
						echo '<caption><span class="table-tab">'.$component_group['title'].'(<strong>gbp.'.$component_group['name'].'</strong>)</span></caption>'."\n";
						echo '<tr><th>#</th><th>Property</th><th>Property Title</th></tr>'."\n</thead><tbody>";
						
						//button allowing 'Select All' for a component class
						
						echo '<tr><td colspan="3" class="select-all"><input type="button" name="'.$name.'-all" id="'.$name.'-all" value="Select All"></td></tr>'."\n";
						
						/**
						 * internal loop for table rows, for all the properties associated with a given component
						 */
						foreach($component_group['properties'] as $id => $property) 
						{
							
							echo '<tr><td class="leftmost2"><input type="checkbox" class="'.$name.'-ind" name="'.$component_group['id'].'-'.$property['id'].'" id="'.$property['id'].'"';
							
							if($property['exe_lock'] == "1")
							{
								echo ' '.$required;
							}
							
							echo '></td><td class="centermost2">'.$property['name'].'<td class="rightmost2">'.$property['title'].'</td></tr>'."\n";
						}
						
						//close the table
						
						echo "</tbody></table>\n";
						
						if($coltick == 2)
						{
							echo "</div>\n";
							$coltick = 0;
						}
						
					}
				}
				
			?>
			
		</fieldset>
        
	        <fieldset id="sub-field">
			<input type="submit" name="subb" class="subb" value="Generate Propfile">
	        </fieldset>
        
        
		</form>
        
        </section>
        
        <footer>
        
        </footer>
        
        <!--local form javascript goes here-->
        <!--this just toggles the checkboxes on and off in each component, very form specific -->
	
        <script>
			var tableArr = document.getElementsByClassName("component-table");
			
			var tableArrLen = tableArr.length;
			
			for(var i = 0; i < tableArrLen; i++) {
				
				if (!tableArr[i].getElementsByClassName) {
					tableArr[i].getElementsByClassName = document.getElementsByClassName;
				}
				
				var toggle = tableArr[i].getElementsByClassName("select-all")[0].getElementsByTagName("input")[0];				
				var currTable = tableArr[i];
				
				toggle.onclick = function () {
					this.inputs = this.parentNode.parentNode.parentNode.getElementsByTagName("input");
					console.log("this.inputs is " + this.inputs + "of length " + this.inputs.length);
					if(this.selected) {
						this.value = "Check All";
						this.selected = false;
					}
					else {
						this.value = "Uncheck All";
						this.selected = true;
					}
						
					for(var i = 0; i < this.inputs.length; i++) {		
						if(this.selected && this.inputs[i].type == "checkbox") {
							this.inputs[i].checked = "checked";
						}
						else {
							this.inputs[i].checked = false;
						}
					}
				}
				
			}
		
		function finish() {
			console.log("in finish for propfile_form.php");
		}
		
		</script>