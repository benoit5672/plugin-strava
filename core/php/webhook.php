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

log::add('strava', 'debug', 'Received webhook notification: ' . $_SERVER['REQUEST_URI']);

include_file('core', 'authentification', 'php');

if (!jeedom::apiAccess(init('apikey'), 'strava')) {
    echo 'Clef API non valide, vous n\'etes pas autorise a effectuer cette action';
    die();
}

$eqLogic = eqLogic::byId(init('eqLogic_id'));
if (!is_object($eqLogic)) {
    echo 'Impossible de trouver l\'équipement correspondant a : ' . init('eqLogic_id');
    exit();
}

//
// SUBSCRIPTIONS CALLBACK
//
//

//
// POST is used when an 'update' is received.
// no need to be "connected" to jeedom to process notifications
// 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $results = json_decode(file_get_contents('php://input'), true);
    //log::add('strava', 'debug', 'Notification content: ' . print_r($results, true));
    if (isset($results)) {

        // 
        // Process the request, and return 200 OK
        // 
        if (isset($results['subscription_id']) 
            and $results['subscription_id'] == $eqLogic->getConfiguration('subscription_id')) {

            // Update the eqLogic with the information provided
            log::add('strava', 'debug', 'Received push notification: ' . print_r($results, true));
            try {
                $eqLogic->processSubscriptionNotification($results);
            } catch(Exception $e) {
                // error processing the notification. 
                log::add('strava', 'warning', 'Error processing notification: ' . $e->getMessage());
                http_response_code(500);
                exit();
            }
        } else {
            // just ignore the notification
            log::add('strava', 'debug', 'Invalid subscription id (RX=' . $results['subscription_id'] . ' OUR=' . $eqLogic->getConfiguration('subscription_id') . ')');
        }
    }
    // Always return 200 OK to prevent retransmission !
    http_response_code(200);
    exit();
}


// 
// GET is used in the case of 'subscribe' challenge 
// 
log::add('strava', 'debug', 'REQUEST_METHOD=' . $_SERVER['REQUEST_METHOD'] . 'args=' . print_r($_GET, true));
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
   if (isset($_GET['hub_challenge']) and isset($_GET['hub_mode']) and isset($_GET['hub_verify_token'])
       and ($_GET['hub_mode'] == 'subscribe')
       and ($_GET['hub_verify_token'] == $eqLogic->getCache('subscription_token'))) {
       //and ($_GET['hub_verify_token'] == $eqLogic->getConfiguration('subscription_token'))) {

       log::add('strava', 'debug', 'Respond with hub.challenge');
       // respond with 200 OK, and hub_challenge
       // 
       echo json_encode(['hub.challenge' => $_GET['hub_challenge']]);
       http_response_code(200);
       exit();
   } else {
       log::add('strava', 'error', __("Au moins un parametre hub.mode, hub.verify_token est manquant ou invalide",__FILE__));
       log::add('strava', 'debug', 'return error 403'); 
       http_response_code(403);
       die();
   }
} 

// Invalid processing, return an error
log::add('strava', 'error', 'Invalide requete recue sur le webhook Strava'); 
http_response_code(500);

