<?php
require_once("Trajectory.php");

class Q3_EntityState {
	public $number;			// entity index
	public $eType;			// entityType_t
	public $eFlags;

	/**
	 * @var Q3_Trajectory
	 */
	public $pos;	// for calculating position

	/**
	 * @var Q3_Trajectory
	 */
	public $apos;	// for calculating angles

	public $time;
	public $time2;

	public $origin; // vec3_t
	public $origin2; // vec3_t

	public $angles; // vec3_t
	public $angles2; // vec3_t

	public $otherEntityNum;	// shotgun sources, etc
	public $otherEntityNum2;

	public $groundEntityNum;	// -1 = in air

	public $constantLight;	// r + (g<<8) + (b<<16) + (intensity<<24)
	public $loopSound;		// constantly loop this sound

	public $modelindex;
	public $modelindex2;
	public $clientNum;		// 0 to (MAX_CLIENTS - 1), for players and corpses
	public $frame;

	public $solid;			// for client side prediction, trap_linkentity sets this properly

	public $event;			// impulse events -- muzzle flashes, footsteps, etc
	public $eventParm;

	// for players
	public $powerups;		// bit flags
	public $weapon;			// determines weapon and flash model, etc
	public $legsAnim;		// mask off ANIM_TOGGLEBIT
	public $torsoAnim;		// mask off ANIM_TOGGLEBIT

	public $generic1;

	public function __construct() {
		$this->pos = new Q3_Trajectory();
		$this->apos = new Q3_Trajectory();

		$this->origin[0] = 0;
		$this->origin[1] = 0;
		$this->origin[2] = 0;

		$this->origin2[0] = 0;
		$this->origin2[1] = 0;
		$this->origin2[2] = 0;

		$this->angles[0] = 0;
		$this->angles[1] = 0;
		$this->angles[2] = 0;

		$this->angles2[0] = 0;
		$this->angles2[1] = 0;
		$this->angles2[2] = 0;
	}

	// magic dont touch!!!
	// count of bits for every netField from enitityState_t (quake3)
	public static $NetFields = array(
		array('pos->trTime', 32 ),
		array('pos->trBase[0]', 0 ),
		array('pos->trBase[1]', 0 ),
		array('pos->trDelta[0]', 0 ),
		array('pos->trDelta[1]', 0 ),
		array('pos->trBase[2]', 0 ),
		array('apos->trBase[1]', 0 ),
		array('pos->trDelta[2]', 0 ),
		array('apos->trBase[0]', 0 ),
		array('event', 10 ),
		array('angles2[1]', 0 ),
		array('eType', 8 ),
		array('torsoAnim', 8 ),
		array('eventParm', 8 ),
		array('legsAnim', 8 ),
		array('groundEntityNum', 10 ),
		array('pos->trType', 8 ),
		array('eFlags', 19 ),
		array('otherEntityNum', 10 ),
		array('weapon', 8 ),
		array('clientNum', 8 ),
		array('angles[1]', 0 ),
		array('pos->trDuration', 32 ),
		array('apos->trType', 8 ),
		array('origin[0]', 0 ),
		array('origin[1]', 0 ),
		array('origin[2]', 0 ),
		array('solid', 24 ),
		array('powerups', 16 ),
		array('modelindex', 8 ),
		array('otherEntityNum2', 10 ),
		array('loopSound', 8 ),
		array('generic1', 8 ),
		array('origin2[2]', 0 ),
		array('origin2[0]', 0 ),
		array('origin2[1]', 0 ),
		array('modelindex2', 8 ),
		array('angles[0]', 0 ),
		array('time', 32 ),
		array('apos->trTime', 32 ),
		array('apos->trDuration', 32 ),
		array('apos->trBase[2]', 0 ),
		array('apos->trDelta[0]', 0 ),
		array('apos->trDelta[1]', 0 ),
		array('apos->trDelta[2]', 0 ),
		array('time2', 32 ),
		array('angles[2]', 0 ),
		array('angles2[0]', 0 ),
		array('angles2[2]', 0 ),
		array('constantLight', 32 ),
		array('frame', 16 )
	);
}
 
