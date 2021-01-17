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
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__  . '/strava_provider.php';
require_once __DIR__  . '/strava_owner.php';

class strava extends eqLogic {
 

    /*     * *************************Attributs****************************** */
    
    public static $_widgetPossibility = array('custom' => true);
    
    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
      public static function cron15() {
      }
     */
    
    /*
     * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
      public static function cron30() {
      }
     */
    
    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {
      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {
      }
     */



    /*     * *********************Méthodes d'instance************************* */

    //
    // function used by League (OAUTH2) client library. 
    // We create a StravaProvider class. The redirectURI is our Strava callback
    // for this module. It contains the API key (to identify external access)
    // and the identifiant (ID) of the user. 
    //
    // For the authorization (OAUTH2), the callback is authorization.php
    // This URL must be accessible from the internet, so we use the external
    // address configured in JEEDOM
    //
    public function getStravaProvider() {

        return new StravaProvider([
        'clientId'     => $this->getConfiguration('client_id'),
        'clientSecret' => $this->getConfiguration('client_secret'),
        'redirectUri'  => network::getNetworkAccess('external') . '/plugins/strava/core/php/authorization.php?apikey=' . jeedom::getApiKey('strava') . '&eqLogic_id=' . $this->getId()
         ]);
    }

    //
    // function that trigger the STRAVA authorization mechanism, and
    // associate the user in the plugin with the strava account
    //
    // This has to be called only once. After the access has been granted
    // then, we will use authenticationToken that we need to refresh
    // on a regular basis.
    //
    public function connectWithStrava() {
        @session_start();
        $provider                = $this->getStravaProvider();
        $authorizationUrl        = $provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $provider->getState();
        return $authorizationUrl;
    }

             
    //
    // Once the authorization has been granted, we need to use the 
    // provided token to authenticate our request.
    // the lifetime of the token is 'short', so we need to check
    // if the token is still valid. If needed, we will renew the 
    // token through this method
    // 
    public function getAccessToken($_force = false) {
        $provider     = $this->getStravaProvider();
        $currentToken = new AccessToken($this->getConfiguration('accessToken'));
        if ($currentToken->hasExpired || $_force == true) {
           // We are using default GrantFactory, so refresh_token
           // will become RefreshToken class
           $newToken = $provider->getAccessToken('refresh_token', [
               'refresh_token' => $currentToken->getRefreshToken()]);
           // Same has before, store the new token
           $this->setConfiguration('accessToken', $newToken->jsonSerialize());
           $this->save();
           return $newToken;
        }
        return $currentToken;
    }

    // 
    // Send a Request to STRAVA.
    // 1. try with the existing authenticationToken (we don't keep track of the 
    //    expiration date of the token
    // 2. if it fails, then retry with a new fresh token
    //
    private function authenticatedRequest($_type, $_request, $_options = array()) {
        $provider = $this->getStravaProvider();
        try {
            $request = $provider->getAuthenticatedRequest(
                $_type, 
                StravaProvider::BASE_STRAVA_API_URL . $_request, 
                $this->getAccessToken(), 
                $_options);
                return json_decode((string)$provider->getResponse($request)->getBody(), true);
        } catch (Exception $e) {
            // just ignore the exception, and retry with a new (refreshed) token
        }
        // Try again, with a new access token
        $request = $provider->getAuthenticatedRequest(
            $_type, 
            StravaProvider::BASE_STRAVA_API_URL . $_request, 
            $this->getAccessToken(true), 
            $_options);
         return json_decode((string)$provider->getResponse($request)->getBody(), true);
    }


    //
    // Subscription section
    // we use a different StravaProvider, because for the subscriptions push service,
    // we use another callback URL: webhook.php
    // 
    public function getSubscriptionProvider() {

        return new StravaProvider([
        'clientId'     => $this->getConfiguration('client_id'),
        'clientSecret' => $this->getConfiguration('client_secret'),
        'redirectUri'  => network::getNetworkAccess('external') . '/plugins/strava/core/php/webhook.php?apikey=' . jeedom::getApiKey('strava') . '&eqLogic_id=' . $this->getId()
         ]);
    }

    //
    // Send unauthenticated request to STRAVA, use only for subscriptions push requests
    //
    private function request(_$type, $_request, $_options = array()) {
        $provider = $this->getSubscriptionProvider();
    $request = $provider->getRequest(
        $_type, 
        StravaProvider::BASE_STRAVA_API_URL . $_request, 
        $_options);
         return json_decode((string)$provider->getResponse($request)->getBody(), true);
    }


    // Subscription: see @links: https://developers.strava.com/docs/webhooks/
    //
    // Subscribe to PUSH notifications from STRAVA
    //
    public function createSubscription($_force=false) {
       // If we already have a subscription, then delete this subscription if force is set
       //
       $rsp = viewSubscription();
       if (isset($rsp['id']) && ($_force == true)) {
       // Delete existing subscription
       $this->deleteSubscription();

       // Create a new verify_token, and save it ! 
       $token = config::getKey();
       $this->setConfiguration('subscription_token', $token);

       // and create the subscription, using webhook callback
       $rsp = $this->request(
            StravaProvider::METHOD_POST, 
          'push_subscriptions', 
          ['verify_token' => $token]);
           if (!isset($rsp['id'])) {
           throw new Exception(__('Impossible de creer une souscription STRAVA', __FILE__));
       }
       }
       $this->setConfiguration('subcription_id', $rsp['id']);
       return $rsp['id'];
    }

    //
    // Unsubscribe to PUSH notifications from STRAVA
    // should return 204 if everything went well
    //
    public function deleteSubscription() {
        $this->request(
            'DELETE',
        'push_subscriptions/' . $this->getConfiguration('subscription_id'), 
        ['verify_token' => $this->getConfiguration('subscription_token')]
        );
    }

    //
    // View subscription to PUSH notifications from STRAVA
    //
    public function viewSubscription() {
       return $this->request(StravaProvider::METHOD_GET, 'push_subscriptions');
    }

    // Fonction exécutée automatiquement après la sauvegarde ecréation ou ma jour) de l'équipement 
    public function postSave() {
        
    }


    // 
    public function preRemove() {
       // Unsubscribe to STRAVA push notification
       try {
          $this->deleteSubscription();
       } catch(Exception $e) {
       }
    }

    /*     * **********************Getteur Setteur*************************** */
}

class stravaCmd extends cmd {
    /*     * *************************Attributs****************************** */
    
    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

     // Exécution d'une commande  
     public function execute($_options = array()) {
        
     }

    /*     * **********************Getteur Setteur*************************** */
}


