<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */


class stravaActivity {

	/*     * *************************Attributs****************************** */

	private $eqLogicId;
	private $stravaId;
	private $time;
    private $type;
	private $distance;
	private $duration;
	private $elevation;

	/*     * ***********************Methode static*************************** */

	/**
  	 * Convert String type to integer
	 */
	private static function str2int($_type) {
		$sports = [
		   'AlpineSki' => 1,
		   'BackcountrySki' => 2,
		   'Canoeing' => 3,
		   'Crossfit' => 4,
		   'EBikeRide' => 5,
		   'Elliptical' => 6,
		   'Golf' => 7,
		   'Handcycle' => 8,
		   'Hike' => 9,
		   'Iceskate' => 10,
		   'InlineSkate' => 11,
		   'Kayaking' => 12,
		   'Kitesurf' => 13,
		   'NordicSki' => 14,
		   'Ride' => 15,
		   'RockClimbing' => 16,
		   'RollerSki' => 17,
		   'Rowing' => 18,
		   'Run' => 19,
		   'Sail' => 20,
		   'Skateboard' => 21,
		   'Snowboard' => 22,
		   'Snowshoe' => 23,
		   'Soccer' => 24,
		   'StairStepper' => 25,
		   'StandUpPaddling' => 26,
		   'Surfing' => 27,
		   'Swim' => 28,
		   'Velomobile' => 29,
		   'VirtualRide' => 30,
		   'VirtualRun' => 31,
		   'Walk' => 32,
		   'WeightTraining' => 33,
		   'Wheelchair' => 34,
		   'Windsurf' => 35,
		   'Workout' => 36,
		   'Yoga' => 37
		];
		return $sports[$_type];
	}


    /**
     * Return an array of elements for the specified eqLogicId,
     * and between start and end (in seconds UTC)
     */
    public function byEqLogicIdTime($_id, $_start, $_end) {

		$parameters = array(
			'eqLogicId' => $_eqLogicId,
            'start'     => $_start,
            'end'       => $_end,
		);
        $sql = 'SELECT eqLogicId, stravaId, time, type, distance, duration, elevation
                FROM `stravaActivity`
                WHERE `eqLogicId` = :eqLogicId AND `time` >= :start AND `time` <= :end
                GROUP BY time
                ORDER BY eqLogicId, time;';
		return DB::Prepare($sql, $parameters, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
    }


    /**
     * Remove all the activities associated to a eqLogic object, for example
     * when the object is deleted
     */
	public static function removeAllbyId($_eqLogic) {

        $parameters = array (
            'eqLogicId' => $_eqLogic,
        );

        $sql = 'DELETE FROM `stravaActivity`
                WHERE `eqLogicId` = :eqLogicId;';
		return DB::Prepare($sql, $parameters, DB::FETCH_TYPE_ROW);
	}
    /**
     * Remove all the activities older than the specified date for
     * eqLogic specified
     */
    public static function removeAllByIdtime($_eqLogicId, $_time) {

        $parameters = array (
            'eqLogicId' => $_eqLogicId,
            'time' => $_time,
        );
        $sql = 'DELETE FROM `stravaActivity`
                WHERE `eqLogicId` = :eqLogicId AND `time` < :time;';
        return DB::Prepare($sql, $parameters, DB::FETCH_TYPE_ROW);
    }

    /**
     * Remove all the activities that don't have pending eqLogic object
	 * @todo
     */
	 /*
    public static function removeDeadActivities() {
        $sql = 'SELECT eqLogicId, stravaId, time, type, distance, duration, elevation
				FROM `stravaActivity`
            	WHERE `eqLogicId` NOT IN
            	(SELECT id FROM `eqLogic` WHERE eqLogicId = id and `eqType_name` = "strava");';
		$values =  DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
        foreach ($values as $value) {
            log::add('strava', 'info',
                __('Suppression des evenements "dead" pour l\'id', __FILE__)
                . $value->getEqLogicId());
            self::removeAllbyId($value->getEqLogicId());
        }
    }
	*/

    /**
     * Create a new stravaActivity according to the specified parameters
     */
    public static function createActivity(
				$_eqLogicId,
				$_stravaId,
				$_time,
				$_type,
				$_distance,
				$_duration,
				$_elevation) {

		log::add('strava', 'debug', 'createActivity');

	    $parameters = array (
			'eqLogicId' => $_eqLogicId,
			'stravaId'  => $_stravaId,
			'time' => $_time,
			'type'   	=> stravaActivity::str2int($_type),
			'distance'  => $_distance,
			'duration' => $_duration,
			'elevation'  => $_elevation);

		log::add('strava', 'debug', 'createActivity type' . $_type . ' => ' . $parameters['type']);

		$sql = 'INSERT IGNORE INTO `stravaActivity` SET
		        `eqLogicId` = :eqLogicId, `stravaId` = :stravaId,
				`time` = :time, `type` = :type,
				`distance` = :distance, `duration` = :duration,
				`elevation` = :elevation;';
		return DB::Prepare($sql, $parameters, DB::FETCH_TYPE_ROW);
    }

	/**
     * Delete an existing stravaActivity
     */
    public static function deleteActivity($_eqLogicId,$_stravaId) {

		$parameters = array (
			'eqLogicId' => $_eqLogicId,
			'stravaId'  => $_stravaId
        );

		$sql = 'DELETE FROM `stravaActivity` WHERE
		        `eqLogicId` = :eqLogicId AND `stravaId` = :stravaId;';
		return DB::Prepare($sql, $parameters, DB::FETCH_TYPE_ROW);
	}


	/*     * *********************Methode d'instance************************* */

    public function __construct($obj = null){
        if ($obj && is_array($obj)) {
            foreach (((object)$obj) as $key => $value) {
                if(isset($value) && in_array($key, array_keys(get_object_vars($this)))){
                    $this->$key = $value;
                }
            }
        }
    }

	/*     * **********************Getteur Setteur*************************** */

	public function getEqLogicId() {
		return $this->eqLogicId;
	}

    public function gettime() {
        return $this->time;
    }

	public function getStravaId() {
        return $this->getStravaId;
    }

    public function getSport() {
        return $this->type;
    }

    public function getDistance() {
       return $this->distance;
    }

	public function getElevation() {
       return $this->elevation;
    }

	public function getDuration() {
       return $this->duration;
    }

}
