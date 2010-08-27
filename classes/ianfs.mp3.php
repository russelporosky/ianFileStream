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
	/**
	 * Has the ID3v2.2 block been found?
	 *
	 * @var bool
	 */
	private $foundID3v22 = false;

	/**
	 * Has the ID3v2.3 block been found?
	 *
	 * @var bool
	 */
	private $foundID3v23 = false;

	/**
	 * Has the ID3v2.4 block been found?
	 *
	 * @var bool
	 */
	private $foundID3v24 = false;

	/**
	 * Has the ID3v1 block been found?
	 *
	 * @var bool
	 */
	private $foundID3v10 = false;

	/**
	 * Has the ID3v1.1 block been found?
	 *
	 * @var bool
	 */
	private $foundID3v11 = false;

	/**
	 * Calls parent constructor, sets the overlap value (minimum trigger
	 * start is 4 characters long), and sets valid ID3 metatags.
	 *
	 * @param string $file Complete or relative location of file.
	 * @param string $filetype Optional filetype (defaults to file extension).
	 * @param string $newname Streams the file with a different name (defaults to null).
	 */
	public function __construct($file, $filetype, $newname) {
		parent::__construct($file, $filetype, $newname);
		$this->overlap = 5;
		$this->addMetatags();
	}

	/**
	 * Adds valid metatags to the class array.
	 */
	private function addMetatags() {
		//parent::addMetaList(array('TT2','COM'));
	}

	/**
	 * Checks for ID3v2 start trigger.
	 *
	 * @param string $buffer Current file buffer.
	 * @param int $location Location, in bytes, that buffer begins at.
	 * @param int $filesize Size of the original file, in bytes.
	 * @return boolean TRUE if trigger is found, FALSE otherwise.
	 */
	protected function hasTriggerStart(&$buffer, $location, $filesize) {
		$return = false;
		// ID3 blocks always start with the text "ID3". And we only
		// want to find it once, thus $this->found is used.
		if (!$this->foundID3v22 && strpos($buffer, 'ID3') !== false) {
			$this->foundID3v22 = true;
			$return = true;
		}
		return $return;
	}

	/**
	 * Checks for ID3v2 end trigger.
	 *
	 * @param string $buffer Current file buffer.
	 * @param int $location Location, in bytes, that buffer begins at.
	 * @param int $filesize Size of the original file, in bytes.
	 * @return boolean TRUE if trigger is found, FALSE otherwise.
	 */
	protected function hasTriggerEnd(&$buffer, $location, $filesize) {
		$return = false;
		// ID3 blocks always end with chr(255), but we only care if it
		// occurs AFTER the "ID3" text.
		if ($this->foundID3v22 && strpos($buffer, chr(255)) !== false && (strpos($buffer, chr(255)) > strpos($buffer, 'ID3'))) {
			$return = true;
		}
		return $return;
	}

	/**
	 * Strips ID3v2 tags from buffer.
	 *
	 * @param string $buffer Current file buffer.
	 * @param int $location Location, in bytes, that buffer begins at.
	 * @param int $filesize Size of the original file, in bytes.
	 * @return string Modified buffer value.
	 */
	protected function editMeta(&$buffer, $location, $filesize) {
		$return = null;
		// All we do here is strip the ID3 block from the buffer.
		$return = substr($buffer, 0, strpos($buffer, 'ID3')).substr($buffer, strpos($buffer, chr(255)) + 1);
		return $return;
	}
}
