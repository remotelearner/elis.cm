M.clustertree = {};
M.clustertree.init_tree = function(Y, wwwroot, instanceid, uniqueid, clustertree_object, executionmode, report_id, dropdown_button_text, tree_button_text) {
    document.wwwroot = wwwroot;
    Y.use('yui2-treeview', function(Y) {
        YAHOO.util.Event.onDOMReady(function() {
            var tree_object = YAHOO.lang.JSON.parse(clustertree_object);
            clustertree_render_tree(instanceid, uniqueid, tree_object, executionmode);
            clustertree_set_toggle_state(report_id,uniqueid,dropdown_button_text, tree_button_text);
        });
    });
};