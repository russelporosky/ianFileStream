<?php
/**
 * Generic file streaming class.
 *
 * @package ianFileStream
 * @subpackage Classes
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2010 IndyArmy Network, Inc.
 */

class ianFS {
	private $buffer = null;			// Temporary buffer storage if needed
	private $chunkCount = 0;		// Chunk counter
	private $chunkMaximum = 8192;		// Maximum chunk size
	private $chunkMinimum = 512;		// Minimum chunk size
	private $chunkSize = -1;		// Immutable chunk size used
	private $currentPosition = -1;		// Current position within the file
	private $file;				// File name
	private $handle = null;			// File handle
	private $modificationTime = -1;		// File modification time
	private $newname;			// New file name
	private $open = false;			// Is the file currently open
	private $size = -1;			// File size in bytes
	private $trigger = false;		// Has the metatag trigger been found
	private $type = null;			// File type (extension, usually)
	protected $metaAllowed = array();	// Array of allowed meta tags
	protected $overlap = 0;			// Bytes of chunk overlap required
	public $chunk = 4096;			// Bytes per chunk
	public $forceDownload = false;		// Force browser download

	/**
	 * Loads a class based on the filetype. If none exists, uses itself as a
	 * generic file streamer.
	 *
	 * @param string $filename Complete or relative location of file.
	 * @param string $filetype Optional filetype (defaults to file extension).
	 * @param string $newname Streams the file with a different name (defaults to null).
	 * @param string $classpath Path to file handler classes (defaults to current folder).
	 * @return File Handler class.
	 */
	public static function load($filename, $filetype = null, $newname = null, $classpath = null) {
		$return = null;
		if (!isset($filetype) || $filetype == '') {
			$filetype = strtolower(substr($filename, strrpos($filename, '.') + 1));
		}
		if (!isset($newname) || $newname == '') {
			$newname = $filename;
		}
		if (!isset($classpath) || $classpath == '') {
			$classpath = substr($_SERVER["SCRIPT_FILENAME"], 0, strrpos($_SERVER["SCRIPT_FILENAME"], DIRECTORY_SEPARATOR));
		}
		if (isset($filetype) && $filetype != '') {
			$type = 'ianfs.'.$filetype;
			$file = $type.'.php';
			if (file_exists($classpath.$file)) {
				require_once($classpath.$file);
				$return = new $filetype($filename, $filetype, $newname);
			} else {
				$return = new ianFS($filename, $filetype, $newname);
			}
		} else {
			die('Unknown file type for <em>'.$filename.'</em>');
		}
		return $return;
	}
	/**
	 * Stores the filename, filetype and rename (if used), and checks for
	 * file existence.
	 *
	 * @param string $file Complete or relative location of file.
	 */
	public function __construct($file, $filetype, $newname) {
		if(!file_exists($file)) {
			// File doesn't exist, output error
			die('Could not find <em>'.$file.'</em>');
		}
		$this->file = $file;
		$this->type = $filetype;
		$this->newname = $newname;
	}
	/**
	 * Opens file handle for streaming and sets immutable chunk size.
	 */
	public function start() {
		if (!isset($this->handle) && !$this->open) {
			if ($this->forceDownload) {
				$this->forceDownload();
			}
			$this->handle = fopen($this->file, 'rb');
			$this->open = true;
			$this->chunkSize = $this->chunk;
			if ($this->chunkSize < $this->chunkMinimum) {
				$this->chunkSize = $this->chunkMinimum;
				$this->chunk = $this->chunkMinimum;
			}
			if ($this->chunkSize > $this->chunkMaximum) {
				$this->chunkSize = $this->chunkMaximum;
				$this->chunk = $this->chunkMaximum;
			}
		}
	}
	/**
	 * Returns a stream of $this->chunkSize bytes from the file. If the
	 * metatag block falls over multiple chunks, the return is all the
	 * chunks that contain the metablock.
	 *
	 * @return string
	 */
	public function nextChunk() {
		$return = null;
		if (isset($this->handle) && $this->open) {
			if (!feof($this->handle)) {
				if (!$this->trigger) {
					$this->currentPosition = ftell($this->handle);
				}
				$buffer = fread($this->handle, $this->chunkSize);
				if ($this->hasTriggerStart($buffer, $this->currentPosition, $this->getSize())) {
					$this->trigger = true;
				}
				if ($this->trigger) {
					$this->buffer .= $buffer;
					$buffer = null;
				}
				if ($this->trigger && $this->hasTriggerEnd($this->buffer, $this->currentPosition, $this->getSize())) {
					$buffer = $this->editMeta($this->buffer, $this->currentPosition, $this->getSize());
					$this->trigger = false;
					$this->buffer = null;
				}
				$return = $buffer;
				ob_flush();
				flush();
				$this->chunkCount++;

			} else {
				$this->closeHandle();
			}
		}
		return $return;
	}
	/**
	 * Closes the file handle if needed.
	 */
	protected function closeHandle() {
		if (isset($this->handle) && $this->open) {
			fclose($this->handle);
			$this->handle = null;
			$this->open = false;
		}
	}
	/**
	 * Resets file info variables and closes the file handle if needed.
	 */
	public function close() {
		$this->closeHandle();
		$this->modificationTime = -1;
		$this->size = -1;
	}
	/**
	 * Checks whether the current file is open.
	 *
	 * @return bool TRUE if file is open, FALSE if not.
	 */
	public function open() {
		return $this->open;
	}
	/**
	 * Returns the UNIX timestamp the file was last modified. Failing that,
	 * returns the current UNIX timestamp from the server.
	 *
	 * @return float UNIX timestamp.
	 */
	public function getModificationTime() {
		if ($this->modificationTime < 0) {
			$this->modificationTime = filemtime($this->file);
			if ($this->modificationTime === false) {
				$this->modificationTime = microtime(true);
			}
		}
		return $this->modificationTime;
	}
	/**
	 * Returns the size in bytes of the file.
	 *
	 * @return int Size of the file in bytes.
	 */
	public function getSize() {
		if ($this->size < 0) {
			$this->size = intval(sprintf("%u", filesize($this->file)));
		}
		return $this->size;
	}
	/**
	 * Forces the browser to show the Open/Save dialog box instead of
	 * displaying a helper application.
	 */
	public function forceDownload() {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.basename($this->newname)).'";modification-date="'.date('r', $this->getModificationTime()).'";';
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: '.$this->getSize());
	}
	/**
	 * Checks whether a given tag is available for this file type.
	 *
	 * @param string $tag Metatag you hope to find.
	 * @return boolean TRUE if tag is allowed, FALSE otherwise.
	 */
	public function takesMeta($tag) {
		$return = false;
		if (in_array($tag, $this->metaAllowed)) {
			$return = true;
		}
		return $return;
	}
	/**
	 * Returns the name of the class being used.
	 *
	 * @return string Name of the current class.
	 */
	public function getType() {
		return get_class($this);
	}
	/**
	 * Adds metatags that are legal for the file type.
	 *
	 * @param mixed $tag Metatag to add, or array of metatags.
	 */
	protected function addMetaList($tag) {
		if (!is_array($tag)) {
			$tag = array($tag);
		}
		array_merge($this->metaAllowed, $tag);
	}
	/**
	 * Checks to see if the start condition for finding meta has been found.
	 *
	 * @param string $buffer
	 * @return bool TRUE if the meta is found, FALSE otherwise.
	 */
	protected function hasTriggerStart(&$buffer, $location, $filesize) {
		return false;
	}
	/**
	 * Checks to see if the end condition for finding meta has been found.
	 *
	 * @param string $buffer
	 * @return bool TRUE if the end of meta is found, FALSE otherwise.
	 */
	protected function hasTriggerEnd(&$buffer, $location, $filesize) {
		return false;
	}
	/**
	 * The work of modifying the buffer contents is done here. The buffer
	 * always contains the entire metatag block, so it may be larger than
	 * the $chunkMaximum value.
	 *
	 * @param string $buffer
	 * @return string Modified contents.
	 */
	protected function editMeta(&$buffer, $location, $filesize) {
		return $buffer;
	}
}
