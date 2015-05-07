## Overview ##

This is a Starcraft 2 replay parser written with php whose aim is the ability to parse SC2 replay files without anything except php support(no external libraries). The parser requires the bzip2 module for Starcraft 2 beta phase 2 and retail replays or if you use the older version for earlier replays, gzlib.<br />

The parser is released under the GNU GPL v3, which means that the code is freely available and there are no real restrictions on its use.<br />

## Current status ##

  * **Latest zip file version: 1.61 (support for 1.4.0)**
  * **A patch 1.5-compatible version is available in the repository. An updated zip file will be uploaded once ability codes and other finishing touches get done. This may take a while, though. (Updated 12.8.2012)**

Note that new zip files are only added after a major or several minor updates. The latest files are always available in the repository.<br />

Currently the parser supports Starcraft 2 retail version replays and also beta phase 2 replays to an extent, but those may be buggy.<br />

The [Overview](Overview.md) page contains some basic instructions. Even though the parser works well, it should be considered a work-in-progress since there are still unknowns in the replay format. For a list of most things that the parser can parse, see [Parseables](Parseables.md) (might be missing some).<br />

The most recent code is always available in the mercurial repository, but whenever major updates are made a new version will also appear under the [Downloads](http://code.google.com/p/phpsc2replay/downloads/list) tab.<br />

An example upload script with the latest files from the repository is running at http://kuukkeli.ath.cx/ooaemkee/SC2Replay/upload_file.php. Since it is also used for testing new code prior to commits, it may throw weird errors sometimes but it should be functional most of the time.<br />

## Acknowledgements ##

Most of the replay format was already figured out by another Starcraft 2 replay parser project at http://code.google.com/p/starcraft2replay/, and their documentation has been invaluable.<br />
A valuable source for the MPQ format itself has been http://wiki.devklog.net/index.php?title=The_MoPaQ_Archive_Format and all relevant portions have been implemented using that document.<br />
There have been a lot of people contributing their time to this project, either by coding, figuring out the format or testing and reporting defects. Thank you to everyone who has helped make this project what it is.

# Security #

Every single piece of data should be considered user-generated data. This means that unless you like your site broken and your users pissed off, **EVERY** piece of parsed data should be given the exact same level of scrutiny that normal user input is.<br />

This means that '<>' should be treated as a mortal enemy, every database access should take extra care to run the parsed data through mysql\_real\_escape\_string or a similar function, etc. **YOU HAVE BEEN WARNED**.<br />

## Contact ##

IRC: irc.quakenet.org, nick Ascylon (usually available from 17 CET onward)<br />
E-mail: lauri.virkamaki`<at>`gmail.com<br />
Google groups: http://groups.google.com/group/phpsc2replay