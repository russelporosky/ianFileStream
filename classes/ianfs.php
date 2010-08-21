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
	/**
	 * Buffer storage.
	 *
	 * Temporary buffer storage is only used if the hasTriggerStart() and
	 * hasTriggerEnd() occur in different chunks.
	 *
	 * @var string
	 */
	private $buffer = null;

	/**
	 * Chunk counter.
	 *
	 * The number of chunks used for this file.
	 *
	 * @var int
	 */
	private $chunkCount = 0;

	/**
	 * Maximum chunk size.
	 *
	 * @var int
	 */
	private $chunkMaximum = 8192;

	/**
	 * Minimum chunk size.
	 *
	 * @var int
	 */
	private $chunkMinimum = 512;

	/**
	 * Actual chunk size used.
	 *
	 * This is a private value (immutable by child classes during transfer)
	 * to prevent the chunk size from changing during processing.
	 *
	 * @var int
	 */
	private $chunkSize = -1;

	/**
	 * Stream cursor position.
	 *
	 * The number of bytes into the file the current buffer begins.
	 *
	 * @var int
	 */
	private $currentPosition = -1;

	/**
	 * Name of the file to stream.
	 *
	 * This is either a relative or complete path to the file, including
	 * the file name.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * PHP file handle object.
	 *
	 * @var object
	 */
	private $handle = null;

	/**
	 * File modification time as a UNIX timestamp value.
	 *
	 * @var int
	 */
	private $modificationTime = -1;

	/**
	 * The $file will be delivered to the user with this name.
	 *
	 * @var string
	 */
	private $newname;

	/**
	 * Is the file currently being streamed?
	 *
	 * @var bool
	 */
	private $open = false;

	/**
	 * File size, in bytes.
	 *
	 * @var int
	 */
	private $size = -1;

	/**
	 * Metatag block is part of the current buffer.
	 *
	 * This remains TRUE between hasTriggerStart() and hasTriggerEnd(). Once
	 * editMeta() is called, value becomes FALSE.
	 *
	 *
	 * @var bool
	 */
	private $trigger = false;

	/**
	 * File type (file extentsion, usually).
	 *
	 * This can be overridden during the factory call. For example, instead
	 * of creating classes for both JPG and JPEG extensions, you can force
	 * *.jpeg files to be treated as JPG file types.
	 *
	 * @var string
	 */
	private $type = null;

	/**
	 * Array of valid metatags.
	 *
	 * @var array
	 */
	protected $metaAllowed = array();

	/**
	 * Overlap between chunks, in bytes.
	 *
	 * The overlap is to ensure that a complete trigger can be found. It
	 * should be set to 1 byte longer than the maximum trigger length. For
	 * example, the trigger for ID3v2 tags is 'ID3', so overlap should be 4.
	 *
	 * @var int
	 */
	protected $overlap = 0;

	/**
	 * Size of each chunk, in bytes.
	 *
	 * The actual chunk size used is determined by chunkMaximum and
	 * chunkMinimum if this value is outside of their range.
	 *
	 * @var int
	 */
	public $chunk = 4096;

	/**
	 * Force browser download.
	 *
	 * Using headers, this will force the browser to display an Open/Save
	 * dialog box instead of loading the browser's default helper
	 * application for this file type.
	 *
	 * @var bool
	 */
	public $forceDownload = false;

	/**
	 * Use proper MIME type.
	 *
	 * If TRUE, the correct MIME type is sent to the browser, which will
	 * trigger any helper application the browser wants. If FALSE, the file
	 * is simply returned as part of the loop and can be handled by the
	 * controller instead. This setting is ignored if forceDownload = TRUE.
	 *
	 * @var bool
	 */
	public $mime = true;

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
	 * @param string $filetype Optional filetype (defaults to file extension).
	 * @param string $newname Streams the file with a different name (defaults to null).
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
			if (!$this->forceDownload && $this->mime) {
				$this->forceDownload($this->getMimeType());
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
	 * metatag block falls over multiple chunks, the return is NULL until
	 * the end of the metatag block is detected.
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
	public function forceDownload($mime = null) {
		if (!isset($mime) || $mime == '') {
			$mime = 'application/octet-stream';
		}
		header('Content-Description: File Transfer');
		header('Content-Type: '.$mime);
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
	 * For example, if the file type is "mp3" and there is a child class
	 * loaded from "ianfs.mp3.php", this function will return "mp3". If no
	 * child class was loaded, it will return "ianFS".
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
	 * Always try and exit quickly from this function to avoid slowing the
	 * transfer down or using up memory.
	 *
	 * @param string $buffer
	 * @return bool TRUE if the meta is found, FALSE otherwise.
	 */
	protected function hasTriggerStart(&$buffer, $location, $filesize) {
		return false;
	}

	/**
	 * Checks to see if the end condition for finding meta has been found.
	 * Always try and exit quickly from this function to avoid slowing the
	 * transfer down or using up memory.
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

	/**
	 * Uses PHP 5.3 functions to check the MIME type of a file. You can also
	 * just install the PECL extension if you run earlier versions of PHP:
	 * http://pecl.php.net/package/Fileinfo/
	 *
	 * Without FileInfo installed, this function returns NULL. This will
	 * trigger ianFS to deliver 'application/octet-stream' as the MIME type,
	 * which is functially identical to forceDownload=TRUE.
	 *
	 * @return string
	 */
	private function getMimeType() {
		$return = null;
		if (function_exists('finfo_open') && function_exists('finfo_file') && function_exists('finfo_close')) {
			$fileinfo = finfo_open(FILEINFO_MIME);
			$return = finfo_file($fileinfo, $this->file);
			finfo_close($fileinfo);
		}
		return $return;
	}
}
