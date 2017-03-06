<?php

    class GBP_CONVERT_CANIUSE extends GBP_CONVERT_BASE {
    
	
	/** 
	 * hard-code the mapping of client names in caniuse to 
	 * client names in gbp. Version numbers are dynamically 
	 * translated
	 */
	 private static $client_trans_table = array (
	 			'ie'       => 'msie',
            	'firefox'  => 'firefox',
            	'chrome'   => 'chrome',
            	'safari'   => 'safari',
            	'opera'    => 'opera',
            	'ios_saf'  => 'mobile_safari',
            	'op_mini'  => 'opera_mini',
            	'android'  => 'android',
            	'bb'       => 'blackberry',
            	'op_mob'   => 'opera_mobi',
            	'and_chr'  => 'chrome_mobile',
            	'and_ff'   => 'firefox_mobile'
	 
	 );
	 
	 
	 /** 
	  'feature' => array(  //file name minus json string
					'apng.json' => 'hasPNGAnimated', // 24-bit animated PNG support
					'audio.json' => 'hasHTML5Audio',
					'audio-api.json' => 'hasHTML5AudioSynth',
					'background-img-opts.json' => 'hasCSS3BkgndImageOpts',
					'blobbuilder.json' => 'hasBlobBuilder', //support for BLOB objects
					'bloburls.json' => 'hasBlobURLs',
					'border-image.json' => 'hasCSSBorderImage',
					'border-radius.json' => 'hasCSSBorderRadius',
					'calc.json' => 'hasCalc',
					'canvas.json' => 'hasCanvas',
					'canvas-text.json' => 'hasCanvasText',
					'classlist.json' => 'hasClassList', //manipulate element classes using DOMTokenList
					'contenteditable.json' => 'hasContentEdit',
					'contentsecuritypolicy.json' => 'hasContentSecurity',
					'cors.json' => 'hasCORS',
					'css3-boxsizing.json' => 'hasCSS3Boxsizing',
					'css3-colors.json' => 'hasCSS3HSL',
					'css3-conditional.json' => 'hasCSS3Conditional',
					'css-animation.json' => 'hasCSS3Animation',
					'css-boxshadow.json' => 'hasCSSBoxShadow',
					'css-canvas.json' => 'hasCSSCanvas',
					'css-counters.json' => 'hasCSSCounters',
					'css-featurequeries.json' => 'hasCSSFeatureQueries',
					'css-filters.json' => 'hasCSSFilters',
					'css-fixed.json' => 'hasCSSFIxed',
					'css-gencontent.json' => 'hasCSSBeforeAfter',
					'css-gradients.json' => 'hasCSSGradients',
					'css-grid.json' => 'hasCSSGrid',
					'css-hyphens.json' => 'hasCSSHyphenation',
					'css-masks.json' => 'hasCSSMasks',
					'css-mediaqueries.json' => 'hasCSSMediaQueries',
					'css-opacity.json' => 'hasCSSOpacity',
					'css-reflections.json' => 'hasCSSReflections',
					'css-regions.json' => 'hasCSSRegions',
					'css-repeating-gradients.json' => 'hasCSSRepeatingGradients',
					'css-resize.json' => 'hasCSSResize',
					'css-sel2.json' => 'hasCSS2Selectors',
					'css-sel3.json' => 'hasCSS3Selectors',
					'css-table.json' => 'hasCSSTable',
					'css-textshadow.json' => 'hasCSSTextShadow',
					'css-transitions.json' => 'hasCSSTransitions',
					'datalist.json' => 'hasDatalist',
					'dataset.json' => 'hasDataset',
					'datauri.json' => 'hasDataURI', //base64 encoding
					'details.json' => 'hasDetails', //non-javaScript show/hide
					'deviceorientation.json' => 'hasDeviceOrientation',
					'dragndrop.json' => 'hasDragAndDrop',
					'eot.json' => 'hasFontEOT',
					'eventsource.json' => 'hasEventSource', //request server push to DOM
					'fileapi.json' => 'hasFileAPI',
					'filereader.json' => 'hasFileReader', 
					'filesystem.json' => 'hasFileWriter',
					'flexbox.json' => 'hasFlexBox',
					'fontface.json' => 'hasCSSFontFace',
					'font-feature.json' => 'hasCSSFontFeature', //apply advanced typographic features
					'forms.json' => 'hasHTML5FormElements',
					'form-validation.json' => 'hasHTML5FormValidation', 
					'fullscreen.json' => 'hasFullscreen',
					'geolocation.json' => 'hasGeolocation',
					'getcomputedstyle.json' => 'hasGetComputedStyles', //get styles currently applied to an element
					'getelementsbyclassname.json' => 'hasGetElementsByClassName',
					'hashchange.json' => 'hasHashtag', //detect changes to hashtag in url
					'history.json' => 'hasHistory',
					'html5semantic.json' => 'hasHTML5Semantic',
					'iframe-sandbox.json' => 'hasiFrameSandbox', //set to run no JS in iframe
					'indexeddb.json' => "hasIndexedDB", //websimple DB supported, indexed db queries
					'inline-block.json' => 'hasCSSInlineBlock', //CSS inline-block
					'input-color.json' => 'hasInputColor', //form control with color
					'input-datetime.json' => 'hasInputDateTime', //form control with date, time
					'input-number.json' => 'hasInputNumber',
					'input-placeholder.json' => 'hasInputPlaceholder',
					'input-range.json' => 'hasInputRange',
					'json.json' => 'hasJSON', 
					'matchselector.json' => 'hasMatchSelector', //check if DOM element matches selector
					'matchmedia.json' => 'hasMatchMedia', //test if media query applies to document
					'mathml.json' => 'hasMathML', //math formulas
					'menu.json' => 'hasMenu', //<menu> context element
					'minmaxwh.json' => 'hasSetMinMax', //sets minimum or max width, height to element
					'mpeg4.json' => 'hasVideoMP4',
					'mulitbackgrounds.json' => "hasCSSMultiBackgrounds", 
					'multicolumn.json' => 'hasCSSMultiColumns', 
					'namevalue-storage.json' => 'hasLocalStorage',
					'nav-timing.json' => 'hasNavTiming', 
					'notifications.json' => 'hasNotifications', 
					'object-fit.json' => 'hasObjectFit', //how audio/video should fit inside its box
					'offline-apps.json' => 'hasCacheManifest', 
					'ogv.json' => 'hasVideoOggThedora', //ogg-thedora video format
					'pagevisibility.json' => 'hasPageVisiblity', //page visibility
					'png-alpha.json' => 'hasPNGAlpha',
					'pointer-events.json' => 'hasPointerEvents', 
					'progressmeter.json' => 'hasProgressMeter', 
					'queryselector.json' => 'hasQuerySelector',
					'rellist.json' => 'hasRelList', //manipulate rel using DOMTokenList
					'rem.json' => 'hasCSSRem', //CSS rem elements
					'requestanimationframe.json' => 'hasRequestAnimationFrame', 
					'ruby.json' => 'hasRuby',
					'script-async.json' => 'hasScriptAsync',
					'script-defer.json' => 'hasScriptDefer',
					'sharedworkers.json' => 'hasWebWorkersShared',
					'spdy.json' => 'hasSPDY', //Google SPDY protocol
					'sql-storage.json' => 'hasSQL', //sql database support
					'stream.json' => 'hasUserMedia', //webcam streams
					'style-scoped.json' => 'hasStyleScoped', //position of style element scopes styles in doc
					'svg.json' => 'hasSVG', 
					'svg-css.json' => 'hasSVGCSS',
					'svg-filters.json' => 'hasSVGFilters', 
					'svg-fonts.json' => 'hasSVGFonts', //use fonts defined as SVG shapes
					'svg-html.json' => 'hasSVGForeignObject', //use SVG filters and transforms on HTML tags using CSS or ForeignObject element
					'svg-html5.json' => 'hasSVGInHTML5', //use SVG tags directly in HTML5 document
					'svg-img.json' => 'hasSVGImg',
					'svg-smil.json' => 'hasSVGSMIL',
					'testfeat.json' => 'hasTestFeature', 
					'text-overflow.json' => 'hasCSSTextElipsis', 
					'text-stroke.json' => 'hasCSSTextStroke',
					'touch.json' => 'hasTouchEvents',
					'transforms2d.json' => 'hasCSSTransforms2D',
					'transforms3d.json' => 'hasCSSTransforms3D',
					'ttf.json' => 'hasFontTTF', 
					'typedarrays.json' => 'hasTypedArrays', 
					'use-strict.json' => 'hasScriptUseStrict', 
					'video.json' => 'hasHTML5Video', 
					'viewport-units.json' => 'hasCSSViewportUnits',
					'wai-aria.json' => 'hasARIA', 
					'webgl.json' => 'hasWebGL',
					'webm.json' => 'hasVideoWebM',
					'websockets.json' => 'hasWebSockets',
					'webworkers.json' => 'hasWebWorkers', 
					'woff.json' => 'hasFontWOFF', 
					'word-break.json' => 'hasCSSWordBreak',
					'wordwrap.json' => 'hasCSSWordwrap',
					'x-doc-messaging.json' => 'hasPostMessage', 
					'xhr2.json' => 'hasXMLHttpRequest2',
					'xhtml.json' => 'hasXHTML',
					'xhtmlsmil.json' => 'hasSMIL'

					)
	 
	 
	 */
	
	

	/** 
	 * constructor
	 */
	 public function __construct($json_file)
	{
	 	parent::__construct();
		
		
	}
	
	 
	/**
	 * @method get_caniuse
	 * wrapper for get_json in caniuse format
	 */
	public static function get_caniuse($caniuse_json_file)
	{
	    
	}

	
	/**
	 * @method convert caniuse() JUST ONE JSON file
	 * load caniuse, and convert between JSON and our PHP array db
	 */
	private static function convert_caniuse($json_dir, $convert_all=true)
	{
		$gbp_user_agents = array();

		//read JSON file
		
		//if OK, load a translation file
		
		
		//do the translation
		
		
		return true;
		
	}
	
	
	/** 
	 * utilities
	 */
	 
	 /** 
	  * @method unroll_caniuse_versions
	  * caniuse JSON has versions like "1.0-3.0" 
	  * unroll to matching verison numbers in GBP
	  */
	 private static function unroll_caniuse_versions()
	 {
	 }

};
