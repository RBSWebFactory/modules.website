function durationPicker(jqueryelt)
{
	jQuery.noConflict();

	jqueryelt.hide();
	var inputValue = jqueryelt.attr('value');
	var value = '';
	var unit = 'd';
	if (inputValue.length != 0)
	{
		value = inputValue.substr(0, inputValue.length-1);
		unit = inputValue.substr(inputValue.length-1);
	}	
	var htmlContent = '<input size="5"  value="' + value + '" maxlength="10" type="text" class="' + jqueryelt.attr('class') + '" id="' + jqueryelt.attr('id') + '_value" />'
	+'<select   id="' + jqueryelt.attr('id') + '_unit"><option value="d">&modules.uixul.bo.duration.Day;</option>'
	+'<option value="w" ' + (unit == 'w' ? 'selected="selected"' : '') + '>&modules.uixul.bo.duration.Week;</option>'
	+'<option value="m" ' + (unit == 'm' ? 'selected="selected"' : '') + '>&modules.uixul.bo.duration.Month;</option>'
	+'<option value="y" ' + (unit == 'y' ? 'selected="selected"' : '') + '>&modules.uixul.bo.duration.Year;</option></select>';
	jqueryelt.after(htmlContent);
	
	var handler = function(){
		var unit = jQuery(jqueryeltId + '_unit').get(0).options[jQuery(jqueryeltId + '_unit').get(0).selectedIndex].value;
		var value = parseInt(jQuery(jqueryeltId + '_value').attr('value'));
		jQuery(jqueryeltId).attr('value', isNaN(value) ?  '' : value + unit )
	};
	var jqueryeltId = '#' + jqueryelt.attr('id');
	jQuery(jqueryeltId + '_unit').change(handler);
	jQuery(jqueryeltId + '_value').change(handler);
}