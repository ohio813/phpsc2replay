<?php
define("MPQ_HASH_TABLE_OFFSET", 0);
define("MPQ_HASH_NAME_A", 1);
define("MPQ_HASH_NAME_B", 2);
define("MPQ_HASH_FILE_KEY", 3);
define("MPQ_HASH_ENTRY_EMPTY", (0xFFFF << 16) | 0xFFFF);
define("MPQ_HASH_ENTRY_DELETED", (0xFFFF << 16) | 0xFFFE);
define("MPQ_ERR_LOWSC2REPLAYVERSION", -1);
define("MPQ_NOT_PARSED", 2);
define("MPQ_PARSE_OK", 1);
define("MPQ_ERR_NOTMPQFILE", -1);

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
	
	
	function __construct() {
		$this->players = array();
		$this->gameLength = 0;
		$this->mapName = NULL;
		$this->gameSpeed = 0;
		$this->teamSize = NULL;
	}
	// parameter needs to be an instance of MPQFile
	function parseReplay($mpqfile) {
		if (!is_a($mpqfile, "MPQFile")) return false;
		if ($mpqfile->getVersion() < 9) return MPQ_ERR_LOWSC2REPLAYVERSION; //demo format changed at major version 9, no support for older ones
		$this->version = $mpqfile->getVersion();
		$this->build = $mpqfile->getBuild();
		// first parse replay.details file
		$file = $mpqfile->readFile("replay.details");
		if ($file !== false) {
			$this->parseDetailsFile($file);
		}
	
		$file = $mpqfile->readFile("replay.attributes.events");
		if ($file !== false) {
			$this->parseAttributesFile($file);
			// parse game length here because the result depends on gamespeed, which replay.attributes.events has
			$fs = $mpqfile->getFileSize("replay.sync.events");
			if ($fs !== false) $this->gameLength = $fs / self::$gameSpeedCE[$this->gameSpeed]; // sync event is 4 bytes, with a sync window of 1/8th to 1/16th of a second
		}
		$file = $mpqfile->readFIle("replay.game.events");
		if (file !== false) $this->parseGameEventsFile($file);
		
	}
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
			if ($key[2] == 9) { $hadKey = true; $keys[$key[1]] = $this->parseKeyVal($string,$numByte); }
			else if ($key[1] == 6 && $key[2] == 2) { break; }
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
				//if ($sName !== NULL) echo sprintf("%s Key: %d, value: %d<br />",$sName,$key[1], $keyVal);
			}
			else if ($key[1] == 5 && $key[2] == 18) {$numByte -= 2; break; } // next player
			else if ($key[1] == 2 && $key[2] == 2) { break; } // end of player section
		}
		if (($sName === NULL) && ($lName === NULL)) return NULL;
		$p = array();
		$p["sName"] = $sName;
		$p["lName"] = $lName;
		$p["race"] = $race;
		$p["party"] = $party;
		$p["color"] = sprintf("%02X%02X%02X",$cR,$cG,$cB);
		return $p;
	}
	
	// parameter is the contents of the replay.attributes.events file
	private function parseAttributesFile($string) {
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
//			echo sprintf("Got attrib \"%04X\" for player %d (%s), attribVal = \"%s\"<br />",
//							$attributeId,$playerId,(($playerId == 0x10)?"ALL":$this->players[$playerId]["sName"]),$attribVal);
			switch ($attributeId) {
				case 0x07D3: // team, VERY uncertain because of gazillion other 0x07DX values
					$this->players[$playerId]["team"] = intval(substr($attribVal,1));
					break;
				case 0x0BBB: // handicap
					$this->players[$playerId]["handicap"] = $attribVal;
					break;
				case 0x0BB8: // game speed
					$this->gameSpeed = intval($attribVal);
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
					$this->players[$playerId]["colorIndex"] = intval($attribVal);
					$this->players[$playerId]["sColor"] = self::$colorIndices[intval($attribVal)];
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
							echo sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte);
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
							echo sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte);
					}				
					break;
				case 0x02: // unused? not so much
					switch($eventCode) {
						case 0x06:
							$numByte += 8; // 00 00 00 04 00 00 00 04
						break;
						default:
							echo sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte);
					}
					break;
				case 0x03: // replay
					switch ($eventCode) {
						case 0x81: // player moves screen
							$numByte += 20; // always 20 bytes
							break;
						default:
							echo sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte);
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
							echo sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte);
					}
					break;
				case 0x05: // system
					switch($eventCode) {
						case 0x89: //automatic synchronization?
							$numByte += 4;
							break;
						default:
							echo sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte);
					}
					break;
				default:
					echo sprintf("DEBUG: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
							$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte);
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
		if ($numLeft == ($numActual - 1)) $this->players[$lastLeaver]['won'] = 0;
		else return;

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
}


