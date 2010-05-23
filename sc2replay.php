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
define("MPQ_ERR_LOWREPLAYVERSION", -1);

class SC2Replay {
	public static $gameSpeeds = array(0 => "Slower", 1=> "Slow", 2=> "Normal", 3=> "Fast", 4=> "Faster");
	public static $gameSpeedCE = array(0 => 39, 1=> 44, 2=> 60, 3=> 64, 4=> 64); // estimates, weird values
	public static $colorIndices = array(1 => "Red", 2=> "Blue", 3=> "Teal", 4=> "Purple", 5=> "Yellow", 6 => "Orange", 7=> "Green", 8=> "Pink");
	private $players; //array, indices: color, team, sname, lname, race, startRace, handicap, ptype
	private $gameLength;
	private $mapName;
	private $gameSpeed;
	private $teamSize;
	private $gamePublic;
	private $version;
	private $build;
	private $events;
	private $debug;
	private $debugNewline;
	
	
	function __construct() {
		$this->players = array();
		$this->gameLength = 0;
		$this->mapName = NULL;
		$this->gameSpeed = 0;
		$this->teamSize = NULL;
		$this->debug = false;
		$this->debugNewline = "<br />\n";
	}
	// parameter needs to be an instance of MPQFile
	function parseReplay($mpqfile) {
		if (!is_a($mpqfile, "MPQFile")) return false;
		$this->gameLength = $mpqfile->getGameLength();
		if ($mpqfile->getBuild() < 15097) {
			if ($this->debug) $this->debug("Too low replay version");
			return MPQ_ERR_LOWREPLAYVERSION; //demo format changed at patch 9, no support for older ones
		}
		$this->version = $mpqfile->getVersion();
		$this->build = $mpqfile->getBuild();
		// first parse replay.details file
		$file = $mpqfile->readFile("replay.details");
		if ($file !== false) {
			$this->parseDetailsFile($file);
		}
		else if ($this->debug) $this->debug("Error reading the replay.details file");
	
		$file = $mpqfile->readFile("replay.attributes.events");
		if ($file !== false) {
			$this->parseAttributesFile($file);
//			$fs = $mpqfile->getFileSize("replay.sync.events");
//			if ($fs !== false) $this->gameLength = $fs / self::$gameSpeedCE[$this->gameSpeed]; // sync event is 4 bytes, with a sync window of 1/8th to 1/16th of a second
		}
		else if ($this->debug) $this->debug("Error reading the replay.attributes.events file");
		
		$file = $mpqfile->readFIle("replay.game.events");
		if (file !== false) $this->parseGameEventsFile($file);
		else if ($this->debug) $this->debug("Error reading the replay.game.events file");
		
	}
	private function debug($message) { echo $message.($this->debugNewline); }
	function setDebugNewline($str) { $this->debugNewline = $str; }
	function setDebug($bool) { $this->debug = $bool; }
	function getPlayers() { return $this->players; }
	function getMapName() { return $this->mapName; }
	function getGameSpeed() { return $this->gameSpeed; }
	function getGameSpeedText() { return self::$gameSpeeds[$this->gameSpeed]; }
	function getTeamSize() { return $this->teamSize; }
	function getVersion() { return $this->version; }
	function getBuild() { return $this->build; }
	// getFormattedGameLength returns the time in h hrs, m mins, s secs 
	function getFormattedGameLength() {
		$hrs = floor($this->gameLength / 3600);
		$mins = floor($this->gameLength / 60);
		$secs = $this->gameLength % 60;
		if ($hrs > 0) $o = "$hrs hrs, ";
		if ($mins > 0) $o .= "$mins mins, ";
		$o .= "$secs secs";
		return $o;
	}
	function getEvents() { return $this->events; }
	function getGameLength() { return $this->gameLength; }
	// parse replay.details file and add parsed stuff to the object
	// $string contains the contents of the file
	function parseDetailsFile($string) {
		if ($this->debug) $this->debug("Parsing replay.details file...");
		$numByte = 0;
		$numByte += 6; 
		$numPlayers = $this->readByte($string,$numByte) / 2;
		for ($i = 0; $i < $numPlayers;$i++) {
			$p = $this->parsePlayerStruct($string,$numByte);
			if ($p !== NULL) {
				$p['id'] = $i;
				$this->players[$i] = $p;
			}
		}
		$mapnameLen = $this->readByte($string,$numByte) / 2;
		$this->mapName = $this->readBytes($string,$numByte,$mapnameLen);

		$numByte += 2; // 04 02
		$u1Len = $this->readByte($string,$numByte) / 2;
		if ($u1Len > 0) $this->readByte($string,$numByte,$u1Len); //$numByte += $u1 = fread($fp,$u1Len);

		
		$numByte += 5; // 06 05 02 00 02
		$minimapnameLen = $this->readByte($string,$numByte) / 2;
		$minimapName = $this->readBytes($string,$numByte,$minimapnameLen);
	}
	
