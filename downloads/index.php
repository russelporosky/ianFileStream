<?php
/**
 * Demonstration controller for ianFileStream.
 *
 * @package ianFileStream
 * @license http://www.gnu.org/licenses/gpl.html
 */

/*
 * Copyright 2010 IndyArmy Network, Inc.
 * ianFileStream is distributed under the terms of the GNU General Public License.
 */

/*
 * ianFileStream is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * ianFileStream is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * ianFileStream.  If not, see <http://www.gnu.org/licenses/>.
 */

define('CLASSPATH', '..'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR);

require_once(CLASSPATH.'ianfs.php');

// Relative or full filesystem path to the file.
$filename = '';
if (intval($_GET['file']) == 1) {
	$filename = '02 Man of Pain.mp3';
}

// Factory call - file name, file type (defaults to file extension), new file
// name (defaults to none), path to child classes (defaults to current folder).
$file = ianFS::load($filename, '', '', CLASSPATH);

// Force the browser to display Open/Save dialog instead of helper application.
$file->forceDownload = true;

// Set a chunk size in bytes - between 512 and 8192.
$file->chunk = 1024;

// Open the file for streaming
$file->start();

// This loop echoes the file contents as long as there is data to stream.
while ($file->open()) {
	echo $file->nextChunk();
}

// Close the file handle and reset the class.
$file->close();

