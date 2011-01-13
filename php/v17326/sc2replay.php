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
class SC2ReplayImpl extends SC2Replay {
	
	function __construct() {
		$this->players = array();
		$this->gameLength = 0;
		$this->mapName = NULL;
		$this->gameSpeed = 0;
		$this->teamSize = NULL;
		$this->realTeamSize = NULL;
		$this->debug = false;
		$this->debugNewline = "<br />\n";
		$this->winnerKnown = false;
		$this->unitsDict = array();
		$this->mapHash = null;
		$this->recorderId = 0;
	}

	// Overrides from base class:

	// parameter is the contents of the replay.attributes.events file
	protected function parseAttributesFile($string) {
		if ($this->debug) $this->debug("Parsing replay.attributes.events file");
		$numByte = 5; // skip the 5-byte header
		$numAttribs = MPQFile::readUInt32($string,$numByte);
		$attribArray = array();
		$difficulties = array("VyEy" => 0, "Easy" => 1, "Medi" => 2, "Hard" => 3, "VyHd" => 4, "Insa" => 5);
		$gameSpeeds = array("Slor" => 0, "Slow" => 1, "Norm" => 2, "Fast" => 3, "Fasr" => 4);
		for ($i = 0;$i < $numAttribs;$i++) {
			$attribHeader = MPQFile::readUInt32($string,$numByte);
			$attributeId = MPQFile::readUInt32($string,$numByte);
			$playerId = MPQFile::readByte($string,$numByte);
			$attribVal = "";
			// values are stored in reverse in the file, eg Terr becomes rreT. The following loop flips the value and removes excess null bytes
			for ($a = 0;$a < 4;$a++) {
				$b = ord(substr($string,$numByte + 3 - $a));
				if ($b != 0) $attribVal .= chr($b);
			}
			$numByte += 4;

			$attribArray[$attributeId][$playerId] = $attribVal;
			if ($this->debug) $this->debug(sprintf("Got attrib \"%04X\" for player %d, attribVal = \"%s\"",
							$attributeId,$playerId,$attribVal));
		}
		if ($numAttribs == 0)
			return;
		// map the player ids to actual player ids
		// assumes that the slots are populated from the lowest player id to highest player id
		if (isset($attribArray[0x01F4])) {
			$tmpPlayerArray = array();
			for ($i = 1,$playerId = 1;isset($attribArray[0x01F4][$i]);$i++) {
				if ($attribArray[0x01F4][$i] == "Open") continue;
				$tmpPlayerArray[$i] = $this->players[$playerId];
				$tmpPlayerArray[$i]['id'] = $i;
				$playerId++;
			}
			unset($this->players);
			$this->players = $tmpPlayerArray;
			$numSlots = $i;
		}
		else $numSlots = 0;
		// see which attribute id gives the correct team values
		switch ($attribArray[0x07D1][0x10]) {
			case "1v1": $teamAttrib = 0x07D2; break;
			case "2v2": $teamAttrib = 0x07D3; break;			
			case "3v3": $teamAttrib = 0x07D4; break;
			case "4v4":	$teamAttrib = 0x07D5; break;
			case "FFA":	$teamAttrib = 0x07D6; break;
			default:
				if ($this->debug) 
					$this->debug(sprintf("Unknown game mode in replay.attributes.events: %s",$attribArray[0x10][0x07D1]));
		}
		// custom games have different values (not tested with all values, algorithm may be wrong)
		switch ($attribArray[0x07D0][0x10]) {
			case 'Cust': $teamAttrib = $teamAttrib + 0x10 - 1; break; // 0x7D3 becomes 0x7E2 etc.
			default:
		}
		// populate the data structures with relevant values
		$teamArray = array();
		for ($i = 1;$i < $numSlots;$i++) {
			if (!isset($this->players[$i])) continue;
			//$actualPlayerId = $playerIdArray[$i];
			// handicap
			$this->players[$i]["handicap"] = $attribArray[0x0BBB][$i];
			// difficulty
			$this->players[$i]["difficulty"] = $difficulties[$attribArray[0x0BBC][$i]];
			// starting race
			$this->players[$i]["srace"] = $attribArray[0x0BB9][$i];
			// set player type
			$this->players[$i]["isComp"] = ($attribArray[0x01F4][$i] == 'Comp')?true:false;
			// set player colors
			$this->players[$i]["colorIndex"] = intval(substr($attribArray[0x0BBA][$i],2));
			$this->players[$i]["sColor"] = self::$colorIndices[intval(substr($attribArray[0x0BBA][$i],2))];
			// set team
			$team = intval(substr($attribArray[$teamAttrib][$i],1));
			if (isset($teamArray[$team])) $teamArray[$team]++;
			else $teamArray[$team] = 1;
			$this->players[$i]["team"] = $team;
		}
		foreach ($teamArray as $team => $count) {
			if (isset($teamSizeString)) $teamSizeString .= "v$count";
			else $teamSizeString = "$count";
		}
		// if no team data, set a default value
		if (!isset($teamSizeString)) $teamSizeString = "0v0";
		// if only one team is found for some weird reason, add v0 for completeness eg 4v0 or so
		if (strpos($teamSizeString,"v") === false) $teamSizeString .= "v0";
		$this->realTeamSize = $teamSizeString;
		// set team size
		$this->teamSize = $attribArray[0x07D1][0x10];
		// game speed
		$this->gameSpeed = $gameSpeeds[$attribArray[0x0BB8][0x10]];
		// set game type
		$this->gamePublic = (($attribArray[0x0BC1][0x10] == "Priv")?false:true);
	}
		

