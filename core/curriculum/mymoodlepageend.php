<?php

    if (isloggedin()) {
        // Can only register if not logged in...
       echo '</td>';
    
        $blocks_preferred_width = bounded_number(180, blocks_preferred_width($pageblocks[BLOCK_POS_RIGHT]), 210);
    
        if (blocks_have_content($pageblocks, BLOCK_POS_RIGHT) || $PAGE->user_is_editing()) {
            echo '<td style="vertical-align: top; width: '.$blocks_preferred_width.'px;" id="right-column">';
            blocks_print_group($PAGE, $pageblocks, BLOCK_POS_RIGHT);
            echo '</td>';
        }
    
    
        /// Finish the page
        echo '</tr></table>';
    }

    print_footer();
?>
