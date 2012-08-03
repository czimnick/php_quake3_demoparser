<?php

require_once("PlayerState.php");

class Q3_Snapshot {
	public $Valid;			// cleared if delta parsing was invalid
	public $SnapFlags;		// rate delayed and dropped commands
	public $ServerTime;		// server time the message is valid for (in msec)

	public $MessageNum;		// copied from netchan->incoming_sequence
	public $DeltaNum;		// messageNum the delta is from
	public $Ping;			// time from when cmdNum-1 was sent to time packet was reeceived
	public $Areamask;		// portalarea visibility bits (MAX_MAP_AREA_BYTES)

	public $CmdNum;			// the next cmdNum the server is expecting

	/**
	 * @var Q3_PlayerState
	 */
	public $Ps;						// complete information about the current player at this time

	public $NumEntities;			// all of the entities that need to be presented
	public $ParseEntitiesNum;		// at the time of this snapshot

	public $ServerCommandNum;		// execute all commands up to this before
									// making the snapshot current


	public function __construct() {
		$this->Ps = new Q3_PlayerState();
	}
}
