/**
 * jsmethods.js
 * add in methods missing from older browsers
 * trigger the load if .getElementsByClassName is missing
 *
 * these routines are written for max compatibility with older browsers,
 * not for speed. They avoid deep recursion, which is also a problem on
 * many older browsers
 * 
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 11.2013
 * @version 1.0
 *
 * @todo list of changes and additions
 * - speed and security tests
 */


/**
 * polyfill for getElementsByClassName
 * non-recursive, since older browsers (e.g. FF2) crash with "too much recursion"
 * when recursive polyfills are used
 */
document.getElementsByClassName = document.getElementsByClassName || (function (match, tag) {  
	var result = [],
	elements = document.getElementsByTagName(tag || '*'),
	i, elem, c;
	for (i = 0; i < elements.length; i++) {
		
		elem = elements[i];
		c = elem.getAttribute("class");
		
		if (elem.className == match) {
			result.push(elem);
		}
		else if (c) {
		
			if (c == match) {
				result.push(elem);
			}
			else {
				var cArr = c.split(" ");
				var len = cArr.length;
				for(var j = 0; j < len; j++) {
					if (cArr[j] == match) {
						result.push(elem);
					}
				}
			}
			
		elem = null;
			
		}
	}
	
	c = null;
	
	return result;
	}
);


/**
 * several Object properties don't work in IE6, preventing the
 * for..in loop construct from working
 * so define prototypes following
 * http://cwestblog.com/2011/06/22/javascript-for-in-loop-ie-bug/
 */
// Only define Object.keys if it doesn't exist.
Object.keys = Object.keys || (function() {
	// This is an IE fix.
	var unenumerableKeys = "constructor,hasOwnProperty,isPrototypeOf,toLocaleString,toString,valueOf".split(",");
	for(var key, o = {}, i = 0; key = unenumerableKeys[i]; i++) {
		o[key] = i;
		for(key in o) {
			unenumerableKeys.splice(i--, 1);
			delete o[key];
		}
	}

	//definition for hasOwnerProperty() because it may be overwritten in the object.

	var hasOwnProperty = Object.prototype.hasOwnProperty;

	// The definition for Object.keys().
	
	return function(obj) {
		if(obj == null) {
			throw new Error("Object.keys called on non-object.");
		}
		var keys = unenumerableKeys.slice(0);
		for(var key in obj) {
			keys.push(key);
		}
		for(var ret = [], i = 0; key = keys[i]; i++) {
			if(hasOwnProperty.call(obj, key))
			ret.push(key);
		}
		return ret;
	};
})();



/**
 * @method Array.prototype.indexOf
 * polyfill for older browsers lacking indexOf()
 * From https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/Array/indexOf
 * @param searchElement item to look for in array
 */
Array.prototype.indexOf = Array.prototype.indexOf || (function (searchElement /*, fromIndex */) {
		"use strict";
		
		if (this === void 0 || this === null) {
			throw new TypeError();
		}
		
		var t = Object(this);
		var len = t.length >>> 0;
		if (len === 0) {
			return -1;
		}
		
		var n = 0;
		if (arguments.length > 0) {
			n = Number(arguments[1]);
			if (isNaN(n)) {
				n = 0;
			} else if (n !== 0 && n !== (1 / 0) && n !== -(1 / 0)) {
				n = (n > 0 || -1) * Math.floor(Math.abs(n));
			}
		}
		
		if (n >= len) { return -1; }
		
		var k = n >= 0 ? n : Math.max(len - Math.abs(n), 0);
		
		for (; k < len; k++) {
			if (k in t && t[k] === searchElement) {
				return k;
			}
		}
		return -1;
});