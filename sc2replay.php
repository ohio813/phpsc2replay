<?php
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
class SC2Replay {
	public static $gameSpeeds = array(0 => "Slower", 1=> "Slow", 2=> "Normal", 3=> "Fast", 4=> "Faster");
	public static $difficultyLevels = array(0 => "Very easy", 1=> "Easy", 2=> "Medium", 3=> "Hard", 4=> "Very Hard", 5 => "Insane");
	public static $gameSpeedCE = array(0 => 39, 1=> 44, 2=> 60, 3=> 64, 4=> 64); // estimates, weird values
	public static $colorIndices = array(1 => "Red", 2=> "Blue", 3=> "Teal", 4=> "Purple", 5=> "Yellow", 6 => "Orange", 7=> "Green", 8=> "Pink");
	private $players; //array, indices: color, team, sname, lname, race, startRace, handicap, ptype
	private $gameLength; // game length in seconds
	private $mapName;
	private $gameSpeed; // game speed, number from 0-4. see $gameSpeeds array above
	private $teamSize; // team size in the format xvx, eg. 1v1
	private $gamePublic;
	private $version;
	private $build;
	private $events; // contains an array of the events in replay.game.events file
	private $debug; // debug, currently true or false
	private $debugNewline; // contents are appended to the end of all debug messages
	private $messages; // contains an array of the chat log messages
	private $winnerKnown;
	private $unitsDict;
	
	function __construct() {
		$this->players = array();
		$this->gameLength = 0;
		$this->mapName = NULL;
		$this->gameSpeed = 0;
		$this->teamSize = NULL;
		$this->debug = false;
		$this->debugNewline = "<br />\n";
		$this->winnerKnown = false;
    $this->unitsDict = array();
	}
	// parameter needs to be an instance of MPQFile
	function parseReplay($mpqfile) {
		if (!($mpqfile instanceof MPQFile) || !$mpqfile->isParsed()) return false;
		// include utility class if it is available and not loaded already
		if (!class_exists('SC2ReplayUtils') && (file_exists('sc2replayutils.php'))) {
			include 'sc2replayutils.php';
		}

		$this->gameLength = $mpqfile->getGameLength();
		$this->version = $mpqfile->getVersion();
		$this->build = $mpqfile->getBuild();
		
		$file = $mpqfile->readFile("replay.initData");
		$start = microtime_float();
		if ($file !== false) {
			$this->parseInitDataFile($file);
		}
		else if ($this->debug) $this->debug("Error reading the replay.initData file");
		if ($this->debug) $this->debug(sprintf("Parsed replay.initData file in %d ms.",(microtime_float() - $start)*1000));
		
		// then parse replay.details file
		$file = $mpqfile->readFile("replay.details");
		$start = microtime_float();
		if ($file !== false) {
			$this->parseDetailsFile($file);
		}
		else if ($this->debug) $this->debug("Error reading the replay.details file");
		if ($this->debug) $this->debug(sprintf("Parsed replay.details file in %d ms.",(microtime_float() - $start)*1000));

		$file = $mpqfile->readFile("replay.attributes.events");
		$start = microtime_float();		
		if ($file !== false) {
			$this->parseAttributesFile($file);
		}
		else if ($this->debug) $this->debug("Error reading the replay.attributes.events file");
		if ($this->debug) $this->debug(sprintf("Parsed replay.attributes.events file in %d ms.",(microtime_float() - $start)*1000));
		
		$num = 0;
		$file = $mpqfile->readFile("replay.game.events");
		$start = microtime_float();	
		if ($file !== false) $num = $this->parseGameEventsFile($file);
		else if ($this->debug) $this->debug("Error reading the replay.game.events file");
		if ($this->debug) $this->debug(sprintf("Parsed replay.game.events file in %d ms, found $num events.",(microtime_float() - $start)*1000));
		
		$file = $mpqfile->readFile("replay.message.events");
		$start = microtime_float();	
		if ($file !== false) $this->parseChatLog($file);
		else if ($this->debug) $this->debug("Error reading the replay.message.events file");
		if ($this->debug) $this->debug(sprintf("Parsed replay.message.events file in %d ms.",(microtime_float() - $start)*1000));		
	}
	private function debug($message) { echo $message.($this->debugNewline); }
	function setDebugNewline($str) { $this->debugNewline = $str; }
	function setDebug($num) { $this->debug = $num; }
	function isWinnerKnown() { return $this->winnerKnown; }
	function getPlayers() { return $this->players; }
	function getMapName() { return $this->mapName; }
	function getGameSpeed() { return $this->gameSpeed; }
	function getGameSpeedText() { return self::$gameSpeeds[$this->gameSpeed]; }
	function getTeamSize() { return $this->teamSize; }
	function getVersion() { return $this->version; }
	function getBuild() { return $this->build; }
	function getMessages() { return $this->messages; }
	// getFormattedGameLength returns the time in h hrs, m mins, s secs 
	function getFormattedGameLength() {
		return $this->getFormattedSecs($this->gameLength);
	}
	function getFormattedSecs($secs) {
		$o = "";
		$hrs = floor($secs / 3600);
		$mins = floor($secs / 60) % 60;
		$secs = $secs % 60;
		if ($hrs > 0) $o = "$hrs hrs, ";
		if ($mins > 0) $o .= "$mins mins, ";
		$o .= "$secs secs";
		return $o;
	}
	function getUnits() { return $this->unitsDict; }
	function getEvents() { return $this->events; }
	function getGameLength() { return $this->gameLength; }
	// parse replay.initData file for player names
	function parseInitDataFile($string) {
		$numByte = 0;
		$numPlayers = $this->readByte($string,$numByte);
		$nullName = false;
		for ($i = 1;$i <= $numPlayers;$i++) {
			$nickLen = $this->readByte($string,$numByte);
			if ($nickLen > 0) {
				$name = $this->readBytes($string,$numByte,$nickLen);
				$this->players[$i] = array( "name" => $name, "isObs" => TRUE, "id" => $i, "isComp" => FALSE, "team" => 0 ); // set initial values
				$numByte += 5;
			} 
			else {
				if (!$nullName) {
					$nullName = true;
					$numByte--;
				}
				$numByte += 5;
			}
		}
	}
	// parse replay.details file and add parsed stuff to the object
	// $string contains the contents of the file
	function parseDetailsFile($string) {
		if ($this->debug) $this->debug("Parsing replay.details file...");
		$numByte = 0;
		$numByte += 6; 
		$numPlayers = $this->readByte($string,$numByte) / 2;
		for ($i = 1; $i <= $numPlayers;$i++) {
			$p = $this->parsePlayerStruct($string,$numByte,$i);
		}
		$mapnameLen = $this->readByte($string,$numByte) / 2;
		$this->mapName = $this->readBytes($string,$numByte,$mapnameLen);

		$numByte += 2; // 04 02
		$u1Len = $this->readByte($string,$numByte) / 2;
		if ($u1Len > 0) $this->readByte($string,$numByte,$u1Len);

		
		$numByte += 5; // 06 05 02 00 02
		$minimapnameLen = $this->readByte($string,$numByte) / 2;
		$minimapName = $this->readBytes($string,$numByte,$minimapnameLen);
	}
	
