<?php
	class Q3_ServerCommand {
		public $Command = "";
		public $Message = "";
		public $SequenceNumber = 0;

		public function __construct($cmd, $seq) {
			$this->parseCmd($cmd);
			$this->SequenceNumber = $seq;
		}

		private function parseCmd($cmd) {
			$property = array('Command', 'Message');
			$propertyNum = 0;

			for($i=0; $i<strlen($cmd); $i++) {
				switch(ord($cmd{$i})) {
					case 34: // ignore
						if($propertyNum == 0)
							$this->Command = substr($this->Command, 0, -1);
						$propertyNum++;
						break;

					case 10:
						$this->{$property[$propertyNum]} .= "\\n";
						break;

					case 13:
						$this->{$property[$propertyNum]} .= "\\r";
						break;

					case 9:
						$this->{$property[$propertyNum]} .= "\\t";
						break;

					default:
						$ord = ord($cmd{$i});
						if($ord >= 32 && $ord <= 126)
							$this->$property[$propertyNum] .= $cmd{$i};

						break;
				}
			}
		}
	}