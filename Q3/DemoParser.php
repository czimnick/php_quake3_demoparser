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
	require_once("Exception.php");
	require_once("ServerCommand.php");
	require_once("Snapshot.php");
	require_once("PlayerState.php");
	require_once("EntityState.php");
	require_once("ParserState.php");

	class Q3_DemoParser {
		private $fileHandler = null;
		private $state;
		private $cycle;
		private $configStrings = array();
		private $entityBaselines = array();
		private $parseEntities = array();
		private $parseEntitiesNum = 0;
		private $packetLoop = array();


		/**
		 * @var Q3_GameState
		 */
		public $currentGameState = null;
		public $gameStates = array();

		public $ErrorString = null;

		/**
		 * @var array|Q3_Snapshot
		 */
		public $snapshots = array();

		/**
		 * @var Q3_Snapshot
		 */
		public $snap;

		public function __construct($filename) {
			$this->snap = new Q3_Snapshot();

			for($i=0; $i<1024; $i++)
				$this->entityBaselines[$i] = new Q3_EntityState();

			for($i=0; $i<2048; $i++)
				$this->parseEntities[$i] = new Q3_EntityState();

			for($i=0; $i<32; $i++)
				$this->snapshots[$i] = new Q3_Snapshot();

			$this->fileHandler = fopen($filename, "r");
			if(!file_exists($filename))
				throw new Exception("can't open demofile $filename...");

			$this->currentGameState = new Q3_GameState();

			$this->state = Q3_DEMOPARSER_STATE_PARSING;
		}

		public function __destruct() {
			if($this->fileHandler) fclose($this->fileHandler);
		}

		/**
		 * @return Q3_ParserState
		 */
		public function nextFrame() {
			if(count($this->packetLoop) == 0)
				$this->packetLoop = $this->readDemoMessage();

			if(is_array($this->packetLoop)) {
				foreach($this->packetLoop as $key => $packet) {
					unset($this->packetLoop[$key]);
					return new Q3_ParserState($packet[0], $packet[1]);
				}
			}

			if($this->state == Q3_DEMOPARSER_STATE_ERROR || $this->state == Q3_DEMOPARSER_STATE_FINISHED)
				return false;
		}

		private function readDemoMessage() {
			if(($this->currentGameState->ServerMessageSequence = $this->readIntegerFromStream()) === false) {
				$this->state = Q3_DEMOPARSER_STATE_FINISHED;
				return null;
			}

			$msg = new Q3_Message(Q3_MAX_MSGLEN);

			if( ($msg->CurSize = $this->readIntegerFromStream()) === false) {
				$this->state = Q3_DEMOPARSER_STATE_FINISHED;
				return null;
			}

			if($msg->CurSize == -1) {
				$this->state = Q3_DEMOPARSER_STATE_FINISHED;
				return null;
			}

			if($msg->CurSize > $msg->MaxSize) {
				throw new Q3_Exception("readDemoMessage(): msglen > maxlen!!! ($this->cycle)");
			}

			if( ($data = fread($this->fileHandler, $msg->CurSize)) === false) {
				throw new Q3_Exception("readDemoMessage(): demo file was truncated! ($this->cycle)");
			}

			$msg->setData($data);
			$msg->ReadCount = 0;

			return $this->parseServerMessage($msg);
		}

		private function parseServerMessage(Q3_Message &$msg) {
			$this->currentGameState->ReliableAcknowledge = $msg->ReadLong();

			if($this->currentGameState->ReliableAcknowledge < $this->currentGameState->ReliableSequence - Q3_DEMOPARSER_MAX_RELIABLE_COMMANDS)
				$this->currentGameState->ReliableAcknowledge = $this->currentGameState->ReliableSequence;

			$ret = array();
			$counter = 0;
			while(true) {
				if($msg->ReadCount > $msg->CurSize) {
					throw new Q3_Exception("parseServerMessage(): read past end of server message. ($this->cycle)");
				}

				$cmd = $msg->ReadByte();

				if($cmd == Q3_DEMOPARSER_SVC_EOF)
					break;

				switch($cmd) {
					default:
						throw new Q3_Exception("illegible server message. ($this->cycle)");
						break;

					case Q3_DEMOPARSER_SVC_NOP:
						// nothing todo
						$ret[$counter][0] = null;
						$ret[$counter][1] = Q3_DEMOPARSER_SVC_NOP;
						break;

					case Q3_DEMOPARSER_SVC_GAMESTATE:
						$ret[$counter][0] = $this->parseGameState($msg);
						$ret[$counter][1] = Q3_DEMOPARSER_SVC_GAMESTATE;
						break;

					case Q3_DEMOPARSER_SVC_SERVERCOMMAND:
						$tmp = $this->parseServerCommand($msg);
						if($tmp !== null) {
							$ret[$counter][0] = $tmp;
							$ret[$counter][1] = Q3_DEMOPARSER_SVC_SERVERCOMMAND;
						}else
							$counter--;
						break;

					case Q3_DEMOPARSER_SVC_SNAPSHOT:
						$ret[$counter][0] = $this->parseSnapShot($msg);
						$ret[$counter][1] = Q3_DEMOPARSER_SVC_SNAPSHOT;
						break;

					case Q3_DEMOPARSER_SVC_DOWNLOAD:
						$ret[$counter][0] = null;
						$ret[$counter][1] = Q3_DEMOPARSER_SVC_DOWNLOAD;
						break;
				}

				$counter++;
			}

			return $ret;
		}

		private function parseServerCommand(Q3_Message &$msg) {
			$seq = $msg->ReadLong();
			$cmd = $msg->ReadString();

			if($this->currentGameState->ServerCommandSequence >= $seq)
				return null; // we have already stored ...

			$this->currentGameState->ServerCommandSequence = $seq;
			return new Q3_ServerCommand($cmd, $seq);
		}

		private function parseSnapShot(Q3_Message &$msg) {
			$newSnap = new Q3_Snapshot();
			$oldSnap = null;

			$newSnap->ServerCommandNum = $this->currentGameState->ServerCommandSequence;
			$newSnap->ServerTime = $msg->ReadLong();
			$newSnap->MessageNum = $this->currentGameState->ServerMessageSequence;

			$deltaNum = $msg->ReadByte();
			if(!$deltaNum)
				$newSnap->DeltaNum = -1;
			else
				$newSnap->DeltaNum = $newSnap->MessageNum - $deltaNum;

			$newSnap->SnapFlags = $msg->ReadByte();

			if($newSnap->DeltaNum <= 0) {
				$newSnap->Valid = true;
				$oldSnap = null;
			}else{
				$oldSnap = &$this->snapshots[$newSnap->DeltaNum & 31];
				if(!$oldSnap->Valid) {
					// printing "Delta from invalid frame (not supposed to happen!)"
				}elseif($oldSnap->MessageNum != $newSnap->DeltaNum) {
					// printing "Delta parseEntitiesNum too old."
				}elseif($this->parseEntitiesNum - $oldSnap->ParseEntitiesNum > 2048-128) {
					// printing "Delta parseEntitiesNum too old."
				}else{
					$newSnap->Valid = true;
				}
			}

			$len = $msg->ReadByte();
			if($len > 32)
				throw new Q3_Exception("Invalid size for areamask.");

			$newSnap->Areamask = $msg->ReadData($len);

			if(!$oldSnap) {
				$this->readDeltaPlayerstate( $msg, $oldSnap, $newSnap->Ps );
				$oldSnap = null;
			}else
				$this->readDeltaPlayerstate( $msg, $oldSnap->Ps, $newSnap->Ps );

			$this->readPacketEntities($msg, $oldSnap, $newSnap);

			// if not valid, dump the entire thing now that it has
			// been properly read
			if(!$newSnap->Valid)
				return;

			$oldMessageNum = $this->snap->MessageNum + 1;

			if($newSnap->MessageNum - $oldMessageNum >= 32)
				$oldMessageNum = $newSnap->MessageNum - 31;

			for(; $oldMessageNum < $newSnap->MessageNum; $oldMessageNum++)
				$this->snapshots[$oldMessageNum & 31]->Valid = false;

			$this->snap = $newSnap;
			$this->snap->Ping = 999;

			$this->snapshots[$this->snap->MessageNum & 31] = $this->snap;
			return $newSnap;
		}

		private function readPacketEntities(Q3_Message &$msg, &$oldSnap, Q3_Snapshot &$newSnap) {
			$newSnap->ParseEntitiesNum = $this->parseEntitiesNum;
			$newSnap->NumEntities = 0;

			$oldindex = 0;
			$oldstate = null;
			$oldnum = 0;

			if($oldSnap === null)
				$oldnum = 99999;
			else{
				if($oldindex >= $oldSnap->NumEntities)
					$oldnum = 99999;
				else{
					$oldstate = &$this->parseEntities[($oldSnap->ParseEntitiesNum + $oldindex) & (2048-1)];
					$oldnum = $oldstate->number;
				}
			}

			while(true) {
				$newnum = (int)$msg->ReadBits(10);

				if( $newnum == (Q3_DEMOPARSER_MAX_GENTITIES-1))
					break;

				if($msg->ReadCount > $msg->CurSize)
					throw new Q3_Exception("end of message");

				while($oldnum < $newnum) {
					$this->deltaEntity($msg, $newSnap, $oldnum, $oldstate, true);
					$oldindex++;

					if($oldindex >= $oldSnap->NumEntities)
						$oldnum = 99999;
					else{
						$oldstate = &$this->parseEntities[($oldSnap->ParseEntitiesNum + $oldindex) & (2048-1)];
						$oldnum = $oldstate->number;
					}
				}

				if($oldnum == $newnum) {
					$this->deltaEntity($msg, $newSnap, $newnum, $oldstate, false);
					$oldindex++;

					if($oldindex >= $oldSnap->NumEntities)
						$oldnum = 99999;
					else{
						$oldstate = &$this->parseEntities[($oldSnap->ParseEntitiesNum + $oldindex) & (2048-1)];
						$oldnum = $oldstate->number;
					}
					continue;
				}

				if($oldnum > $newnum) {
					// delta from baseline
					$this->deltaEntity($msg, $newSnap, $newnum, $this->entityBaselines[$newnum], false);
					continue;
				}
			}

			// any remaining entities in the old frame are copied over
			while( $oldnum != 99999 ) {
				// one or more entities from the old packet are unchanged
				$this->deltaEntity($msg, $newSnap, $oldnum, $oldstate, true);

				$oldindex++;

				if($oldindex >= $oldSnap->NumEntities)
					$oldnum = 99999;
				else{
					$oldstate = &$this->parseEntities[($oldSnap->ParseEntitiesNum + $oldindex) & (2048-1)];
					$oldnum = $oldstate->number;
				}
			}
		}

		private function deltaEntity(Q3_Message &$msg, Q3_Snapshot &$frame, $newnum, &$oldstate, $unchanged) {
			$state = &$this->parseEntities[ $this->parseEntitiesNum & (2048-1)];

			if($unchanged)
				$state = &$oldstate;
			else
				$this->parseDeltaEntity($msg, $oldstate, $state, $newnum);

			if($state->number == (Q3_DEMOPARSER_MAX_GENTITIES-1))
				return; // entity was delta removed

			$this->parseEntitiesNum++;
			$frame->NumEntities++;
		}

		private function readDeltaPlayerstate( Q3_Message &$msg, &$oldPs, Q3_PlayerState &$newPs ) {
			if($oldPs == null)
				$oldPs = new Q3_PlayerState();

			$lc = $msg->ReadByte();
			for($i=0; $i < $lc; $i++) {
				if(!$msg->ReadBits(1)) {
					// no changes... copy from delta playerstate...
					// magic and dirty ;)
					eval("\$newPs->".Q3_PlayerState::$NetFields[$i][0]." = \$oldPs->".Q3_PlayerState::$NetFields[$i][0].";");
				}else{
					if(Q3_PlayerState::$NetFields[$i][1] == 0) {
						if($msg->ReadBits(1) == 0) {
							// integral float
							$trunc = (int)$msg->ReadBits(13);
							// bias to allow equal parts positive and negative
							$trunc -= (1<<(13-1));

							// magic and dirty ;)
							eval("\$newPs->".Q3_PlayerState::$NetFields[$i][0]." = (float)\$trunc;");
						}else{
							// full floating point value
							// magic and dirty ;)
							eval("\$newPs->".Q3_PlayerState::$NetFields[$i][0]." = unpack(\"f\", \$msg->ReadBits(32));");
						}
					}else{
						// magic and dirty ;)
						eval("\$newPs->".Q3_PlayerState::$NetFields[$i][0]." = (int)\$msg->ReadBits(".Q3_PlayerState::$NetFields[$i][1].");");
					}
				}
			}

			for($i = $lc; $i<count(Q3_PlayerState::$NetFields); $i++) {
				// magic and dirty ;)
				eval("\$newPs->".Q3_PlayerState::$NetFields[$i][0]." = \$oldPs->".Q3_PlayerState::$NetFields[$i][0].";");
			}

			if($msg->ReadBits(1)) {
				// parse stats array
				if($msg->ReadBits(1)) {
					$bits = $msg->ReadShort();
					for($i=0; $i<16; $i++) {
						if($bits & (1<<$i)) {
							$newPs->Stats[$i] = $msg->ReadShort();
						}
					}
				}

				// parse persistant array
				if($msg->ReadBits(1)) {
					$bits = $msg->ReadShort();
					for($i=0; $i<16; $i++) {
						if($bits & (1<<$i)) {
							$newPs->Persistant[$i] = $msg->ReadShort();
						}
					}
				}

				// parse ammo array
				if($msg->ReadBits(1)) {
					$bits = $msg->ReadShort();
					for($i=0; $i<16; $i++) {
						if($bits & (1<<$i)) {
							$newPs->Ammo[$i] = $msg->ReadShort();
						}
					}
				}

				// parse powerups array
				if($msg->ReadBits(1)) {
					$bits = $msg->ReadShort();
					for($i=0; $i<16; $i++) {
						if($bits & (1<<$i)) {
							$newPs->Powerups[$i] = $msg->ReadShort();
						}
					}
				}
			}
		}

		private function parseGameState(Q3_Message &$msg) {
			$gameDataLen = 0;
			array_push($this->gameStates, $this->currentGameState);
			$this->currentGameState = new Q3_GameState();

			$this->currentGameState->ServerCommandSequence = $msg->ReadLong();

			while(true) {
				$cmd = $msg->ReadByte();

				if($cmd == Q3_DEMOPARSER_SVC_EOF)
					break;

				if($cmd == Q3_DEMOPARSER_SVC_CONFIGSTRING) {
					$configStringNum = $msg->ReadShort();
					if($configStringNum < 0 || $configStringNum >= Q3_DEMOPARSER_MAX_CONFIGSTRINGS)
						throw new Q3_Exception("parseGameState(): configstrings > MAX_CONFIGSTRINGS! ($this->cycle)");

					$configString = $msg->ReadBigString();
					$this->currentGameState->ConfigStrings[$configStringNum] = $configString;

					if( ($gameDataLen + 1 + strlen($configString)) > Q3_DEMOPARSER_MAX_GAMESTATE_CHARS) {
						// quake3 allow max 16000 gameState data because his char array in c struct is only Q3_DEMOPARSER_MAX_GAMESTATE_CHARS bytes (gameState_t.stringData)
						throw new Q3_Exception("parseGameState(): gameStateData > Q3_DEMOPARSER_MAX_GAMESTATE_CHARS! ($this->cycle)");
					}

					$gameDataLen += strlen($configString) + 1; // for quake3 max gamestate chars check...

				}elseif($cmd == Q3_DEMOPARSER_SVC_BASELINE) {
					$newNum = $msg->ReadBits(Q3_DEMOPARSER_GENTITYNUM_BITS);
					if($newNum < 0 || $newNum >= Q3_DEMOPARSER_MAX_GENTITIES)
						throw new Q3_Exception("parseGameState(): baseline entities out of range! ($this->cycle)");

					$nullState = new Q3_EntityState();
					$es = &$this->entityBaselines[$newNum];
					$this->parseDeltaEntity($msg, $nullState, $es, $newNum);

				}else
					throw new Q3_Exception("parseGameState(): bad gamestate command byte. ($this->cycle)");
			}

			$this->currentGameState->ClientNum = $msg->ReadLong();
			$this->currentGameState->ChecksumFeed = $msg->ReadLong();

			return true;
		}

		private function parseDeltaEntity(Q3_Message &$msg, &$from, Q3_EntityState &$to, $number) {
			// throw all into >> /dev/null
			if($number < 0 || $number >= Q3_DEMOPARSER_MAX_GENTITIES) {
				throw new Q3_Exception("Bad delta entity number: $number ($this->cycle)");
			}

			// check for remove
			if($msg->ReadBits(1) == 1) {
				$to = new Q3_EntityState();
				$to->number = Q3_DEMOPARSER_MAX_GENTITIES-1;
				return;
			}

			// check for no delta
			if($msg->ReadBits(1) == 0) {
				$to = &$from;
				$to->number = $number;
				return;
			}

			$lc = $msg->ReadByte();
			$to->number = $number;

			for($i = 0; $i < $lc; $i++) {
				if($msg->ReadBits(1) == 0) {
					eval("\$to->".Q3_EntityState::$NetFields[$i][0]." = \$from->".Q3_EntityState::$NetFields[$i][0].";");
				}else{
					if(Q3_EntityState::$NetFields[$i][1] == 0) {
						if($msg->ReadBits(1) == 1) {
							if($msg->ReadBits(1) == 0) {
								// integral float
								$trunc = $msg->ReadBits(13); // FLOAT_IN_BITS (QUAKE3)
								// bias to allow equal parts positive and negative
								$trunc -= (1<<(13-1));

								eval("\$to->".Q3_EntityState::$NetFields[$i][0]." = (float)\$trunc;");
							}else
								eval("\$to->".Q3_EntityState::$NetFields[$i][0]." = (float)\$msg->ReadBits(32);"); // full floating point value (QUAKE3)
						}
					}else{
						if($msg->ReadBits(1) == 0)
							eval("\$to->".Q3_EntityState::$NetFields[$i][0]." = (float)0;");
						else
							eval("\$to->".Q3_EntityState::$NetFields[$i][0]." = (float)\$msg->ReadBits(".Q3_EntityState::$NetFields[$i][1].");"); // full floating point value (QUAKE3)
					}
				}
			}

			for($i=$lc; $i<count(Q3_EntityState::$NetFields); $i++)
				eval("\$to->".Q3_EntityState::$NetFields[$i][0]." = \$from->".Q3_EntityState::$NetFields[$i][0].";");

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
