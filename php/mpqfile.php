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



define("MPQ_HASH_TABLE_OFFSET", 0);
define("MPQ_HASH_NAME_A", 1);
define("MPQ_HASH_NAME_B", 2);
define("MPQ_HASH_FILE_KEY", 3);
define("MPQ_HASH_ENTRY_EMPTY", (0xFFFF << 16) | 0xFFFF);
define("MPQ_HASH_ENTRY_DELETED", (0xFFFF << 16) | 0xFFFE);
define("MPQ_NOT_PARSED", 2);
define("MPQ_PARSE_OK", 1);
define("MPQ_ERR_NOTMPQFILE", -1);
define("MPQ_SC2REPLAYFILE", 1);
define("MPQ_UNKNOWNFILE", 0);


class MPQFile {
	private $filename;
	private $fp;
	private $hashtable,$blocktable;
	private $hashTableSize, $blockTableSize;
	private $hashTableOffset, $blockTableOffset;
	private $headerOffset;
	private $init;
	private $verMajor;
	private $build;
	private $sectorSize;
	private $debug;
	private $debugNewline;
	private $gameLen;
	private $versionString;
	public static $cryptTable;
	private $fileType;
	private $fileData;
	
	function __construct($filename, $autoparse = true, $debug = 0) {
		$this->filename = $filename;
		$this->hashtable = NULL;
		$this->blocktable = NULL;
		$this->hashTableSize = 0;
		$this->blockTableSize = 0;
		$this->headerOffset = 0;
		$this->init = false;
		$this->verMajor = 0;
		$this->build = 0;
		$this->gameLen = 0;
		$this->sectorSize = 0;
		$this->debug = $debug;
		$this->debugNewline = "<br />\n";
		$this->versionString = "null";
		$this->fileType = MPQ_UNKNOWNFILE;
		if (!self::$cryptTable)
			self::initCryptTable();
		
		if (file_exists($this->filename)) {
			$fp = fopen($this->filename, 'rb');
			$contents = fread($fp, filesize($this->filename));
			if ($this->debug && $contents === false) $this->debug("Error opening file $filename for reading");
			if ($contents !== false)
				$this->fileData = $contents;
			fclose($fp);
		}
		if ($autoparse)
			$this->parseHeader();
	}
	private function debug($message) { echo $message.($this->debugNewline); }
	function setDebugNewline($str) { $this->debugNewline = $str; }
	function setDebug($bool) { $this->debug = $bool; }
	
