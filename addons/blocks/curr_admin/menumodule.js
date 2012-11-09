M.block_curr_admin = {};
M.block_curr_admin.init_tree = function(Y,object,wwwroot) {
    document.wwwroot = wwwroot;
    Y.use('yui2-treeview', function(Y) {
        YAHOO.util.Event.onDOMReady(function() {
            var tree_object = YAHOO.lang.JSON.parse(object);
            render_curr_admin_tree(tree_object);
        });
    });
};