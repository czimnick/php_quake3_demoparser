<?php
	// some parts converted from source code of quake3 (id software)

	define("Q3_DEMOPARSER_STATE_PARSING", 1);
	define("Q3_DEMOPARSER_STATE_FINISHED", 2);
	define("Q3_DEMOPARSER_STATE_ERROR", 3);

	define("Q3_DEMOPARSER_GENTITYNUM_BITS", 10);

	define("Q3_DEMOPARSER_MAX_RELIABLE_COMMANDS", 64);
	define("Q3_DEMOPARSER_MAX_CONFIGSTRINGS", 1024);
	define("Q3_DEMOPARSER_MAX_GAMESTATE_CHARS", 16000);

	define("Q3_DEMOPARSER_MAX_GENTITIES", (1 << Q3_DEMOPARSER_GENTITYNUM_BITS));

	define("Q3_DEMOPARSER_SVC_NOP", 1);
	define("Q3_DEMOPARSER_SVC_GAMESTATE", 2);
	define("Q3_DEMOPARSER_SVC_CONFIGSTRING", 3);
	define("Q3_DEMOPARSER_SVC_BASELINE", 4);
	define("Q3_DEMOPARSER_SVC_SERVERCOMMAND", 5);
	define("Q3_DEMOPARSER_SVC_DOWNLOAD", 6); // skipped in demo messages...
	define("Q3_DEMOPARSER_SVC_SNAPSHOT", 7);
	define("Q3_DEMOPARSER_SVC_EOF", 8);

	require_once("Message.php");
	require_once("GameState.php");

	class Q3_DemoParser {
		private $fileHandler = null;
		private $state;

		private $configStrings = array();

		public $gameState = null;
		public $gameStates = array();

		// magic dont touch!!!
		// count of bits for every netField from entityState_t (quake3)
		private $entityStateFieldBits = array(32, 0, 0, 0, 0, 0, 0, 0, 0, 10, 0, 8, 8, 8, 8, Q3_DEMOPARSER_GENTITYNUM_BITS, 8, 19, Q3_DEMOPARSER_GENTITYNUM_BITS, 8, 8, 0, 32, 8, 0, 0, 0, 24, 16, 8, Q3_DEMOPARSER_GENTITYNUM_BITS, 8, 8, 0, 0, 0, 8, 0, 32, 32, 32, 0, 0, 0, 0, 32, 0, 0, 0, 32, 16);

		public function __construct($filename) {
			$this->fileHandler = fopen($filename, "r");
			if(!file_exists($filename))
				throw new Exception("can't open demofile $filename...");

			$this->gameState = new Q3_GameState();

			$this->state = Q3_DEMOPARSER_STATE_PARSING;

			while($this->state == Q3_DEMOPARSER_STATE_PARSING)
				$this->readDemoMessage();

			fclose($this->fileHandler);
			$this->fileHandler = null;
		}

		public function __destruct() {
			if($this->fileHandler) fclose($this->fileHandler);
		}

		private function readDemoMessage() {
			if(($this->gameState->ServerMessageSequence = $this->readIntegerFromStream()) === false) {
				$this->state = Q3_DEMOPARSER_STATE_FINISHED;
				return;
			}

			$msg = new Q3_Message(Q3_MAX_MSGLEN);

			if( ($msg->CurSize = $this->readIntegerFromStream()) === false) {
				$this->state = Q3_DEMOPARSER_STATE_FINISHED;
				return;
			}

			if($msg->CurSize > $msg->MaxSize) {
				// msglen > maxlen...!!!
				$this->state = Q3_DEMOPARSER_STATE_ERROR;
				return;
			}

			if( ($data = fread($this->fileHandler, $msg->CurSize)) === false) {
				// demo file was truncated...
				$this->state = Q3_DEMOPARSER_STATE_ERROR;
			}

			$msg->setData($data);
			$msg->ReadCount = 0;

			$this->parseServerMessage($msg);
		}

		private function parseServerMessage(Q3_Message &$msg) {
			$this->gameState->ReliableAcknowledge = $msg->ReadLong();

			if($this->gameState->ReliableAcknowledge < $this->gameState->ReliableSequence - Q3_DEMOPARSER_MAX_RELIABLE_COMMANDS)
				$this->gameState->ReliableAcknowledge = $this->gameState->ReliableSequence;

			while(true) {
				if($msg->ReadCount > $msg->CurSize) {
					// ParseServerMessage: read past end of server message
					$this->state = Q3_DEMOPARSER_STATE_ERROR;
					return;
				}

				$cmd = $msg->ReadByte();

				if($cmd == Q3_DEMOPARSER_SVC_EOF)
					break;

				switch($cmd) {
					default:
						//  illegible server message.
						$this->state = Q3_DEMOPARSER_STATE_ERROR;
						return;
						break;

					case Q3_DEMOPARSER_SVC_NOP:
						// do nothing...
						break;

					case Q3_DEMOPARSER_SVC_GAMESTATE:
						$this->parseGameState($msg);
						break;

					case Q3_DEMOPARSER_SVC_SERVERCOMMAND:
						$this->state = Q3_DEMOPARSER_STATE_FINISHED;
						return;
						//$this->parseServerCommand($msg);
						// TODO: implement parseSnapShot
						break;

					case Q3_DEMOPARSER_SVC_SNAPSHOT:
						$this->state = Q3_DEMOPARSER_STATE_FINISHED;
						//$this->parseSnapShot($msg);
						// TODO: implement parseSnapShot
						return;
						break;

					case Q3_DEMOPARSER_SVC_DOWNLOAD:
						// do nothing... (i think its skipped in demo files)
						// $this->parseDownload();
						break;
				}
			}
		}

		private function parseGameState(Q3_Message &$msg) {
			$gameDataLen = 0;
			array_push($this->gameStates, $this->gameState);
			$this->gameState = new Q3_GameState();

			$this->gameState->ServerCommandSequence = $msg->ReadLong();

			while(true) {
				$cmd = $msg->ReadByte();

				if($cmd == Q3_DEMOPARSER_SVC_EOF)
					break;

				if($cmd == Q3_DEMOPARSER_SVC_CONFIGSTRING) {
					$configStringNum = $msg->ReadShort();
					if($configStringNum < 0 || $configStringNum >= Q3_DEMOPARSER_MAX_CONFIGSTRINGS) {
						// configstrings > MAX_CONFIGSTRINGS
						$this->state = Q3_DEMOPARSER_STATE_ERROR;
						return;
					}

					$configString = $msg->ReadBigString();
					$this->gameState->ConfigStrings[$configStringNum] = $configString;

					if( ($gameDataLen + 1 + strlen($configString)) > Q3_DEMOPARSER_MAX_GAMESTATE_CHARS) {
						// quake3 allow max 16000 gameState data because his char array in c struct is only Q3_DEMOPARSER_MAX_GAMESTATE_CHARS bytes (gameState_t.stringData)
						$this->state = Q3_DEMOPARSER_STATE_ERROR;
						return;
					}

					$gameDataLen += strlen($configString) + 1; // for quake3 max gamestate chars check...

				}elseif($cmd == Q3_DEMOPARSER_SVC_BASELINE) {
					$newNum = $msg->ReadBits(Q3_DEMOPARSER_GENTITYNUM_BITS);
					if($newNum < 0 || $newNum >= Q3_DEMOPARSER_MAX_GENTITIES) {
						// baseline entities out of range...
						$this->state = Q3_DEMOPARSER_STATE_ERROR;
						return;
					}

					$this->parseEntity($msg, $newNum);

				}else{
					// bad gamestate command byte....
					$this->state = Q3_DEMOPARSER_STATE_ERROR;
					return;
				}
			}

			$this->gameState->ClientNum = $msg->ReadLong();
			$this->gameState->ChecksumFeed = $msg->ReadLong();
		}

		private function parseEntity(Q3_Message &$msg, $num) {
			// throw all into >> /dev/null

			if($num < 0 || $num >= Q3_DEMOPARSER_MAX_GENTITIES) {
				$this->state = Q3_DEMOPARSER_STATE_ERROR;
				return;
			}

			if($msg->ReadBits(1) == 1)
				return;

			if($msg->ReadBits(1) == 0)
				return;

			$lc = $msg->ReadByte();

			for($i = 0, $field = 0; $i < $lc; $i++, $field++) {
				if($msg->ReadBits(1) == 1) {
					if($this->entityStateFieldBits[$field] == 0) {
						if($msg->ReadBits(1) == 1) {
							if($msg->ReadBits(1) == 0)
								$msg->ReadBits(13); // FLOAT_IN_BITS (QUAKE3)
							else
								$msg->ReadBits(32); // full floating point value (QUAKE3)
						}
					}elseif($msg->ReadBits(1) == 1) {
						$msg->ReadBits($this->entityStateFieldBits[$field]); // magic structure copy from quake3 ;)
					}
				}
			}
		}

		private function readIntegerFromStream() {
			$data = fread($this->fileHandler, 4);
			if($data === false) {
				$this->state = Q3_DEMOPARSER_STATE_FINISHED;
				return false;
			}

			$data = unpack("L", $data);
			return $data[1];
		}
	}
