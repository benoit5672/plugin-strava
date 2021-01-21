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

use League\OAuth2\Client\Token\AccessToken;

class strava extends eqLogic {
 
    const BASE_STRAVA_SUBSCRIPTIONS = 'https://www.strava.com/api/v3/push_subscriptions';

    /*     * *************************Attributs****************************** */
    
    public static $_widgetPossibility = array('custom' => true);
    
    /*     * ***********************Methode static*************************** */


    //
    // Check network configuration, HTTPS must be enabled
    // Check that Strava authorization has been granted
    // Check that Strava webhook is activated
    //
	public static function health() {
		$https = strpos(network::getNetworkAccess('external'), 'https') !== false;
		$return[] = array(
			'test' => __('HTTPS', __FILE__),
			'result' => ($https) ? __('OK', __FILE__) : __('NOK', __FILE__),
			'advice' => ($https) ? '' : __('Votre Jeedom ne permet pas le fonctionnement de Strava sans HTTPS', __FILE__),
			'state' => $https,
		);

        // @todo: check strava authorization and strava webhook for all Strava users.
		return $return;
	}


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
    public function getProvider() {

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
        log::add('strava', 'debug', 'connect with Strava');
        session_start();
        $provider                = $this->getProvider();
        $authorizationUrl        = $provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $provider->getState();
        return $authorizationUrl;
    }

    //
    // Revoke access from Jeedom to Strava. This can be done through the plugin
    // or through the Strava website
    //
    public function disconnectFromStrava() {
        log::add('strava', 'debug', 'disconnect from Strava');
        $provider            = $this->getProvider();
        $deauthorizationUrl  = $provider->getBaseDeauthorizationUrl();
        try {
            $this->getRequest(StravaProvider::POST, $deauthorizationUrl);
        } catch (Exception $e) {
            log::add('strava', 'error', 'Failed to deauthorized user: ' . $e->getMessage());
            throw $e;
        }
        
        // Delete the information from the session
        //@todo $_SESSION = array();
        unset($_SESSION['oauth2state']);
    }

             
    //
    // Once the authorization has been granted, we need to use the 
    // provided token to authenticate our request.
    // the lifetime of the token is 'short', so we need to check
    // if the token is still valid. If needed, we will renew the 
    // token through this method
    // 
    public function getAccessToken($_force = false) {
        log::add('strava', 'debug', 'BR>> getAccessToken #1 ' . $_force);
        $currentToken = new AccessToken($this->getConfiguration('accessToken'));
        log::add('strava', 'debug', 'BR>> getAccessToken #1.5');
        if ($currentToken->hasExpired() || $_force == true) {
           log::add('strava', 'debug', 'BR>> getAccessToken #2');
           // We are using default GrantFactory, so refresh_token
           // will become RefreshToken class
           $provider = $this->getProvider();
           log::add('strava', 'debug', 'BR>> getAccessToken #3');
           $newToken = $provider->getAccessToken('refresh_token', [
               'refresh_token' => $currentToken->getRefreshToken()]);
           // Same has before, store the new token
           log::add('strava', 'debug', 'BR>> getAccessToken #4');
           $this->setConfiguration('accessToken', $newToken->jsonSerialize());
           $this->save();
           log::add('strava', 'debug', 'BR>> getAccessToken #5');
           return $newToken;
        }
        log::add('strava', 'debug', 'BR>> getAccessToken #6');
        return $currentToken;
    }

    // 
    // Send a Request to STRAVA.
    // 1. try with the existing authenticationToken (we don't keep track of the 
    //    expiration date of the token
    // 2. if it fails, then retry with a new fresh token
    //
    private function getRequest($_type, $_url, $_options = array()) {
        log::add('strava', 'debug', 'SEND ' . $_type . ', ' . $_url); 
        $provider = $this->getProvider();
        try {
            log::add('strava', 'debug', 'BR>> getRequest #1');
            $rsp = $provider->getAuthenticatedRequest(
                $_type, 
                $_url, 
                $this->getAccessToken(), 
                $_options);
            log::add('strava', 'debug', 'BR>> getRequest #2');
            return json_decode((string)$provider->getResponse($rsp)->getBody(), true);
        } catch (Exception $e) {
            // just ignore the exception, and retry with a new (refreshed) token
            log::add('strava', 'debug', 'getRequest raised: ' . $e->getMessage());
        }
        // Try again, with a new access token
        log::add('strava', 'debug', 'BR>> getRequest #3');
        $rsp = $provider->getAuthenticatedRequest(
            $_type, 
            $_url, 
            $this->getAccessToken(true), 
            $_options);
        log::add('strava', 'debug', 'BR>> getRequest #4');
        return json_decode((string)$provider->getResponse($rsp)->getBody(), true);
    }

    // 
    // Set and get StravaId
    //
    public function setStravaId($_id) {
        $this->setConfiguration('strava_id', $_id);
    }

    public function getStravaId($_id) {
        return $this->setConfiguration('strava_id', $_id);
    }

    public function isRegisteredToStrava() {
        $id = int($this->getConfiguration('strava_id', -1)); 
        return ($id !== -1);
    }