class MPQFile {
	private $filename;
	private $filelist;
	private $fp;
	private $hashtable,$blocktable;
	private $hashTableSize, $blocKTableSize;
	private $headerOffset;
	private $listfile;
	private $init;
	private $verMajor;
	private $build;
	private $sectorSize;
	
	function __construct($filename, $autoparse = true) {
		$this->filelist = array();
		$this->filename = $filename;
		$this->hashtable = NULL;
		$this->blocktable = NULL;
		$this->hashTableSize = 0;
		$this->blockTableSize = 0;
		$this->headerOffset = 0;
		$this->listfile = NULL;
		$this->init = false;
		$this->verMajor = 0;
		$this->build = 0;
		$this->sectorSize = 0;
		if (file_exists($this->filename))
			$this->fp = fopen($this->filename, 'rb');
		if ($autoparse)
			$this->parseHeader();
	}
	function __destruct() {
		if ($this->fp !== FALSE)
			fclose($this->fp);
	}
	
	
	function parseHeader() {
		if ($this->fp === FALSE) return false;
		$fp = $this->fp;
		$headerParsed = false;
		$headerOffset = 0;
		while (!$headerParsed) {
			$magic = unpack("c4",fread($fp,4)); // MPQ 1Bh or 1Ah
			if (($magic[1] != 0x4D) || ($magic[2] != 0x50) || ($magic[3] != 0x51)) { $this->init = MPQ_ERR_NOTMPQFILE; return false; }
			if ($magic[4] == 27) { // user data block (1Bh)
				$uDataMaxSize = $this->readUInt32();
				$headerOffset = $this->readUInt32();
				$this->headerOffset = $headerOffset;
				$uDataSize = $this->readUInt32();
				fseek($fp,24,SEEK_CUR); // skip Starcraft II replay 0x1B 0x32 0x01 0x00
				$verMajor =  $this->readUInt32();
				$this->verMajor = $verMajor;
				$build = $this->readUInt32();
				$this->build = $build;
		
				
				fseek($fp,$headerOffset);
			}
			else if ($magic[4] == 26) { // header (1Ah)
				$headerSize = $this->readUInt32();
				$archiveSize = $this->readUInt32();
				$formatVersion = $this->readUInt16();
				$sectorSizeShift = $this->readByte();
				$sectorSize = 512 * pow(2,$sectorSizeShift);
				$this->sectorSize = $sectorSize;
				fseek($fp, 1, SEEK_CUR);
				$hashTableOffset = $this->readUInt32() + $headerOffset;
				$blockTableOffset = $this->readUInt32() + $headerOffset; 
				$hashTableEntries = $this->readUInt32();
				$this->hashTableSize = $hashTableEntries;
				$blockTableEntries = $this->readUInt32();
				$this->blockTableSize = $blockTableEntries;
				
				$headerParsed = true;
			}
			else return false;
		}
		// read and decode the hash table
		fseek($this->fp, $hashTableOffset);
		$hashSize = $hashTableEntries * 4; // hash table size in 4-byte chunks
		$tmp = array();
		for ($i = 0;$i < $hashSize;$i++)
			$tmp[$i] = $this->readUInt32();
		$hashTable = decryptStuff($tmp,hashStuff("(hash table)", MPQ_HASH_FILE_KEY));
		
		// read and decode the block table
		fseek($this->fp, $blockTableOffset);
		$blockSize = $blockTableEntries * 4; // block table size in 4-byte chunks
		$tmp = array();
		for ($i = 0;$i < $blockSize;$i++)
			$tmp[$i] = $this->readUInt32();
		$blockTable = decryptStuff($tmp,hashStuff("(block table)", MPQ_HASH_FILE_KEY));		
		$this->hashtable = $hashTable;
		$this->blocktable = $blockTable;
		$this->init = MPQFILE_PARSE_OK;
		// check if listfile exists

		$listfile = $this->readFile("(listfile)");
		if ($listfile !== FALSE) $this->listfile = $listfile;
		return true;
	}
	
