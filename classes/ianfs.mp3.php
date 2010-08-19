<?php
/**
 * MP3 helper class.
 *
 * @package ianFileStream
 * @subpackage Classes
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2010 IndyArmy Network, Inc.
 */

class mp3 extends ianFS {
	private $found = false;			// have we found the meta

	public function __construct($file, $filetype, $newname) {
		parent::__construct($file, $filetype, $newname);
		$this->overlap = 4;
		$this->addMetatags();
	}
	private function addMetatags() {
		//parent::addMetaList(array('TT2','COM'));
	}
	protected function hasTriggerStart(&$buffer) {
		$return = false;
		// ID3 blocks always start with the text "ID3". And we only
		// want to find it once, thus $this->found is used.
		if (!$this->found && strpos($buffer, 'ID3') !== false) {
			$this->found = true;
			$return = true;
		}
		return $return;
	}
	protected function hasTriggerEnd(&$buffer) {
		$return = false;
		// ID3 blocks always end with chr(255), but we only care if it
		// occurs AFTER the "ID3" text.
		if (strpos($buffer, chr(255)) !== false && (strpos($buffer, chr(255)) > strpos($buffer, 'ID3'))) {
			$return = true;
		}
		return $return;
	}
	protected function editMeta(&$buffer) {
		$return = null;
		// All we do here is strip the ID3 block from the buffer.
		$return = substr($buffer, 0, strpos($buffer, 'ID3')).substr($buffer, strpos($buffer, chr(255)) + 1);
		return $return;
	}
}