	// parse a player struct in the replay.details file
	private function parsePlayerStruct($string,&$numByte,$id) {
		$numByte += 4;
		$sNameLen = $this->readByte($string,$numByte) / 2;
		if ($sNameLen > 0) $sName = $this->readBytes($string,$numByte,$sNameLen);
		else $sName = NULL;

		$numByte += 5; // 02 05 08 00 09
		$numByte += 4; // 00/04 02 07 00
		$numByte += 3; // 00 00 00 // 00 53 32 (S2)
		$hadKey = true;
		$keys = array();
		while ($hadKey) {
			$hadKey = false;
			$key = unpack("c2",$this->readBytes($string,$numByte,2));
			if ($key[2] == 9) { 
				$hadKey = true; 
				$keys[$key[1]] = $this->parseKeyVal($string,$numByte); 
			}
			else if ($key[1] == 4 && $key[2] == 2) { break; }
		}
		if ($this->debug) {
			foreach ($keys as $k => $v)
				$this->debug("Got pre-race($sName) key: $k, value: $v");
		}

		$raceLen = $this->readByte($string,$numByte) / 2;
		if ($raceLen > 0) $race = $this->readBytes($string,$numByte,$raceLen);
		else $race = NULL;
		$numByte += 3; // 06 05 08
		$hadKey = true;
		while ($hadKey) {
			$keyVal = "";
			$hadKey = false;
			$key = unpack("c2",$this->readBytes($string,$numByte,2));
			if ($key[2] == 9) { 
				$hadKey = true;
				$keyVal = $this->parseKeyVal($string,$numByte);
				if ($key[1] == 2) { $cR = $keyVal / 2; } // red color
				if ($key[1] == 4) { $cG = $keyVal / 2; } // green color
				if ($key[1] == 6) { $cB = $keyVal / 2; } // blue color
				if ($key[1] == 16) { $party = $keyVal / 2; } // party number?
				if ($this->debug) $this->debug(sprintf("%s Key: %d, value: %d",$sName,$key[1], $keyVal));
			}
			else if ($key[1] == 5 && $key[2] == 18) {$numByte -= 2; break; } // next player
			else if ($key[1] == 2 && $key[2] == 2) { break; } // end of player section
		}
		if (($sName === NULL)) {
			if ($this->debug) $this->debug("Got null player");
			return;
		}

		// $this->players[$id]["sName"] = $sName; // deprecated array value before there was only a short name
		$this->players[$id]["name"] = $sName; // player name
		$this->players[$id]["race"] = $race; // player race
		$this->players[$id]["party"] = $party;
		$this->players[$id]["team"] = 0;
		$this->players[$id]["color"] = sprintf("%02X%02X%02X",$cR,$cG,$cB);
		$this->players[$id]["apmtotal"] = 0;
		$this->players[$id]["apm"] = array();
		$this->players[$id]["firstevents"] = array();
		$this->players[$id]["numevents"] = array();
		$this->players[$id]["ptype"] = "";
		$this->players[$id]["handicap"] = 0;
		$this->players[$id]["isComp"] = false;
		$this->players[$id]["uid"] = $keys[8];
		$this->players[$id]["isObs"] = false; // all players present in replay.details file are not observers
		if ($this->debug) $this->debug(sprintf("Got player: %s, Race: %s, Party: %s, Color: %s",$sName, $race, $party, $this->players[$id]["color"]));
		return;
	}
	
