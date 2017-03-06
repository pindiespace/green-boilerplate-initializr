<?php 
	//init.php must have been included earlier
	
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
            
     	<h2 class="clearfix">Server Properties</h2>

    	</header>
        
<!--section containing form (in a section)-->

    	<section class="clearfix">

        

		</section><!--end of section containing form-->
       
<!--footer for form-->

   		<footer>
			<p>
           		<!--footer for form-->
        	</p>
   		</footer>

<!--local form javascript goes here-->

<script>
	
	function finish() {
		console.log("in server_form.php");
	}
	
</script>