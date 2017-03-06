/** 
 * GBP_INITIALIZR
 * gbp_initializr.js
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 10.2012
 * @version 1.0
 *
 * @todo list of changes and additions
 * - speed and security tests
 */


/**
 * add a replacement for 'typeof' to global space
 * http://javascriptweblog.wordpress.com/2011/08/08/fixing-the-javascript-typeof-operator/
 * Object.toType(window); //"global" (all browsers)
 * Object.toType([1,2,3]); //"array" (all browsers)
 * Object.toType(/a-z/); //"regexp" (all browsers)
 * Object.toType(JSON); //"json" (all browsers)
 */
Object.toType = (function toType(global) {
	return function(obj) {
		if (obj === global) {
			return "global";
		}
	return ({}).toString.call(obj).match(/\s([a-z|A-Z]+)/)[1].toLowerCase();
  }
})(this);


/**
 * overload the string prototype
 * replace HTML tags and quotes with character entities
 */
String.prototype.encodeHTMLChars = String.prototype.encodeHTMLChars || function () {
	  return this.replace(/&/g, "&amp;") /* must do &amp; first */
	  .replace(/"/g, "&quot;")
	  .replace(/'/g, "&#039;")
	  .replace(/</g, "&lt;")
	  .replace(/>/g, "&gt;");
};

/**
 * convert HTML character entities to HTML tags
 */
String.prototype.decodeHTMLChars = String.prototype.decodeHTMLChars || function () {
	  return this.replace(/&gt;/ig, ">")
	  .replace(/&lt;/ig, "<")
	  .replace(/&#039;/g, "'")
	  .replace(/&quot;/ig, '"')
	  .replace(/&amp;/ig, '&'); /* must do &amp; last */
};


/**
 * provide trim, if not present
 */
String.prototype.trim = String.prototype.trim || function () {
	return this.replace(/^\s+|\s+$/g,'');
};

/**
 * @method stripUnicode
 * will remove any unicode characters from a string
 */
String.prototype.stripUnicode = String.prototype.stripUnicode || function (){
	return this.replace(/[^\x20-\x7E]/g,"");
};

/**
 * @method stripHTMLChars
 * remove HTML symbols, leaving internal text untouched
 */
String.prototype.stripHTMLChars = String.prototype.stripHTMLChars || function () {
	return this.decodeHTMLChars()
	.replace(/"/g, "")
	.replace(/'/g, "")
	.replace(/</g, "")
	.replace(/>/g, "");
}

/**
 * @method stripHTML
 * will remove HTML tags from the string, even if the are
 * character entitites
 */
String.prototype.stripHTML = String.prototype.stripHTML || function() {
	return this.decodeHTMLChars()
	    .replace( /(<([^>]+)>)/ig, "");
};

/**
 * return only the alphanumeric portion of a string
 */
String.prototype.alphanumeric = function alphanumeric () {
	return this
	    .replace(/[^a-z0-9]/gi,'');
};


/**
 * @method capitalize to capitalize first letter
 */
String.prototype.capitalize = String.prototype.capitalize || function() {
    return this.charAt(0).toUpperCase() + this.slice(1);
}


/**
 * @method toCamelCase to make JS-compatible names
 * make alphanumeric, and camel-case hyphens and spaces
 */
String.prototype.camelCase = String.prototype.camelCase || function () {  
	return this
	      .replace(/([^a-zA-Z0-9_\- ])|^[_0-9]+/g, "")
	      .trim().toLowerCase()
	      .replace(/([ -]+)([a-zA-Z0-9])/g, function(a,b,c) {
		      return c.toUpperCase();
		      })
	      .alphanumeric();
};


/**
 * @method Array.prototype.move
 * Move an array element from one position to another
 * http://jsperf.com/array-prototype-move/11
 * @param {Number} from index of array item we want to move
 * @param {Number} newIndex index we want to move it to
 * @returns {Array} the sorted array
 */
Array.prototype.move = function (from, to) {
   this.splice(to, 0, this.splice(from, 1)[0]);
};


/**
 * GBPInitializr
 * object we will use for interface control
 */
var GBPInitializr = (function () {
	
	/**
	 * -------------------------------------------------------------------------
	 * some variables
	 * ------------------------------------------------------------------------- 
	 */
	var MAX_NAME        = 40;
	var MAX_TITLE       = 80;
	var MAX_DESCRIPTION = 255;
	
	var NEW_MSIE        = 10;
	
	var NO_RECORD       = 0; //same as PHP
	
	//zIndex values
	
	var ZINDEX_BOTTOM   = 0;    //push to bottom
	var ZINDEX_REF_WIN  = 9990; //references
	var ZINDEX_CALENDAR = 9992; //inline calendar polyfill
	var ZINDEX_POPPUP   = 9995; //inline alert
	var ZINDEX_SPINNER  = 1000; //ajax spinner
	
	/**
	 * activate console.log
	 * DEBUG == 0 no debug, but error messages written to console.log
	 * DEBUG == 1 errors, status warnings
	 */
	var DEBUG           = 1;
	
	var lastFocus       = false;     //save last DOM control clicked on
	var lastFocusData   = undefined; //primitive undo
	
	/**
	 * -------------------------------------------------------------------------
	 * some utilities
	 * ------------------------------------------------------------------------- 
	 */
	
	/**
	 * save the activeElement, without using .activeElement, for old browser compatibility
	 * we restrict saving focus exclusively to text fields with an active cursor and type='date'
	 * HTML5 controls. All other field types update the moment they are clicked, so we can't
	 * lose data during a page reload or top menu choice
	 */
	function saveFocus(target) {
		
		if(DEBUG) console.log("saveFocus() - target.name="+target.name+" and target.type="+target.type+" and target.value="+target.value);
			
		//this fires every time we have a focus event
		
		if (target && target.name) {
			lastFocus = target;
			lastFocusData = target.value;
		}
		else {	
			clearFocus();
		}	
	}
	
	function getLastFocus() {
		return lastFocus;
	}
	
	function getLastFocusData() {
		return lastFocusData;
	}
	
	function clearFocus() {
		lastFocus = false;
		lastFocusData = undefined;
	}
	
        
        /**
         * @method getConfirm
         * create a confirmation dialog
         * TODO: make this a non-alert window
         */
        function getConfirm(str) {
            return confirm(str);
        }
	
	/**
	 * @method isOldIE
	 * unfortunate but necessary browser sniffing when feature-detection won't
	 * help (e.g. the failure for blur events to delegate in IE < 10)
	 * From MSDN
	 * http://msdn.microsoft.com/en-us/library/ms537509(v=vs.85).aspx
	 * HOW JQUERY FIXES old IE
	 * http://www.jquery4u.com/ie/jquery-1-9-1-overcomes-internet-explorer-678-javascript/
	 * 
	 */
	function isOldIE() {
		if (navigator  &&  navigator.userAgent.match( /MSIE/i ))  { //<IE10
			var rv = -1; // Return value assumes failure.
			if (navigator.appName == 'Microsoft Internet Explorer') {
				var ua = navigator.userAgent;
				var re  = new RegExp("MSIE ([0-9]{1,}[\.0-9]{0,})");
				if (re.exec(ua) != null) {
					rv = parseFloat( RegExp.$1 );
					if (rv > -1 && rv < NEW_MSIE) {
						return true;
					}
				}
			}
			
		}
	  
	  return false;
	}
	
	/**
	 * @method isNumber
	 * confirm we have a number
	 * @param {Mixed} n the value tested for numericity
	 * @return {Boolean} if a number, return true, else false
	 */
	function isNumber(n) {
		return !isNaN(parseFloat(n)) && isFinite(n);
	}
	
	
	/**
	 * @method isDate
	 * confirm we have a valid date string
	 * @param {String} currDate the prospective date
	 * @return {Boolean} if valid, return true, else return false
	 */
	function isDate(currDate) {
		var theDate = new Date(currDate);
		if (theDate) {
			return true;
		}
		showMessage("invalid date");
		return false;
	}
	
	
	/**
	 * @method isDateRange
	 * confirm we have a valid date range for the client (browser)
	 * @param {Date} startDate the start value tested for appropriate range
	 * @param {Date} currate the end value tested for appropriate range
	 * @reaturn {Boolean} if a date, return true, else false
	 */
	function isDateRange(startDate, currDate) {
		
		if (isNaN(startDate)) {
			showMessage("invalid start date");
			return false;
		}
		if (isNaN(currDate)) {
			showMessage("invalid end date");
			return false;
		}
		
		return (currDate.getTime() > startDate.getTime());
	}
	
	
	/**
	 * @method isTimeStamp
	 * confirm we have a valid Unix timestamp
	 * @param {Number} stamp prospective timestamp
	 * @return {Boolean} if a timestampe, return true, else false
	 */
	function isTimeStamp(stamp) {
		var theDate = new Date(stamp * 1000);
		if (theDate) {
			return true;	//code
		}
		showMessage("invalid timestamp");
		return false;
	}
	
	
	/**
	 * @method isLocation
	 * confirm we have a valid GBP location object
	 * @param {Number} stamp prospective location object
	 * @return {Boolean} if a timestampe, return true, else false
	 * lat = {
	 *   lat:float, -90/+90
	 *   latdir = 'n, s'
	 *   long: float, -180/+180-
	 *   longdir = 'e, w'
	 *   }
	 *   example: 77.33 N, 55.2 E
	 */
	function isLocation(loc) {
		valid = 0;
		if (loc.latitude && loc.latitude >= -90 && loc.latitude <= 90 ) {
			valid++;
		}
		if (loc.longdir && (loc.latdir.toLowerCase() == "n" || loc.latdir.toLowerCase() == "s")) {
			valid++;
		}
		if (loc.longt && loc.longitude >= 0 && loc.longitude <= 180) {
			valid++; 
		}
		if (loc.longdir && (loc.longdir.toLowerCase() == "e" || loc.longDir.toLowerCase() == "w")) {
			valid++;
		}
		if (valid == 4) {
			return true;
		}
		showMessage("invalid location");
		return false;
	}
	
	
	/**
	 * @method isDimensions
	 * confirm we have a valid GBP dimensions object
	 * @param {Number} stamp prospective dimensions object
	 * @return {Boolean} if a timestampe, return true, else false
	 * dir = {
	 *   width: float, >= 0
	 *   height: float, >= 0
	 *   depth: float, >=0 (optional)
	 *   }
	 */
	function isDimensions(dim) {
		if (dim.width && dim.width >=0 ) {
			valid++;
		}
		if (dim.height && dim.height >= 0) {
			valid++;
		}
		if (dim.depth && dim.depth >= 0) {
			valid++;
		}
		else {
			valid--;
		}
		if (valid >= 2) {
			return true;
		}
		return false;
	}
	
	
	/**
	 * check to make sure that the browser core version number makes sense
	 * http://stackoverflow.com/questions/82064/a-regex-for-version-number-parsing
	 */
	function isVersion(vers) {
		
		vers = vers.toString();
		
		//get stuff like 10.2.3
		
		if(/^(\d+\.)?(\d+\.)?(\d+\.)?(\*|\+|\d+)()(\+)?$/.test(vers)) {
			return true;
		}
		
		//get stuff like 10_4_4
		
		else if(/^(\d+\_)?(\d+\_)?(\d+\_)?(\*|\+|\d+)$/.test(vers)) {
			return true;
		}
		
		//get stuff that is almost entirely numbers, any order, e.g. 4.03.b5.55x
		
		var result = vers.match(/[0-9\.]/g);
		var dotnums = 0;
		if(result) {
			for (var i in result) {
				
				dotnums++;
			}
			
			var tot = (vers.length)/1.5;
			
			//half of the string should be numbers and dots
			
			if (dotnums > tot) {
				return true;
			}
		}
		
		return false;
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * wrap getElementById  and parent and child-getting DOM elements
	 * so we can handle either a DOM element or id='xxx'
	 * as our input field
	 * ------------------------------------------------------------------------- 
	 */
	
	/**
	 * @method getElement
	 * get an element, allowing id string to be passed, or the element itself
	 * simplifies DOM access.
	 * NOTE: requires that Object.toType() be defined!
	 * @param {String|Object} elem the parameter to have its type tests
	 * @returnm {Object|false} a DOMElement, or 'false' if not found
	 */
	function getElement(elem) {
		
		if (Object.toType(elem) === "string") {
			return document.getElementById(elem);
		}
		else if(Object.toType(elem) === "number") {
			console.log("ERROR: getElement() passed a number as an DOMElement"+elem);
		}
		else {
			if(elem && 'nodeType' in elem) { //test if a DOM object
				return elem;	
			}
			else {
				console.log("ERROR: getElement() unknown element:"+elem);
			}
			
		}
		return false;
	}
	
	
	/**
	 * return a reference to the parent of an element pointed
	 * @param {DOMObject|string} the internal element, or its id.
	 * @param {String} tagName name of the tag to recurse upwards to
	 * @return {DOMObject} the parent or ancestor element, or itself if fails
	 */
	function getParentElement(id, tagName) {
		
		var elm = getElement(id);
		if (elm) {
			tagName = tagName.toUpperCase();
			
			if(DEBUG) {
				console.log("getParentElement() - id:"+id+" and tagName:"+tagName);
				console.log("tagName is:"+tagName);
				console.log("nodeName is:"+elm.nodeName);
			}
			
			//loop up until parent element is found
			
			while (elm && elm.nodeName !== tagName && elm.parentNode) {
				elm = elm.parentNode;
				if(DEBUG) console.log("getParentElement() - elm.parentNode:"+elm.parentNode);
			}
			}
			else {
				console.log("ERROR: getParentElement() - element not on page anymore");
			}
			
		//return the parent element
		
		return elm;
	}
	
	/**
	 * @method getChildElements
	 * determine if an element has another element as its child, cross-browser
	 * http://stackoverflow.com/questions/2161634/how-to-check-if-element-has-any-children-in-javascript
	 * @param {DOMElement} elem a DOMElement with potential children
	 * @return {DOMElement Array}if there are non-text node children, return them in a JS array
	 */
	function getChildElements(elem) {
		var parent = getElement(elem);
		var child, childList = [];
		for (child = parent.firstChild; child; child = child.nextSibling) {
			if (child.nodeType == 1) { //nodeType 1 == Element
				childList[j++] = child;
			}
		}
		
		return childList;
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * emulate a combobox input field in old browsers
	 * TODO: NOT USED
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/** 
	 * imitate HTML5 (combobox)
	 * comboInit binds the input field the pulldown list
	 * @param {Object|String} lst the list object (sent as 'this')
	 * @param {Object|String} inp input of associated text field object
	 */
	function comboInit(lst, ipt) {
		input = getElement(ipt);
		list  = getElement(lst);
		var index = list.selectedIndex;
		var content = list.options[index].innerHTML;
		if(input.value == "") {
			input.value = content;
		}
	}
	
	
	/** 
	 * imitate HTML5 combobox
	 * @param {Object|String} lst list object (sent as 'this')
	 * @param {Object|String} ipt input field, of the associated text field object
	 */
	function combo(lst, ipt) {
		list  = getElement(lst);
		input = getElement(ipt);
		var index = list.selectedIndex;
		var content = list.options[index].innerHTML;
		input.value = content;	
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * select fields
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/** 
	 * get the current select
	 * @param {Object|String} sel the DOM object (<select>), or the id of the object
	 * @return {String|Boolean} if ok, return the value (e.g. id, not visible text) of the selected option, otherwise false
	 */
        function getCurrentSelect(sel) {
		var list = getElement(sel);
		if(list && list.options && list.selectedIndex != -1) {
			return list.options[list.selectedIndex].value;
		}
		else {
			console.log("ERROR: getCurrentSelect() - invalid selectedIndex for:"+list);
		}
		
		return false;
        }
	
	
	/** 
	 * get the current select text (between <option>...</option>
	 * @param {Object|String} id id of DOM element (must be a <select> tag);
	 * @return {String|Boolean} if ok, return the text between <option>...</option>, otherwise false
	 */
        function getCurrentSelectText(sel) {
		var list = getElement(sel);
		if(list && list.options && list.selectedIndex != -1) {
			return list.options[list.selectedIndex].text;
		}
		else {
			console.log("ERROR: getCurrentSelectText() - selectedIndex for:"+list);
		}
		
		return false;
          }
	
        
        /**
         * @sortSelect
         * sort the <select> text
         * @param {DOMObject|string} element, by id or actual object
         * @param {String} selector which option to select as active
         * @param {String} topValue if present, scan for this item in the menu,
         * and move it to the top of the menu. Used to make sure "New Property" is
         * at the top after a sort.
         */
          function sortSelect(sel, selector, topValue) {
                if (!selector) {
                        selector = 0;
                }
                var list = getElement(sel);
		
                var tmpAry = new Array(); //temp array
                for (var i=0; i<list.options.length; i++) {
                        tmpAry[i] = new Array();
                        tmpAry[i][0] = list.options[i].text;
                        tmpAry[i][1] = list.options[i].value;
                }
                    
                tmpAry.sort(); //actual sort
		
		//if topItem is present, move to top
		
		if (topValue !== undefined) {
			//finding i will require a loop
			var len = tmpAry.length - 1;
			for(var i = len; i--;){
				if (tmpAry[i][1] == topValue) { // "0" to 0
					var topItem = [tmpAry[i][0], tmpAry[i][1]];
					tmpAry.splice(i, 1);
					break; //only look for the first
				}
			}
			if (topItem) {
				tmpAry.unshift(topItem);
			}
		}
			
		//null out our old list for old IE
		    
                while (list.options.length > 0) {
                      list.options[0] = null;
		}
			
                for (var i=0; i < tmpAry.length; i++) {
			var op = new Option(tmpAry[i][0], tmpAry[i][1]);
                        if(op.value == selector) {
                                op.selected = true;
                        }
                        list.options[i] = op;
                }      
                if(DEBUG) console.log("sortSelect() - sorting...");
                return;
          }
	
	/** 
	 * select an option in a select list when its value matches a supplied string
	 * @param {String} id the id of the DOM element
	 * @param {String} value we are scanning for in the <select> tag
	 */
	function setSelectByOptionValue(sel, value) {
		
          	var list = getElement(sel);
		var valueSet = false;
		var listLen = list.options.length;
		for (var i = 0; i < listLen; i++) {
			if (list.options[i].value == value) {
			      list.options[i].selected = true;
			      valueSet = true;
			}
 		}
		
		if (!valueSet) {
			for (var i = 0; i < listLen; i++) {
			if (list.options[i].value == NO_RECORD) {
				list.options[i].selected = true;
                        }        
                    }
		}
	}
	
	//TODO: we need a server-side filter as well!
	
	/**
	 * @method updateSelectOption(id, value)
	 * if we got a new list of options back from the server, change the
	 * name(s) in the <option> list, usually the 'title' field in the db
	 * @param {String} id the id of the select element
	 * @param {Number} optionValue <option value="...">
	 * @param {String} optionText the new text between the <option>...</option>
	 */
	function updateSelectOption(sel, optionValue, optionText) {
		var list = getElement(sel);
		var listLen = list.options.length;
		if(DEBUG) console.log("updateSelectOption() - listLen:"+list.options.length);
		for (var i = 0; i < listLen - 1; i++) {
			if (list.options[i].value == optionValue) {
				console.log("updateSelectOption() - changing to "+optionText);
				//list.options[i].text = optionText; //just doing this doesn't update the select
				val = list.options[i].value;
				list.options[i] = new Option(optionText, val);
				list.options[i].selected = true;
			}
		}
	}
	
	
	/**
	 * @method deleteSelectOptionByValue
	 * delete an option by the its value in the option list
	 */
	function deleteSelectOptionByValue(sel, value) {
		var list = getElement(sel);
		var listLen = list.length - 1;
		for (var i = listLen; i >= 0; i--) {
			if (list.options[i].value == value) {
				list.remove(i);
				//list.options[i] = null; //may need for some old browsers
			}
		}
		
	}
	
	
	/**
	 * @method deleteSelectOption
	 * delete an existing <option>... that is selected from the list
	 */
	function deleteSelectOption(sel) {
	  
		var list = getElement(sel);
		var listLen = list.length - 1;
		for (var i = listLen; i >= 0; i--) {
			if (list.options[i].selected) {
				list.remove(i);
				//list.options[i] = null; //may need for some old browsers
			}
		}
	}
	
	
	/**
	 * @method insertSelectOption
	 * insert an existing <option>.. from the list
	 * @param {Object|String} sel the <select> element, or its id
	 * @param {String} optionValue value of the <option value="...">
	 * @param {String} optionText the text between the <option>...</option>
	 * @param {Number} index (optional) if provided the position to insert new
	 * option into the list
	 */
	function insertSelectOption(sel, optionValue, optionText, index) {
	  
		//accept id or DOMElement
		
		console.log("insertSelectOption");////////////////////////////
	  
		var list = getElement(sel);
	 
		//if an index is not provided, grab the currently selected index in the <select>
	  
		if (!index || index > list.options.length || index < 0) {
			index = list.selectedIndex;
		}
	  
		//create <option> object
	  
		var optionNew   = document.createElement('option');
		optionNew.text  = optionText;
		optionNew.value = optionValue;
		
		//get the old selection
	  
		var optionOld = list.options[list.selectedIndex];
		
		//insert before selected element
	  
		try {
			console.log("standards version"); ////////////////////////
			list.add(optionNew, optionOld); // standards compliant; doesn't work in IE
		}
		catch(ex) {
			console.log("ie version"); /////////////////////////
			list.add(optionNew, list.selectedIndex); // IE only
		}
		optionNew.selected = true;
		
		//redraw fix, may be necessary in some versions of Opera
		//http://ajaxian.com/archives/forcing-a-ui-redraw-from-javascript

		//list.style.display="none";
		//list.offsetHeight;
		//list.style.display="block";
		//list.className = list.className;
		
		return optionNew; //return the list element (keep track if we sort later)
	}
	
	
	/**
	 * MSIE before MSIE 10 sets the <option> list of a <select> as read-only. So, you can't
	 * insert an option list into the table. This patch works for IE < 10
	 * http://stackoverflow.com/questions/4729644/cant-innerhtml-on-tbody-in-ie
	 * helper function provide event handling for the table body, since it has
	 * been re-created for IE, we have to re-set it.
	 */
	function MSIEsetSelInnerHTML(sel, selId, selName, html) { //fix old IE problem with dynamically updating <select> lists
		var temp = MSIEsetSelInnerHTML.temp;
		if (!selId) {
			console.log("ERROR: MSIEsetSelInnerHTML id undefined for this select form element");
		}
		if (!selName) {
			console.log("ERROR: MSIEsetSelInnerHTML name undefined for this select form element");
		}
		temp.innerHTML = '<select id="'+selId+'" name="'+selName+'">' + html + '</select>';
		sel.parentNode.replaceChild(temp.firstChild, sel); //NOT firstChild.firstChild (that is for a table)
	}
	
	
	/**
	 * @method setSelOptions
	 * set the innerHTML of a <select> list in a way compatible with old IE
	 * @param selId the id of the <select>
	 * @param html the HTML we want to insert (an option list)
	 * @return {Boolean} if true, we didn't use the nasty MSIE<10 method, and we are
	 * done. If false, we just re-created the entire table and need to re-attach
	 * event handlers, styles, etc.
	 */
	function setSelOptions(selId, html) {
		var sel = getElement(selId);
		if(DEBUG) console.log("setSelOptions compatible set for select innerHTML");
		if (sel) {
			if (isOldIE()) {
				console.log("setSelOptions() - is old IE");
				MSIEsetSelInnerHTML.temp = document.createElement('div');
				MSIEsetSelInnerHTML(sel, sel.id, sel.name, html); 
			}
			else { //by specs, you can not use "innerHTML" until after the page is fully loaded  
				sel.innerHTML = html; //delegate events are ok
				return true;
			}	
			
		}
		return false; //need to re-attach events!
	 }
	 
	 
	/**
	 * -------------------------------------------------------------------------
	 * radio buttons
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/** 
	 * manually check/uncheck the radio buttons, since we are using stopPropagation()
	 * NOTE: IE will also return any id values that are the same as the name= attribute,
	 * as will older versions of Opera
	 * @param {String} name <input type="radio" name="name"...
	 * @param {String} val value of radio button
	 */
	function setRadio(name, val) {
		
		var radios = document.getElementsByName(name);
		for (var i = 0; i < radios.length; i++) {
			radios[i].checked = (radios[i].value == val);
		}
	}
	

	/**
	 * -------------------------------------------------------------------------
	 * equivalent of sleep() in DOM
	 * ------------------------------------------------------------------------- 
	 */

	
	/**
	 * @method sleep
	 * pause execution of a function for a given period of time, then execute it
	 * up to 3 parameters may be passed.
	 * @param {Function} fn the function we are delaying
	 * @param {Number} timeout the time to delay, in milliseconds
	 * @param {mixed} p1-p4 (up to 4 parameters, nulled later for old IE compatibility)
	 */
	function delay (fn, timeout, p1, p2, p3) {
		if (typeof fn === "function" && timeout > 0) {
			setTimeout(function () { fn(p1, p2, p3); p1=null;p2=null;p3=null;}, timeout);
		}
		else {
			console.log("bad sleep, fn:"+fn+" timeout:"+timeout);
		}
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * element covering the screen, prevent click-through
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/**
	 * create a translucent element, expand it to cover the screen, and stop click-through
	 */
	function showBlur() {
		
		console.log("creating blurDiv");
		var blurDiv = document.getElementById("blurDiv");
		if (!blurDiv) {
			blurDiv = document.createElement("div");
		}
		
		//we use the height of the whole document, rather than screen height
		
		blurDiv.id  = "blurDiv";
		var h = getDocumentHeight();
		blurDiv.style.cssText = "position:absolute; top:0; right:0; width:" + screen.width + "px; height:" + h + "px; background-color: #000000; opacity:0.3; filter:alpha(opacity=40); z-index:1000";
		document.getElementsByTagName("body")[0].appendChild(blurDiv);
		return blurDiv;
	}
	
	
	/**
	 * remove a blur element preventing click-through to the window
	 */
	function hideBlur (topElem) {
		var blurDiv = document.getElementById("blurDiv");
		if (blurDiv) {
			console.log("removing blurdiv");
			blurDiv.parentNode.removeChild(blurDiv);
		}		
	}
	
	
	/**
	 * ------------------------------------------------------------------------
	 * utilities for cented modal windows
	 * ------------------------------------------------------------------------
	 */
	
	
	/**
	 * @method getScroll
	 * get amount of scrolling for the window, useful for centering
	 * modal windows onscreen
	 * @returns {Object} an object, with x and y values for scrolling, with 0 meaning
	 * no scrolling has occured
	 */
	function getScroll() {
		var win = window, scrolledX, scrolledY;
		if(win.pageYOffset) {
			scrolledX = win.pageXOffset;
			scrolledY = win.pageYOffset;
		}
		else if(document.documentElement && document.documentElement.scrollTop) { //old IE
			scrolledX = document.documentElement.scrollLeft;
			scrolledY = document.documentElement.scrollTop;
			}
		else if( document.body ) {
			scrolledX = document.body.scrollLeft;
			scrolledY = document.body.scrollTop;
		}
		return {
			x:scrolledX,
			y:scrolledY
		}
	}
	
	/**
	 * @method getCenter
	 * get the center coordinate of the browser window
	 * @returns {Object}, an object, with x and y values for the center coordinate,
	 * measured fromt the top of the window
	 */
	function getCenter() {
		var win = window, centerX, centerY; 
		if(win.innerHeight ) {
			centerX = win.innerWidth;
			centerY = win.innerHeight;
		}
		else if(document.documentElement && document.documentElement.clientHeight) {
			centerX = document.documentElement.clientWidth;
			centerY = document.documentElement.clientHeight;
		}
		else if(document.body) {
			centerX = document.body.clientWidth;
			centerY = document.body.clientHeight;
		}
		return {
			x:centerX,
			y:centerY
		}
	}
	
	
	/**
	 * @method getViewportCenter()
	 * get the exact center of the browser window, adjusted for scrolling
	 * @returns {Object} an object, with the x and y coordinates of the
	 * current viewport
	 */
	function getViewportCenter() {
		
		//determine amount of scrolling, and coordinates of window center
		
		var scrolled   = getScroll();
		var center     = getCenter();
		var leftOffset = scrolled.x + (center.x / 2);
		var topOffset  = scrolled.y + (center.y / 2);
		
		return {
			top:topOffset,
			left:leftOffset
		}	
	} 
	
	/**
	 * @method getDocumentHeight()
	 * get the height of the document (may be scrolled and larger than screen)
	 */
	function getDocumentHeight() {
		return Math.max(document.documentElement["clientHeight"],
			document.body["scrollHeight"],
			document.documentElement["scrollHeight"],
			document.body["offsetHeight"],
			document.documentElement["offsetHeight"]);
		
	}
	
	
	/**
	 * @method getCSSStyle
	 * look in the computed style, and get a defined CSS property. 
	 * reference: http://www.javascriptkit.com/dhtmltutors/dhtmlcascade4.shtml
	 * @param {DOMObject} elm DOM element
	 * @param {String} cssProp the css property, e.g. "width"
	 * @returns {String} the string value of the CSS property
	 */
	function getCSSStyle(elm, cssProp){
		if (elm.currentStyle) { //old IE
			return elm.currentStyle[cssProp];
		}
		else if (document.defaultView && document.defaultView.getComputedStyle) { //Firefox, Chrome, Safari
			return document.defaultView.getComputedStyle(elm, "")[cssProp];
		}
		else { //default to inline style
			return elm.style[cssProp];
		}
	}
	
	/**
	 * -------------------------------------------------------------------------
	 * ajax spinner
	 * ------------------------------------------------------------------------- 
	 */
	

	/**
	 * @method hasSpinner
	 * check if the document currently has a spinner above it
	 * @returns {Boolean} true if spinner present, false otherwise
	 */
	function hasSpinner(spinnerClassName) {
		var spinner = document.getElementsByClassName(spinnerClassName)[0];
		if (spinner.style.display == "block" && spinner.style.zIndex == ZINDEX_SPINNER) {
			return true;
		}
		else {
			return false;
		}
	}
	
	
	/** 
	 * show the spinner (as defined by spinnerClassName)
	 * @param {String} spinnerClassName - name of element showing spinner
	 * @param {Array} localCenter x/y point coordinates for centering the spinner on a specific element
	 * @param {Array} localSize x/y point coordinates to sizing the spinner relative to a element being updated
	 * 'spinner-win'
	 */
	function showSpinner(spinnerClassName, localCenter, localSize) {
		
		var spinner = document.getElementsByClassName(spinnerClassName)[0];
		
		if(spinner) {
			
			/**
			 * if we finish before the spinner delay is over, we might show the spinner
			 * after we have hidden it. If so, the value of id will be negative.
			 */
			if (spinner.id < 0) {
				console.log("already done, spinner not needed");
				spinner.id = 0;
				return;
			}
			
			console.log("showing spinner");
			
			var pos = getViewportCenter();
			spinner.style.top  = pos.top  - 74 + "px"; //HARD-CODED!!!! see gbp_initializr.css
			spinner.style.left = pos.left - 74  + "px";
			
			spinner.style.zIndex = ZINDEX_SPINNER;     /* force spinner to top */
			spinner.style.display = "block";
			spinner.style.visibility = "visible";
			spinner.id = "1";
			
			//now create a temporary div that blocks editing the form while ajax call is underway
			
			showBlur();
		}
		else {
			console.log("ERROR: showSpinner() - spinner not found, className:"+spinnerClassName);
		}
	}


	/** 
	 * hide the spinner (as defined by spinnerClassName)
	 * @param {String} spinnerClassName - name of element showing spinner,
	 * 'spinner-win'
	 */
	function hideSpinner(spinnerClassName) {
		
		var spinner = document.getElementsByClassName(spinnerClassName)[0];
		
		if(spinner) {
			
			console.log("hiding spinner");
			spinner.style.display = "none";
			spinner.style.visiblity = "hidden";
			spinner.style.zIndex  = ZINDEX_BOTTOM;  /* return spinner to low level in stack */
			var num = parseInt(spinner.id);
			num--;
			spinner.id = num;
			
		}
		else {
			//lack of spinner may not be an error
			//console.log("ERROR: hideSpinner() - spinner not found");
		}
		
		hideBlur();
	}
	
	
	/**
	 * ajax mini-spinner (used in form rows)
	 */
	function showMiniSpinner(spinnerId) {
		var spin = getElement(spinnerId);
		if (spin) {
			var img = new Image();
			img.id  = 'spinner-small-id'
			img.src = 'img/icons/spinner-butt.gif';
			img.style.margin = "1px 0 0 0";
			spin.appendChild(img);
		}
	}
	
	function hideMiniSpinner(spinnerId) {
		var spin = getElement(spinnerId);
		if (spin) {
			var img = spin.getElementsByTagName('img')[0];
			if (img) {
				spin.removeChild(img);
			}
		}
	}
	
	/**
	 * -------------------------------------------------------------------------
	 * reference window
	 * ------------------------------------------------------------------------- 
	 */
	
	/**
	 * @method urlMatch
	 * see if a string is a url
	 * @param {String} str the string to test
	 * @returns {Boolean} if it is a url, return true, else false
	 */
	function urlMatch(url) {
		if (url.length > 0) {
			var matcher = /https?\:\/\/\w+((\:\d+)?\/\S*)?/;
			if (url.match(matcher)) {
				console.log("returning true for:"+url);
				return true;
			}
		}
		console.log("returning false for:"+url);
		return false;
	}
	
	
	/**
	 * @method addReference
	 * add a new reference to the database. Fired when a blur event happens with
	 * fields in the reference window. A click on the "close" button will be trapped by
	 * the "checkForRefWinClose()" and not come here
	 */
	function ajaxAddReference(refField, refTableName, refItemId, refClassName) {
		
		//check the position of the mouse. If it is on our close button, don't process
		
		console.log("in ajaxAddReference");
		var tbl = getElement('reference-url-list');
		var row = getTableRow(tbl, refField);
		
		//get the individual cell fields
		
		var datField  = row.cells[0].getElementsByTagName('input')[0]; //date
		var titlField = row.cells[1].getElementsByTagName('input')[0]; //title
		var urlField  = row.cells[2].getElementsByTagName('input')[0]; //url
		var descField = row.cells[3].getElementsByTagName('input')[0]; //description
		
		var dat    = datField.value;  //date 'ref_date'
		var titl   = titlField.value; //website name, 'site_name'
		var url    = urlField.value;  //website url, 'url'
		var desc   = descField.value; //description
		
		console.log("tableName:" + refTableName + "itemId:" + refItemId + " dat:" + dat + " titl:" + titl + " url:" + url + " desc:" + desc);
		
		//do a regex test of the URL. If not valid, don't submit
		
		if (dat.length > 0 && titl.length > 0 && url.length > 0 && desc.length > 0) {
			
			if(!urlMatch(url)) {
				alert("Invalid URL, should have http:// or https");
				urlField.focus();
				urlField.select();
				return;
			}
			
			if (!isDate(dat)) {
				alert("Invalid date (Y-M-D)");
				return
			}
			
			//spinner-ref-div
			
			showMiniSpinner('spinner-ref-div');
			
			//ajax call to create the new row
			
			ajaxRequest(null, 'php/api.php?cmd=insert_new_reference&tblnm=' + refTableName + '&itmid=' + refItemId + '&dat=' + dat + '&url=' +url + '&titl='+titl + '&desc=' + desc, function (response) {
				
				var JSONObject = GBPInitializr.processJSON(response, ["apiresult"]);
				
				if(JSONObject) {
					console.log("got back an object");
					
					//inserts don't return a full object, so construct
					
					var obj = {
						'id':JSONObject.reference_id,  //id of REFERENCE record
						'ref_table_name':refTableName, //table name reference is linked to
						'ref_table_item_id':refItemId, //record in table reference is linked to
						'url':url,                     //information about the reference
						'ref_date':dat,
						'title':titl,
						'description':desc
					};
					
					//after return, move the row up into the array, and create a new blank row
					
					var tblBody   = tbl.getElementsByTagName('tbody')[0];
					deleteTableRow(titlField);
					refWinInsertRow(tblBody, obj);
					refWinInsertEditRow(tblBody, refTableName, refItemId);
					
					//we also need to resize our refWin
					
					var height = calcRefWinSize(refClassName);
					
				}
				hideMiniSpinner('spinner-ref-div');
				
			}, //end of callback
			
			'get'); //end of ajaxRequest()
			
		}
		else {
			
		}
		
	}
	
	
	/**
	 * @method deleteReference
	 * delete a reference from the database
	 */
	function ajaxDeleteReference(refField, refClassName) {
		console.log("in ajaxDeleteReference, refField:"+ refField);
		
		if(refField && refField.id) {
			
			var id = refField.id;
			if(id) {
				var refid = id.split('-');
					console.log("refid:"+refid);
				
				//this is enough to delete the row
				
				GBPInitializr.ajaxRequest(null, 'php/api.php?cmd=delete_reference&refid=' + refid, function (response) {
					
					var JSONObject = GBPInitializr.processJSON(response);
					console.log("JSONObject is:"+JSONObject);
					
					if(JSONObject) {
						console.log("got back an object");
						if(JSONObject.apiresult == "true") {
							deleteTableRow(refField);
							
							//resize table's parent modal window
							
							var height = calcRefWinSize(refClassName); 
						}
						else {
							console.log("ERROR: failed to delete record for id:"+id);
						}
					}
				
				},
				
				'get'); //end of ajaxRequest(
			}
			else {
				console.log("ERROR: ajaxDeleteReference() invalid id");
				
			} //valid id
			
		} //valid reference field
		
	}
	
	
	/**
	 * @method showRefWin
	 * show a modal window to add, remove, check references associated with a particular item on the screen
	 * for a particular table
	 * @param {Object} obj the object (Array) with a list of current references
	 * @param {String} refTableName the name of the table we're associating the reference with
	 * @param {Number} refItemId the row id in the table we're associating the reference with
	 * @param {String} refItemName the name of the reference (specific to each reference hyperlink)
	 * @param {String} refClassName the class='xxx' for this window
	 */
	function showRefWin(obj, refTableName, refItemId, refItemName, refClassName) {
		
		console.log("showing reference window, tableName:"+refTableName+" itemId:"+refItemId+" refItemName:"+refItemName+" refClassName:"+refClassName);
		
		//get the window as the first element with its className
		
		var ref = document.getElementsByClassName(refClassName)[0];
		if (ref) {
			
			//if our window is already active, return
			
			if (ref.style.zIndex >= ZINDEX_REF_WIN) {
				return;
			}
			
			/**
			 * our refWindow is currently invisible and empty.
			 * get the number of references, and construct an appropariate title
			 */
			
			var len = obj.length;
			
			console.log("found the ref window by class, number of refs is:"+len);
			
			var title = document.getElementById('reference-title');
			
			if (refItemName === false) {
				refItemName = '';
			}
			
			var titleStr = ": " + refItemName + ' in ' + refTableName;
			
			if(len == 0) {
				titleStr += ' (none defined)';
			}
			
			//apply the title
			
			title.innerHTML = titleStr;
			
			//generate an HTML table
			
			var tbl          = ref.getElementsByTagName('table')[0];
			var tblBody      = tbl.getElementsByTagName('tbody')[0];
			
			deleteTableContent(tbl); //delete if hasn't been deleted yet
			
			//loop through contents
			
			for(var i = 0; i < len; i++) {
				refWinInsertRow(tblBody, obj[i], refClassName);
			}
			
			/**
			 * define the new reference fields, and an onblur event
			 * we DON'T use event delegation here, to avoid complexity and
			 * collisions with the delegate processing on the main form. This
			 * is OK since any one element with a "ref" will likely have only one
			 * or two references associated with it
			 */
			refWinInsertEditRow(tblBody, refTableName, refItemId, refClassName);
			
			//set position and visibility NOTE: width is HARD-CODED since 
			//the .style property doesn't return a value yet
			
			var pos = getViewportCenter();
			var width = 800; //HARD-CODED!!!
			var height = calcRefWinSize(refClassName);
			
			ref.style.top       =  pos.top - height/2 + "px";
			ref.style.left      =  pos.left - 400 + "px";
			ref.style.zIndex    = ZINDEX_REF_WIN;     /* force to top, but below spinner and calendar */
			ref.style.visiblity = "visible";
			ref.style.display   = "block";
			
			//using the HTML Table API makes it more likely that we can attach a calendar polyfill
			
			addCalendarWidget(tblBody);
		}
		showBlur(); //cover the main window with blur div to prevent click-through
	}
	
	/**
	 * @method refWinInsertRow
	 * insert a row, with data, into the reference window table
	 * NOTE: these cells are NOT active, except for their "delete" button
	 * @param {DOMTableObject} tblBody the <tbody>
	 * @param {Object} obj JSON object with values to insert into rows
	 */
	function refWinInsertRow(tblBody, obj, refClassName) {
		
		//the id refers to the id of the REFERENCE record
		
		var delButt = '<input type="button" class="subb" name="'+obj.id+'-ref" id="'+obj.id+'-ref" value="delete" onclick="GBPInitializr.ajaxDeleteReference(this, \''+refClassName+'\')">';
		
		var row    = tblBody.insertRow(-1); //into <tbody>, always last
		var link   = '<a href="'+obj.url+'" target="_blank">'+obj.title+'</a>';
		
		if (row) {
			var cell        = row.insertCell(-1); //date
			cell.innerHTML  = obj.ref_date;
			cell            = row.insertCell(-1); //title
			cell.innerHTML  = obj.title;
			cell            = row.insertCell(-1); //url
			cell.innerHTML  = link;
			cell            = row.insertCell(-1); //description
			cell.innerHTML  = obj.description;
			cell            = row.insertCell(-1); //delete button
			cell.className  = "center";
			cell.innerHTML  = delButt;
		}
	}
	
	
	/**
	 * @method refWinInsertEditRow
	 * insert a blank row, with edit fields, into the reference window table
	 * @param {DOMTableObject} tBody <tbody>
	 * @param {String} refTableName name of table the reference is linked to
	 * @param {Number} itemId the row in the table the reference is linked to
	 */
	function refWinInsertEditRow(tblBody, refTableName, refItemId, refClassName) {
		
		var dat  = '<input type="date" name="0-refdate" id="0-refdate" onblur="GBPInitializr.ajaxAddReference(this,\''  + refTableName + '\','+refItemId + ',\'' + refClassName + '\');">';
		var titl = '<input type="text" name="0-refsite" id="0-reftitl" onblur="GBPInitializr.ajaxAddReference(this,\''  + refTableName + '\','+refItemId + ',\'' + refClassName + '\');">';
		var url  = '<input type="text" name="0-refurl"  id="0-refurl" onblur="GBPInitializr.ajaxAddReference(this,\''   + refTableName + '\','+refItemId + ',\'' + refClassName + '\');">';
		var desc = '<input type="text" name="0-refdesc"  id="0-refdesc" onblur="GBPInitializr.ajaxAddReference(this,\'' + refTableName + '\','+refItemId + ',\'' + refClassName + '\');">';
		
		//TODO: figure out a better idea for a form control here. Possibly a checkbox that "checks" when complete
		//TODO: also "center" is not working
		var spinnerdiv = '<div id="spinner-ref-div" class="subb spinner-div" style="padding:0">';
		
		var highlightClass = 'row-highlight';
		
		var row = tblBody.insertRow(-1);
		
		var cellFirst = row.insertCell(-1); //date
		cellFirst.className = highlightClass;
		cellFirst.innerHTML = dat;
		var cell = row.insertCell(-1); //title
		cell.className = highlightClass;
		cell.innerHTML = titl;
		cell = row.insertCell(-1); //url
		cell.className = highlightClass;
		cell.innerHTML = url;
		cell = row.insertCell(-1); //description
		cell.className = highlightClass + " center";
		cell.innerHTML = desc;
		cell = row.insertCell(-1); //delete button
		cell.className = highlightClass + " center"; //TODO: THIS DOES NOT CENTER!!!!!!!!!!!!!!!!
		cell.innerHTML = spinnerdiv;
		
		//add the calendar widget to first row, if needed
		
		addCalendarWidget(tblBody);
		
		//select the first field
		
		var refField = cellFirst.getElementsByTagName('input')[0];
		refField.focus();
		//refField.select();
	}
	
	
	/** 
	 * @method calcRefWinSize 
	 * computes a height for the reference window, based on the numbers 
	 * of rows in the reference table
	 * @param {String} refClassName class for the reference window
	 * @returns {Number} an integer giving the computed height of the table
	 */
	 function calcRefWinSize (refClassName) {
		var ref = document.getElementsByClassName(refClassName)[0];
		if (ref) {
			var tbl = ref.getElementsByTagName('table')[0];
			if(tbl.rows && tbl.rows.length) {
				return tbl.rows.length*19;
			}
		}
		return 0;
	 }
	 

	/**
	 * @method hidRefWin
	 * hide a modal reference window, doing any saves and deleting DOM content
	 * @param {String} refClassName the class="xxx" for the reference window
	 */
	function hideRefWin (refClassName) {
		console.log("hiding reference window");
		
		//if the calendar polyfill was visible, hide it
		//TODO: DOES NOT WORK, RE-WRITE CALENDAR POLYFILL
		
		if (window.calendar) {
			window.calendar.hideCalendar();
		}
		
		var ref = document.getElementsByClassName(refClassName)[0];
		if (ref) {
			
			//remove the blur event
			
			var inputs = document.getElementsByTagName('input');
			for (var i = 0; i < inputs.length; i++) {
				inputs[i].onblur = null; //kill the blur event
			}
			
			//delete the current table content
			
			var tbl           = ref.getElementsByTagName('table')[0];
			var tblBody       = tbl.getElementsByTagName('tbody')[0];
			
			deleteTableContent(tbl); //deletes all <td> rows, assume first row is a <th>
			
			ref.style.display = "none";
			ref.style.zIndex  = ZINDEX_BOTTOM;  /* return spinner to low level in stack */
		}
		hideBlur(); //restore access to main window
	}
	
	
	/**
	 * @method checkForRefWinClose
	 * blur events happen before click events. So, if we have invalid input and
	 * we post an alert on an onclick event, the blur will happen. But mousedown events
	 * happen before a blur, so handle the case where we just want to close here.
	 * @param {String} refClassName className for reference window
	 */
	function checkForRefWinClose(refClassName) {
		console.log("in checkForRefWinClose");
		
		var ref = document.getElementsByClassName(refClassName)[0];
		if (ref) {
			hideRefWin();
		}
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * calendar widget
	 * -------------------------------------------------------------------------
	 */

	
	 /**
	 * attach a 'calendar' polyfill, if needed
	 * called when we have a 'date' field present in the tBody
	 * looking at input.type is unreliable, many browsers will list
	 * input type='date' as input.type === 'text' so we use the
	 * custom id we set for date fields
	 * we only use this if we've loaded a 'calendar' polyfill correctly over in
	 * index.php (using Modernizr or GBP)
	 * @param {String|DOMElement} tblId either an element, or element.id string
	 */
	function addCalendarWidget(tblId) {
		
		//accept id or actual element
		
		var tbl = getElement(tblId);
		
		//confirm polyfill was loaded
		if (window.calendar) {
			
			var tbl = GBPInitializr.getElement(tblId);
			var inputs = tbl.getElementsByTagName('input');
			console.log("addCalendarWidget() - got calendar inputs");
			window.inputs = inputs;
			var len = inputs.length;
			for (var i = 0; i < len; i++) {
				if (inputs[i].id && inputs[i].id.indexOf('date') !== -1) { //NOTE: SPECIFIC to our id= value for this kind of field
					//console.log("addCalendarWidget() - got a date input");
					calendar.set(inputs[i]); //we use 'date' to set these	
				}
			}
		}
		//calendar.set("date");
	}

	
	/**
	 * -------------------------------------------------------------------------
	 * table widget (table with form fields)
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/**
	 * @method delegate
	 * @param scope Object :  The scope in which to execute the delegated function.
	 * @param func Function : The function to execute
	 * @param data Object or Array : The data to pass to the function. If the function is also passed arguments, the data is appended to the arguments list. If the data is an Array, each item is appended as a new argument.
	 * @param isTimeout Boolean : Indicates if the delegate is being executed as part of timeout/interval method or not. This is required for Mozilla/Gecko based browsers when you are passing in extra arguments. This is not needed if you are not passing extra data in.
	 * @param {Boolean} isMozilla if true, Mozilla/Gecko-based browser, otherwise false
	 */
	function delegate(scope, func, data, isTimeout, isMozilla) {
		return function() {
			var args = Array.prototype.slice.apply(arguments).concat(data);
			//Mozilla/Gecko passes a extra arg to indicate the "lateness" of the interval
			//this needs to be removed otherwise your handler receives more arguments than you expected.
			if (isTimeout && isMozilla) {
				 args.shift();
			}
			
			func.apply(scope, args);
		}
	}
	
	
	/**
	 * MSIE before MSIE 10 sets the <tbody> of a table as read-only. So, you can't
	 * insert a tbody into the table. This patch works for IE < 10
	 * http://stackoverflow.com/questions/4729644/cant-innerhtml-on-tbody-in-ie
	 * helper function provide event handling for the table body, since it has
	 * been re-created for IE, we have to re-set it.
	 */
	function MSIEsetTBodyInnerHTML(tBody, tBodyId, html) { //fix old IE problem with dynamically updating tables
		var temp = MSIEsetTBodyInnerHTML.temp;
		if (!tBodyId) {
			console.log("ERROR: MSIEsetTBodyInnerHTML() - id not defined for the table body");
		}
		temp.innerHTML = '<table><tbody id="'+tBodyId+'">' + html + '</tbody></table>';
		tBody.parentNode.replaceChild(temp.firstChild.firstChild, tBody);
	}
	 
	 
	/**
	  * public function for setting tBody
	  * @param {DOMElement|String} tBody - table body, or its id
	  * @param {String} html a string with table rows and columns
	  * @return {Boolean} if true, we didn't use the nasty MSIE<10 method, and we are
	  * done. If false, we just re-created the entire table and need to re-attach
	  * event handlers, styles, etc.
	  */
	function setTBody(tBodyId, html) {
	  
		var tBody = getElement(tBodyId);
		if (tBody) {
			if (isOldIE()) {
				MSIEsetTBodyInnerHTML.temp = document.createElement('div');
				MSIEsetTBodyInnerHTML(tBody, tBody.id, html); 
			}
			else { //by specs, you can not use "innerHTML" until after the page is fully loaded
				tBody.innerHTML = html; //delegate events are OK
				return true;
			}
		}
		
		return false; //need to re-attach events!
	}
	 
	  
	/**
	 * get a table row when an event comes from an element in
	 * a table cell - useful for highlighting the row
	 * @param {HTMLTableElement} tbl the HTML <table>
	 * @param (HTMLDOMElement) elem a DOM element which MUST be inside
	 * a table cell.
	 * @returns {Array} Array with all the <td> cell elements from the row
	 * containing the DOMElement
	 */
	function getTableRow(tbl, cellElem) {
		var cellList = [];
		var numCells = 0;
		tbl  = getElement(tbl);
		var elem = getElement(cellElem);
		
		if (tbl && tbl.rows && tbl.rows.length > 0) {
			var numCols = tbl.rows[0].cells.length;
			var td = elem.parentNode;
			if(DEBUG) console.log("getTableRow() - Number of columns is:"+numCols)				
			for (var i = 0, row; row = tbl.rows[i]; i++) {
				for (var j = 0, col; col = row.cells[j]; j++) {
					if (col == td) {
						return tbl.rows[i];
					}
				}
			}
		}
		return false;
	}
	
	
	/**
	 * @method getCellWithElement
	 * Given a table row, return the table cell <td>
	 */
	function getCellWithElement(row, cellElem) {
		var elem = getElement(cellElem);
		if (elem && row.cells && row.cells.length > 0) {
			var cells     = row.cells;
			var lenCells  = row.cells.length;
			for (var i = 0; i < lenCells; i++) {
				var childArr = getChildElements(cells[i]);
				if (childArr && childArr.length) {
					var lenChildren = childArr.length;
					for (var j = 0; j < lenChildren; j++) {
						if (childArr[j] == cellElem) {
							return cells[i];
						}
					}
				}
			}
		}
		return false;
	}	


	/**
	 * we don't have an insertTableRow because in each case, the created
	 * row is unique to the table
	 */
	
	
	/**
	 * deleteTableRowByNum
	 */
	function deleteTableRowByNum(tbl, rowNum) {
		var len = tbl.rows.length;
		if(rowNum >= 0 && rowNum < len) {
			tbl.deleteRow(rowNum);
		}
		
	}
	
	/**
	 * delete a row in an HTML table by finding the row which
	 * houses a given HTML element, like a button.
	 * Note: assumes we used a <tbody> in constructing the table
	 * @param {HTMLDomElement|String} cellElem id or the element inside a table cell. We assume
	 * that the element has a unique id
	 */
	function deleteTableRow(cellElem) {
		
		var elem = getElement(cellElem); //get the content tag inside the table cell
		
		if (!elem.id) {
			console.log("ERROR: deleteTableRow() - DOMElement doesn't have a id");
			return false;
		}
		
		if(DEBUG) console.log("deleteTableRow()  - cellElem.id is:"+elem.id);
		
		var rowSelected = getParentElement(elem.id, "tr");              //recurse upward
		if (rowSelected && rowSelected != elem) {                       //fails if parent is not returned
			if(DEBUG) console.log("deleteTableRow() - found tr");
			var tbl = getParentElement(rowSelected, "tbody");       //recurse upward
			if (tbl == rowSelected) {                               //didn't find it
				if(DEBUG) console.log("deleteTableRow() - didn't find tbody, looking for table");
				tbl = getParentElement(rowSelect, "table");     //in case there's no tbody
			}
			if (tbl && tbl != rowSelected) {
				if(DEBUG) console.log("deleteTableRow() - deleting table row");
				var rowCount = tbl.rows.length;
				for(var i = 0; i < rowCount; i++) {
					if(tbl.rows[i] == rowSelected) {
						tbl.deleteRow(i); //wipe out the row
						rowCount--;
						i--;
						return true;
					}
				}
				console.log("ERROR: deleteTableRow() - row not found in table");
				return false;
			}
			else {
				console.log("ERROR: deleteTableRow() - failed to find enclosing tbody or table");
			}
		}
		else {
			console.log("ERROR: deleteTableRow() - failed to find enclosing tr, cell NOT in table");
		}
		
		return false;
	}
	

	/**
	 * delete all rows in a table
	 * NOTE: we only delete rows with a <td>, avoiding <th>
	 * @param {DOMTableElement} tbl the table with contents
	 */
	function deleteTableContent(tbl) {
		
		console.log("in deleteTableRow");
		
		if (tbl.rows.length > 0) {
			
			for(var i = tbl.rows.length - 1; i >= 0; i--) {
				//console.log(tbl.rows[i].cells[0].nodeName);
				if (tbl.rows[i].cells[0].nodeName == "TD") { //case important!
					tbl.deleteRow(i);
				}
			}
		}
	}
	
	/**
	 * add and remove class functions
	 * http://www.avoid.org/javascript-addclassremoveclass-functions/
	 */
	function hasClass(el, name) {
		return new RegExp('(\\s|^)'+name+'(\\s|$)').test(el.className);
	}

	function addClass(el, name) {
		if (!hasClass(el, name)) { el.className += (el.className ? ' ' : '') +name; }
	}

	function removeClass(el, name) {
		if (hasClass(el, name)) {
			el.className = el.className.replace(new RegExp('(\\s|^)'+name+'(\\s|$)'),' ').replace(/^\s+|\s+$/g, '');
		}
	}
	
	
	/**
	 * highlight a table row, by matching an element in the table row
	 * @param {DOMElement} tbl the table tbody with the table rows
	 * @param {String} highlightClass name of the highlight class we apply to the table row
	 * @param {String} unhighlightClass name of class that un-highlights the table
	 */
	function highlightTableRow(tbl, highlightClass, unhighlightClass) {
		
		tbl = getElement(tbl);
		
		if (!tbl.id) {
			console.log("ERROR: highlightTableRow() - DOMElement doesn't have a id");
			return false;
		}
		
		if (!highlightClass || !unhighlightClass) {
			console.log("ERROR: highlightTableRow() - missing a class");
		}
		
		var rowSelected = getParentElement(tbl.id, "tr"); //recurse upward
		if (rowSelected && rowSelected != tbl) { //fails if parent is not returned
			if(DEBUG) console.log("highlightTableRow() - found tr");
			var cols    = rowSelected.cells;
			var numCols = rowSelected.cells.length;
			if (numCols > 0) {
				
				for (var i = 0; i < numCols; i++) {
					removeClass(cols[i], unhighlightClass);
					addClass(cols[i], highlightClass);
				}
				return true;
			}
			else {
				if(DEBUG) console.log("highlightTableRow() - no columns in table row");
			}
		}
		else {
			console.log("ERROR: highlightTableRow() - failed to find enclosing tr, cell NOT in table");	
		}
		
		return false;
	}
	
	
	/**
	 * un-highlight all rows in a table
	 * @param {String} tblBodyId table or table body id
	 * @param {String} highlightClass highlighting CSS class
	 * @param {String} unhighlightClass default highlightingCSS class
	 * @param {String} exceptionClass don't unhighlight if also has exception class
	 */
	function unHighlightTableRows(tbl, highlightClass, unhighlightClass, exceptionClass) {
		
		tbl  = getElement(tbl);
		
		if (!tbl.id) {
			console.log("ERROR: highlightTableRow() - DOMElement doesn't have a id");
			return false;
		}
		
		if (!highlightClass || !unhighlightClass) {
			console.log("ERROR: unHighlightTableRow() - missing a class");
			return false;
		}
		
		if (tbl.rows && tbl.rows.length > 0) {
			var cols = tbl.getElementsByTagName("td");
			var len = cols.length;
			if (len) {
				for (var i = 0; i < cols.length; i++) {
					if (!hasClass(cols[i], exceptionClass)) {
						addClass(cols[i], unhighlightClass);
						removeClass(cols[i], highlightClass);
					}
				}
			}
			else {
				if(DEBUG) console.log("unHighlightTableRows() - in unhighlightTableRows, no td columns found");
				return false;
			}
		}
		else {
			if(DEBUG) console.log("unhighlightTableRows() - no tr rows found");
			return false;
		}
		return true;
	}
	
	
	/**
	 * @method insertRowByDOM
	 * polyfill for rows in old IE, assuming we're inserting into a tBody element
	 */
	function insertRowByDOM(tbl, index) {
	    var row = tbl.insertRow(index);
	    if (!row) {
		if(DEBUG) console.log("no row, start appendChild");
		if (index == -1 || tbl.childNodes.length == 0) {
		    if(DEBUG) console.log("appending row to end of tbody");
		    row = tbl.appendChild(document.createElement("tr"));
		}
		else {
		    var afterNode = tbl.childNodes[index];
		    if(DEBUG) console.log("inserting row at "+index);
		    if (afterNode) {
			if(DEBUG) console.log("found afterNode, inserting");
			row = tbl.insertBefore(document.createElement("tr"), afterNode);
		    }
		  else {
			if(DEBUG) console.log("in insertRow, tried to define position outside of table list");
			row = null; //node specified outside of element position
		    }
		}
	    }
	    return row;
	}
	
	
	/**
	 * @method insertCell
	 */
	function insertCellByDOM(row, index) {
	    var cell = row.insertCell(index);
	    if (!cell) {
		if (index == -1 || row.childNodes.length == 0) {
		    cell = row.appendChild(document.createElement("td"));
		    cell.appendChild(document.createTextNode(""));
		}
		else {
		    var afterNode = row.childNodes[index];
		    if (afterNode) {
			cell = row.insertBefore(document.createElement("td"), afterNode);
		    }
		    else {
			console.log("ERROR: insertCellByDOM() - in insertCell, tried to define position outside row list");
			row = null;
		    }
		}
	    }
	    return cell;
	}
	
	
	function clearTableRows(tbl) {
		
		tbl = getElement(tbl);
		
		if (!tbl) {
			console.log("ERROR: clearTableRows - not a table element");
		}
		
		var len = tbl.rows.length;
		for (var i = len-1; i >= 0; i--) {
			console.log("i:"+i+" len:"+len)
   			tbl.deleteRow(i);
		}
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * these methods are form-specific, and assume specific id= fields
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/** 
	 * get the component <select> list
	 * @return {DOMObject} the component <select> as a DOM Object
	 */
	function getCurrentComponent() {
		return getCurrentSelect('component');
	}
	
	
	/** 
	 * get the data source
	 * @return {DOMObject} the source <select> as a DOM Object
	 */
	function getCurrentSource() {
		return getCurrentSelect('source');
	}
	
	
	/** 
	 * get the currently selected client_id (in other words, the client)
	 * @return {DOMObject} the client <select> as a DOM object
	 */
	function getCurrentClient() {
		return getCurrentSelect('client');
	}
	
	
	/**
	 * @method getCurrentProperty
	 * get the current property
	 * @return {DOMObject the property <select> as a DOM object}
	 */
	function getCurrentProperty() {
		return getCurrentSelect('property');
	}	
	
	
	/**
	 * @method processName
	 * process a string to valid GBP name
	 * camelCased
	 * alphanumeric
	 * 1-40 chars in length
	 * @param {String} nameStr incoming string
	 * @return {String|Boolean} of valid, return modified string, else false
	 */
	function processName(nameStr) {
		var str = nameStr
		.alphanumeric()
		.camelCase();
		//str = str.camelCase();
		if (str.length > MAX_NAME) {
		    str = str.substring(0, MAX_NAME);
		}
		else if (str.length < 1) {
			return false;
		}
		return str;
	}
	
	
	/**
	 * @method processTitle
	 * make sure its not too big
	 * 1-80 chars in length
	 * @param {String} titleStr supplied by user
	 * @raturn {String} string, clipped to the maximum allowed length for the title
	 */
	function processTitle(titleStr) {
		var str = titleStr;
		if (str.length > MAX_TITLE) {
			str = str.substring(0, MAX_TITLE);
		}
		return str;
	}

	
	/**
	 * @method processDescription
	 * process description, trim whitespace
	 * @param {String} descriptionStr the description
	 * @return {String} processed string
	 */
	function processDescription(descriptionStr) {
		return descriptionStr;
	}
	
	
	/**
	 * @method showMessage
	 * show an error in our current form field (replace alert)
	 */
	function showMessage(errStr) {
		getElement('err-list').innerHTML = errStr;
	}
	
	/**
	 * @method showPoppup
	 * show a non-alert DOM-created popup window with supplied content
	 * @param {String} msgStr string, could be HTML for .innerHTML
	 * @param {Number} width (optional) width of window
	 * @param {Number} height (optional) height of window
	 * @param {String} title (optional) title of window
	 * @param {Function} make a button visible, attach 'onclick' callback
	 */
	function showPoppup(msgStr, width, height, title, callback, callback2) {
		
		//defaults
		if (!width) {
			width = 250; //HARD-CODED, matches gbp_initializr.css
		}
		if (!height) {
			height = 200; //HARD-CODED, matches gbp_initializr.css
		}
		if (!title) {
			title = "Alert";
		}
		
		//get the elements
		
		var poppup        = document.getElementById('poppup-win');
		var poppupTitle   = document.getElementById('poppup-win-title');
		var poppupContent = document.getElementById('poppup-win-content');
		
		if (poppup && poppupTitle && poppupContent) {
			
			var pos = getViewportCenter();
			
			console.log("width:"+width+" height:"+height);
			
			poppup.style.width  = width + "px";
			poppup.style.height = height + "px";
			poppup.style.top    = pos.top  - height/2 + "px";
			poppup.style.left   = pos.left - width/2  + "px";
			
			console.log("poppup.top:"+poppup.style.top+" poppup.left:"+poppup.style.left+" width:"+poppup.style.width+" height:"+poppup.style.height);
			
			//set content
			
			poppupTitle.innerHTML   = title;
			poppupContent.innerHTML = msgStr;
			
			//callback function
			
			if (typeof callback == "function") {
				
				var poppupAction = document.getElementById('poppup-action-button');
				poppupAction.style.display = "inline";
				poppupAction.style.visibility = "visible";
				
				//wrap our callback in a function
				
				poppupAction.onclick = function () {
					console.log("firing callback");
					callback(); //NOTE: we can call GBPInitializr.hidePoppup here if useful
					hidePoppup();
				}
			}
			
			//make visible
			
			poppup.style.zIndex    = ZINDEX_POPPUP;
			poppup.style.visibility = "visible";
			poppup.style.display   = "block";
		}
		else { //html elements not present, use default
			alert(msgStr);
		}
		
	}
	
	
	/**
	 * @method hidePoppup
	 * hide a visible poppup window
	 */
	function hidePoppup() {
		var poppup = document.getElementById('poppup-win');
		var poppupContent = document.getElementById('poppup-win-content');
		poppup.style.display = "none";
		poppup.style.visibility = "hidden";
		poppup.style.zIndex = ZINDEX_BOTTOM;
		poppupContent.innerHTML = '';
		
	}
	
	/**
	 * -------------------------------------------------------------------------
	 * cross-browser event handling
	 * ------------------------------------------------------------------------- 
	 */
	 
	function getMousePosition(e) {
		var posx = 0;
		var posy = 0;
		if(!e) var e = window.event;
		if(e.pageX || e.pageY) {
			posx = e.pageX;
			posy = e.pageY;
		}
		else if (e.clientX || e.clientY) {
			posx = e.clientX + document.body.scrollLeft
				+ document.documentElement.scrollLeft;
			posy = e.clientY + document.body.scrollTop
				+ document.documentElement.scrollTop;
		}
		
		return {
			x:posx,
			y:posy
		};
		
	}	
	
	/**
	 * detect whether a specific event is supported
	 * similar to: https://github.com/kangax/iseventsupported/blob/gh-pages/isEventSupported.js
	 * @param {String} eventName name of event
	 * @param {String|DOMObject} id or DOM element we are checking for event support on
	 * @return {Boolean} if supported, return true, else false
	 */
	function isEventSupported(eventName, element) {
		
		element = element || document.createElement(TAGNAMES[eventName] || 'div');
		eventName = 'on' + eventName;
		
		var isSupported = (eventName in element);
		
		if (!isSupported) {
			
			// if it has no `setAttribute` (i.e. doesn't implement Node interface), try generic element
			
			if (!element.setAttribute) {
				
				element = document.createElement('div');
			}
			if (element.setAttribute && element.removeAttribute) {
				
				element.setAttribute(eventName, '');
				
				isSupported = typeof element[eventName] == 'function';
				
				// if property was created, "remove it" (by setting value to `undefined`)
				
				if (typeof element[eventName] != 'undefined') {
					element[eventName] = undef;
				}
				
				element.removeAttribute(eventName);
			}
		}
		
		element = null;
		return isSupported;
	}

	
	/** 
	 * add an event
	 * example: addEvent(elem, "blur", function () {}, true); 
	 * @param {DOMObject} elm, HTML element we're attaching event to
	 * @param {String} evType a string with event "click", "focus", "blur"....
	 * @param {Function} fn the callback function for the event
	 * @param Boolean useCapture true if we want event bubbling reversed, 
	 * which is needed to detect onblur events during event delegation when 
	 * the parent doesn't support an 'onblur' event
	 * @return {Boolean} success in attachment
	 */
	function addEvent(elem, evtType, fn, useCapture) {
		
		var elm = getElement(elem);
		
		if (!useCapture) {
			useCapture = false;
		}
		
		if (elm) {
			
			if (elm.addEventListener) {
				return elm.addEventListener(evtType, fn, useCapture); 
				//return true; 
			}
			
			else if (elm.attachEvent) { //old IE
				return elm.attachEvent('on' + evtType, fn); //returns true if attach ok
			}
			
			else {
				elm['on' + evtType] = fn;
				return true;
			}
			
		}
		else {
			console.log("ERROR: addEvent() - supplied element is NOT a DOM object");
			return false;
		}
	}
	
	
	/** 
	 * remove an event
	 * example: addEvent(elem); 
	 * @param {DOMObject} elm, HTML element we're attaching event to
	 * @return {Boolean} success in un-attachment
	 */
	function removeEvent(elem, evtType, fn, useCapture) {
		var elm = getElement(elem);
		
		if (!useCapture) {
			useCapture = false;
		}
		if (elm) {
			
			if(elm.removeEventListener) {
				elm.removeEventListener(evtType, fn, useCapture);
			}
			else if(elm.detachEvent) {
				elm.detachEvent('on' + evtType, fn);
				elm[fn.toString()] = null;
				}
			else {
				elm['on' + evtType]=function(){};
			}
			
			return true;
		}
		else {
			console.log("ERROR: removeEvent() - elm is NOT a DOM object");
			return false;	
		}
	}
	
	
	/** 
	 * get the event target element in different browsers. We mess with 
	 * the event itself by applying the "fossil" principle - nobody is ever
	 * going to use e.srcElement or mistakenly assign e incorrectly in 
	 * e.nodeType again, so we are o.k. messing with the event object in 
	 * "fossil" browsers
	 * @param {Event|Null} e the event, or null in old IE which uses window.event
	 * @return{Event} the event object, normalized across browsers
	 */
	function getEvent(evt) {
		
		var e;
		if (!evt) {
			e = window.event; //IE
		}
		else {
			e = evt;
		}
		if (e.srcElement) {
			e.target = e.srcElement; //normalize IE, but FAIL in Opera < 12, IE < 5 (DOMException readonly error)
		}
		if (e.target.nodeType == 3)  { //textNode, defeat old Safari bug
			e.target = e.target.parentNode;
		}
		return e;

	}

	
	/**
	 * @method getEventTarget
	 * use when we have a readonly propblem with changing e
	 */
	function getEventTarget(evt) {
		var e, target;
		if (!evt) {
			e = window.event //IE
		}
		else {
			e = evt;
		}
		if (e) {
			if (e.srcElement) {
				target = e.srcElement;
			}
                        else if (e.target) {
                              target = e.target;
                        }
                        
			if (target.nodeType == 3) { //textNode, defeat old Safari bug
				target = e.target.parentNode;
			}
			return target;
		}
		
		return null;
	}
	
	
	/** 
	 * stop event bubbling
	 * @param {Event} e browser event
	 */
	function stopEvent(e) {
	  if (e.preventDefault) {
	    e.preventDefault();
	  }
	  else if (e.stopPropagation) {
	    e.stopPropagation() ? e.stopPropagation() : (e.cancelBubble=true);
	  }
	
	}

	
	/** 
	 * create an event in the program
	 * http://jehiah.cz/a/firing-javascript-events-properly
	 * @param {HTMLElement} recipient the DOM element receiving the event
	 * @param {String} eventType the string describing the event, e.g., "mousedown", on "mouseover"
	 * @param {Event} event (optional) event, when running old IE, which uses event.srcElement
	 */
	function sendEventById(id, eventType, event) {

		recipient = getElement(id);
		
		if(recipient) {
		
			//dispatch for everyone but old IE
			
			if(document.createEvent) {
				var evt = document.createEvent("HTMLEvents");
				evt.initEvent(evtType, true, true);
				return !recipient.dispatchEvent(evt);	
			}
		
			//dispatch for IE - http://help.dottoro.com/ljvtddtm.php
			
			else if(document.createEventObject){
				var evt = document.createEventObject(window.event);
				return event.srcElement.fireEvent('on'+eventType, evt);
 			}
			else {
				console.log("ERROR: sentEventById() - can't create new event in this browser");
			}
		
		}
		
		return false;
	}
	
	
	/**
	 * classic addLoadEvent - used to set default selected
	 * elements on form load
	 * @param {Function} func the function we want to add in a list
	 * of events to perform on load
	 */
	function addLoadEvent(func) {
		var oldonload = window.onload;
		if (typeof window.onload != 'function') {
			window.onload = func;
		}
		else {
			window.onload = function() {
				if (oldonload) {
					oldonload();
				}
				func();
			}
		  }
	}

	
	/**
	 * -------------------------------------------------------------------------
	 * ajax requests
	 * ------------------------------------------------------------------------- 
	 */

	
	/** 
	 * simple ajax call
	 * adapted from https://github.com/aseemagarwal19/omee/blob/master/src/omee.js
	 * @param params parameters to the request.
	 * @param {String} url url of the request
	 * @param {Function} func callback function
	 * @param {String} connType 'GET' or 'POST'
	 * @param {Boolean} sync if true, use synchronous operation
	 */
	function ajaxRequest(params, url, func, connType, useSync) {
		
		var xmlhttp;
		
		if (window.XMLHttpRequest) { //code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} 
		else { //code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		
		if(!xmlhttp) {
			console.log("ERROR: ajaxRequest() - unable to initialize XMLHttpRequest object");
			return false;
		}
		else {
			if(DEBUG) console.log("ajaxRequest() - XMLHttpRequest initialized");
		}
		
		xmlhttp.onreadystatechange = function() {
			/** 
			 * DOM Exception 11 if console.log here, if xmlHTTPRequest async = true, .status and .responseText not available yet
			 * http://stackoverflow.com/questions/3488698/invalid-state-err-dom-exception-11-webkit
			 * console.log("in onreadystatechange, status = " + xmlhttp.readyState + " xmlhttp.status = " + xmlhttp.status);
			*/
			if(xmlhttp.readyState == 4) { //data returned
				
				if(xmlhttp.status == 200) { //ok
					
					//this allows passing in object.function for our callback, but uses eval()
					
					if(func.indexOf && func.indexOf(".") > -1) {
						if(DEBUG) console.log("ajaxRequest() - decomposing method call " + func);
						var res = xmlhttp.responseText;
						var str = func.substring(0, func.lastIndexOf("."));
						var f = eval(str);
						str = func.substring(func.lastIndexOf(".") + 1, func.length);
						f[str](res);
					} 
					else {
						if(DEBUG) console.log("ajaxRequest() - executing callback function");
						var resp = func(xmlhttp.responseText);
						return resp;
					}
				}
				else if (xmlhttp.status == 403) { //we can get forbidden if our data is blocked by .htaccess security
					showPoppup("Forbidden - data supplied can't be placed on server", 250, 150, "Ajax Error");
					return false;
				}
			}
		}
		
		if (useSync && useSync === true) {
			xmlhttp.open(connType, url, false);
		}
		else {
			xmlhttp.open(connType, url, true);	
		}
	
		//send the message URL encoded
		
		xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
		xmlhttp.send(params);
		
		return true;
	}
	
	
	/**
	 * -------------------------------------------------------------------------
	 * JSON processing
	 * ------------------------------------------------------------------------- 
	 */
	
	
	/** 
	 * parse JSON string, handle exceptions, and 
	 * check if the returned object matches expectations, and write warnings into
	 * an object without the required properties. Take any PHP errors
	 * in the JSON output and move them to a separate JSONError object
	 * @param jsonString   - a string supposed to be JSON
	 * @param removeList   - list of properties to remove, if present, from object
	 * @param propertyList - list of properties supposed to be in the object
	 */
	function processJSON(jsonString, removeList, propertyList) {
		
		if(jsonString) {
			
			try {
				var JSONObject = JSON.parse(jsonString);
				
				if (DEBUG) {
					
					if(JSONObject.error != 'undefined') { //can return .error = false
						if(DEBUG) console.log("processJSON() - server warnings for api call " + JSONObject.apicall);
						JSONObject.error.JS_CALLER = arguments.callee.caller.toString();
						window.JSONError = JSONObject.error;
						delete JSONObject.error;
					}
					else {
						window.JSONError = 'error array undefined, no errors';
					}
				}
				
				//remove unwanted properties (e.g. those that list API status)
				
				if(removeList) {
					for(var i = 0; i < removeList.length; i++) {
						if(DEBUG) console.log("processJSON() - deleting property:" + removeList[i]);
						delete JSONObject[removeList[i]];
					}
				}
				
				//check for the properties we want being present. if not, error
				
				if(propertyList) {
					for(var i = 0; i < propertyList.length; i++) {
						if(DEBUG) console.log("ajaxRequest() - checking for existence of property:" + propertyList[i]);
						if(!JSONObject[propertyList[i]]) {
							console.log("ERROR: ajaxRequest() - invalid object, property " + propertyList[i] + " is not present");
							return false;
						}
					}
				}
				
				//save the response separately, if we are in DEBUG mode
				
				if (DEBUG) {
					
					window.JSONObject = JSONObject;
				}
				
				return JSONObject;
				
			} catch(e){
				alert(e + " jsonString:"+jsonString); //error in the above string(in this case,yes)!
			}
		}
		else if(DEBUG) {
			
			//define empty objects to prevent trace errors
			
			window.JSONObject = false;
			window.JSONError  = false;
		}
		return false;
	}
	
	
	/**
	 * @method validServerInsert
	 * valid server return. The error handling assumes that the field name in
	 * the html form matches the column name returned from the server
	 * NOTE: in some cases the server may "trim" the string, so adjust accordingly - not checked here
	 * @param {String} tableName name of table that was updated on server
	 * @param {String} columnName name of column in table that was updated on server
	 * @param {String|Boolean} original value sent to server
	 * @param {String|Boolean} value returned by server
	 * @returns if the same return true, else false
	 */
	function validServerInsert(originalValue, returnedValue) {
		if (returnedValue == originalValue) { //we returned a value that we want to compare (string, number)
			return true;
		}
		else if (String(returnedValue) == "true") { //requested a delete or insert was successful
			return true;
		}
		return false;
	}
	
	
	return {
			//utility 
			
			saveFocus:saveFocus,
			getLastFocus:getLastFocus,
			getLastFocusData:getLastFocusData,
			clearFocus:clearFocus,
                        getConfirm:getConfirm,
			isOldIE:isOldIE,
			isNumber:isNumber,
			isDate:isDate,
			isDateRange:isDateRange,
			isTimeStamp:isTimeStamp,
			isLocation:isLocation,
			isDimensions:isDimensions,
			isVersion:isVersion,
			
			//class
			
			hasClass:hasClass,
			addClass:addClass,
			removeClass:removeClass,
			
			//parent-child elements take either DOM element, or its Id
			
			getElement:getElement,
			getParentElement:getParentElement,
			
			//strings
			
			processName:processName,
			processTitle:processTitle,
			processDescription:processDescription,
			
			//combo box emulation
			
			comboInit:comboInit,
			combo:combo,
			
			//radio button processing
			
			setRadio:setRadio,
			
			//messages
			
			showMessage:showMessage,
			showPoppup:showPoppup,
			hidePoppup:hidePoppup,
			
			//ajax spinner
			
			delay:delay,
			hasSpinner:hasSpinner,
			showSpinner:showSpinner,
			hideSpinner:hideSpinner,
			showMiniSpinner:showMiniSpinner,
			hideMiniSpinner:hideMiniSpinner,
			
			//reference window
			
			ajaxAddReference:ajaxAddReference,
			ajaxDeleteReference:ajaxDeleteReference,
			showRefWin:showRefWin,
			hideRefWin:hideRefWin,
			checkForRefWinClose:checkForRefWinClose,
			
			//calendar widget
			
			addCalendarWidget:addCalendarWidget,
			
			//table widget
			
			delegate:delegate,
			setTBody:setTBody,
			getTableRow:getTableRow,
			deleteTableRow:deleteTableRow,
			deleteTableContent:deleteTableContent,
			highlightTableRow:highlightTableRow,
			unHighlightTableRows:unHighlightTableRows,
			clearTableRows:clearTableRows,
			insertRowByDOM:insertRowByDOM,
			insertCellByDOM:insertCellByDOM,
			
			//select menus
			
			getCurrentSelect:getCurrentSelect,
			getCurrentSelectText:getCurrentSelectText,
                        sortSelect:sortSelect,
			setSelectByOptionValue:setSelectByOptionValue,
			insertSelectOption:insertSelectOption,
			updateSelectOption:updateSelectOption,
			deleteSelectOptionByValue:deleteSelectOptionByValue, //deletes by value
			deleteSelectOption:deleteSelectOption, //deletes selected options
			setSelOptions:setSelOptions,
			
			//form-specific (require specific id)
			
			getCurrentComponent:getCurrentComponent,
			getCurrentSource:getCurrentSource,
			getCurrentClient:getCurrentClient,
			getCurrentProperty:getCurrentProperty,
			validServerInsert:validServerInsert,
			
			//event processing
			
			isEventSupported:isEventSupported,
			addEvent: addEvent,
			removeEvent:removeEvent,
			getEvent:getEvent,
			getEventTarget:getEventTarget,
			stopEvent:stopEvent,
			sendEventById: sendEventById,
			addLoadEvent:addLoadEvent,
			
			
			//ajax
			
			ajaxRequest:ajaxRequest,
			
			//json
			
			processJSON:processJSON
		};
	}
());

