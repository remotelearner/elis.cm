<?php

defined('MOODLE_INTERNAL') || die();

require_once ($CFG->dirroot . '/lib/formslib.php');

class resultsconfigform extends moodleform {
    public $title;
    public $maxrowid = 0;

    public function __construct($action=null, $customdata=null) {
        $this->populate_title();
        parent::__construct($action, $customdata);
    }

    public function populate_title() {
        $this->title=get_string('results_engine_defaults_config','elis_program');
    }

    public function definition() {
        global $OUTPUT, $PAGE;
        $PAGE->requires->js('/elis/program/js/results_engine/jquery-1.6.2.min.js', true);
        $PAGE->requires->js('/elis/program/js/results_engine/results_config.js', true);

        $mform =& $this->_form;
        $mform->addElement('header', 'activationrules',$this->title);
        if (!empty($this->_customdata['results'])) {
            $mform->addElement('html',$this->_customdata['results']);
        }

        $stored_rowids = array();
        foreach ($this->_customdata['defaults'] as $i => $row) {
            if (is_numeric($i) && !empty($row['rowid'])) {
                $stored_rowids[$row['rowid']] = $row['rowid'];
            }
        }

        if (!empty($stored_rowids)) {
            $this->maxrowid = max($stored_rowids);
        }

        $cd=(array)$this->_customdata;
        $cd['nrc']=(isset($cd['nrc']))?$cd['nrc']:1;
        $used_rowids = array();
        for($i=0;$i<$cd['nrc'];$i++){

            if (empty($this->_customdata['defaults'][$i]['rowid'])) {
                $this->maxrowid++;
                $id = $this->maxrowid;
            } else {
                $id = $this->_customdata['defaults'][$i]['rowid'];
            }

            if (isset($used_rowids[$id])) {
                $this->maxrowid++;
                $id = $this->maxrowid;
            }

            $used_rowids[$id] = $id;

            $this->generate_row($i,$mform,$id);

        }


        $mform->addElement('hidden', 'rowcount',$cd['nrc'],array('id'=>'rowcount'));
        $mform->addElement('submit','addrange',get_string('results_add_another_score_btn', 'elis_program'),array('onclick'=>'$(\'#rowcount\').val(parseInt($(\'#rowcount\').val())+1)'));

        $this->add_additional_form_elements();

        $mform->addElement('submit','finalize',get_string('savechanges'));
    }
/*
    function get_raw_dynamic_data() {
         return ($this->_form->_submitValues);
    }

    function get_dynamic_data() {
        return $this->normalize_submitted_data($this->get_raw_dynamic_data());
    }
*/
    protected function add_additional_form_elements() {
        return true;
    }

    protected function generate_row_additional_elements(&$group,$i) {
        return true;
    }

    protected function generate_row($i,&$mform,$id=0) {
        global $OUTPUT;

        $textgroup=array();
        $textgroup[]=&$mform->createElement('static','grouplabel','','Range '.($i+1).':');
        $textgroup[]=&$mform->createElement('hidden','rowid','Id',array('size'=>5));
        $textgroup[]=&$mform->createElement('static','minlabel','','Min');
        $textgroup[]=&$mform->createElement('text','mininput','Min',array('size'=>5));
        $textgroup[]=&$mform->createElement('static','maxlabel','','Max');
        $textgroup[]=&$mform->createElement('text','maxinput','Max',array('size'=>5));
        $textgroup[]=&$mform->createElement('static','namelabel','','Name');
        $textgroup[]=&$mform->createElement('text','nameinput','Name');
        $textgroup[]=&$mform->createElement('static','deleteLink','','<img src="'.$OUTPUT->pix_url('delete','elis_program').'" onclick="delete_row('.$i.',$(this))" alt="Delete" style="cursor:pointer" title="Delete" /></a>');
        $this->generate_row_additional_elements($textgroup,$i);

        $mform->addGroup($textgroup,'textgroup_'.$i);
        $mform->setDefault('textgroup_'.$i.'[rowid]',$id);

        if (!empty($this->_customdata['defaults'][$i]))
        {
            $fields=array('min','max','name');
            foreach ($fields as $f) {
                if (isset($this->_customdata['defaults'][$i][$f])) {
                    $mform->setDefault('textgroup_'.$i.'['.$f.'input]',$this->_customdata['defaults'][$i][$f]);
                }
            }
        }
    }


}

/*$fields=array('mininput','maxinput','nameinput');
foreach ($fields as $field) {
$$field=(isset($v[$field.'_'.$i]))?$v[$field.'_'.$i]:'';
}
*/