	static function readByte($string, &$numByte) {
		$tmp = unpack("C",substr($string,$numByte,1));
		$numByte++;
		return $tmp[1];
	}
	static function readBytes($string, &$numByte, $length) {
		$tmp = substr($string,$numByte,$length);
		$numByte += $length;
		return $tmp;
	}
	static function readUInt16($string, &$numByte) {
		$tmp = unpack("v",substr($string,$numByte,2));
		$numByte += 2;
		return $tmp[1];
	}
	static function readUInt32($string, &$numByte) {
		$tmp = unpack("V",substr($string,$numByte,4));
		$numByte += 4;
		return $tmp[1];
	}
	function parseHeader() {
		$fp = 0;
		$headerParsed = false;
		$headerOffset = 0;
		while (!$headerParsed) {
			$magic = unpack("c4",self::readBytes($this->fileData,$fp,4)); // MPQ 1Bh or 1Ah
			if (($magic[1] != 0x4D) || ($magic[2] != 0x50) || ($magic[3] != 0x51)) { $this->init = MPQ_ERR_NOTMPQFILE; return false; }
			if ($magic[4] == 27) { // user data block (1Bh)
				if ($this->debug) $this->debug(sprintf("Found user data block at %08X",$fp));
				$uDataMaxSize = self::readUInt32($this->fileData, $fp);
				$headerOffset = self::readUInt32($this->fileData, $fp);
				$this->headerOffset = $headerOffset;
				$uDataSize = self::readUInt32($this->fileData, $fp);
				$uDataStart = $fp;
				//fseek($fp,5,SEEK_CUR); // skip 05 08 00 02 2c
				//fseek($fp,24,SEEK_CUR); // skip Starcraft II replay 0x1B 0x32 0x01 0x00
				//fseek($fp,25,SEEK_CUR); // skip Starcraft II replay 0x1B 0x31 0x31 0x02 0x05 0x0c
				//fseek($fp,30,SEEK_CUR);
				$fileTypeHeader = self::readUInt16($this->fileData, $fp);
				$fp -= 2;
				if ($uDataSize == 0 || $fileTypeHeader != 0x0805) { // file is not replay file, so skip following section
					if ($this->debug) $this->debug(sprintf("Unknown user data block(%04X), skipping...",$fileTypeHeader));
					$fp = $headerOffset;
					continue;
				}
				if ($this->debug) $this->debug(sprintf("File seems to be a Starcraft 2 replay file."));
				$this->fileType = MPQ_SC2REPLAYFILE;
				$fp += 30;
				$loop = 0;
				$versiontemp1 = 0;
				$versiontemp2 = 0;
				while ($fp < ($uDataSize + $uDataStart)) {
					$key = unpack("C2",self::readBytes($this->fileData,$fp,2));
					$value = $this->parseKeyVal($this->fileData,$fp);
					if ($this->debug)
						$this->debug(sprintf("User header key: %02X %02X value: %d",$key[1],$key[2],$value));
					if ($loop == 0) {
						switch ($key[1]) {
							case 0x02: // major version
								$this->verMajor = $value / 2;
								break;	
							case 0x04: // minor version
								$versiontemp1 = $value / 2;
								break;
							case 0x06: // minorer? version
								$versiontemp2 = $value / 2;
								break;
							case 0x08;
								$this->build = $value / 2;
								$loop++;
								break;
							default:
						}
					}
					if ($loop == 1) {
						switch ($key[1]) {
							case 0x06:
								$this->gameLen = ceil($value / 32);
								break;
							default:
						}
					}
				}
				$this->versionString = sprintf("%d.%d.%d.%d",$this->verMajor,$versiontemp1,$versiontemp2,$this->build);
				/*
				$verMajor =  $this->readUInt16();
				$this->verMajor = $verMajor;
				$build = $this->readUInt32(true);
				$this->build = $build;
				$build2 = $this->readUInt32(true);
				fseek($fp,2,SEEK_CUR); // skip 02 00
				$gameLen =  $this->readUInt16(true) / 2;
				$this->gameLen = $gameLen;
				*/
				$fp = $headerOffset;
			}
			else if ($magic[4] == 26) { // header (1Ah)
				if ($this->debug) $this->debug(sprintf("Found header at %08X",$fp));
				$headerSize = self::readUInt32($this->fileData, $fp);
				$archiveSize = self::readUInt32($this->fileData, $fp);
				$formatVersion = self::readUInt16($this->fileData, $fp);
				$sectorSizeShift = self::readByte($this->fileData, $fp);
				$sectorSize = 512 * pow(2,$sectorSizeShift);
				$this->sectorSize = $sectorSize;
				$fp++;
				$hashTableOffset = self::readUInt32($this->fileData, $fp) + $headerOffset;
				$this->hashTableOffset = $hashTableOffset;
				$blockTableOffset = self::readUInt32($this->fileData, $fp) + $headerOffset; 
				$this->blockTableOffset = $blockTableOffset;
				$hashTableEntries = self::readUInt32($this->fileData, $fp);
				$this->hashTableSize = $hashTableEntries;
				$blockTableEntries = self::readUInt32($this->fileData, $fp);
				$this->blockTableSize = $blockTableEntries;
				
				$headerParsed = true;
			}
			else {
				if ($this->debug) $this->debug("Could not find MPQ header");
				return false;
			}
		}
		// read and decode the hash table
		$fp = $hashTableOffset;
		$hashSize = $hashTableEntries * 4; // hash table size in 4-byte chunks
		$tmp = array();
		for ($i = 0;$i < $hashSize;$i++)
			$tmp[$i] = self::readUInt32($this->fileData, $fp);
		if ($this->debug) {
			$this->debug("Encrypted hash table:");
			$this->printTable($tmp);
		}
		$hashTable = self::decryptStuff($tmp,self::hashStuff("(hash table)", MPQ_HASH_FILE_KEY));
		if ($this->debug) {
			$this->debug("DEBUG: Hash table");
			$this->debug("HashA, HashB, Language+platform, Fileblockindex");
			$tmpnewline = $this->debugNewline;
			$this->debugNewline = "";
			for ($i = 0;$i < $hashTableEntries;$i++) {
				$filehashA = $hashTable[$i*4];
				$filehashB = $hashTable[$i*4 +1];
				$lanplat = $hashTable[$i*4 +2];
				$blockindex = $hashTable[$i*4 +3];
				$this->debug(sprintf("<pre>%08X %08X %08X %08X</pre>",$filehashA, $filehashB, $lanplat, $blockindex));
			}
			$this->debugNewline = $tmpnewline;
		}		
		// read and decode the block table
		$fp = $blockTableOffset;
		$blockSize = $blockTableEntries * 4; // block table size in 4-byte chunks
		$tmp = array();
		for ($i = 0;$i < $blockSize;$i++)
			$tmp[$i] = self::readUInt32($this->fileData, $fp);
		if ($this->debug) {
			$this->debug("Encrypted block table:");
			$this->printTable($tmp);
		}

		$blockTable = self::decryptStuff($tmp,self::hashStuff("(block table)", MPQ_HASH_FILE_KEY));		
		$this->hashtable = $hashTable;
		$this->blocktable = $blockTable;
		if ($this->debug) {
			$this->debug("DEBUG: Block table");
			$this->debug("Offset, Blocksize, Filesize, flags");
			$tmpnewline = $this->debugNewline;
			$this->debugNewline = "";
			for ($i = 0;$i < $blockTableEntries;$i++) {
				$blockIndex = $i * 4;
				$blockOffset = $this->blocktable[$blockIndex] + $this->headerOffset;
				$blockSize = $this->blocktable[$blockIndex + 1];
				$fileSize = $this->blocktable[$blockIndex + 2];
				$flags = $this->blocktable[$blockIndex + 3];
				$this->debug(sprintf("<pre>%08X %8d %8d %08X</pre>",$blockOffset, $blockSize, $fileSize, $flags));
			}
			$this->debugNewline = $tmpnewline;
		}
		$this->init = MPQ_PARSE_OK;

		return true;
	}
	

