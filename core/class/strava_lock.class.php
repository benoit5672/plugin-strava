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

//
// From https://stackoverflow.com/questions/325806/best-way-to-obtain-a-lock-in-php
//

class StravaLock {
    protected $key = null;   //user given value
    protected $file = null;  //resource to lock
    protected $own = false;  //have we locked resource

    function __construct($key) {
        $this->key = $key;
        //create a new resource or get exisitng with same key
        $lockFile = jeedom::getTmpFolder('strava').$key.'.lockfile';
        $this->file = fopen($lockFile, 'w+');
    }

	function __destruct() {
        if ($this->own == true) {
            $this->unlock();
        }
	}

	function Lock() {
        $retry = 0;
        do {
            if (flock($this->file, LOCK_EX)) {
                ftruncate($this->file, 0);
                fwrite($this->file, "Locked\n");
                fflush($this->file);

                $this->own = true;
                return true;
            }
            sleep(1);
            $retry++;
        } while ($retry <= 3);

		return false;
	}

	function unlock() {
		if ($this->own) {
            if (!flock($this->file, LOCK_UN)) {
                return false;
            }
            ftruncate($this->file, 0);
            fwrite($this->file, "Unlocked\n");
            fflush($this->file);
            $this->own = false;
        }
        return true;
	}
}
