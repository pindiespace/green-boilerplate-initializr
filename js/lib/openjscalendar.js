/**
 * Calendar Script
 * Creates a calendar widget which can be used to select the date more easily than using just a text box
 * http://www.openjs.com/scripts/ui/calendar/
 *
 * MODIFIED 6.2013 to handle 'onblur' and 'onfocusout' events being fired before the calendar can
 * update the text box. Important since GBP uses these events for Ajax updates of text-like fields (like date)
 * 
 * Example:
 * <input type="text" name="date" id="date" />
 * <script type="text/javascript">
 * 		calendar.set("date");
 * </script>
 */
window.calendar = {
	month_names: ["January","February","March","April","May","June","July","August","September","October","November","December"],
	weekdays: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
	month_days: [31,28,31,30,31,30,31,31,30,31,30,31],
	//Get today's date - year, month, day and date
	today : new Date(),
	opt : {},
	data: [],
	dateStr: '',
	//doUpdate: "false",  //semaphore to tell the calling program whether to run update events (e.g. Ajax)
	isVisible: "false", //flag for visiblity
		
	/**
	* add a replacement for 'typeof' to global space. We don't test for 'global'
	* http://javascriptweblog.wordpress.com/2011/08/08/fixing-the-javascript-typeof-operator/
	*/
	toType:function (obj) {
		return ({}).toString.call(obj).match(/\s([a-z|A-Z]+)/)[1].toLowerCase();
	},
	
	/**
	 * add the ability to trigger an event in the form element receiving the calendar
	 * data when the calendar window closes
	 * @param {String} evtType string name of event, e.g. 'click', 'blur'
	 */
	fireEvent: function (elm, evtType) {
		var event;
		if (document.createEvent) {
			event = document.createEvent("HTMLEvents");
			event.initEvent(evtType, true, true);
		}
		else {
			event = document.createEventObject();
			event.eventType = evtType;
		}
		event.eventName = evtType;
		if (document.createEvent) {
			elm.dispatchEvent(event);
		}
		else {
			elm.fireEvent("on" + event.eventType, event);
		}
	},

	//Used to create HTML in a optimized way.
	wrt:function(txt) {
		this.data.push(txt);
	},
	
	/* Inspired by http://www.quirksmode.org/dom/getstyles.html */
	getStyle: function(ele, property){
		if (ele.currentStyle) {
			var alt_property_name = property.replace(/\-(\w)/g,function(m,c){return c.toUpperCase();});//background-color becomes backgroundColor
			var value = ele.currentStyle[property]||ele.currentStyle[alt_property_name];
		
		} else if (window.getComputedStyle) {
			property = property.replace(/([A-Z])/g,"-$1").toLowerCase();//backgroundColor becomes background-color

			var value = document.defaultView.getComputedStyle(ele,null).getPropertyValue(property);
		}
		
		//Some properties are special cases
		if(property == "opacity" && ele.filter) value = (parseFloat( ele.filter.match(/opacity\=([^)]*)/)[1] ) / 100);
		else if(property == "width" && isNaN(value)) value = ele.clientWidth || ele.offsetWidth;
		else if(property == "height" && isNaN(value)) value = ele.clientHeight || ele.offsetHeight;
		return value;
	},
	
	getPosition:function(ele) {
		var x = 0;
		var y = 0;
		while (ele) {
			x += ele.offsetLeft;
			y += ele.offsetTop;
			ele = ele.offsetParent;
		}
		if (navigator.userAgent.indexOf("Mac") != -1 && typeof document.body.leftMargin != "undefined") {
			x += document.body.leftMargin;
			offsetTop += document.body.topMargin;
		}
	
		var xy = new Array(x,y);
		return xy;
	},
	
	//Called when the user clicks on a date in the calendar.
	selectDate:function(year, month, day) {
		var ths = _calendar_active_instance;
		console.log("calendar: CHANGING date field value");
		var dateStr = year + "-" + month + "-" + day;
		
		var input = document.getElementById(ths.opt["input"]); /////////////////////////////////////
		
		/**
		 * ================================================================================
		 * Earlier, we turned off 'blur' and also wrote a 'noBlur=' attribute into our
		 * input field. Set 'noBlur' to false and fire an event
		 * if we are being called by a text field, fire a 'blur' event to make Ajax update
		 *
		 * We HAVE to use the .noBlur - if we try to have our calling script read a property
		 * from the calendar object, we will get the OLD VALUE for .noBlur, since the 'onblur'
		 * event is QUEUED and will execute BEFORE we get to update the field.
		 * ================================================================================
		 */
		if (input) {
			//this.doUpdate = "false";
			input.value = dateStr; //UPDATE the date string
			
			/**
			 * look for lack of support for 'onblur', and use 'onfocusout' to support
			 * old IE and Opera. Note that Firefox will fire (incorrectly) for == undefined (it sees it
			 * the same as null), but IE will croak if we go === undefined or === "undefined". So to support
			 * oldIE we do both tests. The first one nabs IE6 and the second && test prevents browsers reading
			 * .onblur as null from responding
			 * NOTE: using addEventListener() will leave document.onblur as 'null' in modern browsers. we
			 * do the function test for DOM0 events, e.g. document.onblur = function () {.....}
			 */
			//if (document.onblur == undefined && document.onblur !== null) { //old IE and Opera
			if (document.onblur !== null && toType(document.onblur) !== "function") {
				
				console.log("calendar:doing the focusout");
				this.fireEvent(input, 'focusout'); //old IE and Opera using 'focusout'
			}
			else {
				this.fireEvent(input, 'blur');    //everyone except old IE
			}
		}
		else {
			//CONSOLE.LOG
		}
		
		ths.hideCalendar();
	},
	
	//Creates a calendar with the date given in the argument as the selected date.
	
	makeCalendar:function(year, month, day) {
		year = parseInt(year);
		month= parseInt(month);
		day	 = parseInt(day);
		
		//Display the table
		var next_month = month+1;
		var next_month_year = year;
		if(next_month>=12) {
			next_month = 0;
			next_month_year++;
		}
		
		var previous_month = month-1;
		var previous_month_year = year;
		if(previous_month< 0) {
			previous_month = 11;
			previous_month_year--;
		}
		
		this.wrt("<table>");
		this.wrt("<tr><th><a href='javascript:calendar.makeCalendar("+(previous_month_year)+","+(previous_month)+");' title='"+this.month_names[previous_month]+" "+(previous_month_year)+"'>&lt;</a></th>");
		this.wrt("<th colspan='5' class='calendar-title'><select name='calendar-month' class='calendar-month' onChange='calendar.makeCalendar("+year+",this.value);'>");
		for(var i in this.month_names) {
			this.wrt("<option value='"+i+"'");
			if(i == month) this.wrt(" selected='selected'");
			this.wrt(">"+this.month_names[i]+"</option>");
		}
		this.wrt("</select>");
		this.wrt("<select name='calendar-year' class='calendar-year' onChange='calendar.makeCalendar(this.value, "+month+");'>");
		var current_year = this.today.getYear();
		if(current_year < 1900) current_year += 1900;
		
		for(var i=current_year-70; i<current_year+10; i++) {
			this.wrt("<option value='"+i+"'")
			if(i == year) this.wrt(" selected='selected'");
			this.wrt(">"+i+"</option>");
		}
		this.wrt("</select></th>");
		this.wrt("<th><a href='javascript:calendar.makeCalendar("+(next_month_year)+","+(next_month)+");' title='"+this.month_names[next_month]+" "+(next_month_year)+"'>&gt;</a></th></tr>");
		this.wrt("<tr class='header'>");
		for(var weekday=0; weekday<7; weekday++) this.wrt("<td>"+this.weekdays[weekday]+"</td>");
		this.wrt("</tr>");
		
		//Get the first day of this month
		var first_day = new Date(year,month,1);
		var start_day = first_day.getDay();
		
		var d = 1;
		var flag = 0;
		
		//Leap year support
		if(year % 4 == 0) this.month_days[1] = 29;
		else this.month_days[1] = 28;
		
		var days_in_this_month = this.month_days[month];
		
		//Create the calender
		for(var i=0;i<=5;i++) {
			if(w >= days_in_this_month) break;
			this.wrt("<tr>");
			for(var j=0;j<7;j++) {
				if(d > days_in_this_month) flag=0; //If the days has overshooted the number of days in this month, stop writing
				else if(j >= start_day && !flag) flag=1;//If the first day of this month has come, start the date writing
				
				if(flag) {
					var w = d, mon = month+1;
					if(w < 10)	w	= "0" + w;
					if(mon < 10)mon = "0" + mon;
					
					//Is it today?
					var class_name = '';
					var yea = this.today.getYear();
					if(yea < 1900) yea += 1900;
					
					if(yea == year && this.today.getMonth() == month && this.today.getDate() == d) class_name = " today";
					if(day == d) class_name += " selected";
					
					class_name += " " + this.weekdays[j].toLowerCase();
					
					this.wrt("<td class='days"+class_name+"'><a href='#' onclick ='calendar.selectDate(\""+year+"\",\""+mon+"\",\""+w+"\");'>"+w+"</a></td>");
					d++;
				} else {
					this.wrt("<td class='days'>&nbsp;</td>");
				}
			}
			this.wrt("</tr>");
		}
		this.wrt("</table>");
		this.wrt('<div><input type="button" onclick="calendar.hideCalendar();" value="hide"></div>');
		document.getElementById(this.opt['calendar']).innerHTML = this.data.join("");
		this.data = [];
	},
	
	/**
	 * Display the calendar - if a date exists in the input box, that will be selected in the calendar.
	 * @param {DOMElement} inputElm the field we want to update
	 */
	showCalendar: function(inputElm) {
		var input = document.getElementById(this.opt['input']);
		
		//Position the div in the correct location...
		var div = document.getElementById(this.opt['calendar']);
		var xy = this.getPosition(input);
		var width = parseInt(this.getStyle(input,'width'));
		div.style.left=(xy[0]+width+10)+"px";
		div.style.top=xy[1]+"px";

		// Show the calendar with the date in the input as the selected date
		var existing_date = new Date();
		var date_in_input = input.value;
		if(date_in_input) {
			var selected_date = false;
			var date_parts = date_in_input.split("-");
			if(date_parts.length == 3) {
				date_parts[1]--; //Month starts with 0
				selected_date = new Date(date_parts[0], date_parts[1], date_parts[2]);
			}
			if(selected_date && !isNaN(selected_date.getYear())) { //Valid date.
				existing_date = selected_date;
			}
		}
		
		var the_year = existing_date.getYear();
		if(the_year < 1900) the_year += 1900;
		this.makeCalendar(the_year, existing_date.getMonth(), existing_date.getDate());
		
		var opt = document.getElementById(this.opt['calendar']);
		opt.style.zIndex = "9999"; //one below an update spinner
		opt.style.display = "block";
		
		//document.getElementById(this.opt['calendar']).style.zIndex = "10000";
		//document.getElementById(this.opt['calendar']).style.display = "block";
		
		/**
		 * ================================================================================
		 * save the input element reference, and add a semaphore to the input DOMElement
		 * to prevent blur processing (which will happen since when we opened the calendar
		 * window, we also queued a blur event which will run BEFORE anything our calendar
		 * does on closing its window)
		 * ================================================================================
		 */
		input.noBlur = "true"; //make sure it is intially true
		this.doUpdate = "true";
		this.isVisible = "true";
		_calendar_active_instance = this;
	},
	
	/// Hides the currently shown calendar.
	
	hideCalendar: function(instance) {
		var active_calendar_id = "";
		if(instance) {
			active_calendar_id = instance.opt['calendar'];
		}
		else {
			active_calendar_id = _calendar_active_instance.opt['calendar'];
		}
		
		if(active_calendar_id) {
			document.getElementById(active_calendar_id).style.display = "none";
		}
		this.isVisible = "false";
		_calendar_active_instance = {};
	},
	
	//Setup a text input box to be a calendar box.
	
	set: function(input_id) {
		
		var input;
		if (this.toType(input_id) == 'string') {
			input = document.getElementById(input_id);
		}
		else {
			input = input_id;
		}
		console.log("calendar:input is a "+ typeof input);
		if(!input) return; //If the input field is not there, exit.
		
		if(!this.opt['calendar']) this.init();
		
		var ths = this;
		input.onclick=function(){
			
			ths.opt['input'] = this.id;
			ths.showCalendar(); //pass in the element we want to modify
		};
	},

	/// Will be called once when the first input is set.
	init: function() {
		if(!this.opt['calendar'] || !document.getElementById(this.opt['calendar'])) {
			var div = document.createElement('div');
			if(!this.opt['calendar']) this.opt['calendar'] = 'calender_div_'+ Math.round(Math.random() * 100);

			div.setAttribute('id',this.opt['calendar']);
			div.className="calendar-box";
			
			//add a mouseover and mouseout event to the calendar
			
			var ths = this;
			
			div.onmouseover = function()   {
				this.mouseIsOver = true;
			
				var input = document.getElementById(ths.opt["input"]);
				input.noBlur = "false";
				
				//console.log("mouse over calendar");
				};
			div.onmouseout = function()   {
				//console.log("mouse leaving calendar");
				this.mouseIsOver = false;
				var input = document.getElementById(ths.opt["input"]);
				input.noBlur = "true";
			};
			
			document.getElementsByTagName("body")[0].insertBefore(div,document.getElementsByTagName("body")[0].firstChild);
		}
	}
}