	function getFileSize($filename) {
		if ($this->init !== MPQ_PARSE_OK) {
			if ($this->debug) $this->debug("Tried to use getFileSize without initializing");
			return false;
		}
		$hashA = self::hashStuff($filename, MPQ_HASH_NAME_A);
		$hashB = self::hashStuff($filename, MPQ_HASH_NAME_B);
		$hashStart = self::hashStuff($filename, MPQ_HASH_TABLE_OFFSET) & ($this->hashTableSize - 1);
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
		if ($this->debug) $this->debug("Did not find file $filename in archive");
		return false;
	}
	
	function readFile($filename) {
		if ($this->init !== MPQ_PARSE_OK) {
			if ($this->debug) $this->debug("Tried to use getFile without initializing");
			return false;
		}
		$hashA = self::hashStuff($filename, MPQ_HASH_NAME_A);
		$hashB = self::hashStuff($filename, MPQ_HASH_NAME_B);
		$hashStart = self::hashStuff($filename, MPQ_HASH_TABLE_OFFSET) & ($this->hashTableSize - 1);
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
		if ($blockSize == -1) {
			if ($this->debug) $this->debug("Did not find file $filename in archive");
			return false;
		}
		$flag_file       = $flags & 0x80000000;
		$flag_checksums  = $flags & 0x04000000;
		$flag_deleted    = $flags & 0x02000000;
		$flag_singleunit = $flags & 0x01000000;
		$flag_hEncrypted = $flags & 0x00020000;
		$flag_encrypted  = $flags & 0x00010000;
		$flag_compressed = $flags & 0x00000200;
		$flag_imploded   = $flags & 0x00000100;
		
		if ($this->debug) $this->debug(sprintf("Found file $filename with flags %08X, block offset %08X, block size %d and file size %d",
										$flags, $blockOffset,$blockSize,$fileSize));
		
		if (!$flag_file) return false;
		$fp = $blockOffset;
		if ($flag_checksums) {
			for ($i = $fileSize;$i > 0;$i -= $this->sectorSize) {
				$sectors[] = self::readUInt32($this->fileData, $fp);
				$blockSize -= 4;
			}
			$sectors[] = self::readUInt32($this->fileData, $fp);
			$blockSize -= 4;
		}
		else {
			$sectors[] = 0;
			$sectors[] = $blockSize;
		}
		$c  = count($sectors) - 1;
		$totDur = 0;
		$output = "";
		for ($i = 0;$i < $c;$i++) {
			$sectorLen = $sectors[$i + 1] - $sectors[$i];
			if ($sectorLen == 0) break;
			$fp = $blockOffset + $sectors[$i];
			$sectorData = self::readBytes($this->fileData, $fp,$sectorLen);
			if ($this->debug) $this->debug(sprintf("Got %d bytes of sector data",strlen($sectorData)));
			if ($flag_compressed && (($flag_singleunit && ($blockSize < $fileSize)) || ($flag_checksums && ($sectorLen <  $this->sectorSize)))) {
				$numByte = 0;
				$compressionType = self::readByte($sectorData,$numByte);
				$sectorData = substr($sectorData,1);
				switch ($compressionType) {
					case 0x02:
						if ($this->debug) $this->debug("Compression type: gzlib");
						$output .= self::deflate_decompress($sectorData);
						break;
					case 0x10:
						if ($this->debug) $this->debug("Compression type: bzip2");
						$output .= self::bzip2_decompress($sectorData);
						break;
					default:
						if ($this->debug) $this->debug(sprintf("Unknown compression type: %d",$compressionType));
						return false;
				}
			}
			else $output .= $sectorData;
		}
		if (strlen($output) != $fileSize) {
			if ($this->debug) $this->debug(sprintf("Decrypted/uncompressed file size(%d) does not match original file size(%d)",
											strlen($output),$fileSize));
			return false;
		}
		return $output;
	}
	
