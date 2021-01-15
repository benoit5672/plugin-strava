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

include_file('core', 'authentification', 'php');
if (!jeedom::apiAccess(init('apikey'), 'strava')) {
    echo 'Clef API non valide, vous n\'etes pas autorise a effectuer cette action';
    die();
}

$eqLogic = eqLogic::byId(init('eqLogic_id'));
if (!is_object($eqLogic)) {
    echo 'Impossible de trouver l\'équipement correspondnat a : ' . init('eqLogic_id');
    exit();
}

//
// SUBSCRIPTIONS CALLBACK
//

// 
// GET is used in the case of 'subscribe' challenge 
// hub.xxxx is replaced by hub_xxxx when using the $_GET function
// 
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
   if (isset($_GET['hub_challenge']) and isset($_GET['hub_mode']) and isset($_GET['hub_verify_token'])
       and ($_GET['hub_mode'] == 'subscribe')
       and ($_GET['hub_verify_token'] == 'test')) {
       //and ($_GET['hub_verify_token'] === $eqLogic->getConfiguration('subscription_token'))) {

      // respond with 200 OK, and hub.challenge
      echo json_encode(['hub.challenge' => $_GET['hub_challenge']]);
      http_response_code(200);
      exit();
   } else {
      echo "Au moins un parametre hub.mode, hub.verify_token est manquant ou invalide";
      http_response_code(403);
      die();
   }
} 


//
// POST is used when an 'update' is received.
// 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $results = json_decode(file_get_contents('php://input'), true);
    if (isset($results)) {

       // Drop the update, 
       //
       // Process the request, and return 200 OK
       // 
       if ($results['subscription_id'] === $eqLogic->getConfiguration('subscription_id')) {
          // Update the eqLogic with the information provided
       }
    }
}

