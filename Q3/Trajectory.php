<?php
class Q3_Trajectory {
	public $trType;
	public $trTime;
	public $trDuration;			// if non 0, trTime + trDuration = stop time
	public $trBase; // vec3_t
	public $trDelta;			// velocity, etc (vec3_t)

	public function __construct() {
		$this->trBase[0] = 0;
		$this->trBase[1] = 0;
		$this->trBase[2] = 0;

		$this->trDelta[0] = 0;
		$this->trDelta[1] = 0;
		$this->trDelta[2] = 0;
	}
}