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

/* * ***************************Includes********************************* */

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Implement Abstract methods of the Abstract provider class.
 */

class StravaProvider extends AbstractProvider {

    use BearerAuthorizationTrait;

    const ACCESS_TOKEN_RESOURCE_OWNER_ID = 'athlete.id';
    const BASE_STRAVA_URL                = 'https://www.strava.com';

    protected $apiVersion = 'v3';

    public function __construct(array $options = [], array $collaborators = []) {
        parent::__construct($options, $collaborators);

        foreach ($options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->{$option} = $value;
            }
        }
    }

    public function getBaseAuthorizationUrl() {
        return self::BASE_STRAVA_URL . '/oauth/authorize';
    }

    public function getBaseDeauthorizationUrl() {
        return self::BASE_STRAVA_URL . '/oauth/deauthorize';
    }

    public function getBaseAccessTokenUrl(array $params) {
        return self::BASE_STRAVA_URL . '/oauth/token';
    }

    public function getBaseApi() {
        return self::BASE_STRAVA_URL . '/api/' . $this->apiVersion;
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token) {
        return self::BASE_STRAVA_URL . '/api/' . $this->apiVersion . '/athlete';
    }

    // see https://strava.github.io/api/v3/oauth/#get-authorize
    protected function getDefaultScopes() {
        return ['read', 'read_all', 'profile:read_all', 'profile:write', 'activity:read_all'];
    }

    protected function checkResponse(ResponseInterface $response, $data) {
        if ($response->getStatusCode() >= 400) {
            throw new IdentityProviderException(
                $data['message'] ?: $response->getReasonPhrase(),
                $response->getStatusCode(),
                $response
            );
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token) {
        return new StravaResourceOwner($response);
    }

    protected function getDefaultHeaders() {
        return [
            'Accept'          => 'application/json',
            'Accept-Encoding' => 'gzip',
        ];
    }
}