	// parameter is the contents of the replay.attributes.events file
	private function parseAttributesFile($string) {
		if ($this->debug) $this->debug("Parsing replay.attributes.events file");
		$numByte = 4; // skip the 4-byte header
		$numAttribs = $this->readUInt32($string,$numByte);
		$attribArray = array();
		for ($i = 0;$i < $numAttribs;$i++) {
			$attribHeader = $this->readUInt32($string,$numByte);
			$attributeId = $this->readUInt32($string,$numByte);
			$playerId = $this->readByte($string,$numByte);
			$attribVal = "";
			// values are stored in reverse in the file, eg Terr becomes rreT. The following loop flips the value and removes excess null bytes
			for ($a = 0;$a < 4;$a++) {
				$b = ord(substr($string,$numByte + 3 - $a));
				if ($b != 0) $attribVal .= chr($b);
			}
			$numByte += 4;
			$attribArray[$playerId][$attributeId] = $attribVal;
			if ($this->debug) $this->debug(sprintf("Got attrib \"%04X\" for player %d (%s), attribVal = \"%s\"",
							$attributeId,$playerId,(($playerId == 0x10)?"ALL":$this->players[$playerId]["name"]),$attribVal));
			switch ($attributeId) {
				// FFA
/*				case 0x07D6:
					break;
				// 4v4
				case 0x07D5:
					break;
				// 3v3
				case 0x07D4: 
					break;
				// 2v2
				case 0x07D3: // my hypothesis is that every 0x07D<X> value is what the teams would
							 // be if the game type/team size was changed.
							 // for example if you changed the dropdownbox from 3v3 to FFA, the values
							 // under 0x07D6 would be the initial values that you could edit.
							 // why this is included in replay files is weird to say the least
				
					break;
				// 1v1
				case 0x07D2:
					break;
*/
				case 0x0BBB: // handicap
					$this->players[$playerId]["handicap"] = $attribVal;
					break;
				case 0x0BBC: // difficulty level (of computer player, Medi for humans)
					switch ($attribVal) {
						case "Insa":
							$tmp = 5;
							break;
						case "VyHd":
							$tmp = 4;
							break;
						case "Hard":
							$tmp = 3;
							break;
						case "Medi":
							$tmp = 2;
							break;
						case "Easy":
							$tmp = 1;
							break;
						case "VyEy":
							$tmp = 0;
							break;
						default:
							$tmp = 2;
					}
					$this->players[$playerId]["difficulty"] = $tmp;
					break;
				case 0x0BB8: // game speed
					switch ($attribVal) {
						case "Fasr":
							$tmp = 4;
							break;
						case "Fast":
							$tmp = 3;
							break;
						case "Norm":
							$tmp = 2;
							break;
						case "Slow":
							$tmp = 1;
							break;
						case "Slor":
							$tmp = 0;
							break;
						default:
							$tmp = 2;
					}
					$this->gameSpeed = $tmp;
					break;
				case 0x01F4: // player type, Humn or Comp
					$this->players[$playerId]["ptype"] = $attribVal; // deprecated
					$this->players[$playerId]["isComp"] = ($attribVal == 'Comp')?true:false;
					break;
				case 0x0BB9: // initial race, Prot Terr Zerg or RAND
					$this->players[$playerId]["srace"] = $attribVal;
					break;
				case 0x07D1: // teamsizes
					$this->teamSize = $attribVal;
					break;
				case 0x0BC1: // game type, private(Priv)/open(Amm)?
					$this->gamePublic = (($attribVal == "Priv")?false:true);
					break;
				case 0x0BBA: // color index
					$this->players[$playerId]["colorIndex"] = intval(substr($attribVal,2));
					$this->players[$playerId]["sColor"] = self::$colorIndices[intval(substr($attribVal,2))];
					break;
				default:
			}
		}
		switch ($attribArray[0x10][0x07D1]) {
			case "1v1":
				$attrib = 0x07D2;
				break;
			case "2v2":
				$attrib = 0x07D3;
				break;			
			case "3v3":
				$attrib = 0x07D4;
				break;
			case "4v4":
				$attrib = 0x07D5;
				break;
			case "FFA":
				$attrib = 0x07D6;
				break;
			default:
				if ($this->debug) 
					$this->debug(sprintf("Unknown game mode in replay.attributes.events: %s",$attribArray[0x10][0x07D1]));
				return;
		}
		foreach ($attribArray as $playerId => $values) {
			if ($playerId == 0x10) continue;
			$this->players[$playerId]["team"] = intval(substr($values[$attrib],1));
		}
	}
	
