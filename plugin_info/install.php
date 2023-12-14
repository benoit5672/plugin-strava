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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

  function createDB() {
      // Create the table with all the monitored sports
      $sql = 'CREATE TABLE IF NOT EXISTS `stravaSport` ('
             . '`type` TINYINT UNSIGNED UNIQUE NOT NULL,'
             . '`name` VARCHAR(32) NOT NULL'
             . ') ENGINE=InnoDB DEFAULT CHARSET=utf8;';
      DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);

      // Populate the stravaTypeDB
      $sql = 'INSERT IGNORE INTO `stravaSport` (`type`, `name`) VALUES'
                . '(1, "AlpineSki"), (2, "BackcountrySki"), (3, "Canoeing"), '
                . '(4, "Crossfit"), (5, "EBikeRide"), (6, "Elliptical"), '
                . '(7, "Golf"), (8, "Handcycle"), (9, "Hike"), (10, "Iceskate"), '
                . '(11, "InlineSkate"), (12, "Kayaking"), (13, "Kitesurf"), '
                . '(14, "NordicSki"), (15, "Ride"), (16, "RockClimbing"), '
                . '(17, "RollerSki"), (18, "Rowing"), (19, "Run"), (20, "Sail"), '
                . '(21, "Skateboard"), (22, "Snowboard"), (23, "Snowshoe"), '
                . '(24, "Soccer"), (25, "StairStepper"), (26, "StandUpPaddling"), '
                . '(27, "Surfing"), (28, "Swim"), (29, "Velomobile"), '
                . '(30, "VirtualRide"), (31, "VirtualRun"), (32, "Walk"), '
                . '(33, "WeightTraining"), (34, "Wheelchair"), (35, "Windsurf"), '
                . '(36, "Workout"), (37, "Yoga"), '
                . '(38, "Badminton"), (39, "EMountainBikeRide"), (40, "GravelRide"), ' 
                . '(41, "HighIntensityIntervalTraining"), (42, "MountainBikeRide"), ' 
                . '(43, "Pickleball"), (44, "Pilates"), (45, "Racquetball"), '
                . '(46, "Squash"), (47, "TableTennis"), (48, "Tennis"), '
                . '(49, "TrailRun"), (50, "VirtualRow");' 
                
      DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);

      // Create the table where the activities are stored
      $sql = 'CREATE TABLE IF NOT EXISTS `stravaActivity` ('
             . '`eqLogicId` INT(11) NOT NULL,'
             . '`stravaId` BIGINT UNSIGNED UNIQUE NOT NULL,'
             . '`time` INT UNSIGNED NOT NULL,'
             . '`type` TINYINT UNSIGNED NOT NULL,'
             . '`distance` FLOAT UNSIGNED NOT NULL,'
             . '`duration` INT UNSIGNED NOT NULL,'
             . '`elevation` FLOAT UNSIGNED NOT NULL'
             . ') ENGINE=InnoDB DEFAULT CHARSET=utf8;';
      DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
}

// Fonction exécutée automatiquement après l'installation du plugin
  function strava_install() {
      createDB();
  }

  // Fonction exécutée automatiquement après la mise à jour du plugin
  function strava_update() {
      createDB();
  }

  // Fonction exécutée automatiquement après la suppression du plugin
  function strava_remove() {
      // drop the table where the activities are stored
      DB::Prepare('DROP TABLE IF EXISTS `stravaActivity`;', array(), DB::FETCH_TYPE_ROW);
      DB::Prepare('DROP TABLE IF EXISTS `stravaSport`;', array(), DB::FETCH_TYPE_ROW);
  }

?>
