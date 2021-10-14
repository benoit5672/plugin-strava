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

use League\OAuth2\Client\Provider\ResourceOwnerInterface;


class StravaResourceOwner implements ResourceOwnerInterface {
    protected $userInfo = [];

    public function __construct(array $response) {
        $this->userInfo = $response;
    }

    public function getId() {
        return $this->userInfo['id'] ?: null;
    }

    public function getFirstName() {
        return $this->userInfo['firstname'] ?: null;
    }

    public function getLastName() {
        return $this->userInfo['lastname'] ?: null;
    }

    public function getPremium() {
        return $this->userInfo['premium'] ?: false;
    }

    public function getWeight() {
        return $this->userInfo['weight'] ?: 0;
    }

    public function toArray() {
        return $this->userInfo;
    }
}
