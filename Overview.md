# General #

The replay parser consists of two different classes, MPQFile and SC2Replay. These are located within mpqfile.php and sc2replay.php files, respectively. The purpose of these classes is the following:
  * MPQFile handles reading the metadata of an MPQ archive, and has a function for extracting a specific file out of it as well creating an instance of SC2Replay.
  * SC2Replay is the actual class doing the parsing, and (will) contain a function for every file inside the MPQ archive that may have interesting information. It also has member functions for accessing the parsed data.

There's another utility file called **sc2replayutils.php**, which is completely optional and simply contains a class for the different ability codes and possibly other utility data in the future. Due to being rewritten completely in beta phase 2, there will be missing codes but most important ones should be present.<br />

For testing and demonstration purposes there's also a file called **upload\_file.php**. This is a simple file to demonstrate how the classes can be used to access the data of an uploaded file. Just make sure mpqfile.php, sc2replay.php and optionally sc2replayutils.php are in the same folder or within the include path.


# Usage #

All you will need to do is to include **mpqfile.php** on any php page that you want to use the parsing functionality on. You can also include sc2replay.php, but the parseReplay function in the MPQFile class will automatically include the file if SC2Replay class is not found. The correct way to use the two classes is the following:
  1. $mpqfile = new MPQFile('filename.SC2Replay');
  1. $replay = $mpqfile->parseReplay();

After this, the $replay instance will have parsed what it can from the file, and the values are accessible through the relevant member functions. For more specifics, see [Parseables](Parseables.md).