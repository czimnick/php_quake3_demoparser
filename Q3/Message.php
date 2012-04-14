<?php
	// some parts converted from source code of quake3 (id software)
	
	define("Q3_MAX_MSGLEN", 16384);
	define("Q3_BIG_INFO_STRING", 8192);
	define("MAX_STRING_CHARS", 1024);

	require_once("Huffman/Decompressor.php");
	require_once("Huffman/Node.php");

	class Q3_Message {
		private $Data = "";

		public $MaxSize = 0;
		public $CurSize = 0;
		public $ReadCount = 0;
		public $Bit = 0;

		private $huffman = null;

		public function __construct($len) {
			$this->huffman = Q3_Huffman_Decompressor::create();

			$this->MaxSize = $len;
		}

		public function setData($data) {
			$this->Data = $data;
		}

		public function ReadBits($bits) {
			$sgn = false;
			$value = 0;

			if($bits < 0) {
				$bits = -$bits;
				$sgn = true;
			}

			$nbits = 0;
			if($bits&7) {
				$nbits = $bits&7;
				for($i=0;$i<$nbits;$i++) {
					$value |= (Q3_Huffman_Decompressor::GetBit($this->Data, $this->Bit)<<$i);
				}
				$bits = $bits - $nbits;
			}

			if ($bits) {
				for($i=0;$i<$bits;$i+=8) {
					$get = $this->huffman->OffsetReceive($this->Data, $this->Bit);
					$value |= ($get<<($i+$nbits));
				}
			}
			$this->ReadCount = ($this->Bit>>3)+1;

			if ( $sgn ) {
				if ( $value & ( 1 << ( $bits - 1 ) ) ) {
					$value |= -1 ^ ( ( 1 << $bits ) - 1 );
				}
			}

			return $value;
		}

		public function ReadBigString() {
			$maxLen = 8192;
			$curLen = 0;
			$data = "";

			for($i = 0; $i < 8192; $i++) {
				$byte = $this->ReadByte();
				if($byte == -1 || $byte == 0) break;

				// translate all fmt spec to avoid crash bugs (c++)
				if($byte == '%') $byte = ord(".");

				$data .= chr($byte);
			}

			return $data;
		}

		public function ReadString() {
			$maxLen = 1024;
			$curLen = 0;
			$data = "";

			for($i = 0; $i < 1024; $i++) {
				$byte = $this->ReadByte();
				if($byte == -1 || $byte == 0) break;

				// translate all fmt spec to avoid crash bugs (c++)
				if($byte == '%') $byte = ord(".");

				// don't allow higher ascii values
				if($byte > 127) $byte = ord(".");
				$data .= chr($byte);
			}

			return $data;
		}

		public function ReadData($len) {
			$data = array();

			for ($i=0 ; $i<$len ; $i++) {
				$data[$i] = (int)$this->ReadByte();
			}

			return $data;
		}

		public function ReadLong() { return $this->Read(32); }
		public function ReadShort() { return $this->Read(16); }
		public function ReadByte() { return $this->Read(8); }

		private function Read($bits) {
			$c = $this->ReadBits($bits);

			if($this->ReadCount > $this->CurSize)
				$c = -1;

			return $c;
		}
	}
