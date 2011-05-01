<?php
define('LITTLE_ENDIAN', '<');
define('BIG_ENDIAN', '>');

class ByteBuffer {
    private $contents;
    private $len;
    private $numByte;
    private $bitShift;
    private $lastByte;

    static private $LO_MASKS =     array(0x00, 0x01, 0x03, 0x07, 0x0F, 0x1F, 0x3F, 0x7F, 0xFF);
    static private $LO_MASKS_INV = array(0x00, 0x80, 0xC0, 0xE0, 0xF0, 0xF8, 0xFC, 0xFE, 0xFF);
    static private $HI_MASKS =     array(0xFF, 0xFE, 0xFC, 0xF8, 0xF0, 0xE0, 0xC0, 0x80, 0x00);
    static private $HI_MASKS_INV = array(0xFF, 0x7F, 0x3F, 0x1F, 0x0F, 0x07, 0x03, 0x01, 0x00);

    public function __construct($string) {
        $this->contents = $string;
        $this->len = strlen($string);
        $this->numByte = 0;
        $this->bitShift = 0;
        $this->lastByte = null;
    }
    
    /**
     * Current position
     */
    public function tell() {
        return $this->numByte;
    }
    
    /**
     * Set position
     */
    public function seek($pos) {
        $this->numByte = $pos;
    }

    /**
     * Read byte directly from stream
     */
    private function readBasic($length) {
        $tmp = substr($this->contents, $this->numByte, $length);
        $this->numByte += $length;
        return $tmp;
    }

    /**
     * Read bytes after applying bit shift
     */
    public function readBytes($length = 0) {
        if ($this->bitShift == 0) {
            return $this->readBasic($length);
        } else {
            $chars = array();
            foreach ($this->read($length) as $byte) {
                $chars[] = pack('C', $byte);
            }
            return implode('', $chars);
        }
    }

    /**
     * Wrapper around readBasic that returns the byte
     */
    private function rawByte() {
        $tmp = unpack("C", $this->readBasic(1));
        return $tmp[1];
    }

    /**
     * Read that accounts for bit shift
     */
    public function read($bytes = 0, $bits = 0) {
        $bytes = $bytes + intval($bits / 8);
        $bits = $bits % 8;
        $bitCount = $bytes * 8 + $bits;

        if ($bitCount == 0) {
            return array();
        }

        // read doesn't span multiple bytes
        if ($bitCount <= (8 - $this->bitShift)) {
            return array($this->shift($bitCount));
        }

        // byte-aligned read
        if ($this->bitShift == 0) {
            $base = array();
            for ($i = 0; $i < $bytes; ++$i) {
                $base[] = $this->rawByte();
            }
            if ($bits != 0) {
                $base[] = $this->shift($bits);
            }
            return $base;
        }

        // general case
        $oldBitShift = $this->bitShift;
        $newBitShift = ($this->bitShift + $bits) % 8;
        
        $loMask = self::$LO_MASKS[$oldBitShift];
        $loMaskInv = self::$LO_MASKS_INV[$oldBitShift];
        $hiMask = self::$HI_MASKS[$oldBitShift];
        $hiMaskInv = self::$HI_MASKS_INV[$oldBitShift];

        if ($newBitShift == 0) { // this read is going to re-align the buffer
          $lastMask = 0xFF;
          $adjustment = 8 - $oldBitShift;
        } else {
          $lastMask = self::$LO_MASKS[$newBitShift];
          $adjustment = $newBitShift - $oldBitShift;
        }

        /* loop invariants:
         * each iteration reads at most one byte
         * $first holds the first part of the byte
         * $bitCount is the number of bits remaining to be read
         * $prev is the previous byte
         * $next is the next byte
         * $rawBytes are the bytes that we have already read
         */

        $rawBytes = array();
        $prev = $this->lastByte;
        $next = $this->rawByte();
        $first = $prev & $hiMask;
        $bitCount -= (8 - $oldBitShift);

        while ($bitCount > 0) {
          if ($bitCount <= 8) { // last byte, last iteration
            $last = ($next & $lastMask);
            if ($adjustment < 0) {
              $first = $first >> abs($adjustment);
            }

            $rawBytes[] = $first | ($last >> max($adjustment, 0));
            if ($adjustment > 0) {
              $rawBytes[] = $last & self::$LO_MASKS[$adjustment];
            }
            $bitCount = 0;
          }
          if ($bitCount > 8) { // there are more bytes still, wrapping around
            $second = ($next & $loMaskInv) >> (8 - $oldBitShift);
            $rawBytes[] = $first | $second;

            // To remain consistent, always shift these bits into the hi_mask
            $first = ($next & $hiMaskInv) << $oldBitShift;
            $bitCount -= 8;

            $prev = $next;
            $next = $this->rawByte();
          }
        }
        $this->lastByte = $next;
        $this->bitShift = $newBitShift;
        return $rawBytes;
    }
    