	// parse a player struct in the replay.details file
	private function parsePlayerStruct($string,&$numByte) {
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
			else if ($key[1] == 6 && $key[2] == 2) { break; }
		}
		if ($this->debug) {
			foreach ($keys as $k => $v)
				$this->debug("Got pre-longname($sName) key: $k, value: $v");
		}
		$lNameLen = $this->readByte($string,$numByte) / 2;
		if ($lNameLen > 0) $lName = $this->readBytes($string,$numByte,$lNameLen);
		else $lName = NULL;
		$numByte += 2; // 04 02
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
				if ($key[1] == 2) { $cR = $keyVal; } // red color
				if ($key[1] == 4) { $cG = $keyVal; } // green color
				if ($key[1] == 6) { $cB = $keyVal; } // blue color
				if ($key[1] == 16) { $party = $keyVal / 2; } // party number?
				if ($this->debug) $this->debug(sprintf("%s Key: %d, value: %d",$sName,$key[1], $keyVal));
			}
			else if ($key[1] == 5 && $key[2] == 18) {$numByte -= 2; break; } // next player
			else if ($key[1] == 2 && $key[2] == 2) { break; } // end of player section
		}
		if (($sName === NULL) && ($lName === NULL)) {
			if ($this->debug) $this->debug("Got null player");
			return NULL;
		}
		$p = array();
		$p["sName"] = $sName;
		$p["lName"] = $lName;
		$p["race"] = $race;
		$p["party"] = $party;
		$p["color"] = sprintf("%02X%02X%02X",$cR,$cG,$cB);
		if ($this->debug) $this->debug(sprintf("Got player: %s (%s), Race: %s, Party: %s, Color: %s",$sName, $lName, $race, $party, $p["color"]));
		return $p;
	}
	
	// parameter is the contents of the replay.attributes.events file
	private function parseAttributesFile($string) {
		if ($this->debug) $this->debug("Parsing replay.attributes.events file");
		$numByte = 4; // skip the 4-byte header
		$numAttribs = $this->readUInt32($string,$numByte);
		for ($i = 0;$i < $numAttribs;$i++) {
			$attribHeader = $this->readUInt16($string,$numByte);
			$numByte += 2; //skip the 00 00 bytes
			$attributeId = $this->readUInt16($string,$numByte);
			$numByte += 2; //skip another 00 00 bytes
			$playerId = $this->readByte($string,$numByte);
			$attribVal = "";
			for ($a = 0;$a < 4;$a++) {
				$b = ord(substr($string,$numByte + 3 - $a));
				if ($b != 0) $attribVal .= chr($b);
			}
			$numByte += 4;
			if ($this->debug) $this->debug(sprintf("Got attrib \"%04X\" for player %d (%s), attribVal = \"%s\"",
							$attributeId,$playerId,(($playerId == 0x10)?"ALL":$this->players[$playerId]["sName"]),$attribVal));
			switch ($attributeId) {
				case 0x07D3: // team, VERY uncertain because of gazillion other 0x07DX values
					$this->players[$playerId]["team"] = intval(substr($attribVal,1));
					break;
				case 0x0BBB: // handicap
					$this->players[$playerId]["handicap"] = $attribVal;
					break;
				case 0x0BB8: // game speed
					if ($this->build >= 15449) {
						switch ($attribVal) {
							case "Fasr":
								$tmp = 4;
								break;
							case "Fast":
								$tmp = 3;
								break;
							default:
								$tmp = 2;
						}
						$this->gameSpeed = $tmp;
					}
					else {
						$this->gameSpeed = intval($attribVal);
					}
					break;
				case 0x01F4: // player type, Humn or Comp
					$this->players[$playerId]["ptype"] = $attribVal;
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
					if ($this->build >= 15449) {
						$this->players[$playerId]["colorIndex"] = intval(substr($attribVal,2));
						$this->players[$playerId]["sColor"] = self::$colorIndices[intval(substr($attribVal,2))];
					}
					else {
						$this->players[$playerId]["colorIndex"] = intval($attribVal);
						$this->players[$playerId]["sColor"] = self::$colorIndices[intval($attribVal)];
					}
					break;
				default:
			}
		}
	}
	
	// parse a key/value -pair struct in the replay.details file
	private function parseKeyVal($string, &$numByte) {
		$one = $this->readByte($string,$numByte); //$one[1];
		if (($one & 192) > 0) { // check if value is two bytes
			$two = unpack("v",substr($string,$numByte -1,2));
			$two = ($two[1] >> 2); // get rid of extra bits
			$numByte += 1;
			return $two;
		}
		return $one;
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
	
	public function getActualPlayers() {
		$tmp = array();
		foreach ($this->players as $val)
			if ($val['party'] > 0)
				$tmp[] = $val;
		return $tmp;
	}
	
	// parameter is the contents of the replay.game.events -file
	private function parseGameEventsFile($string) {
		$numByte = 0;
		$len = strlen($string);
		$playerLeft = array();
		$events = array();
		$time = 0;
		while ($numByte < $len) {
			$timeStamp = $this->parseTimeStamp($string,$numByte);
			$nextByte = $this->readByte($string,$numByte);
			$eventType = $nextByte >> 5; // 3 lowest bits
			$globalEventFlag = $nextByte & 16; // 4th bit
			$playerId = $nextByte & 15; // bits 5-8
			$playerName = $this->players[$playerId]['sname'];
			$eventCode = $this->readByte($string,$numByte);
			$time += $timeStamp;
			// weird timestamp values mean that there's likely a problem with the alignment of the parse(too few/too many bytes read for an eventcode)
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
					//echo sprintf("ETYPE: %d, First action event data at byte: %d, eventcode %d<br />\n",$eventType,$numByte,$eventCode);
					switch ($eventCode) {
						case 0x09: // player quits the game
							if ($this->players[$playerId]['party'] > 0) // don't log observers/party members etc
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
							//echo sprintf("Unit ability: %06X, reqtarget = %02X<br />\n",$this->readUnitAbility($data),$reqTarget);
							// at least with attack, move, right-click, if the byte after unit ability bytes is 
							// 0x30 or 0x50, the struct takes 1 extra byte. With build orders the struct seems to be 32 bytes
							// and this byte is 0x00.
							// might also be in some other way variable-length.
							if ($reqTarget == 0x30) 
								$data .= $this->readByte($string,$numByte); 
							if ($reqTarget == 0x50)
								$data .= $this->readByte($string,$numByte);
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
							$dsuExtraBits = $dsuCount % 8;
							if ($dsuCount > 0)
								$dsuMap = $this->readBytes($string,$numByte,floor($dsuCount / 8));
							if ($dsuExtraBits != 0) { // not byte-aligned
								$dsuMapLastByte = $this->readByte($string,$numByte);

								$nByte = $this->readByte($string,$numByte);
								
								$uTypesCount = (($dsuMapLastByte & (0xFF - ((1 << $dsuExtraBits) - 1))) | ($nByte & (0xFF >> (8 - $dsuExtraBits))));
								for ($i = 1;$i <= $uTypesCount;$i++) {
									$n3Bytes = unpack("C3",$this->readBytes($string,$numByte,3));
									$tmp = (($nByte & (0xFF - ((1 << $dsuExtraBits) - 1))) | ($nBytes[1] & (0xFF >> (8 - $dsuExtraBits))));
									$tmp2 = (($nBytes[1] & (0xFF - ((1 << $dsuExtraBits) - 1))) | ($nBytes[2] & (0xFF >> (8 - $dsuExtraBits))));
									$tmp3 = (($nBytes[2] & (0xFF - ((1 << $dsuExtraBits) - 1))) | ($nBytes[3] & (0xFF >> (8 - $dsuExtraBits))));
									$tmp = ($tmp << 16) | ($tmp2 << 8) | $tmp3;
									$uType[$i]['id'] = $tmp;
									$nByte = $this->readByte($string,$numByte);
									$tmp = (($nBytes[2] & (0xFF - ((1 << $dsuExtraBits) - 1))) | ($nByte & (0xFF >> (8 - $dsuExtraBits))));
									//$tmp = (($n2Bytes[2] >> $dsuExtraBits) | (($nByte << (8 - $dsuExtraBits))& 0xFF));
									$uType[$i]['count'] = $tmp;
								}
								$lByte = $this->readByte($string,$numByte);
								$tmp = (($nByte & (0xFF - ((1 << $dsuExtraBits) - 1))) | ($lByte & (0xFF >> (8 - $dsuExtraBits))));
								//$tmp = (($nByte >> $dsuExtraBits) | (($lByte << (8 - $dsuExtraBits))& 0xFF));
								$totalUnits = $tmp;
								//unnecessary to parse unit ID values at this point, so skip them
								$numByte += $totalUnits * 4;
								//$numByte++; // padding to get to byte boundary
								//if ($uTypesCount == 0) $numByte--;
							} else { // byte-aligned
								$uTypesCount = $this->readByte($string,$numByte);
								for ($i = 1;$i <= $uTypesCount;$i++) {
									$uType[$i]['id'] = $this->readUnitTypeID($string,$numByte);
									$uType[$i]['count'] = $this->readByte($string,$numByte);
								}
								$totalUnits = $this->readByte($string,$numByte);
								//unnecessary to parse unit ID values at this point, so skip them
								$numByte += $totalUnits * 4;
								//if ($uTypesCount == 0) $numByte--;
								//if ($dsuCount % 8 != 0) $numByte++;
							}
							break;
						case 0x0D: // manually uses hotkey
						case 0x1D:
											// 00 00 == assigned new group? (ctrl+hotkey)
											// 02 00 == selected group? (hotkey)
											// 06 34
											// 06 be
											// 0a 03 04
											// 0a 03 04
											// 0a 13 04
											// 02 1b
											//other file: dronsu kusetus
											// 0a 84 00
											// 0a a7 0d
											// 0a a7 0d
											// 0a a7 0d
											// 0a af 0d
											// 0a c0 00
											// c0 00
											// 0a 0c 00
											// 0a c0 00
											// 0a 80 00
											// 0a 80 00
											// 0a 06 02
											// 0a 26 0e
											// 0a 3e 0e
											
											
											// 0e 3c 2c
											// 1a 03 08 00 04
											// 12 8a 0c 02
											// 0d 2f 68 01
											// 0d 00 0a
											// 19 1b 77 9c 1d
											// 06 83 00
											// 06 cb 00
											// 09 a4 00
											// 16 21 eb 7b
											// 16 f5 db 7a
											// 16 01 00 18
											// 16 09 00 19
											// 0e 83 e4 00
											// 2e 58 b0 f1 eb 62 38
											// 2e 07 00 40 00 00 20 00
											// 02 05
											// 36 b0 80 21 76 6d 84 0c
											// 3e b1 c0 31 fe 6d 8c 0b 50
											// 1e 3c 5d bf 2e
											// 46 c4 02 ea 93 1e 70 99 65 21
											// 62 9f 7d fb 6f ff 7f ff fb bf df 9e 7e 1e
											// 26 03 00 04 02 98 00
											// 2a 00 00 04 03 cc 00
											// 16 aa c8 7f
											// 0e 17 20 00
											// 16 c1 10 14
											// 3a 5e 75 86 00 10 00 08 03
											// 0e 07 22 00
											// 06 c3 00
											// 11 fd dd 07
											// 05 c3 00
											// 02 17
											// 2e 03 20 82 00 00 20 00
											// 16 03 45 ac 00
											// 0e 03 20 00
											// 16 ff c1 fa 00
										
						case 0x2D:
						case 0x3D:
						case 0x4D:
						case 0x5D:
						case 0x6D:
						case 0x7D:
						case 0x8D:
						case 0x9D:
							$byte1 = $this->readByte($string,$numByte);
							$byte2 = $this->readByte($string,$numByte);
							$extraBytes = floor($byte1 / 8);
							$numByte += $extraBytes;
							$extraExtraByte = ((($byte1 & 4) == 4) && (($byte2 & 3) == 3))?1:0;
							$numByte += $extraExtraByte;
/*
							switch ($byte2) {
								case 0x83:
								case 0xcb:
								case 0xc3:
								case 0x17:
								case 0x07:
								case 0x03:
								case 0x2f:
									$numByte++;
									break;
							}
							
							switch ($byte1) {
								case 0x05:
								case 0x06:
									if ($byte2 == 0x83) $numByte += 1;
									if ($byte2 == 0xcb) $numByte += 1;
									if ($byte2 == 0xc3) $numByte += 1;
									break;
								case 0x09:
								case 0x0a:
								case 0x0e:
									if ($byte2 == 0x83) $numByte += 1;
									if ($byte2 == 0x17) $numByte += 1;
									if ($byte2 == 0x07) $numByte += 1;
									if ($byte2 == 0x03) $numByte++;
									$numByte += 1;
									break;
								case 0x11:
								case 0x12:
									$numByte += 2;
									break;
								case 0x16:
									if ($byte2 == 0x03) $numByte++;
									if ($byte2 == 0xff) $numByte++;
									$numByte += 2;
									break;
								case 0x19:
								case 0x1a:
								case 0x1e:
									$numByte += 3;
									break;
								case 0x22:
								case 0x26:
									if ($byte2 == 0x03) $numByte++;
									$numByte += 4;
									break;
								case 0x2a:
								case 0x2e:
									if ($byte2 == 0x07) $numByte++;
									if ($byte2 == 0x03) $numByte++;
									$numByte += 5;
									break;
								case 0x32:
								case 0x36:
									$numByte += 6;
									break;
								case 0x3a:
								case 0x3e:
									$numByte += 7;
									break;
								case 0x46:
									$numByte += 8;
									break;
								case 0x62:
									$numByte += 12;
									break;
								case 0x0d: // 0d 2f 68 01, 0d 00 0a
									switch ($byte2) {
										case 0x2f:
											$numByte += 2;
											break;
										case 0x00:
											$numByte += 1;
											break;
										default:
											$numByte += 2;
									}
								}
							*/
							//if ($extrabytes > 0)
//								$numByte += $extrabytes;
							//if ($byte1 < 0x0a) break; // values of 0x0a and 0x0e have a third byte at least.
							//$byte3 = $this->readByte($string,$numByte);
							//if ($byte1 == 0x1a) // value of 0x1a makes the total length 5 bytes (2 extra compared to 0x0a and 0x0e)
												// perhaps bits 4 and 5 of byte 1 make up the number of extra bytes to read?
							
							break;
/*						case 0x0C: // automatic update of hotkey?
						case 0x1C:
						case 0x2C:
						case 0x3C: // 01 01 01 01 11 01 03 02 02 38 00 01 02 3c 00 01 00
						case 0x4C: // 01 02 02 01 0d 00 02 01 01 a8 00 00 01
						case 0x5C: // 01 01 01 01 16 03 01 01 03 18 00 01 00
						case 0x6C: // 01 04 08 01 03 00 02 01 01 34 c0 00 01
						case 0x7C: // 01 05 10 01 01 10 02 01 01 1a a0 00 01
						case 0x8C:
						case 0x9C:
							$byte1 = $this->readByte($string,$numByte);
							$numByte += 12;							
							if ($byte1 == 1) $numByte += 4;
						//$numByte += 13;
							break;
						*/
						case 0x1F: // no idea
							$numByte += 17; // 84 00 00 0c 84 00 00 00 80 00 00 00 80 00 00 00 00
							break;
						default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
					}				
					break;
				case 0x02: // unused? not so much
					switch($eventCode) {
						case 0x06:
							$numByte += 8; // 00 00 00 04 00 00 00 04
						break;
						default:
						if ($this->debug) $this->debug(sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
					}
					break;
				case 0x03: // replay
					switch ($eventCode) {
						case 0x81: // player moves screen
							$numByte += 20; // always 20 bytes
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
				$this->players[$val]['won'] = 0;
			$lastLeaver = $val;
		}
		// if the number of players who left is $numActual - 1, then everyone else except the recorder left and he is the winner
		// if the number of players who left is $numActual - 2, then whoever left after the recorder is the winner. can be determined if the recorder is known.
		// otherwise the winner cannot be determined, since any one of the players who left after the recorder could be the winner
		if ($numLeft == ($numActual - 1)) {
			if ($this->debug) $this->debug("Found winner");
			$this->players[$lastLeaver]['won'] = 0;
		}
		else {
			if ($this->debug) $this->debug("Unable to parse winner");
			return;
		}

		foreach ($this->players as $val) {
			if (($val['party'] > 0) && (!isset($val['won']))) $winteam = $val['party'];
		}
		foreach ($this->players as $val) {
			if ($val['party'] == $winteam) $this->players[$val['id']]['won'] = 1;
			else if ($val['party'] > 0) $this->players[$val['id']]['won'] = 0;
		}
		
		/*
		foreach ($this->players as $key => $value) {
			for ($i = 0;$i < $numLeft;$i++) {
				if (!isset($value['team']) || $value['id'] == $playerLeft[$i]) break;
			}
			// if the following is true, the inner loop did not break and the found player did not get logged as leaving, hence the winner
			if ($i == $numLeft) {
				$team = $value['team'];
				foreach ($this->players as $player) { //  ($a = 0;$a < count($this->players);$a++) {
					if ($player['team'] == $team) $this->players[$player['id']]['won'] = true;
					else $this->players[$player['id']]['won'] = false;
				}
				break;
			}
		}*/
	}
	private function parseTimeStamp($string, &$numByte) {
		$one = $this->readByte($string,$numByte); //$one[1];
		if (($one & 3) > 0) { // check if value is two bytes
			$two = $this->readByte($string,$numByte);
			//$two = unpack("v",substr($string,$numByte -1,2));
			$two = ((($one >> 2) << 8) | $two);
			if (($one & 3) >= 2) {
				$tmp = $this->readByte($string,$numByte);			
				$two = (($two  << 8) | $tmp);
				if (($one & 3) == 3) {
					$tmp = $this->readByte($string,$numByte);			
					$two = (($two  << 8) | $tmp);
				}
			}
			return $two;
		}
		return $one;
	}
	// gets the literal string from the sc2_abilitycodes array based on the ability code
	// returns false if the variable doesn't exist or the file cannot be included
	function getAbilityString($num) {
		global $sc2_abilityCodes;
		if (isset($sc2_abilityCodes) || (include 'abilitycodes.php')) {
			if ($this->build >= 15449) {
				$num -= 0x400;
				if ((($num & 0x06FD00) == 0x06FD00) || (($num & 0x08F000) == 0x08F000))
					$num -= 0x00F000;
			}
			if ($this->debug) return sprintf("%s (%06X)",$sc2_abilityCodes[$num],$num);
			else return $sc2_abilityCodes[$num];
		}
		return false;
	}
}


?>