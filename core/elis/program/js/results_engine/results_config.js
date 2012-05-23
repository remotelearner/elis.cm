function delete_row(rid,delbutton)
{
	num_rows=parseInt($('#rowcount').val());
	
	//update rows
		for (i=rid;i<=num_rows;i=i+1)
		{
			if (i!=num_rows) {
				//not last row, replace values with values from next row
				$('input[name="textgroup_'+i+'[rowid]"]').val($('input[name="textgroup_'+(i+1)+'[rowid]"]').val());
				$('input[name="textgroup_'+i+'[mininput]"]').val($('input[name="textgroup_'+(i+1)+'[mininput]"]').val());
				$('input[name="textgroup_'+i+'[maxinput]"]').val($('input[name="textgroup_'+(i+1)+'[maxinput]"]').val());
				$('input[name="textgroup_'+i+'[nameinput]"]').val($('input[name="textgroup_'+(i+1)+'[nameinput]"]').val());
				//look for optional color values
				if ($('input[name="textgroup_'+i+'[color]"]').length > 0) {
					$('input[name="textgroup_'+i+'[color]"]').val($('input[name="textgroup_'+(i+1)+'[color]"]').val());
					
					//change colorbox colors
					nextbox = $('#colorbox'+(i+1));
					newbg = (nextbox.length > 0) ? nextbox.css('background-color') : 'transparent';
					$('#colorbox'+i).css('background-color',newbg);
				}
			} else {
				//last row. clear values, hide group
				$('input[name="textgroup_'+i+'[rowid]"]').val('');
				$('input[name="textgroup_'+i+'[mininput]"]').val('');
				$('input[name="textgroup_'+i+'[maxinput]"]').val('');
				$('input[name="textgroup_'+i+'[nameinput]"]').val('');
				$('input[name="textgroup_'+i+'[nameinput]"]').parents('.fitem').hide();
			}
		}
	
	//update rowcount
		$('#rowcount').val(num_rows-1);
}