	function readUInt32($string = false, $offset = -1) {
		if ($this->fp === FALSE) return false;
		if ($offset != -1) fseek($this->fp, $offset);
		$t = unpack("V",fread($this->fp,4));
		return $t[1];
	}
	function readUInt16($string = false, $offset = -1) {
		if ($this->fp === FALSE) return false;
		if ($offset != -1) fseek($this->fp, $offset);
		$t = unpack("v",fread($this->fp,2));
		return $t[1];
	}
	function readByte(&$string = false, $offset = -1) {
		if ($string === false && $this->fp === FALSE) return false;
		if ($string === false && $offset != -1) fseek($this->fp, $offset);
		if ($string !== false) {
			$t = unpack("C",substr($string,0,1));
			$string = substr($string,1,strlen($string) -1);
		}	
		else
			$t = unpack("C",fread($this->fp,1));
		return $t[1];
	}
	
	function getFileSize($filename) {
		if ($this->init === false) return false;
				$hashA = hashStuff($filename, MPQ_HASH_NAME_A);
		$hashB = hashStuff($filename, MPQ_HASH_NAME_B);
		$hashStart = hashStuff($filename, MPQ_HASH_TABLE_OFFSET) & ($this->hashTableSize - 1);
		$tmp = $hashStart;
		do {
			if (($this->hashtable[$tmp*4 + 3] == MPQ_HASH_ENTRY_DELETED) || ($this->hashtable[$tmp*4 + 3] == MPQ_HASH_ENTRY_EMPTY)) return false;
			if (($this->hashtable[$tmp*4] == $hashA) && ($this->hashtable[$tmp*4 + 1] == $hashB)) { // found file
				$blockIndex = ($this->hashtable[($tmp *4) + 3]) *4;
				$fileSize = $this->blocktable[$blockIndex + 2];
				return $fileSize;
			}
			$tmp = ($tmp + 1) % $this->hashTableSize;
		} while ($tmp != $hashStart);
		return false;
	}
	
	function readFile($filename) {
		if ($this->init === false) return false;
		$hashA = hashStuff($filename, MPQ_HASH_NAME_A);
		$hashB = hashStuff($filename, MPQ_HASH_NAME_B);
		$hashStart = hashStuff($filename, MPQ_HASH_TABLE_OFFSET) & ($this->hashTableSize - 1);
		$tmp = $hashStart;
		$blockSize = -1;
		do {
			if (($this->hashtable[$tmp*4 + 3] == MPQ_HASH_ENTRY_DELETED) || ($this->hashtable[$tmp*4 + 3] == MPQ_HASH_ENTRY_EMPTY)) return false;
			if (($this->hashtable[$tmp*4] == $hashA) && ($this->hashtable[$tmp*4 + 1] == $hashB)) { // found file
				$blockIndex = ($this->hashtable[($tmp *4) + 3]) *4;
				$blockOffset = $this->blocktable[$blockIndex] + $this->headerOffset;
				$blockSize = $this->blocktable[$blockIndex + 1];
				$fileSize = $this->blocktable[$blockIndex + 2];
				$flags = $this->blocktable[$blockIndex + 3];
				break;
			}
			$tmp = ($tmp + 1) % $this->hashTableSize;
		} while ($tmp != $hashStart);
		if ($blockSize == -1) return false;
		$flag_file       = $flags & 0x80000000;
		$flag_checksums  = $flags & 0x04000000;
		$flag_deleted    = $flags & 0x02000000;
		$flag_singleunit = $flags & 0x01000000;
		$flag_hEncrypted = $flags & 0x00020000;
		$flag_encrypted  = $flags & 0x00010000;
		$flag_compressed = $flags & 0x00000200;
		$flag_imploded   = $flags & 0x00000100;

		if (!$flag_file) return false;
		fseek($this->fp,$blockOffset);
		if ($flag_checksums) {
			for ($i = $fileSize;$i > 0;$i -= $this->sectorSize) {
				$sectors[] = $this->readUInt32();
				$blockSize -= 4;
			}
			$sectors[] = $this->readUInt32();
			$blockSize -= 4;
		}
		else {
			$sectors[] = 0;
			$sectors[] = $blockSize;
		}
		$c  = count($sectors) - 1;
		$totDur = 0;
		for ($i = 0;$i < $c;$i++) {
			$sectorLen = $sectors[$i + 1] - $sectors[$i];
			fseek($this->fp,$blockOffset + $sectors[$i],SEEK_SET);
			$sectorData = fread($this->fp,$sectorLen);
			if ($flag_compressed && (($flag_singleunit && ($blockSize < $fileSize)) || ($flag_checksums && ($sectorLen <  $this->sectorSize)))) {
				$compressionType = $this->readByte($sectorData);
				switch ($compressionType) {
					case 2:
						$output .= deflate_decompress($sectorData);
						break;
					default:
						return false;
				}
			}
			else $output .= $sectorData;
		}
		if (strlen($output) != $fileSize) return false;
		return $output;
	}
	function getState() {
		return $this->init;
	}
	function getBuild() { return $this->build; }
	function getVersion() { return $this->verMajor; }
}
function deflate_decompress($string) {
	if (function_exists("gzinflate")){
		$tmp = gzinflate(substr($string,2,strlen($string) - 2));
		return $tmp;
	}
	return false;
}


