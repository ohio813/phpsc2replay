## Constructor ##
#### construct($filename, $autoparse = true, $debug = false) ####

The constructor requires a single parameter, which is the file name of the mpq file. The constructor will open up a file pointer, which will be stored in the member variable **fp**. Any function reading from the file should always check that **fp** is valid before attempting a read.<br /><br />
The optional parameter $autoparse, if left to its default value of true, will immediately call the parseHeader() -member function. If false, this will have to be done seperately.<br /><br />
The optional parameter $debug will make the class generate debug output. Any debug output will go through the private member function debug($string), so if non-default debug destinations or formatting are desired, that can be edited to suit one's needs.

## Member variables ##
All member variables are declared private, and can only be accessed through getters and setters. The only fields with setter functions are the $debug and $debugNewline member variables.

## Functions ##
#### function setDebugNewline($str) ####
This function will add an optional suffix to all debug messages. By default it is `<br>\n`.

#### function setDebug($bool) ####
This function will turn debug messages on or off. Any value that php interprets as non-false will turn debugging on.

#### function parseHeader() ####
This function will parse the MPQ header file, reading both the user data section as well as the actual archive header. Both the hash and block tables, as well as their offsets will be read into their respective variables. For more information about the MPQ format, see http://wiki.devklog.net/index.php?title=The_MoPaQ_Archive_Format. <br /><br />

Returns true if parsing was successful, false if it failed for some reason. The member variable **init** will contain the specific error code and can be read with the member function getState().

#### function getFileSize($filename) ####
Returns the file size or false, if the file does not exist or the header hasn't been parsed

#### function getFile($filename) ####
Returns the file contents of a file inside the archive as a string if file exists, or false if an error occurred. As of major version 9, the following files are present in Starcraft 2 replay archives:
  * (attributes)
  * (listfile)
  * replay.attributes.events
  * replay.details
  * replay.game.events
  * replay.initData
  * replay.message.events
  * replay.smartcam.events
  * replay.sync.events

#### function parseReplay() ####
A convenience function for getting an instance of the SC2Replay class with the file contents parsed. Returns an instance of the SC2Replay class with the contents of this MPQFile parsed.

#### function getBuild() ####
Returns the build number, which is present in the user data portion of the header.

#### function getVersion() ####
Returns the major version number, which is present in the user data portion of the header.

#### function getGameLength() ####
Returns the game length in seconds, which is present in the user data portion of the header.