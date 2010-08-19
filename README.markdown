# ianFileStream: on-the-fly stream modification
v0.1 - 2010/08/19

## Description
ianFS allows on-the-fly modification of files as they are sent from the server to the user.

This means you could change the ID3 tags on an MP3 while it was being transferred, or modify EXIF tags in a JPEG file before sending it to the browser.

## Server Requirements

* PHP 5.0+
* Apache 2.0+

Other configurations have not been tested.

## Technical Notes
ianFS functionally consists of only two files - the `ianfs.php` class file and the `index.php` download controller. Also included is a `ianfs.mp3.php` helper class.

When the `ianFS` class is loaded, it will use the `filetype` to try and find a helper class. If the helper cannot be located in the `classpath` folder or the current folder, the `ianFS` will act as a generic handler itself.

Child classes have only one requirement: they **must** call `parent::__construct(...)` in their constructor.

The included example `ianfs.mp3.php` file does only one thing - it strips the ID3 metatags from an MP3 file without modifying the original file.

## Release Notes
Future version of this library will include support for many kinds of media files, the ability to add hidden information, and better metatag manipulation.

This initial release is primarily meant for testing on various configurations, although it may work well enough to use in a production environment.

**NOTE:** This initial release of ianFS has one known bug: if a trigger condition is split on a chunk boundary, it cannot be detected. For example, if the text "ID3" is split across two chunks, it will not be successfully detected. A patch for this will be made in version 0.2.

## Contact Info
My name is Russ Porosky, and I can be contacted at russ @ indyarmy . com

## License Info
ianFileStream is licensed under the GPL3. Please read the "license.txt" file included in the ianFileStream archive.

The music track "Man of Pain" by Dominion Mine is licensed as CC-BY-NC-SA. Please read http://creativecommons.org/licenses/by-nc-sa/3.0/ for more information.
