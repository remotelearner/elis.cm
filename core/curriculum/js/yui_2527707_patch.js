// This is a monkey patch to fix YUI ticket #2527707
// See http://yuilibrary.com/projects/yui2/ticket/2527707 

YAHOO.widget.DataTable.prototype.getTdEl = function(cell) {

	var Dom = YAHOO.util.Dom,

	lang = YAHOO.lang, elCell, el = Dom.get(cell);

	// Validate HTML element
	if (el && (el.ownerDocument == document)) {

		// Validate TD element
		if (el.nodeName.toLowerCase() != "td") {

			// Traverse up the DOM to find the corresponding TR element
			elCell = Dom.getAncestorByTagName(el, "td");

		} else {

			elCell = el;

		}

		// Make sure the TD is in this TBODY
		if (elCell && (elCell.parentNode.parentNode == this._elTbody)) {

			// Now we can return the TD element
			return elCell;

		}

	} else if (cell) {

		var oRecord, nColKeyIndex;

		if (lang.isString(cell.columnKey) && lang.isString(cell.recordId)) {

			oRecord = this.getRecord(cell.recordId);
			var oColumn = this.getColumn(cell.columnKey);
			if (oColumn) {

				nColKeyIndex = oColumn.getKeyIndex();

			}

		}
		if (cell.record && cell.column && cell.column.getKeyIndex) {

			oRecord = cell.record;
			nColKeyIndex = cell.column.getKeyIndex();

		}
		var elRow = this.getTrEl(oRecord);
		if ((nColKeyIndex !== null) && elRow && elRow.cells
				&& elRow.cells.length > 0) {

			return elRow.cells[nColKeyIndex] || null;

		}

	}

	return null;

};