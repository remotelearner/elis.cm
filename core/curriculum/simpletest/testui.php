<?php
/**
 * Unit tests for curriculum manager UI.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

if (!defined('MOODLE_INTERNAL')) {
	die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

// Load general config
require_once($CFG->dirroot . '/curriculum/config.php'); // Include the code to test

// Load moodle functions
require_once($CFG->libdir . '/dmllib.php');

class curriculum_ui_test extends WebTestCase {

	var $logged_in = false;
	
	public function __construct() {
		// pass
	}
	
	/**
	 * Gets a path relative to the moodle wwwroot.
	 */
	public function getRelative($path) {
		global $CFG;
		
		$this->get($CFG->wwwroot . $path);
	}

	public function login() {
		$this->getRelative('/login/index.php');
		$this->setField('username', 'admin');
        $this->setField('password', 'admin');
        $this->click('Login');
	}

	public function setUp() {
		if(!$this->logged_in) {
			$this->login();
		}
	}

	public function tearDown() {
		// pass
	}
	
	public function assertLinks($links) {
		foreach($links as $link) {
			$this->assertLink($link);
		}
	}

	public function testIndex() {
		$this->getRelative('/curriculum');
		
		$links = array(
			'Manage Tags',
			'Manage Environments',
			'Manage Users',
			'Manage Clusters',
			'Manage Courses',
			'Manage Curricula',
			'Manage Classes',
			'Manage Tracks',
		);
		
		$this->assertLinks($links);
	}
	
	public function testTag() {
		$this->getRelative('/curriculum');
		
		$this->clickLink('Manage Tags');
		
		// Add tag
		$this->clickLink('Add Tag');
		
		$this->assertField('name', '');
		$this->assertField('description', ''); 
		
		$this->setField('name', 'aaaTestName');
		$this->setField('description', 'aaaTestDescription');
		
		$this->clickSubmit('Save');
		
		$this->assertText('aaaTestName');
		$this->assertText('aaaTestDescription');
		
		// TODO test delete, edit
	}
}
?>