	function parseReplay() {
		if ($this->init !== MPQ_PARSE_OK) {
			if ($this->debug) $this->debug("Tried to use parseReplay without initializing");
			return false;
		}
		if (class_exists("SC2Replay") || (include 'sc2replay.php')) {
			$tmp = new SC2Replay();
			if ($this->debug) $tmp->setDebug($this->debug);
			$tmp->parseReplay($this);
			return $tmp;
		}
		else {
			if ($this->debug) $this->debug("Unable to find or load class SC2Replay");
			return false;
		}
	}
	function isParsed() {
		return $this->init === MPQ_PARSE_OK;
	}
	function getState() {
		return $this->init;
	}
	function getFileType() { return $this->fileType; }
	function getBuild() { return $this->build; }
	function getVersion() { return $this->verMajor; }
	function getVersionString() { return $this->versionString; }
	function getHashTable() { return $this->hashtable; }
	function getBlockTable() { return $this->blocktable; }
	function getGameLength() { return $this->gameLen; }
	// prints block table or hash table, $data is the data in an array of UInt32s
	function printTable($data) {
		$this->debug("Hash table: HashA, HashB, Language+platform, Fileblockindex");
		$this->debug("Block table: Offset, Blocksize, Filesize, flags");
		$entries = count($data) / 4;
		$tmpnewline = $this->debugNewline;
		$this->debugNewline = "";
		for ($i = 0;$i < $entries;$i++) {
			$blockIndex = $i * 4;
			$blockOffset = $data[$blockIndex] + $this->headerOffset;
			$blockSize = $data[$blockIndex + 1];
			$fileSize = $data[$blockIndex + 2];
			$flags = $data[$blockIndex + 3];
			$this->debug(sprintf("<pre>%08X %08X %08X %08X</pre>",$blockOffset, $blockSize, $fileSize, $flags));
		}
		$this->debugNewline = $tmpnewline;
	}
	