    //
    // Subscription section
    //
    // Subscription: see @links: https://developers.strava.com/docs/webhooks/
    //
    private function subscriptionsRequest($_type, $_url, $_options = array()) {
        if ($_type === 'GET') {
            $url  = $_url . '?client_id=' . urlencode($this->getConfiguration('client_id'));
            $url .= '&client_secret=' . urlencode($this->getConfiguration('client_secret'));
            $data = [];
        } else { 
            // 'POST' or 'DELETE'
            $url  = $_url;
            $data = [
                'client_id'     => $this->getConfiguration('client_id'),
                'client_secret' => $this->getConfiguration('client_secret')
            ];
            if (isset($_options['callback_url'])) {
                $data['callback_url'] = $_options['callback_url'];
            } 
            if (isset($_options['verify_token'])) {
                $data['verify_token'] = $_options['verify_token'];
            } 
        }
	    log::add('strava', 'debug', ' url ' . $url . ', data=' . print_r($data, true));

        $nbRetry  = 0;
        $maxRetry = 3;
        while ($nbRetry < $maxRetry) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            if (count($data) > 0) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);

            // set the verb (POST, GET, DELETE)
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_type);

            $rsp = curl_exec($ch);        
            $nbRetry++;
            if (curl_errno($ch) && $nbRetry < $maxRetry) {
                curl_close($ch);
                usleep($this->getSleepTime());
            } else {
                $nbRetry = $maxRetry + 1;
            }
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        /**
        //$httpRequest = new com_http($url);
        if (count($data) > 0) {
            $httpRequest->setHeader(array('Content-Type: multipart/form-data'));
	        $httpRequest->setPost($data);
        }
	    $httpRequest->setNoReportError(true);
		$rsp = $httpRequest->exec(30);
         */
        log::add('strava', 'debug', 'Response : ' . $rsp . ', code=' . $http_code);
        if (($_type === 'DELETE' && $http_code != 204)
            || ($_type === 'POST' && $http_code != 201)
            || ($_type === 'GET' && $http_code != 200)) { 
			throw new Exception(__('Erreur lors de la communication avec STRAVA: ', __FILE__) . $rsp . 'http code: ' . $http_code);
		}
		return json_decode($rsp, true);
    }

    //
    // Subscribe to PUSH notifications from STRAVA
    //
    public function createSubscription($_force=false) {

       log::add('strava', 'debug', 'createSubscription');

       // If we already have a subscription, then delete this subscription if force is set
       $subscriptionId = $this->viewSubscription();
       if ($subscriptionId < 0 or $_force == true) {

           log::add('strava', 'debug', 'BR>> createSubscription #2');
           if ($subscriptionId > 0) {
               // Delete existing subscription
               $this->deleteSubscription();
           }
           log::add('strava', 'debug', 'BR>> createSubscription #3');
           
           // Create a new verify_token, and save it ! 
           $token = config::genKey();
           //$this->setConfiguration('subscription_token', $token);
           //$this->save();
           $this->setCache('subscription_token', $token);
           log::add('strava', 'debug', 'BR>> createSubscription #4 ' . $token);

           // and create the subscription, using webhook callback
           $rsp = $this->subscriptionsRequest(
                    'POST',
                    self::BASE_STRAVA_SUBSCRIPTIONS,
                    [
                        'callback_url' => network::getNetworkAccess('external') . '/plugins/strava/core/php/webhook.php?apikey=' . jeedom::getApiKey('strava') . '&eqLogic_id=' . $this->getId(),
                        'verify_token' => $token
                    ]);

           log::add('strava', 'debug', 'BR>> createSubscription #5');
           // The subscriptionsToken is no more used, so reset it
           //$this->setConfiguration('subscription_token', '');
           $this->setCache('subscription_token', null);

           if (!isset($rsp['id'])) {
               log::add('strava', 'error', __('Impossible de creer une souscription STRAVA', __FILE__));
               throw new Exception(__('Impossible de creer une souscription STRAVA', __FILE__));
           }
           log::add('strava', 'debug', 'BR>> createSubscription #6');

           // Save the subscription information
           $subscriptionId = $rsp['id'];
           $this->setConfiguration('subscription_id', $subscriptionId);
           $this->save();
           
           log::add('strava', 'debug', 'BR>> createSubscription #7');
       }
       log::add('strava', 'debug', 'BR>> createSubscription #8');
       return $subscriptionId;
    }

    //
    // Unsubscribe to PUSH notifications from STRAVA
    // should return 204 if everything went well
    //
    public function deleteSubscription() {
       log::add('strava', 'debug', 'deleteSubscription');
       $subscriptionId = $this->viewSubscription();
       if ($subscriptionId > 0) {
           $this->subscriptionsRequest(
                'DELETE',
                self::BASE_STRAVA_SUBSCRIPTIONS . '/' . $subscriptionId
           );
       }
       $this->setConfiguration('subscription_id', -1);
       $this->save();
    }

    //
    // View subscription to PUSH notifications from STRAVA
    //
    public function viewSubscription() {
       log::add('strava', 'debug', 'viewSubscription');
       $subscriptionId = $this->getConfiguration('subscription_id', -2);
       try {
           $rsp = $this->subscriptionsRequest('GET', self::BASE_STRAVA_SUBSCRIPTIONS);
           if (count($rsp) > 0 && isset($rsp[0]['id'])) {
               $this->setConfiguration('subscription_id', $rsp[0]['id']);
           } else {
               $this->setConfiguration('subscription_id', -1);
           }
           $this->save();
           return $this->getConfiguration('subscription_id');
       } catch (Exception $e) {
           log::add('strava', 'error', $e->getMessage());
           throw $e;
       }
    }

    public function isRegisteredForSubscription() {
        $id = int($this->getConfiguration('subscription_id', -1)); 
        return ($id !== -1);
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

       // Deauthorize the user
       try {
          $this->disconnectFromStrava();
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


