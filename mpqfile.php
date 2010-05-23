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


class MPQFile {
	private $filename;
	private $fp;
	private $hashtable,$blocktable;
	private $hashTableSize, $blocKTableSize;
	private $headerOffset;
	private $init;
	private $verMajor;
	private $build;
	private $sectorSize;
	private $debug;
	private $debugNewline;
	private $gameLen;
	
	function __construct($filename, $autoparse = true, $debug = false) {
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
		
		if (file_exists($this->filename)) {
			$this->fp = fopen($this->filename, 'rb');
			if ($this->debug && $this->fp === false) $this->debug("Error opening file $filename for reading");
		}
		if ($autoparse)
			$this->parseHeader();
	}
	function __destruct() {
		if ($this->fp !== FALSE)
			fclose($this->fp);
	}
	private function debug($message) { echo $message.($this->debugNewline); }
	function setDebugNewline($str) { $this->debugNewline = $str; }
	function setDebug($bool) { $this->debug = $bool; }
	
	function parseHeader() {
		if ($this->fp === FALSE) {
			return false;
			if ($this->debug) $this->debug("Invalid file pointer");
		}
		$fp = $this->fp;
		$headerParsed = false;
		$headerOffset = 0;
		while (!$headerParsed) {
			$magic = unpack("c4",fread($fp,4)); // MPQ 1Bh or 1Ah
			if (($magic[1] != 0x4D) || ($magic[2] != 0x50) || ($magic[3] != 0x51)) { $this->init = MPQ_ERR_NOTMPQFILE; return false; }
			if ($magic[4] == 27) { // user data block (1Bh)
				if ($this->debug) $this->debug(sprintf("Found user data block at %08X",ftell($fp)));
				$uDataMaxSize = $this->readUInt32();
				$headerOffset = $this->readUInt32();
				$this->headerOffset = $headerOffset;
				$uDataSize = $this->readUInt32();
				fseek($fp,24,SEEK_CUR); // skip Starcraft II replay 0x1B 0x32 0x01 0x00
				$verMajor =  $this->readUInt16();
				$this->verMajor = $verMajor;
				$build = $this->readUInt32(true);
				$this->build = $build;
				$build2 = $this->readUInt32(true);
				fseek($fp,2,SEEK_CUR); // skip 02 00
				$gameLen =  $this->readUInt16(true) / 2;
				$this->gameLen = $gameLen;
				
				fseek($fp,$headerOffset);
			}
			else if ($magic[4] == 26) { // header (1Ah)
				if ($this->debug) $this->debug(sprintf("Found header at %08X",ftell($fp)));
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
			else {
				if ($this->debug) $this->debug("Could not find MPQ header");
				return false;
			}
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

		return true;
	}
	
	// read little endian 32-bit integer
	private function readUInt32($bigendian = false) {
		if ($this->fp === FALSE) return false;
		$t = unpack(($bigendian === true)?"N":"V",fread($this->fp,4));
		return $t[1];
	}

	private function readUInt16($bigendian = false) {
		if ($this->fp === FALSE) return false;
		$t = unpack(($bigendian === true)?"n":"v",fread($this->fp,2));
		return $t[1];
	}
	private function readByte() {
		if ($this->fp === FALSE) return false;
		$t = unpack("C",fread($this->fp,1));
		return $t[1];
	}
	
	// read a byte from string and remove the read byte
	private function readSByte(&$string) {
		$t = unpack("C",substr($string,0,1));
		$string = substr($string,1,strlen($string) -1);
		return $t[1];	
	}
	
	function getFileSize($filename) {
		if ($this->init !== MPQFILE_PARSE_OK) {
			if ($this->debug) $this->debug("Tried to use getFileSize without initializing");
			return false;
		}
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
		if ($this->debug) $this->debug("Did not find file $filename in archive");
		return false;
	}
	
	function readFile($filename) {
		if ($this->init !== MPQFILE_PARSE_OK) {
			if ($this->debug) $this->debug("Tried to use getFile without initializing");
			return false;
		}
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
				$compressionType = $this->readSByte($sectorData);
				switch ($compressionType) {
					case 2:
						$output .= deflate_decompress($sectorData);
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
		if ($this->init !== MPQFILE_PARSE_OK) {
			if ($this->debug) $this->debug("Tried to use parseReplay without initializing");
			return false;
		}
		if (class_exists("SC2Replay") || (include 'sc2replay.php')) {
			$tmp = new SC2Replay();
			if ($this->debug) $tmp->setDebug(true);
			$tmp->parseReplay($this);
			return $tmp;
		}
		else {
			if ($this->debug) $this->debug("Unable to find or load class SC2Replay");
			return false;
		}
	}
	
	function getState() {
		return $this->init;
	}
	function getBuild() { return $this->build; }
	function getVersion() { return $this->verMajor; }
	function getGameLength() { return $this->gameLen; }
}
function deflate_decompress($string) {
	if (function_exists("gzinflate")){
		$tmp = gzinflate(substr($string,2,strlen($string) - 2));
		return $tmp;
	}
	if ($this->debug) $this->debug("Function 'gzinflate' does not exist, is gzlib installed as a module?");
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