	// the following replaces a file in the archive, meaning a file with that filename must be present already.
	function replaceFile($filename, $filedata) {
		if ($this->getFileSize($filename) === false || strlen($filedata) == 0) return false;
		if ($this->init !== MPQ_PARSE_OK) {
			if ($this->debug) $this->debug("Tried to use replaceFile without initializing");
			return false;
		}
		$hashA = self::hashStuff($filename, MPQ_HASH_NAME_A);
		$hashB = self::hashStuff($filename, MPQ_HASH_NAME_B);
		$hashStart = self::hashStuff($filename, MPQ_HASH_TABLE_OFFSET) & ($this->hashTableSize - 1);
		$tmp = $hashStart;
		$blockIndex = -1;
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
		if ($blockIndex == -1) return false;

		// fix block table offsets
		/*for ($i = 0;$i < $this->blockTableSize;$i++) {
			if ($i == $blockIndex) continue;
			if ($this->blocktable[$i*4] > ($blockOffset - $this->headerOffset))
				$this->blocktable[$i*4] -= $blockSize;
		}
		*/
		// remove the original file contents
		//$this->fileData = substr_replace($this->fileData,'',$blockOffset,$blockSize);		
		$this->fileData = substr_replace($this->fileData,str_repeat(chr(0),$blockSize),$blockOffset,$blockSize);		
		$newFileSize = strlen($filedata);
		// attempt to use bzip2 compression
		$compressedData =  chr(16) . bzcompress($filedata);
		//$compressedData = $filedata;
		$newBlockOffset = strlen($this->fileData) - $this->headerOffset;
		if (strlen($compressedData) >= $newFileSize) {
			$newFlags = 0x81000000;
			$compressedData = $filedata;
			$newBlockSize = $newFileSize;
		}
		else {
			$newFlags = 0x81000200;
			$newBlockSize = strlen($compressedData);
		}
		
		// populate variables
		$this->fileData = substr_replace($compressedData,$this->fileData,0,0);
		$this->blocktable[$blockIndex] = $newBlockOffset;
		$this->blocktable[$blockIndex + 1] = $newBlockSize;
		$this->blocktable[$blockIndex + 2] = $newFileSize;
		$this->blocktable[$blockIndex + 3] = $newFlags;
		// encrypt the block table
		$resultBlockTable = self::encryptStuff($this->blocktable,self::hashStuff("(block table)", MPQ_HASH_FILE_KEY));		
		// replace the block table in fileData variable
		for ($i = 0;$i < $this->blockTableSize;$i++) {
			$this->fileData = substr_replace($this->fileData, pack("V",$resultBlockTable[$i]), $this->blockTableOffset + $i * 4, 4); 
		}
		return true;
	}

	function insertChatLogMessage($newMessage, $player, $time) {
		if ($this->init !== MPQ_PARSE_OK || $this->getFileSize("replay.message.events") == 0) return false;
		$string = $this->readFile("replay.message.events");
		$numByte = 0;
		$time = $time * 16;
		$fileSize = strlen($string);
		$messageSize = strlen($newMessage);
		if ($messageSize >= 256) return;
		$totTime = 0;
		while ($numByte < $fileSize) {
			$pastHeaders = true;
			$start = $numByte;
			$timestamp = SC2Replay::parseTimeStamp($string,$numByte);
			$playerId = self::readByte($string,$numByte);
			$opcode = self::readByte($string,$numByte);
			$totTime += $timestamp;
			if ($opcode == 0x80) {
				$numByte += 4;
				$pastHeaders = false;
			}
			else if (($opcode & 0x80) == 0) { // message
				$messageTarget = $opcode & 3;
				$messageLength = self::readByte($string,$numByte);
				if (($opcode & 8) == 8) $messageLength += 64;
				if (($opcode & 16) == 16) $messageLength += 128;
				$message = self::readBytes($string,$numByte,$messageLength);
			}
			else if ($opcode == 0x83) { // ping on map? 8 bytes?
				$numByte += 8;
			}
			if ($pastHeaders && ($totTime >= $time)) {
				$opcode = 0;
				if ($messageSize >= 128) {
					$opcode = $opcode | 16;
					$messageSize -= 128;
				}
				if ($messageSize >= 64) {
					$opcode = $opcode | 8;
					$messageSize -= 64;
				}
				$messageString = pack("c4", 4, $player, $opcode, $messageSize). $newMessage;
				$newData = substr_replace($string, $messageString, $start, 0);
				$this->replaceFile("replay.message.events", $newData);
				return true;
			}
		}
		return true;
	}
	// $obsName is the fake observer name, $string is the contents of replay.initData file
	function addFakeObserver($obsName) {
		if ($this->init !== MPQ_PARSE_OK || $this->getFileSize("replay.initData") == 0) return false;
		$string = $this->readFile("replay.initData");
		$numByte = 0;
		$numPlayers = MPQFile::readByte($string,$numByte);
		for ($i = 1;$i <= $numPlayers;$i++) {
			$nickLen = MPQFile::readByte($string,$numByte);
			if ($nickLen > 0) {
				$numByte += $nickLen;
				$numByte += 5;
			} 
			else {
				// first empty slot
				$numByte--;
				if ($i == $numPlayers)
					$len = 5;
				else
					$len = 6;
				$obsNameLength = strlen($obsName);
				$repString = chr($obsNameLength) . $obsName . str_repeat(chr(0),5);
				$newData = substr_replace($string,$repString,$numByte,$len);
				$this->replaceFile("replay.initData", $newData);
				return $i;
			}
		}
		return false;
	}
	
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
	
