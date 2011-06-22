
/**
 * Search a Moodle form to find all the fdate_time_selector and fdate_selector
 * elements, and add date_selector_calendar instance to each.
 */
function init_date_selectors(firstdayofweek) {
//    var els = YAHOO.util.Dom.getElementsByClassName('fdate_time_selector', 'fieldset');
//    for (var i = 0; i < els.length; i++) {
//        new date_selector_calendar(els[i], firstdayofweek);
//    }
    var els = YAHOO.util.Dom.getElementsByClassName('fdate_selector', 'fieldset');
    for (i = 0; i < els.length; i++) {
        new date_selector_calendar(els[i], firstdayofweek);
    }
}

/**
 * Constructor for a JavaScript object that connects to a fdate_time_selector
 * or a fdate_selector in a Moodle form, and shows a popup calendar whenever
 * that element has keyboard focus.
 * @param el the fieldset class="fdate_time_selector" or "fdate_selector".
 */
function date_selector_calendar(el, firstdayofweek) {
    // Ensure that the shared div and calendar exist.
    if (!date_selector_calendar.panel) {
        date_selector_calendar.panel = new YAHOO.widget.Panel('date_selector_calendar_panel',
                {visible: false, draggable: false});
	date_selector_calendar.panel.element.className += ' yui-skin-sam';
        var div = document.createElement('div');
        date_selector_calendar.panel.setBody(div);
        date_selector_calendar.panel.render(document.body);

        YAHOO.util.Event.addListener(document, 'click', date_selector_calendar.document_click);
        date_selector_calendar.panel.showEvent.subscribe(function() {
            date_selector_calendar.panel.fireEvent('changeContent');
        });
        date_selector_calendar.panel.hideEvent.subscribe(date_selector_calendar.release_current);

        date_selector_calendar.calendar = new YAHOO.widget.Calendar(div,
                {iframe: false, hide_blank_weeks: true, start_weekday: firstdayofweek});
        date_selector_calendar.calendar.renderEvent.subscribe(function() {
            date_selector_calendar.panel.fireEvent('changeContent');
            date_selector_calendar.delayed_reposition();
        });
    }

    this.fieldset = el;
    var controls = el.getElementsByTagName('select');
    for (var i = 0; i < controls.length; i++) {
        if (/\[year\]$/.test(controls[i].name)) {
            this.yearselect = controls[i];
        } else if (/\[month\]$/.test(controls[i].name)) {
            this.monthselect = controls[i];
        } else if (/\[day\]$/.test(controls[i].name)) {
            this.dayselect = controls[i];
        } else {
            YAHOO.util.Event.addFocusListener(controls[i], date_selector_calendar.cancel_any_timeout, this);
            YAHOO.util.Event.addBlurListener(controls[i], this.blur_event, this);
        }
    }
    if (!(this.yearselect && this.monthselect && this.dayselect)) {
        throw 'Failed to initialise calendar.';
    }
    YAHOO.util.Event.addFocusListener([this.yearselect, this.monthselect, this.dayselect], this.focus_event, this);
    YAHOO.util.Event.addBlurListener([this.yearselect, this.monthselect, this.dayselect], this.blur_event, this);

    this.enablecheckbox = el.getElementsByTagName('input')[0];
    if (this.enablecheckbox) {
        YAHOO.util.Event.addFocusListener(this.enablecheckbox, this.focus_event, this);
        YAHOO.util.Event.addListener(this.enablecheckbox, 'change', this.focus_event, this);
        YAHOO.util.Event.addBlurListener(this.enablecheckbox, this.blur_event, this);
    }
}

/** The pop-up calendar that contains the calendar. */
date_selector_calendar.panel = null;

/** The shared YAHOO.widget.Calendar used by all date_selector_calendars. */
date_selector_calendar.calendar = null;

/** The date_selector_calendar that currently owns the shared stuff. */
date_selector_calendar.currentowner = null;

/** Used as a timeout when hiding the calendar on blur - so we don't hide the calendar
 * if we are just jumping from on of our controls to another. */
date_selector_calendar.hidetimeout = null;

/** Timeout for repositioning after a delay after a change of months. */
date_selector_calendar.repositiontimeout = null;

/** Member variables. Pointers to various bits of the DOM. */
date_selector_calendar.prototype.fieldset = null;
date_selector_calendar.prototype.yearselect = null;
date_selector_calendar.prototype.monthselect = null;
date_selector_calendar.prototype.dayselect = null;
date_selector_calendar.prototype.enablecheckbox = null;