	// parse a key/value -pair struct in the replay.details file
	private function parseKeyVal($string, &$numByte) {
		$one = unpack("C",substr($string,$numByte,1)); 
		$one = $one[1];
		$retVal = $one & 0x7F;
		$shift = 1;
		$numByte++;
		while (($one & 0x80) > 0) {
			$one = unpack("C",substr($string,$numByte,1)); 
			$one = $one[1];
			$retVal = (($one & 0x7F) << $shift*7) | $retVal;
			$shift++;
			$numByte++;
		}
		return $retVal;
	}
	
	private function readByte($string, &$numByte) {
		$tmp = unpack("C",substr($string,$numByte,1));
		$numByte++;
		return $tmp[1];
	}
	private function readBytes($string, &$numByte, $length) {
		$tmp = substr($string,$numByte,$length);
		$numByte += $length;
		return $tmp;
	}
	private function readUInt16($string, &$numByte) {
		$tmp = unpack("v",substr($string,$numByte,2));
		$numByte += 2;
		return $tmp[1];
	}
	private function readUInt32($string, &$numByte) {
		$tmp = unpack("V",substr($string,$numByte,4));
		$numByte += 4;
		return $tmp[1];
	}
	private function readUnitTypeID($string,&$numByte) {
		return (($this->readByte($string,$numByte) << 16) | ($this->readByte($string,$numByte) << 8) | ($this->readByte($string,$numByte)));
	}
	private function readUnitAbility($string) {
		$bytes = unpack("C3",substr($string,4,3));
		return (($bytes[1] << 16) | ($bytes[2] << 8) | ($bytes[3]));
	}
	