	// parameter is the contents of the replay.game.events -file
	protected function parseGameEventsFile($string) {
		$len = strlen($string);
		$playerLeft = array();
		$events = array();
		$previousEventByte = 0; // start of the previous event's data location
		$time = 0;
		$numEvents = 0;
		$numByte = 0;
		$eventType = 0;
		$eventCode = 0;
		while ($numByte < $len) {
			$knownEvent = true;
			$timeStamp = self::parseTimeStamp($string,$numByte);
			$nextByte = MPQFile::readByte($string,$numByte);
			$eventType = $nextByte >> 5; // 3 lowest bits
			$globalEventFlag = $nextByte & 16; // 4th bit
			$playerId = $nextByte & 15; // bits 5-8
			if (isset($this->players[$playerId]))
				$playerName = $this->players[$playerId]['name'];
			else
				$playerName = "";
			$eventCode = MPQFile::readByte($string,$numByte);
			$time += $timeStamp;
			$numEvents++;

			if ($globalEventFlag > 0 && $playerId > 0)
				$knownEvent = false;
			else
			switch ($eventType) {
				case 0x00: // initialization
					switch ($eventCode) {
						case 0x2B:
						case 0x0C: // Player enters game
							if ($playerId == 0)
								$knownEvent = false;
							break;
						case 0x05: // game starts
							if ($globalEventFlag == 0 || $playerId > 0)
								$knownEvent = false;
							break;
						default:
							$knownEvent = false;
					}
					break;
				case 0x01: // action
					switch ($eventCode) {
						case 0x09: // player quits the game
							if ($this->players[$playerId]['team'] > 0) // don't log observers/party members etc
								$playerLeft[] = $playerId;
							break;
						case 0x1B:
						case 0x2B:
						case 0x3B:
						case 0x4B:
						case 0x5B:
						case 0x6B:
						case 0x7B:
						case 0x8B:
						case 0x9B:
						case 0x0B: // player uses an ability
							if ($this->build >= 16561) {
								$firstByte = MPQFile::readByte($string,$numByte);
								$temp = MPQFile::readByte($string,$numByte);
								$ability = (MPQFile::readByte($string,$numByte) << 16) | (MPQFile::readByte($string,$numByte) << 8) | (MPQFile::readByte($string,$numByte) & 0x3F);
								if ($temp == 0x20 || $temp == 0x22) {
									$nByte = $ability & 0xFF;
									if ($nByte > 0x07) {
										if ($firstByte == 0x29 || $firstByte == 0x19) { $numByte += 4; break; }
										$numByte += 9;
										if (($nByte & 0x20) > 0)
											$numByte += 9;
									}
								}
								else if ($temp == 0x48 || $temp == 0x4A)
									$numByte += 7;
								else if ($temp == 0x88 || $temp == 0x8A)
									$numByte += 15;
								if (!$this->players[$playerId]['isObs'] && $this->players[$playerId]['race'] == "") {
									switch ($ability) {
										case 0x020A00: //SCV
											$this->players[$playerId]['race'] = "Terran";
											break;
										case 0x021E00: //probe
											$this->players[$playerId]['race'] = "Protoss";
											break;										
										case 0x023000: //drone
											$this->players[$playerId]['race'] = "Zerg";
											break;
									}
								}
								if ($temp & 0x20) {
									$this->addPlayerAbility($playerId, ceil($time /16), $ability);
									$events[] = array('p' => $playerId, 't' => $time, 'a' => $ability);
									$this->events = $events;
								}
								
								if ($this->debug) $this->debug(sprintf("Used ability - player id: $playerId - time: %d - ability code: %06X",floor($time / 16),$ability));
								$this->addPlayerAction($playerId, floor($time / 16));
									
								break;
							}
							// at least 32 bytes
							$data = MPQFile::readBytes($string,$numByte,32);
							$reqTarget = unpack("C",substr($data,7,1));
							$reqTarget = $reqTarget[1];
							$ability = $this->readUnitAbility($data);
							if ($ability != 0xFFFF0F) {
								$events[] = array('p' => $playerId, 't' => $time, 'a' => $ability);
								$this->events = $events;
								// populate non-locale-specific race strings based on worker type
								if (!$this->players[$playerId]['isObs'] && $this->players[$playerId]['race'] == "") {
									switch ($ability) {
										case 0x080A00: //SCV
											$this->players[$playerId]['race'] = "Terran";
											break;
										case 0x090E00: //probe
											$this->players[$playerId]['race'] = "Protoss";
											break;										
										case 0x0B0000: //drone
											$this->players[$playerId]['race'] = "Zerg";
											break;
									}
								}
								
							}
							// at least with attack, move, right-click, if the byte after unit ability bytes is 
							// 0x30 or 0x50, the struct takes 1 extra byte. With build orders the struct seems to be 32 bytes
							// and this byte is 0x00.
							// might also be in some other way variable-length.
							if ($reqTarget == 0x30) 
								$data .= MPQFile::readByte($string,$numByte); 
							if ($reqTarget == 0x50)
								$data .= MPQFile::readByte($string,$numByte);
							// update apm array
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
							if ($this->build >= 16561) {
								$numByte++; // skip flag byte
								$deselectFlags = MPQFile::readByte($string,$numByte);
								if (($deselectFlags & 3) == 1) {
									$nextByte = MPQFile::readByte($string,$numByte);
									$deselectionBits = ($deselectFlags & 0xFC) | ($nextByte & 3);
									while ($deselectionBits > 6) {
										$nextByte = MPQFile::readByte($string,$numByte);
										$deselectionBits -= 8;
									}
									$deselectionBits += 2;
									$deselectionBits = $deselectionBits % 8;
									$bitMask = pow(2,$deselectionBits) - 1;
								}
								else if (($deselectFlags & 3) == 2 || ($deselectFlags & 3) == 3) {
									$nextByte = MPQFile::readByte($string,$numByte);
									$deselectionBytes = ($deselectFlags & 0xFC) | ($nextByte & 3);
									while ($deselectionBytes > 0) {
										$nextByte = MPQFile::readByte($string,$numByte);
										$deselectionBytes--;
									}
									$bitMask = 3;
								}
								else if (($deselectFlags & 3) == 0) {
									$bitMask = 3;
									$nextByte = $deselectFlags;
								}
								$uType = array();
								$unitIDs = array();
								$prevByte = $nextByte;
								$nextByte = MPQFile::readByte($string,$numByte);
								if ($bitMask > 0)
									$numUnitTypeIDs = ($prevByte & (0xFF - $bitMask)) | ($nextByte & $bitMask);
								else
									$numUnitTypeIDs = $nextByte;
								for ($i = 0;$i < $numUnitTypeIDs;$i++) {
									$unitTypeID = 0;
									for ($j = 0;$j < 3;$j++) {
										$prevByte = $nextByte;
										$nextByte = MPQFile::readByte($string,$numByte);
										if ($bitMask > 0)
											$byte = ($prevByte & (0xFF - $bitMask)) | ($nextByte & $bitMask);
										else
											$byte = $nextByte;
										$unitTypeID = $byte << ((2 - $j )* 8) | $unitTypeID;
									}
									$prevByte = $nextByte;
									$nextByte = MPQFile::readByte($string,$numByte);
									if ($bitMask > 0)
										$unitTypeCount = ($prevByte & (0xFF - $bitMask)) | ($nextByte & $bitMask);
									else
										$unitTypeCount = $nextByte;
									$uType[$i + 1]['count'] = $unitTypeCount;
									$uType[$i + 1]['id'] = $unitTypeID;
								}
								$prevByte = $nextByte;
								$nextByte = MPQFile::readByte($string,$numByte);
								if ($bitMask > 0)
									$numUnits = ($prevByte & (0xFF - $bitMask)) | ($nextByte & $bitMask);
								else
									$numUnits = $nextByte;
								
								for ($i = 0;$i < $numUnits;$i++) {
									$unitID = 0;
									for ($j = 0;$j < 4;$j++) {
										$prevByte = $nextByte;
										$nextByte = MPQFile::readByte($string,$numByte);
										if ($bitMask > 0)
											$byte = ($prevByte & (0xFF - $bitMask)) | ($nextByte & $bitMask);
										else
											$byte = $nextByte;
										if ($j < 2)
											$unitID = ($byte << ((1 - $j )* 8)) | $unitID;
									}
									$unitIDs[] = $unitID;
								}
								$a = 0;
								if($this->debug) {
									$this->debug("Selection Change");
									$this->debug(sprintf("Player %s", $playerId));
								}
								foreach($uType as $unitType){
									for($i = 1; $i <= $unitType['count']; $i++){
										$uid = $unitIDs[$a];
										//Bytes 3 + 4 contain flag info (perhaps same as in 1.00)
										$this->addSelectedUnit($uid, $unitType['id'], $playerId, floor($time / 16));
										if ($this->debug) {
											$this->debug(sprintf("  0x%06X -> 0x%04X", $unitType['id'], $uid));
										}
										$a++;
									}
								}
								if ($eventCode == 0xAC) {
									$this->addPlayerAction($playerId, floor($time / 16));
								}
								break;
							}
							$selFlags = MPQFile::readByte($string,$numByte);
							$dsuCount = MPQFile::readByte($string,$numByte);
							if($this->debug){
								$this->debug("Selection Change");
								$this->debug(sprintf("Player %s", $playerId));
								$this->debug(sprintf("Time %d", $time));
								$this->debug(sprintf("Deselected Count: %d", $dsuCount));
							}
							$dsuExtraBits = $dsuCount % 8;
							$uType = array();
							if ($dsuCount > 0)
								$dsuMap = MPQFile::readBytes($string,$numByte,floor($dsuCount / 8));
							if ($dsuExtraBits != 0 && $this->build < 16561) { // not byte-aligned
								$dsuMapLastByte = MPQFile::readByte($string,$numByte);

								$nByte = MPQFile::readByte($string,$numByte);

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
									$nBytes = unpack("C3",MPQFile::readBytes($string,$numByte,3));
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

									$nByte = MPQFile::readByte($string,$numByte);
									$uType[$i]['count'] = ($nBytes[3] & $offsetHeadMask) |
															($nByte     & $offsetTailMask);

									if($this->debug){
										$this->debug(sprintf("  %d x 0x%06X", $uType[$i]['count'], $uType[$i]['id']));
									}

								}
								$lByte = MPQFile::readByte($string,$numByte);
							
								$totalUnits = ($nByte & $offsetHeadMask) |
								  ($lByte & $offsetTailMask);

								if($this->debug){ 
								  $this->debug(sprintf("TOTAL: %d", $totalUnits));
								}


								//Populate the unitsDict
								foreach($uType as $unitType){
									for($i = 1; $i <= $unitType['count']; $i++){
										$nBytes = unpack("C4", MPQFile::readBytes($string, $numByte,4));
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
								$uTypesCount = MPQFile::readByte($string,$numByte);
								if($this->debug){
									$this->debug(sprintf("Number of New Unit Types %d", $uTypesCount));
								}
								for ($i = 1;$i <= $uTypesCount;$i++) {
									$uType[$i]['id'] = $this->readUnitTypeID($string,$numByte);
									$uType[$i]['count'] = MPQFile::readByte($string,$numByte);
									if($this->debug){
										$this->debug(sprintf("  %d x 0x%06X", $uType[$i]['count'], $uType[$i]['id']));
									}
								}
								$totalUnits = MPQFile::readByte($string,$numByte);
								if($this->debug){
									$this->debug(sprintf("TOTAL: %d", $totalUnits));
								}

							//Populate the Units Dict
								foreach($uType as $unitType){
									for($i = 1; $i <= $unitType['count']; $i++){
										$nBytes = unpack("C4", MPQFile::readBytes($string, $numByte, 4));
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
							$byte1 = MPQFile::readByte($string,$numByte);
							if ($byte1 <= 3) break;
							if ($numByte < $len) {
								$byte2 = MPQFile::readByte($string,$numByte);
								$numByte--;
							}
							if ($this->build < 16561) {
								$tmp = 0;
								$extraBytes = floor($byte1 / 8);
								$numByte += $extraBytes;	
								if (($byte1 & 4) && (($byte2 & 6) == 6)) {
									$tmp = MPQFile::readByte($string,$numByte);
									$numByte++;
								}
								else if ($byte1 & 4)
									$tmp = MPQFile::readByte($string,$numByte);
							}
							if ($this->build >= 16561) { 
								if ($byte1 & 8) {
								//if ($byte1 == 0x0A || $byte1 == 0x09) {
									$numByte += MPQFile::readByte($string,$numByte) & 0x0F;
									break;
								}
								$extraBytes = floor($byte1 / 8);
								$numByte += $extraBytes;	
								$tmp = MPQFile::readByte($string,$numByte);
								if ($extraBytes == 0) {
									if (($byte2 & 7) > 4) $numByte++;
									if ($byte2 & 8) $numByte++;
								}
								else {
									if (($byte1 & 4) && ($byte2 & 7) > 4) $numByte++;
									if (($byte1 & 4) && ($byte2 & 8)) $numByte++;
									//if ($byte1 & 8) $numByte += ($byte2 & 0x0F) - 1;
								}
							}
							if ($this->debug) $this->debug(sprintf("Byte1: $byte1, Byte2: $byte2, Numbyte: %04X, Extra bytes: $extraBytes",$numByte));
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
							$bytes = MPQFile::readBytes($string,$numByte,4);
							$mBytes = unpack("C4",$bytes);
							$mineralValue = ((($mBytes[1] << 20) | ($mBytes[2] << 12) | ($mBytes[3] << 4)) >> 1) + ($mBytes[4] & 0x0F);
							// sent gas
							$bytes = MPQFile::readBytes($string,$numByte,4);
							$mBytes = unpack("C4",$bytes);
							$gasValue = ((($mBytes[1] << 20) | ($mBytes[2] << 12) | ($mBytes[3] << 4)) >> 1) + ($mBytes[4] & 0x0F);
							
							// last 8 bytes are unknown
							$numByte += 8;
							break;
						default:
							$knownEvent = false;
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
							$knownEvent = false;
					}
					break;
				case 0x03: // replay
					switch ($eventCode) {
						case 0x87:
							$numByte += 8;
							break;
						case 0x08:
							$numByte += 10;
							break;
						case 0x18:
							$numByte += 162;
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
							$numByte += 3;
							$nByte = MPQFile::readByte($string,$numByte);
							switch (($nByte & 0x70)) {
								case 0x10: // zoom camera up or down
								case 0x30: // only 0x10 matters, but due to 0x70 mask in comparison, check for this too
								case 0x50:
									$numByte++;
									$nByte = MPQFile::readByte($string,$numByte);
								case 0x20:
									if (($nByte & 0x20) > 0) { // zooming, if comparison is 0 max/min zoom reached
										$numByte++;
										$nByte = MPQFile::readByte($string,$numByte);
									}
									if (($nByte & 0x40) == 0) break; // if non-zero (as in 0x40), rotate segment(2 bytes) follows
								case 0x40: // rotate camera
									$numByte += 2;
							}
							break;
						default:
							$knownEvent = false;
					}
					break;
				case 0x04: // inaction
					if (($eventCode & 0x0F) == 2) { $numByte += 2; break; }
					else if (($eventCode & 0x0C) == 2) break;
					switch($eventCode) {
						case 0x16:
							$numByte += 24;
							break;
						case 0xC6: // unknown
							$numByte += 16;
							break;
						case 0x18:
							$numByte += 4;
							break;
						case 0x1C:
							break;
						case 0x87: //unknown
							$numByte += 4;
							break;
						default:
							$knownEvent = false;
					}
					break;
				case 0x05: // system
					switch($eventCode) {
						case 0x89: //automatic synchronization?
							$numByte += 4;
							break;
						default:
							$knownEvent = false;
					}
					break;
				default:
					$knownEvent = false;
			}
			if ($knownEvent == false) {
				if ($this->debug) $this->debug(sprintf("Unknown event: Timestamp: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
								$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
				return false;
			}
			else if ($this->debug >= 2) $this->debug(sprintf("DEBUG L2: Timestamp: %d, Frames: %d, Type: %d, Global: %d, Player ID: %d (%s), Event code: %02X Byte: %08X<br />\n",
					floor($time / 16),$timeStamp, $eventType, $globalEventFlag,$playerId,$playerName,$eventCode,$numByte));
		}
		// in case ability codes change, populate empty 'race' array index to the locale-specific value
		$teamCounts = array();
		foreach ($this->getActualPlayers() as $val) {
			if ($val['race'] == "") 
				$this->players[$val['id']]['race'] = $val['lrace'];
			if ($this->recorderId > 0 && $val['id'] == $this->recorderId) // if the recorder is a player, add him to the player left -array
				$playerLeft[] = $val['id'];								  // for maximum accuracy in winner detection
			if (isset($teamCounts[$val['team']]))
				$teamCounts[$val['team']]++; // populate the array with the number of players for each team
			else
				$teamCounts[$val['team']] = 1;
		}
		$numLeft = count($playerLeft);
		$numActual = count($this->getActualPlayers());
		$lastLeaver = -1;
		foreach ($playerLeft as $val) {
			$lastLeaver = $val;
			$teamCounts[$this->players[$val]['team']]--;
		}
		// at this point teams with 0 players are clearly the losers (with the exception below). 
		// If there are two or more teams with 1 or more players still left, winner can't be determined.
		// If there is exactly one team with more than 0 players left, that team is the winner
		// If no teams have more than 0 players left, the last leaver's team is the winner.
		$tempWinnerTeam = 0;
		$winnerKnown = false;
		
		for ($i = 1;$i <= 15;$i++) { // maximum number of teams is 15 (ffa)
			if (!isset($teamCounts[$i])) continue;
			if ($teamCounts[$i] > 0 && $tempWinnerTeam == 0) { $winnerKnown = true; $tempWinnerTeam = $i; } // initially set winner as known
			else if ($teamCounts[$i] > 0 && $tempWinnerTeam > 0) { $winnerKnown = false; break; } // more than 1 team with 1 or more players left, winner undeterminable
		}
		if ($tempWinnerTeam == 0 && $lastLeaver > 0) { // this means that no team had more than 0 players left, so use the team of $lastLeaver
			$winnerKnown = true;
			$tempWinnerTeam = $this->players[$lastLeaver]['team'];
		}
		$this->winnerKnown = $winnerKnown;
		if ($winnerKnown) {
			foreach ($this->getActualPlayers() as $val) {
				if ($val['team'] == $tempWinnerTeam) $this->players[$val['id']]['won'] = 1;
				else $this->players[$val['id']]['won'] = 0;
			}
		}
		return $numEvents;
	}
}


?>