    public function shift($bits) {
        $bitShift = $this->bitShift;
        $newShift = $bitShift + $bits;

        if ($newShift <= 8) {
            if (!$bitShift) {
                $this->lastByte = $this->rawByte();
            }
            $ret = ($this->lastByte >> $bitShift) & self::$LO_MASKS[$bits];
            $this->bitShift = $newShift == 8 ? 0 : $newShift;
            return $ret;
        } else {
            die("Cannot shift off $bits. Only " . (8 - $this->bitShift) . " bits remaining.");
        }
    }

    /*
     * Returns true if there are bytes remaining
     */
    public function hasBytes() {
        return $this->numByte < $this->len;
    }

    /*
     * Skip over $amount bytes
     */
    public function skip($amount) {
        $this->numByte += $amount;
        if ($this->hasBytes() && $this->bitShift != 0) {
          --$this->numByte;
          $this->lastByte = $this->rawByte();
        }
    }

    /*
     * Align to byte boundary
     */
    public function align() {
        $this->bitShift = 0;
    }

    /*
     * Return a byte accounting for any bit shifting
     */
    public function readByte() {
        if ($this->bitShift == 0) {
            return $this->rawByte();
        } else {
            $data = $this->read(1);
            return $data[0];
        }
    }

    /*
     * Return byte string
     */    
    public function readHex($length = 0) {
        $tmp = unpack('H*', $this->readBytes($length));
        return $tmp[1];
    }

    public function readShort($endian = LITTLE_ENDIAN) {
        $chars = $this->readBytes(2);
        if ($endian == LITTLE_ENDIAN) {
            $tmp = unpack("v", $chars);
        } else {
            $tmp = unpack("n", $chars);
        }
        return $tmp[1];
    }

    public function readInt($endian = LITTLE_ENDIAN) {
        $chars = $this->readBytes(4);
        if ($endian == LITTLE_ENDIAN) {
            $tmp = unpack("V", $chars);
        } else {
            $tmp = unpack("N", $chars);
        }
        return $tmp[1];
    }

    // Replay specific structures

    public function readLength() {
      return intval($this->readByte() / 2);
    }

    public function readObjectType($readModifier = false) {
        $type = $this->readShort(BIG_ENDIAN);
        if ($readModifier) {
            $type = ($type << 8) | $this->readByte();
        }
        return $type;
    }

    public function readObjectId() {
        return $this->readInt(BIG_ENDIAN);
    }

    public function readBitmask() {
        $length = $this->readByte();
        $bytes = array_reverse($this->read(0, $length));
        $mask = 0;
        foreach ($bytes as $byte) {
            $mask = ($mask << 8) | $byte;
        }
        return $mask;
    }

    // Timestamps are 1-4 bytes long, length-1 is specified in last 2 bits of first byte
    public function readTimestamp() {
        $first = $this->readByte();
        $count = $first & 0x03;
        $time = $first >> 2;
        for ($i = 0; $i < $count; ++$i) {
            $time = ($time << 8) | $this->readByte();
        }
        return $time;
    }

    private static function combineCoord($coord) {
      $ret = $coord[0];
      $fraction = $coord[1];
      for ($i = 11; $i >= 0; --$i) {
        $ret += (($fraction >> $i) & 1) * pow(2, $i-12); // decreasing significance
      }
      return $ret;
    }

    public function readCoordinate() {
        $coord = $this->read(0, 20);
        return self::combineCoord(array($coord[0], $coord[1] << 4 | $coord[2]));
    }
        
    public function readVariableInt() {        
        $byte = $this->readByte();
        $shift = 1;
        $value = $byte & 0x7F;
        $sign = 1;
        if ($value & 1) {
            $sign = -1;
            $value--;
        }
        while (($byte & 0x80) != 0) {
            $byte = $this->readByte();
            $value += (($byte & 0x7F) * pow(2, $shift * 7));
            $shift++;
        }
        $value *= $sign;
        $value /= 2;
        return $value;
    }
    
    public function readDataStruct() {
        $datatype = $this->readByte();
        if ($datatype == 0x02) {
            // byte string
            $count = $this->readLength();
            return $this->readBytes($count);
        } else if ($datatype == 0x04) {
            // array
            $this->skip(2); // 01 00
            $count = $this->readLength();
            $list = array();
            for ($i = 0; $i < $count; ++$i) {
                $list[] = $this->readDataStruct();
            }
            return $list;
        } else if ($datatype == 0x05) {
            // associative array
            $data = array();
            $count = $this->readLength();
            for ($i = 0; $i < $count; ++$i) {
              $key = $this->readLength(); // keys are single byte integers with signedness as rightmost (0x1) bit
                $data[$key] = $this->readDataStruct();
            }
            return $data;
        } else if ($datatype == 0x06) {
            return $this->readByte();
        } else if ($datatype == 0x07) {
            return $this->readInt();
        } else if ($datatype == 0x09) {
            return $this->readVariableInt();
        }
        throw new UnexpectedValueException("Unknown Data Structure: " . sprintf('%02X', $datatype));
    }
}
