<?php
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

include_file('core', 'authentification', 'php');
if (!jeedom::apiAccess(init('apikey'), 'strava')) {
	echo 'Clef API non valide, vous n\'êtes pas autorie effectuer cette action';
	die();
}
$eqLogic = eqLogic::byId(init('eqLogic_id'));
if (!is_object($eqLogic)) {
	echo 'Impossible de trouver l\'equipement correspondant a : ' . init('eqLogic_id');
	exit();
}

if (!isConnect()) {
	echo 'Vous ne pouvez pas appeller cette page sans être connecté. Veuillez vous connecter <a href=' . network::getNetworkAccess() . '/index.php>ici</a> avant et refaire l\'opération de synchronisation';
	die();
}

//
// AUTHORIZATION CALLBACK
//
// As we extend AbstractProvider from League, then we should get our provider
// check the state session compared to the state of the request
// 
$provider = $eqLogic->getProvider();

// Check given state against previously stored one to mitigate CSRF attack
if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} 

try {
    // Try to get an access token (using the authorization code grant)
    // We use 'default' grant factory, so authorization_code will
    // use AuthorizationCode class
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // We have our STRAVA token, so save it for later utilization
    $eqLogic->setConfiguration('accessToken', $token->jsonSerialize());
    $eqLogic->save();

    // Start fetching information about the user
    //$eqLogic->getInfoFromStrava();

    // Optional: Now you have a token you can look up a users profile data
    //try {
        // We got an access token, let's now get the user's details
        //$user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        //printf('Hello %s!', $user->getFirstName() . ' ' . $user->getLastName());
    //} catch (Exception $e) {

        // Failed to get user details
    //    exit('Oh dear...');
    //}
    //
    //
    // At the end of the callback, go back to the configuration page of the STRAVA user 
    redirect(network::getNetworkAccess('external') . '/index.php?v=d&p=strava&m=strava&id=' . $eqLogic->getId());

} catch (Exception $e) {
	exit(print_r($e));
}