function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function initCryptTable() {
	$cryptTable = array();
	$seed = 0x00100001;
	$index1 = 0;
	$index2 = 0;
	
	for ($index1 = 0; $index1 < 0x100; $index1++) {
		for ($index2 = $index1, $i = 0; $i < 5; $i++, $index2 += 0x100) {
			$seed = (uPlus($seed * 125,3)) % 0x2AAAAB;
			$temp1 = ($seed & 0xFFFF) << 0x10;
			
			$seed = (uPlus($seed * 125,3)) % 0x2AAAAB;
			$temp2 = ($seed & 0xFFFF);
			
			$cryptTable[$index2] = ($temp1 | $temp2);
		}
	}
	return $cryptTable;
}
function mod_s($a, $b) {
	while (($a - $b) >= $b)
		$a = $a - $b;
	return $a;
}
function uPlus($o1, $o2) {
	$o1h = ($o1 >> 16) & 0xFFFF;
	$o1l = $o1 & 0xFFFF;
	
	$o2h = ($o2 >> 16) & 0xFFFF;
	$o2l = $o2 & 0xFFFF;	

	$ol = $o1l + $o2l;
	$oh = $o1h + $o2h;
	if ($ol > 0xFFFF) { $oh += (($ol >> 16) & 0xFFFF); }
	return ((($oh << 16) & 0xFFFF0000) | ($ol & 0xFFFF));
}

function rShift($num,$bits) {
	return (($num >> 1) & 0x7FFFFFFF) >> ($bits - 1);
}

function hashStuff($string, $hashType) {
	global $cryptTable;
	$seed1 = 0x7FED7FED;
	$seed2 = ((0xEEEE << 16) | 0xEEEE);
	$strLen = strlen($string);
	
	for ($i = 0;$i < $strLen;$i++) {
		$next = ord(strtoupper(substr($string, $i, 1)));

		$seed1 = $cryptTable[($hashType << 8) + $next] ^ (uPlus($seed1,$seed2));
		$seed2 = uPlus(uPlus(uPlus(uPlus($next,$seed1),$seed2),$seed2 << 5),3);
	}
	return $seed1;
}

function decryptStuff($data, $key) {
	global $cryptTable;
	$seed = ((0xEEEE << 16) | 0xEEEE);
	$datalen = count($data);
	for($i = 0;$i < $datalen;$i++) {
		$seed = uPlus($seed,$cryptTable[0x400 + ($key & 0xFF)]);
		$ch = $data[$i] ^ (uPlus($key,$seed));

		$data[$i] = $ch;

		$key = (uPlus(((~$key) << 0x15), 0x11111111)) | (rShift($key,0x0B));
		$seed = uPlus(uPlus(uPlus($ch,$seed),($seed << 5)),3);
	}
	return $data;
}

$cryptTable = initCryptTable();

?>
