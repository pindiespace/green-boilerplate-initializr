/**
 * console.js
 * GBP debugging console object, based on
 * http://www.paulirish.com/2009/log-a-lightweight-wrapper-for-consolelog/
 * 
 * @license http://opensource.org/licenses/gpl-license.php  GNU Public License
 * @author Pete Markiewicz 11.2013
 * @version 1.0
 * 
 */
window.console = window.console || (function() {
	
	var cWin,
		USE_ALERT = 1,
		USE_POPPUP = 2,
		USE_EDIT_FIELD = 3,
		USE_NOTHING = 4;
	
	var method = USE_POPPUP;
	
	var history = history || [];

	if (!cWin) {	
		cWin = window.open("","GBPConsoleWindow","height=300,width=400,scrollbars=1,resizable=1,menubar=no,status=no");
	}
	
	var log = function() {
		var args = Array.prototype.slice.call(arguments);
		history.push(args);
		switch (method) {
			case USE_ALERT:
				alert(args);
				break;
			case USE_POPPUP:
				cWin.document.write('<pre>');
				cWin.document.write(args);
				cWin.document.write('</pre>');
				break;
			case USE_EDIT_FIELD:
				cWin.document.write('<form method="post" action="#"><textarea rows="2" cols="80">');
				cWin.document.write(args);
				cWin.document.write('</textarea></form></body>');			
				break;
			case USE_NOTHING:
				//we have to examine window.log array manually
				break;
			default:
				break;
		}
	}

	return {
		log:log,
		cWin:cWin
	};
})();
