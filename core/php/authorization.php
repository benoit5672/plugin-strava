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

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

log::add('strava', 'debug', 'Received authorization request: ' . $_SERVER['REQUEST_URI']);

include_file('core', 'authentification', 'php');
if (!jeedom::apiAccess(init('apikey'), 'strava')) {
	echo 'Clef API non valide, vous n\'êtes pas autorisé effectuer cette action';
	die();
}


//
// There is a trick here, instead of using eqLogic_id, which is the parameter
// we sent to the function, it has been url_encoded with &amp. So search for
// amp;amp;eqLogic_id instead of eqLogic_id
$names=['eqLogic_id', 'amp;eqLogic_id', 'amp;amp;eqLogic_id'];
$eqLogic = NULL;
foreach ($names as $name) {
    $eqLogic = eqLogic::byId(init($name));
    if (is_object($eqLogic) && method_exists($eqLogic, 'getProvider')) {
        break;
    }
    $eqLogic = NULL;
}
if (!is_object($eqLogic)) {
	echo 'Impossible de trouver l\'utilisateur Strava correspondant à : ' . init('eqLogic_id');
	exit();
}

//
// AUTHORIZATION CALLBACK
//

//
// Check given state against previously stored one to mitigate CSRF attack
//
if (cache::exist('strava::state')) {
	$state = cache::byKey('strava::state')->getValue();
}
if (empty($_GET['state']) || !isset($state) || $_GET['state'] !== $state) {

    log::add('strava', 'error', __('Invalide state : _GET=' . $_GET['state'] . ', cache=' . $state, __FILE__));
	if (cache::exist('strava::state')) {
		cache::delete('strava::state');
	}
    exit('Invalid state');
}
cache::delete('strava::state');

try {
	// As we extend AbstractProvider from League, then we should get our provider
	// check the state session compared to the state of the request
	//
	$provider = $eqLogic->getProvider();

    // Try to get an access token (using the authorization code grant)
    // We use 'default' grant factory, so authorization_code will
    // use AuthorizationCode class
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // We have our STRAVA token, so save it for later utilization
    // as the scope that contained the granted permissions
    $eqLogic->setConfiguration('accessToken', $token->jsonSerialize());
    $eqLogic->setConfiguration('scope', $_GET['scope']);
    $eqLogic->save();

    // Optional: Now you have a token you can look up a users profile data
    try {
        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        log::add('strava', 'info', __('L\'autorisation de l\'utilisateur ', __FILE__)
        		. $user->getFirstName() . ' ' . $user->getLastName() . '(' . $user->getId()
        		. __(') a réussie !', __FILE__));
        $eqLogic->setStravaId($user->getId());

    } catch (Exception $e) {

        // Failed to get user details
        log::add('strava', 'error', $e->getMessage());
        $eqLogic->setStravaId(-1);
        exit($e->getMessage());
    }
    // Create webhook subscription for this user
    // delete existing one, if any previous subscription exists
    try {
        $eqLogic->createSubscription(true);
    } catch (Exception $e) {
        // will try to do it later.
    }

    // At the end of the callback, go back to the configuration page of the STRAVA user
    redirect(network::getNetworkAccess('external') . '/index.php?v=d&p=strava&m=strava&id=' . $eqLogic->getId());

} catch (Exception $e) {
    log::add('strava', 'error', $e->getMessage());
    http_error_code(500);
	exit(print_r($e));
}
