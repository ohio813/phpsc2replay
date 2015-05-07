## Overview ##

**Information** specifies the type of information, **function** defines the member function of the SC2Replay class that the information can be accessed with, **contained in** defines the file(s) inside the MPQ archive that the information is parsed from.

| **Information** | **Function** | **Contained in** |
|:----------------|:-------------|:-----------------|
| Player name | getPlayers(),<br />array index 'name' | replay.details,<br />replay.initData |
| Player race | getPlayers(),<br />array index 'race' | replay.details,<br />replay.attributes.events |
| Player race(includes random) | getPlayers(),<br />array index 'srace' | replay.details,<br />replay.attributes.events |
| Player color(text) | getPlayers(),<br />array index 'scolor' | replay.attributes.events |
| Player color(rgb) | getPlayers(),<br />array index 'color' | replay.details |
| Player team | getPlayers(),<br />array index 'team' | replay.details |
| Player type(computer or human) | getPlayers(),<br />array index 'isComp' (true or false) | replay.attributes.events |
| Player handicap | getPlayers(),<br />array index 'handicap' | replay.attributes.events |
| Difficulty(computer opponents) | getPlayers(),<br />array index 'difficulty' | replay.attributes.events |
| Game length | getGameLength(),<br />getFormattedGameLength() | MPQ user data header |
| Game speed | getGameSpeedText() | replay.attributes.events |
| Team size | getTeamSize() | replay.attributes.events |
| Actual team size | getRealTeamSize() | replay.attributes.events |
| Map name | getMapName() | replay.details |
| Winner(**very unrealiable**) | getPlayers(),<br />array index 'won' | replay.game.events |
| Battle.net profile uid | getPlayers(),<br />array index 'uid' | replay.details |
| Battle.net profile subregion | getPlayers(),<br />array index 'uidIndex' | replay.details |
| Replay recorder (experimental) | getRecorder(),<br />returns a player array | replay.message.events |
| Replay build | getBuild() | MPQ user data header |
| Replay major version | getVersion() | MPQ user data header |
| Game events | getEvents() | replay.game.events |
| Actions per second(see notes below) | getPlayers(),<br />array index 'apm' | replay.game.events |
| Total actions | getPlayers(),<br />array index 'apmtotal' | replay.game.events |
| Chat log | getMessages() | replay.message.events |
| Local time when game was played | getCtime() | replay.details |



## Notes ##

  * Parsing the winner is done by marking everyone who is noticed leaving the game as a loser.
  * Actions per second consists of an array, whose indexes are seconds since game start and values are actions for that second. Using this it is fairly straightforward to graph APM. Note that seconds that have 0 actions will not be present.
  * The actions per second functionality may not reflect the APM shown in-game. The actions that are counted are build orders, move orders, selection events, hotkey presses and right-clicks. In addition, the APM is not yet adjusted based on game speed.
  * Chat log is an array of arrays, with array indexes 'id' (player id), 'name' (player name), 'target' (message target, 0 for all, 2 for alliance), 'time' (seconds elapses since game start), 'message' (chat message)
