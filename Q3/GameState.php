<?php
	class Q3_GameState {
		/**
		 * @var int
		 */
		public $ServerMessageSequence, $ReliableAcknowledge, $ReliableSequence, $ServerCommandSequence, $ClientNum, $ChecksumFeed;

		public $ServerCommands = array();
		public $SnapShot = "";
		public $SnapShots = array();
		public $NewSnapShots = null;
		public $ConfigStrings = array();

		public $EntityBaseLines = null;
		public $ParseEntities = null;
	}
