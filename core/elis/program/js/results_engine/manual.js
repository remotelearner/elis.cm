YUI().use('io-base', 'node', function(Y) {

function complete(id, o, args) {
    var div = Y.one('#results');
    var message = M.str.elis_program.results_done;
    if (o.status != 200) {
    	message = message +"<br />\n"+ o.statusText;
    } else {
    	message = message +"<br />\n"+ o.responseText;
    }
    div.set("innerHTML", message)
}

M.results_engine = {
    process: function(source, pmclass) {
        var uri = source +"?id="+pmclass;
        var request = Y.io(uri);
    }
}

Y.on('io:complete', complete, Y, 'process');

});