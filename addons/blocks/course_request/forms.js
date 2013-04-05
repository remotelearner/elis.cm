function handle_course_change() {
	var elements = document.getElementsByName("courseid");
	var value = 0;
	var text = "";
	for(var i = 0; i < elements.length; i++) {
		value = elements[i].value;
		text = elements[i].options[value].text;
		break;
	}

	if(value != 0) {
		var title_elements = document.getElementsByName("title");
		for(var i = 0; i < title_elements.length; i++) {
			title_elements[i].value = text;
			title_elements[i].disabled = true;
		}
	} else {
		var title_elements = document.getElementsByName("title");
		for(var i = 0; i < title_elements.length; i++) {
			title_elements[i].disabled = false;
			title_elements[i].value = "";
		}
	}
}