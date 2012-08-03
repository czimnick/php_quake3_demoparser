<?php
	class Q3_ParserState {
		public $Packet;
		public $PacketType;

		public function __construct($p, $type) {
			$this->Packet = $p;
			$this->PacketType = $type;
		}
	}
 