	// gets players who actually played in the game, meaning excludes observers and party members.
	public function getActualPlayers() {
		$tmp = array();
		foreach ($this->players as $val)
			if ($val['team'] > 0)
				$tmp[] = $val;
		return $tmp;
	}
	// parameter is the contents of the replay.message.events -file
	private function parseChatLog($string) {
		$numByte = 0;
		$len = strlen($string);
		$messages = array();
		$totTime = 0;
		while ($numByte < $len) {
			$timestamp = $this->parseTimeStamp($string,$numByte);
			$playerId = $this->readByte($string,$numByte);
			$opcode = $this->readByte($string,$numByte);
			$totTime += $timestamp;
			if ($opcode == 0x80) // header weird thingy?
				$numByte += 4;
//			else if ($opcode == 0x00 || $opcode == 0x02 || $opcode == 0x0a) { // message
			else if (($opcode & 0x80) == 0) { // message
				$messageTarget = $opcode & 3;
				$messageLength = $this->readByte($string,$numByte);
				if (($opcode & 8) == 8) $messageLength += 64;
				if (($opcode & 16) == 16) $messageLength += 128;
				$message = $this->readBytes($string,$numByte,$messageLength);
				$messages[] = array('id' => $playerId, 'name' => $this->players[$playerId]['name'], 'target' => $messageTarget,
									'time' => floor($totTime / 16), 'message' => $message);
			}
			else if ($opcode == 0x83) { // ping on map? 8 bytes?
				$numByte += 8;
			}

		}
		$this->messages = $messages;
	}
	// parameter is the contents of the replay.game.events -file
	private function parseGameEventsFile($string) {
		$numByte = 0;
		$len = strlen($string);
		$playerLeft = array();
		$events = array();

		$time = 0;
		$numEvents = 0;
		while ($numByte < $len) {
			$timeStamp = $this->parseTimeStamp($string,$numByte);
			$nextByte = $this->readByte($string,$numByte);
			$eventType = $nextByte >> 5; // 3 lowest bits
			$globalEventFlag = $nextByte & 16; // 4th bit
			$playerId = $nextByte & 15; // bits 5-8
			if (isset($this->players[$playerId]))
				$playerName = $this->players[$playerId]['name'];
			else
				$playerName = "";
			$eventCode = $this->readByte($string,$numByte);
			$time += $timeStamp;
			$numEvents++;
			// weird timestamp values mean that there's likely a problem with the alignment of the parse(too few/too many bytes read for an eventcode)
			if ($this->debug >= 2) {
//				if ($len - $numByte > 24) {
//				$bytes = unpack("C24",substr($string,$numByte,24));
//				$dataBytes = "";
//				for ($i = 1;$i <= 24;$i++) $dataBytes .= sprintf("%02X",$bytes[$i]);
				$this->debug(sprintf("DEBUG L2: Timestamp: %d, Frames: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
					floor($time / 16),$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
//				}
			}
			switch ($eventType) {
				case 0x00: // initialization
					switch ($eventCode) {
						case 0x1B: // Player enters game
						case 0x0B:
							break;
						case 0x05: // game starts
							break;
						default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
					}
					break;
				case 0x01: // action
					switch ($eventCode) {
						case 0x09: // player quits the game
							if ($this->players[$playerId]['team'] > 0) // don't log observers/party members etc
								$playerLeft[] = $playerId;
							break;
						case 0x0B: // player uses an ability
							// at least 32 bytes
							$data = $this->readBytes($string,$numByte,32);
							$reqTarget = unpack("C",substr($data,7,1));
							$reqTarget = $reqTarget[1];
							$ability = $this->readUnitAbility($data);
							if ($ability != 0xFFFF0F) {
								$events[] = array('p' => $playerId, 't' => $time, 'a' => $ability);
								$this->events = $events;
							}
							// at least with attack, move, right-click, if the byte after unit ability bytes is 
							// 0x30 or 0x50, the struct takes 1 extra byte. With build orders the struct seems to be 32 bytes
							// and this byte is 0x00.
							// might also be in some other way variable-length.
							if ($reqTarget == 0x30) 
								$data .= $this->readByte($string,$numByte); 
							if ($reqTarget == 0x50)
								$data .= $this->readByte($string,$numByte);
							// update apm array
							$this->addPlayerAction($playerId, floor($time / 16));

							$this->addPlayerAbility($playerId, ceil($time /16), $ability);
							break;
						case 0x2F: // player sends resources
							$numByte += 17; // data is 17 bytes long
							break;
						case 0x0C: // automatic update of hotkey?
						case 0x1C:
						case 0x2C:
						case 0x3C: // 01 01 01 01 11 01 03 02 02 38 00 01 02 3c 00 01 00
						case 0x4C: // 01 02 02 01 0d 00 02 01 01 a8 00 00 01
						case 0x5C: // 01 01 01 01 16 03 01 01 03 18 00 01 00
						case 0x6C: // 01 04 08 01 03 00 02 01 01 34 c0 00 01
						case 0x7C: // 01 05 10 01 01 10 02 01 01 1a a0 00 01
						case 0x8C:
						case 0x9C:
						case 0xAC: // player changes selection
							$selFlags = $this->readByte($string,$numByte);
							$dsuCount = $this->readByte($string,$numByte);
              if($this->debug){
                $this->debug("Selection Change");
                $this->debug(sprintf("Player %s", $playerId));
                $this->debug(sprintf("Time %d", $time));
                $this->debug(sprintf("Deselected Count: %d", $dsuCount));
              }
							$dsuExtraBits = $dsuCount % 8;
              $uType = array();
							if ($dsuCount > 0)
								$dsuMap = $this->readBytes($string,$numByte,floor($dsuCount / 8));
							if ($dsuExtraBits != 0) { // not byte-aligned
								$dsuMapLastByte = $this->readByte($string,$numByte);

                $nByte = $this->readByte($string,$numByte);

                //Recalculating these is excessive.             //ex: For extra = 2
                $offsetTailMask = (0xFF >> (8-$dsuExtraBits));  //ex: 00000011 
                $offsetHeadMask = (~$offsetTailMask) & 0xFF;    //ex: 11111100
                $offsetWTailMask = 0xFF >> $dsuExtraBits;       //ex: 00111111
                $offsetWHeadMask = (~$offsetWTailMask) & 0xFF;  //ex: 11000000

                $uTypesCount = ($dsuMapLastByte & $offsetHeadMask) |
                               ($nByte          & $offsetTailMask);

                if($this->debug){
                  $this->debug(sprintf("Number of New Unit Types %d", $uTypesCount));
                }

								for ($i = 1;$i <= $uTypesCount;$i++) {
                  $nBytes = unpack("C3",$this->readBytes($string,$numByte,3));
                  $byte1 = ( $nByte     & $offsetHeadMask) |
                           (($nBytes[1] & $offsetWHeadMask) >> (8 - $dsuExtraBits));
                  $byte2 = (($nBytes[1] & $offsetWTailMask) << $dsuExtraBits) |
                           ( $nBytes[2] & $offsetTailMask);
                  $byte3 = (($nBytes[2] & $offsetHeadMask) << $dsuExtraBits) |
                           ( $nBytes[3] & $offsetTailMask);

                  //Byte3 is almost invariably 0x01

                  $uType[$i]['id'] = (($byte1 << 16) | 
                                     ($byte2 << 8)  | 
                                      $byte3) & 0xFFFFFF;

									$nByte = $this->readByte($string,$numByte);
                  $uType[$i]['count'] = ($nBytes[3] & $offsetHeadMask) |
                                        ($nByte     & $offsetTailMask);

                  if($this->debug){
                    $this->debug(sprintf("  %d x 0x%06X", $uType[$i]['count'], $uType[$i]['id']));
                  }

								}
								$lByte = $this->readByte($string,$numByte);
                
                $totalUnits = ($nByte & $offsetHeadMask) |
                  ($lByte & $offsetTailMask);

                if($this->debug){ 
                  $this->debug(sprintf("TOTAL: %d", $totalUnits));
                }


                //Populate the unitsDict
                foreach($uType as $unitType){
                  for($i = 1; $i <= $unitType['count']; $i++){
                    $nBytes = unpack("C4", $this->readBytes($string, $numByte,4));
                    $byte1 = ($lByte      & $offsetHeadMask) |
                             (($nBytes[1] & $offsetWHeadMask) >> (8 - $dsuExtraBits));
                    $byte2 = (($nBytes[1] & $offsetWTailMask) << $dsuExtraBits) |
                             (($nBytes[2] & $offsetWHeadMask) >> (8 - $dsuExtraBits));
                    $byte3 = (($nBytes[2] & $offsetWTailMask) << $dsuExtraBits) |
                             (($nBytes[3] & $offsetWHeadMask) >> (8 - $dsuExtraBits));
                    $byte4 = (($nBytes[3] & $offsetWTailMask) << $dsuExtraBits) |
                             ( $nBytes[4] & $offsetTailMask);

                    $uid = ($byte1 << 8) | $byte2;
                    //Bytes 3 + 4 contain Flag Info
                    
                    $this->addSelectedUnit($uid, $unitType['id'], $playerId, floor($time / 16));

                    if($this->debug){
                      $this->debug(sprintf("  0x%06X -> 0x%02X", $unitType['id'], $uid));
                    }

                    $lByte = $nBytes[4]; //For looping.
                  }
                }

							} else { // byte-aligned
								$uTypesCount = $this->readByte($string,$numByte);
                if($this->debug){
                  $this->debug(sprintf("Number of New Unit Types %d", $uTypesCount));
                }
								for ($i = 1;$i <= $uTypesCount;$i++) {
									$uType[$i]['id'] = $this->readUnitTypeID($string,$numByte);
									$uType[$i]['count'] = $this->readByte($string,$numByte);
                  if($this->debug){
                    $this->debug(sprintf("  %d x 0x%06X", $uType[$i]['count'], $uType[$i]['id']));
                  }
								}
								$totalUnits = $this->readByte($string,$numByte);
                if($this->debug){
                  $this->debug(sprintf("TOTAL: %d", $totalUnits));
                }

                //Populate the Units Dict
                foreach($uType as $unitType){
                  for($i = 1; $i <= $unitType['count']; $i++){
                    $nBytes = unpack("C4", $this->readBytes($string, $numByte, 4));
                    $uid = ($nBytes[1] << 8) | $nBytes[2];

                    $this->addSelectedUnit($uid, $unitType['id'], $playerId, floor($time / 16));
                    if($this->debug){
                      $this->debug(sprintf("  0x%06X -> 0x%02X", $unitType['id'], $uid));
                    }
                  }
                }
							}
							
							//update apm fields
							if ($eventCode == 0xAC) {
								$this->addPlayerAction($playerId, floor($time / 16));
							}
							break;
						case 0x0D: // manually uses hotkey
						case 0x1D:
						case 0x2D:
						case 0x3D:
						case 0x4D:
						case 0x5D:
						case 0x6D:
						case 0x7D:
						case 0x8D:
						case 0x9D:
							$byte1 = $this->readByte($string,$numByte);
							if ($numByte < $len) {
								$byte2 = $this->readByte($string,$numByte);
								$numByte--;
							}
							$extraBytes = floor($byte1 / 8);
							$numByte += $extraBytes;
							if ($byte1 & 4 && ($this->debug))
								$this->debug("Found candidate hotkey event!");
							if (($byte1 & 4) && (($byte2 & 6) == 6))
								$numByte += 2;
							else if ($byte1 & 4)
								$numByte += 1;
							// update apm
							$this->addPlayerAction($playerId, floor($time / 16));
							break;
						case 0x1F: // send resources
						case 0x2F: 
						case 0x3F: 
						case 0x4F:
						case 0x5F:
						case 0x6F:
						case 0x7F:
						case 0x8F:
							$numByte++; // 0x84
							$sender = $playerId;
							$receiver = ($eventCode & 0xF0) >> 4;
							// sent minerals
							$bytes = $this->readBytes($string,$numByte,4);
							$mBytes = unpack("C4",$bytes);
							$mineralValue = ((($mBytes[1] << 20) | ($mBytes[2] << 12) | ($mBytes[3] << 4)) >> 1) + ($mBytes[4] & 0x0F);
							// sent gas
							$bytes = $this->readBytes($string,$numByte,4);
							$mBytes = unpack("C4",$bytes);
							$gasValue = ((($mBytes[1] << 20) | ($mBytes[2] << 12) | ($mBytes[3] << 4)) >> 1) + ($mBytes[4] & 0x0F);
							
							// last 8 bytes are unknown
							$numByte += 8;
							break;
						default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
					}				
					break;
				case 0x02: // weird
					switch($eventCode) {
						case 0x06:
							$numByte += 8; // 00 00 00 04 00 00 00 04
							break;
						case 0x07:
							$numByte += 4;
							break;
						default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
					}
					break;
				case 0x03: // replay
					switch ($eventCode) {
						case 0x87:
							$numByte += 8;
							break;
						case 0x01: // camera movement
						case 0x11:						
						case 0x21:
						case 0x31:
						case 0x41:
						case 0x51:
						case 0x61:
						case 0x71:
						case 0x81:
						case 0x91:
						case 0xA1:
						case 0xB1:
						case 0xC1:
						case 0xD1:
						case 0xE1:
						case 0xF1:
							// assume AB CD EF GH IJ, where AB is event code (0x01-0xF1)
							// and CD EF GH IJ are the next four bytes, each letter corresponds to 4 bits
							// x-coordinate would be ACDF and y-coordinate EGHJ
							// where A and E are most significant
							// I is some kind of flag, value higher than 9 indicates at least 2 extra bytes
							// similarly, in the following 2 extra bytes, VW XY,
							// X is a flag value, if  > 9 then 2 extra bytes are read
							// extra bytes occur when camera is zoomed in or out. No more than 4 extra bytes
							// total have been observed
							// initial camera event has the I value set as D (13)
							$numByte += 3;
							$nByte = $this->readByte($string,$numByte);
							if (($nByte & 0x10) > 0) {
								$numByte ++;
								$nByte = $this->readByte($string,$numByte);
								if (($nByte & 0xF0) >= 0x20) {
									$numByte ++;
									$nByte = $this->readByte($string,$numByte);
									if (($nByte & 0x40) > 0)
										$numByte += 2;
								}
							}
							break;
						default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
					}
					break;
				case 0x04: // inaction
					switch($eventCode) {
						case 0x00: //automatic synchronization
							$numByte += 4;
							break;
						case 0x16:
							$numByte += 24;
							break;
						case 0x18:
							$numByte += 4;
							break;
						case 0x1C:
						case 0x2C: // no data
							break;
						default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
					}
					break;
				case 0x05: // system
					switch($eventCode) {
						case 0x89: //automatic synchronization?
							$numByte += 4;
							break;
						default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
					}
					break;
				default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
			}
		}
		// update winners based on $playerLeft -array
		$numLeft = count($playerLeft);
		$numActual = count($this->getActualPlayers());
		$lastLeaver = -1;
		foreach ($playerLeft as $val) {
			// mark the previous leaver as a loser
			if ($lastLeaver != -1) 
				$this->players[$val]['won'] = -1;
			$lastLeaver = $val;
		}
		// if the number of players who left is $numActual - 1, then everyone else except the recorder left and he is the winner
		// if the number of players who left is $numActual - 2, then whoever left after the recorder is the winner. can be determined if the recorder is known.
		// otherwise the winner cannot be determined, since any one of the players who left after the recorder could be the winner
		if ($numLeft == ($numActual - 1)) {
			if ($this->debug) $this->debug("Found winner");
			$this->players[$lastLeaver]['won'] = -1;
			$this->winnerKnown = true;
			foreach ($this->getActualPlayers() as $value) {
				if (isset($value['won']) && $value['won'] == -1) continue;
				$winteam = $value['team'];
			}
		}
		else  if ($numLeft == ($numActual)) {
			if ($this->debug) $this->debug("Found winner");
			$this->players[$lastLeaver]['won'] = 1;
			$this->winnerKnown = true;
			$winteam = $this->players[$lastLeaver]['team'];
		}
		else {
			if ($this->debug) $this->debug("Unable to parse winner");
			return $numEvents;
		}

		foreach ($this->getActualPlayers() as $val) {
			if ($val['team'] == $winteam) $this->players[$val['id']]['won'] = 1;
			else $this->players[$val['id']]['won'] = 0;
		}
		return $numEvents;
	}
  // inserts unit into the unit dictionary and updates $time seen
  private function addSelectedUnit($uId, $uType, $playerId, $time){
    if(!isset($this->unitsDict[$playerId][$uId])){
      //First Time Seen
      $this->unitsDict[$playerId][$uId]['type'] = $uType;
      $this->unitsDict[$playerId][$uId]['firstSeen'] = $time;
    }
    $this->unitsDict[$playerId][$uId]['lastSeen'] = $time;
  }

	// updates apm array and total action count for $playerId, $time is in seconds
	private function addPlayerAction($playerId, $time) {
		if (!isset($this->players[$playerId]) || $this->players[$playerId]['isObs'])
			return;
		$this->players[$playerId]['apmtotal']++;
		if (isset($this->players[$playerId]['apm'][$time]))
			$this->players[$playerId]['apm'][$time]++;
		else
			$this->players[$playerId]['apm'][$time] = 1;
	}
	// updates numevents and firstevents arrays with the ability code
	private function addPlayerAbility($playerId, $time, $abilitycode) {
		if (!isset($this->players[$playerId]))
			return;
		$this->players[$playerId]['apmtotal']++;
		if (isset($this->players[$playerId]['numevents'][$abilitycode]))
			$this->players[$playerId]['numevents'][$abilitycode]++;
		else {
			$this->players[$playerId]['numevents'][$abilitycode] = 1;
			$this->players[$playerId]['firstevents'][$abilitycode] = $time;
		}
	}
	private function parseTimeStamp($string, &$numByte) {
		$one = $this->readByte($string,$numByte);
		if (($one & 3) > 0) { // check if value is two bytes or more
			$two = $this->readByte($string,$numByte);
			$two = ((($one >> 2 ) << 8) | $two);
			if (($one & 3) >= 2) {
				$tmp = $this->readByte($string,$numByte);			
				$two = (($two << 8) | $tmp);
				if (($one & 3) == 3) {
					$tmp = $this->readByte($string,$numByte);			
					$two = (($two  << 8) | $tmp);
				}
			}
			return $two;
		}
		return ($one >> 2);
	}

	// gets the literal string from the sc2_abilitycodes array based on the ability code
	// returns false if the variable doesn't exist or the file cannot be included
	function getAbilityString($num) {
		if (class_exists('SC2ReplayUtils')) {
			if ($this->debug) $debug = sprintf(" (%06X)",$num);
			else $debug = "";
			if (isset(SC2ReplayUtils::$ABILITYCODES[$num]))
				return SC2ReplayUtils::$ABILITYCODES[$num]['desc'].$debug;
			else if ($this->debug)
				$this->debug(sprintf("Unknown ability code: %06X",$num));
		}
		else if ($this->debug)
			$this->debug("Class SC2ReplayUtils not found!");
		return false;
	}
	function getAbilityArray($num) {
		if (class_exists('SC2ReplayUtils')) {
			if (isset(SC2ReplayUtils::$ABILITYCODES[$num]))
				return SC2ReplayUtils::$ABILITYCODES[$num];
			else if ($this->debug)
				$this->debug(sprintf("Unknown ability code: %06X",$num));
		}
		else if ($this->debug)
			$this->debug("Class SC2ReplayUtils not found!");
		return false;
	}
  function getUnitArray($num) {
    if(class_exists('SC2ReplayUtils')) {
      if (isset(SC2ReplayUtils::$UNITCODES[$num]))
        return SC2ReplayUtils::$UNITCODES[$num];
      else if ($this->debug)
        $this->debug(sprintf("Unknown unit code: %04X", $num));
    }
    else if($this->debug)
      $this->debug("Class SC2ReplayUtils not found!");
    return false;
  }
}


?>
