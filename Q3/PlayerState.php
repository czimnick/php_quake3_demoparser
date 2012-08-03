<?php
class Q3_PlayerState {
	public $CommandTime;	// cmd->serverTime of last executed command
	public $PmType;
	public $BobCycle;		// for view bobbing and footstep generation
	public $PmFlags;		// ducked, jump_held, etc
	public $PmTime;

	public $Origin = array(); // vec3_t
	public $Velocity = array(); // vec3_t

	public $WeaponTime;
	public $Gravity;
	public $Speed;
	public $DeltaAngles = array();	// add to command angles to get view direction (length 3)
								// changed by spawns, rotating objects, and teleporters

	public $GroundEntityNum;// ENTITYNUM_NONE = in air

	public $LegsTimer;		// don't change low priority animations until this runs out
	public $LegsAnim;		// mask off ANIM_TOGGLEBIT

	public $TorsoTimer;		// don't change low priority animations until this runs out
	public $TorsoAnim;		// mask off ANIM_TOGGLEBIT

	public $MovementDir;	// a number 0 to 7 that represents the reletive angle
							// of movement to the view angle (axial and diagonals)
							// when at rest, the value will remain unchanged
							// used to twist the legs during strafing

	public $GrapplePoint;	// location of grapple to pull towards if PMF_GRAPPLE_PULL (vec3_t)

	public $eFlags;			// copied to entityState_t->eFlags

	public $EventSequence;	// pmove generated events
	public $Events = array();
	public $EventParms = array();

	public $ExternalEvent;	// events set on player from another source
	public $ExternalEventParm;
	public $ExternalEventTime;

	public $ClientNum;		// ranges from 0 to MAX_CLIENTS-1
	public $Weapon;			// copied to entityState_t->weapon
	public $Weaponstate;

	public $ViewAngles;		// for fixed views (vec3_t)
	public $ViewHeight;

	// damage feedback
	public $DamageEvent;	// when it changes, latch the other parms
	public $DamageYaw;
	public $DamagePitch;
	public $DamageCount;

	public $Stats = array();
	public $Persistant = array();	// stats that aren't cleared on death
	public $Powerups = array();	// level.time that the powerup runs out
	public $Ammo = array();

	public $Generic1;
	public $LoopSound;
	public $JumpPadEnt;	// jumppad entity hit this frame

	// not communicated over the net at all
	public $Ping;			// server to game info for scoreboard
	public $PmoveFrameCount;	// FIXME: don't transmit over the network
	public $JumpPadFrame;
	public $EntityEventSequence;
	
	// magic dont touch!!!
	// count of bits for every netField from playerState_t (quake3)
	public static $NetFields = array(
		array('CommandTime', 32 ),
		array('Origin[0]', 0 ),
		array('Origin[1]', 0 ),
		array('BobCycle', 8 ),
		array('Velocity[0]', 0 ),
		array('Velocity[1]', 0 ),
		array('ViewAngles[1]', 0 ),
		array('ViewAngles[0]', 0 ),
		array('WeaponTime', -16 ),
		array('Origin[2]', 0 ),
		array('Velocity[2]', 0 ),
		array('LegsTimer', 8 ),
		array('PmTime', -16 ),
		array('EventSequence', 16 ),
		array('TorsoAnim', 8 ),
		array('MovementDir', 4 ),
		array('Events[0]', 8 ),
		array('LegsAnim', 8 ),
		array('Events[1]', 8 ),
		array('PmFlags', 16 ),
		array('GroundEntityNum', 10 ),
		array('Weaponstate', 4 ),
		array('eFlags', 16 ),
		array('ExternalEvent', 10 ),
		array('Gravity', 16 ),
		array('Speed', 16 ),
		array('DeltaAngles[1]', 16 ),
		array('ExternalEventParm', 8 ),
		array('ViewHeight', -8 ),
		array('DamageEvent', 8 ),
		array('DamageYaw', 8 ),
		array('DamagePitch', 8 ),
		array('DamageCount', 8 ),
		array('Generic1', 8 ),
		array('PmType', 8 ),
		array('DeltaAngles[0]', 16 ),
		array('DeltaAngles[2]', 16 ),
		array('TorsoTimer', 12 ),
		array('EventParms[0]', 8 ),
		array('EventParms[1]', 8 ),
		array('ClientNum', 8 ),
		array('Weapon', 5 ),
		array('ViewAngles[2]', 0 ),
		array('GrapplePoint[0]', 0 ),
		array('GrapplePoint[1]', 0 ),
		array('GrapplePoint[2]', 0 ),
		array('JumpPadEnt', 10 ),
		array('LoopSound', 16 )
	);

	public function __construct() {
		$this->Origin[0] = 0;
		$this->Origin[1] = 0;
		$this->Origin[2] = 0;

		$this->Velocity[0] = 0;
		$this->Velocity[1] = 0;
		$this->Velocity[2] = 0;

		$this->ViewAngles[0] = 0;
		$this->ViewAngles[1] = 0;
		$this->ViewAngles[2] = 0;

		$this->Events[0] = 0;
		$this->Events[1] = 0;
		$this->Events[2] = 0;

		$this->DeltaAngles[0] = 0;
		$this->DeltaAngles[1] = 0;
		$this->DeltaAngles[2] = 0;

		$this->EventParms[0] = 0;
		$this->EventParms[1] = 0;
		$this->EventParms[2] = 0;

		$this->GrapplePoint[0] = 0;
		$this->GrapplePoint[1] = 0;
		$this->GrapplePoint[2] = 0;
	}
}
 
