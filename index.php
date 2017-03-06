<?php require_once("php/init.php"); ?>
<!doctype html>
    
<!--
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 10.2013
 * @version 1.0
-->

<!-- 
320 and Up by Andy Clarke, Version: 3.0
URL: http://stuffandnonsense.co.uk/projects/320andup/
-->

<!-- HTML5 Boilerplate -->
<!--[if lt IE 7]><html class="no-js lt-ie9 lt-ie8 lt-ie7" lang="en"> <![endif]-->
<!--[if (IE 7)&!(IEMobile)]><html class="no-js lt-ie9 lt-ie8" lang="en"><![endif]-->
<!--[if (IE 8)&!(IEMobile)]><html class="no-js lt-ie9" lang="en"><![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js" lang="en"><!--<![endif]-->

<head>
<meta charset="utf-8">

<title>GBP Initializr</title>

<!--NOTE meta name='description' will prevent IE6 and IE7 from reading forms with name=description(!). In any
case 'description' is ignored by most search engines now, so it isn't helpful SEO.
http://www.impressivewebs.com/avoiding-problems-with-javascript-getelementbyid-method-in-internet-explorer-7/
-->

<meta name="keywords" content="Green Boilerplate, initializr">
<meta name="author" content="Pete Markiewicz">

<!-- http://t.co/dKP3o1e -->

<meta name="HandheldFriendly" content="True">
<meta name="MobileOptimized" content="320">
    
<!-- viewport -->

<meta name="viewport" content="width=device-width, initial-scale=1.0">
    
<!-- html5 specific preloading of images-->

<link rel="prefetch" href="img/icons/spinner-butt.gif">
<link rel="prefetch" href="img/icons/spinner.gif">

<!-- CSS for all browsers -->

<link rel="stylesheet" href="css/gbp_initializr.css">

<!-- JavaScript polyfills -->

<!--[if (lt IE 9) & (!IEMobile)]>
<script src="js/lib/selectivizr-min.js"></script>
<![endif]-->

<!--load the console object, if needed (separate reporter window) -->

<script>window.console || document.write("<script src='js/lib/console.js'>\x3C/script>");</script>

<!--absolutely need JSON support, so load it early
Use JSON3 - http://bestiejs.github.io/json3/ -->

<script>window.JSON || document.write("<script src='js/lib/json3.js'>\x3C/script>");</script>

<!--same for some basic JavaScript method polyfills missing in very old browsers (e.g. FF1) -->

<script>(document.getElementsByClassName && Object.keys && Array.prototype.indexOf) || document.write("<script src='js/lib/jsmethods.js'>\x3C/script>");</script>

<!--modernizr (for now)-->

<script src="js/lib/modernizr.2.6.2.min.js"></script>

<script>
//preload some images which are first revealed in ajax
yepnope.addPrefix('preload', function (resource) {
    resource.noexec = true;
    return resource;
});

//TODO: we are using HTML5 prefetch above in a meta-tag

Modernizr.load('preload!img/icons/spinner.gif');
Modernizr.load('preload!img/icons/spinner-butt.gif');

</script>

<!-- Icons -->

<!-- 16x16 -->
<link rel="shortcut icon" href="/favicon.ico">
<!-- 32x32 -->
<link rel="shortcut icon" href="/favicon.png">
<!-- 57x57 (precomposed) for iPhone 3GS, pre-2011 iPod Touch and older Android devices -->
<link rel="apple-touch-icon-precomposed" href="img/apple-touch-icon-precomposed.png">
<!-- 72x72 (precomposed) for 1st generation iPad, iPad 2 and iPad mini -->
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="img/apple-touch-icon-72x72-precomposed.png">
<!-- 114x114 (precomposed) for iPhone 4, 4S, 5 and post-2011 iPod Touch -->
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="img/apple-touch-icon-114x114-precomposed.png">
<!-- 144x144 (precomposed) for iPad 3rd and 4th generation -->
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="img/apple-touch-icon-144x144-precomposed.png">

<!-- Windows 8 / RT -->

<meta name="msapplication-TileImage" content="img/apple-touch-icon-144x144-precomposed.png">
<meta name="msapplication-TileColor" content="#000">
<meta http-equiv="cleartype" content="on">
</head>

<body class="clearfix">
	
<!--we put this script here, instead at the end of the document, since 
our inserted PHP files may load JavaScript depending on these libraries-->
	    <script>
		
	    Modernizr.load(
		{
			load: 
			[
			"js/app/gbp_initializr.js",
			],
			complete: function () {
				
				console.log("index: all libs loaded");
				
				if(window.init) { //our init functions in each <section> are attached globally
				    /**
				     * set up event delegation for the top menu
				     */
				    var mainMenu = GBPInitializr.getElement('main-menu');
				    if (mainMenu) {
					GBPInitializr.addEvent(mainMenu, "click", function (e) {
						var target = GBPInitializr.getEventTarget(e);
					
						console.log("href:"+target.href);
					    
						finish(GBPInitializr.getEventTarget()); //fire when top menu tab is clicked
					    
					    //GBPInitializr.stopEvent(e); //stop propagation
					    },
					    
				        true); //true=reverse bubbling to delegate onblur to a non-input element
					
					
				    }
				    else {
					console.log("index: error in setting up events for top menu");
				    }
				    
				    /*
				     * call local startup scripts, mostly event delegators
				     */
				    init();
				}
			}
		} //end of first object
		
	); //end of Modernizr.load
	    
	/**
	 * use yepnope in Moderinzr to detect whether to load a calendar widget
	 */
	yepnope(
	    {
		test : Modernizr.inputtypes && Modernizr.inputtypes.date,
		nope : [
		    'js/lib/openjscalendar.css',
		    'js/lib/openjscalendar.js'
		    ]
	    }
	);
	
	</script>

	<div id="main">
		
<!--we add an (invisible) foreground spinner image here. Background CSS animated 
	spinners don't always appear immediately-->
	
	<div class="spinner-win">
		<img src="img/icons/spinner.gif">
	</div>
	
<!--add an (invisible) reference poppup window for collecting and showing modal
	data to the user-->
	
	<div class="reference-window">
		<div class="reference-wrapper">
			<h2>References<span id="reference-title"></span></h2>
			<div class="reference-list-wrapper">
				<table id="reference-url-list">
					<thead>
						<tr>
							<th>Date (Y-M-D)</th>
							<th>Site</th>
							<th>URL (use http:)</th>
							<th>Description</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>	
					</tbody>
				</table>
			</div>
			
			<div class="reference-close">
				<form>
					<input type="button" name="reference-close-button" id="reference-close-button" value="Close" onmousedown="GBPInitializr.checkForRefWinClose('reference-window');" onclick="GBPInitializr.hideRefWin('reference-window');">
				</form>
			</div>
		</div><!--end of wrapper-->
	</div>
	
<!--add a small informational callout-->
	
	<div id="poppup-win" class="poppup">
		<span id="poppup-win-title" class="poppup-title"></span>
		<div id="poppup-win-content" class="poppup-content"></div>
		<div class="poppup-win-buttons">
			<form>
				<input type="button" name="poppup-action-button" id="poppup-action-button" class="subb" value="OK">
				<input type="button" name="poppup-close-button" id="poppup-close-button" class="subb" value="Close" onclick="GBPInitializr.hidePoppup();">

			</form>
		</div>
	</div>
	
<!--header for entire page-->
	
	<header role="banner" class="clearfix">
		
	    <h1 id="app-name"><span class="greened">GBP</span> <span class="greyed">initializr</span></h1>
	     
	    <nav class="menu-tab">
		
		<ul id="main-menu" class="list-tab clearfix">
			<li class="<?php if($state == 'propertyedit') echo 'tab-active'; else echo 'tab-inactive'; ?>"><a href="index.php?state=propertyedit">Properties</a></li>
			<li class="<?php if($state == 'clientedit') echo 'tab-active'; else echo 'tab-inactive'; ?>"><a href="index.php?state=clientedit">Clients</a></li>
			<li class="<?php if($state == 'clienthistory') echo 'tab-active'; else echo 'tab-inactive'; ?>"><a href="index.php?state=clienthistory">Client Properties</a></li>
			<li class="<?php if($state == 'deviceedit') echo 'tab-active'; else echo 'tab-inactive'; ?>"><a href="index.php?state=deviceedit">Device</a></li>
			<li class="<?php if($state == 'serveredit') echo 'tab-active'; else echo 'tab-inactive'; ?>"><a href="index.php?state=serveredit">Server</a></li>
			<li class="<?php if($state == 'humanedit') echo 'tab-active'; else echo 'tab-inactive'; ?>"><a href="index.php?state=humanedit">Human</a></li>
			<li class="<?php if($state == 'utility')  echo 'tab-active'; else echo 'tab-inactive'; ?>"><a href="index.php?state=utility">Utility</a></li>
			<li class="<?php if($state == 'compile')  echo 'tab-active'; else echo 'tab-inactive'; ?>"><a href="index.php?state=compile">Compile</a></li>
		</ul>
		
	    </nav>
	    
	</header>
	
	<div class="content clearfix">
	    
	    <section id="tab">
		
	    <?php
		    //switch states
		    switch($state)
		    {
			case 'propertyedit':
				include_once('php/app/property_edit.php'); //edit or update property
				break;
			    
			case 'clientedit':
				include_once('php/app/client_edit.php');
				break;
			    
			case 'clienthistory':
				include_once('php/app/client_property_history.php'); //client-property link table
				break;
			    
			case 'deviceedit':
				include_once('php/app/device_form.php');
				break;
			    
			case 'serveredit':
				include_once('php/app/server_form.php'); //validate one or more properties
				break;
				
			case 'humanedit':
				include_once('php/app/human_form.php'); //human factors
				break;
				
			case 'utility':
				include_once('php/app/utility_form.php'); //utility
			    break;
				
			case 'compile':
				include_once('php/app/compile_form.php'); //make the properties file for GBP
				break;
			    
			default:
				break;	
			}
		?>
		
		<div id="push"></div>
		
	    </section>
	    
	<footer id="page-footer" role="contentinfo">
	    <p>GBP Initializr. Created 2013 Pete Markiewicz</p>
	</footer>
	
	</div><!--end of content div -->
	</div><!-- end of main div -->
	
</body>
</html>