	function deflate_decompress($string) {
		if (function_exists("gzinflate")){
			$tmp = gzinflate(substr($string,2,strlen($string) - 2));
			return $tmp;
		}
		if ($this->debug) $this->debug("Function 'gzinflate' does not exist, is gzlib installed as a module?");
		return false;
	}
	function bzip2_decompress($string) {
		if (function_exists("bzdecompress")){
			$tmp = bzdecompress($string);
			if (is_numeric($tmp) && $this->debug) {
				$this->debug(sprintf("Bzip2 returned error code: %d",$tmp));
			}
			return $tmp;
		}
		if ($this->debug) $this->debug("Function 'bzdecompress' does not exist, is bzip2 installed as a module?");
		return false;
	}


	static function initCryptTable() {
		if (!self::$cryptTable)
			self::$cryptTable = array();
		$seed = 0x00100001;
		$index1 = 0;
		$index2 = 0;
		
		for ($index1 = 0; $index1 < 0x100; $index1++) {
			for ($index2 = $index1, $i = 0; $i < 5; $i++, $index2 += 0x100) {
				$seed = (uPlus($seed * 125,3)) % 0x2AAAAB;
				$temp1 = ($seed & 0xFFFF) << 0x10;
				
				$seed = (uPlus($seed * 125,3)) % 0x2AAAAB;
				$temp2 = ($seed & 0xFFFF);
				
				self::$cryptTable[$index2] = ($temp1 | $temp2);
			}
		}
	}

	static function hashStuff($string, $hashType) {
		$seed1 = 0x7FED7FED;
		$seed2 = ((0xEEEE << 16) | 0xEEEE);
		$strLen = strlen($string);
		
		for ($i = 0;$i < $strLen;$i++) {
			$next = ord(strtoupper(substr($string, $i, 1)));

			$seed1 = self::$cryptTable[($hashType << 8) + $next] ^ (uPlus($seed1,$seed2));
			$seed2 = uPlus(uPlus(uPlus(uPlus($next,$seed1),$seed2),$seed2 << 5),3);
		}
		return $seed1;
	}

	static function decryptStuff($data, $key) {
		$seed = ((0xEEEE << 16) | 0xEEEE);
		$datalen = count($data);
		for($i = 0;$i < $datalen;$i++) {
			$seed = uPlus($seed,self::$cryptTable[0x400 + ($key & 0xFF)]);
			$ch = $data[$i] ^ (uPlus($key,$seed));

			$data[$i] = $ch & ((0xFFFF << 16) | 0xFFFF);

			$key = (uPlus(((~$key) << 0x15), 0x11111111)) | (rShift($key,0x0B));
			$seed = uPlus(uPlus(uPlus($ch,$seed),($seed << 5)),3);
		}
		return $data;
	}
	static function encryptStuff($data, $key) {
		$seed = ((0xEEEE << 16) | 0xEEEE);
		$datalen = count($data);
		for($i = 0;$i < $datalen;$i++) {
			$seed = uPlus($seed,self::$cryptTable[0x400 + ($key & 0xFF)]);
			$ch = $data[$i] ^ (uPlus($key,$seed));

			$key = (uPlus(((~$key) << 0x15), 0x11111111)) | (rShift($key,0x0B));
			$seed = uPlus(uPlus(uPlus($data[$i],$seed),($seed << 5)),3);
			$data[$i] = $ch & ((0xFFFF << 16) | 0xFFFF);
		}
		return $data;
	}
}

function microtime_float() {
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}

// function that adds up two integers without allowing them to overflow to floats
function uPlus($o1, $o2) {
	$o1h = ($o1 >> 16) & 0xFFFF;
	$o1l = $o1 & 0xFFFF;
	
	$o2h = ($o2 >> 16) & 0xFFFF;
	$o2l = $o2 & 0xFFFF;	

	$ol = $o1l + $o2l;
	$oh = $o1h + $o2h;
	if ($ol > 0xFFFF) { $oh += (($ol >> 16) & 0xFFFF); }
	return ((($oh << 16) & (0xFFFF << 16)) | ($ol & 0xFFFF));
}

// right shift without preserving the sign(leftmost) bit
function rShift($num,$bits) {
	return (($num >> 1) & 0x7FFFFFFF) >> ($bits - 1);
}

?>