date_selector_calendar.cancel_any_timeout = function() {
    if (date_selector_calendar.hidetimeout) {
        clearTimeout(date_selector_calendar.hidetimeout);
        date_selector_calendar.hidetimeout = null;
    }
    if (date_selector_calendar.repositiontimeout) {
        clearTimeout(date_selector_calendar.repositiontimeout);
        date_selector_calendar.repositiontimeout = null;
    }
}

date_selector_calendar.delayed_reposition = function() {
    if (date_selector_calendar.repositiontimeout) {
        clearTimeout(date_selector_calendar.repositiontimeout);
        date_selector_calendar.repositiontimeout = null;
    }
    date_selector_calendar.repositiontimeout = setTimeout(date_selector_calendar.fix_position, 500);
}

date_selector_calendar.fix_position = function() {
    if (date_selector_calendar.currentowner) {
        date_selector_calendar.panel.cfg.setProperty('context', [date_selector_calendar.currentowner.fieldset, 'bl', 'tl']);
    }
}

date_selector_calendar.release_current = function() {
    if (date_selector_calendar.currentowner) {
        date_selector_calendar.currentowner.release_calendar();
    }
}

date_selector_calendar.prototype.focus_event = function(e, me) {
    date_selector_calendar.cancel_any_timeout();
    if (me.enablecheckbox == null || !me.enablecheckbox.checked) {
        me.claim_calendar();
    } else {
        if (date_selector_calendar.currentowner) {
            date_selector_calendar.currentowner.release_calendar();
        }
    }
}

date_selector_calendar.prototype.blur_event = function(e, me) {
    date_selector_calendar.hidetimeout = setTimeout(date_selector_calendar.release_current, 300);
}

date_selector_calendar.prototype.handle_select_change = function(e, me) {
    me.set_date_from_selects();
}

date_selector_calendar.document_click = function(event) {
    if (date_selector_calendar.currentowner) {
        var currentcontainer = date_selector_calendar.currentowner.fieldset;
        var eventarget = YAHOO.util.Event.getTarget(event);
        if (YAHOO.util.Dom.isAncestor(currentcontainer, eventarget)) {
            setTimeout(function() {date_selector_calendar.cancel_any_timeout()}, 100);
        } else {
            date_selector_calendar.currentowner.release_calendar();
        }
    }
}

date_selector_calendar.prototype.claim_calendar = function() {
    date_selector_calendar.cancel_any_timeout();
    if (date_selector_calendar.currentowner == this) {
        return;
    }
    if (date_selector_calendar.currentowner) {
        date_selector_calendar.currentowner.release_calendar();
    }

    if (date_selector_calendar.currentowner != this) {
        this.connect_handlers();
    }
    date_selector_calendar.currentowner = this;

    date_selector_calendar.calendar.cfg.setProperty('mindate', new Date(this.yearselect.options[0].value, 0, 1));
    date_selector_calendar.calendar.cfg.setProperty('maxdate', new Date(this.yearselect.options[this.yearselect.options.length - 1].value, 11, 31));
    this.fieldset.insertBefore(date_selector_calendar.panel.element, this.yearselect.nextSibling);
    this.set_date_from_selects();
    date_selector_calendar.panel.show();
    var me = this;
    setTimeout(function() {date_selector_calendar.cancel_any_timeout()}, 100);
}

date_selector_calendar.prototype.set_date_from_selects = function() {
    var year = parseInt(this.yearselect.value);
    var month = parseInt(this.monthselect.value) - 1;
    var day = parseInt(this.dayselect.value);
    date_selector_calendar.calendar.select(new Date(year, month, day));
    date_selector_calendar.calendar.setMonth(month);
    date_selector_calendar.calendar.setYear(year);
    date_selector_calendar.calendar.render();
    date_selector_calendar.fix_position();
}

date_selector_calendar.prototype.set_selects_from_date = function(eventtype, args) {
    var date = args[0][0];
    var newyear = date[0];
    var newindex = newyear - this.yearselect.options[0].value;
    this.yearselect.selectedIndex = newindex;
    this.monthselect.selectedIndex = date[1] - this.monthselect.options[0].value;
    this.dayselect.selectedIndex = date[2] - this.dayselect.options[0].value;
}

date_selector_calendar.prototype.connect_handlers = function() {
    YAHOO.util.Event.addListener([this.yearselect, this.monthselect, this.dayselect], 'change', this.handle_select_change, this);
    date_selector_calendar.calendar.selectEvent.subscribe(this.set_selects_from_date, this, true);
}

date_selector_calendar.prototype.release_calendar = function() {
    date_selector_calendar.panel.hide();
    date_selector_calendar.currentowner = null;
    YAHOO.util.Event.removeListener([this.yearselect, this.monthselect, this.dayselect], this.handle_select_change);
    date_selector_calendar.calendar.selectEvent.unsubscribe(this.set_selects_from_date, this);
}
