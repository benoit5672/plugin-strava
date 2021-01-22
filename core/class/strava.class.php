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
    const API_LIMIT_15M             = 100;
    const API_LIMIT_DAY             = 1000;

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
     */
    public static function cron15() {
    	foreach (self::byType(__CLASS__) as $user) {
            if (is_object($user) && $user->getIsEnable() == 1) {
                $user->setCache('15mUsage', 0);
            } 
        }
    }
    
    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
     */
    public static function cronDaily() {
    	foreach (self::byType(__CLASS__) as $user) {
           if (is_object($user) && $user->getIsEnable() == 1) {
               $user->setCache('dayUsage', 0);
           } 
        }
    }



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
    // Send a Request to STRAVA.
    // 1. try with the existing authenticationToken (we don't keep track of the 
    //    expiration date of the token
    // 2. if it fails, then retry with a new fresh token
    //
    private function getRequest($_verb, $_url, $_options = array()) {
        //log::add('strava', 'debug', 'SEND ' . $_verb . ', ' . $_url . ', ' . print_r($_options, true)); 
        if ($this->getCache('15mUsage', 0) >= $this->getCache('15Limit', self::API_LIMIT_15M)
            or $this->getCache('dayUsage', 0) >= $this->getCache('dayLimit', self::API_LIMIT_DAY)) {
            log::add('strava', 'error', __('Limite de requetes atteinte pour la journee ou les 15 dernieres minutes', __FILE__));
            return [];
        }

        // Let's process request
        $provider = $this->getProvider();
        try {
            $this->setCache('15mUsage', $this->getCache('15mUsage') + 1);
            $this->setCache('dayUsage', $this->getCache('dayUsage') + 1);
            $rsp = $provider->getAuthenticatedRequest(
                $_verb, 
                $_url, 
                $this->getAccessToken(), 
                $_options);

            // Update our usage counters, available when we are close to our API call limits
            $limit = $rsp->getHeader('X-Ratelimit-Limit');
            $usage = $rsp->getHeader('X-Ratelimit-Usage');
            if (count($limit) > 0 and count($usage) > 0) {
                $this->setCache('15mLimit', $limit[0]);
                $this->setCache('dayLimit', $limit[1]);
                $this->setCache('15mUsage', $usage[0]);
                $this->setCache('dayUsage', $usage[1]);
                log::add('strava', 'debug', 'Limits: 15l=' . $limit[0] . ', dl=' . $limit[1] . ', 15u=' . $usage[0] . ', du=' . $usage[1]);
            }
            return json_decode((string)$provider->getResponse($rsp)->getBody(), true);
        } catch (Exception $e) {
            // just ignore the exception, and retry with a new (refreshed) token
            log::add('strava', 'debug', 'getRequest raised: ' . $e->getMessage());
        }
        // Try again, with a new access token
        $this->setCache('15mUsage', $this->getCache('15mUsage') + 1);
        $this->setCache('dayUsage', $this->getCache('dayUsage') + 1);
        $rsp = $provider->getAuthenticatedRequest(
            $_verb, 
            $_url, 
            $this->getAccessToken(true), 
            $_options);
        
        // Update our usage counters
        if (count($limit) > 0 and count($usage) > 0) {
            $this->setCache('15mLimit', $limit[0]);
            $this->setCache('dayLimit', $limit[1]);
            $this->setCache('15mUsage', $usage[0]);
            $this->setCache('dayUsage', $usage[1]);
            log::add('strava', 'debug', 'Limits: 15l=' . $limit[0] . ', dl=' . $limit[1] . ', 15u=' . $usage[0] . ', du=' . $usage[1]);
        }

        return json_decode((string)$provider->getResponse($rsp)->getBody(), true);
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
            $this->getRequest('POST', $deauthorizationUrl);
            $this->setConfiguration('strava_id', -1);
            $this->setConfiguration('subscription_id', -1);
        } catch (Exception $e) {
            log::add('strava', 'error', 'Failed to deauthorized user: ' . $e->getMessage());
            throw $e;
        }
        
        // Delete the information from the session
        //@todo $_SESSION = array();
        //unset($_SESSION['oauth2state']);
    }

             
    //
    // Once the authorization has been granted, we need to use the 
    // provided token to authenticate our request.
    // the lifetime of the token is 'short', so we need to check
    // if the token is still valid. If needed, we will renew the 
    // token through this method
    // 
    public function getAccessToken($_force = false) {
        $currentToken = new AccessToken($this->getConfiguration('accessToken'));
        if ($currentToken->hasExpired() || $_force == true) {
           // We are using default GrantFactory, so refresh_token
           // will become RefreshToken class
           $provider = $this->getProvider();
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
    // function to get the current usage and limit
    // 
    public function getUsagesAndLimits() {
        return [
            [$this->getCache('15mLimit', self::API_LIMIT_15M), $this->getCache('dayLimit', self::API_LIMIT_DAY)],
            [$this->getCache('15mUsage', 0), $this->getCache('dayUsage', 0)]
        ];
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
        $id = $this->getConfiguration('strava_id', -1); 
        return ($id !== -1);
    }


    public function getAthleteStats() {
        log::add('strava', 'debug', 'BR>> getAthleteStats #1');
        if ($this->isRegisteredToStrava()) {
            log::add('strava', 'debug', 'BR>> getAthleteStats #2');
            $rsp = $this->getRequest(
                'GET', 
                $this->getProvider()->getBaseApi() . '/athletes/' . $this->getConfiguration('strava_id') . '/stats');
            log::add('strava', 'debug', 'BR>> getAthleteStats #3 (rx:' . print_r($rsp, true) . ')');
        } else {
            log::add('strava', 'warn', __('Vous n\'etes pas connecte a Strava', __FILE__));
        }
        log::add('strava', 'debug', 'BR>> getAthleteStats #4');
    }

    public function getAuthenticatedAthlete() {
        if ($this->isRegisteredToStrava()) {
            $rsp = $this->getRequest(
                'GET', 
                $this->getProvider()->getBaseApi() . '/athlete');
        } else {
            log::add('strava', 'warn', __('Vous n\'etes pas connecte a Strava', __FILE__));
        }
    }


    public function getDailyActivitiesStats() {
        $before   = time();
        $after    = strtotime('yesterday'); 
        //$after    = strtotime('Monday this week'); 
        return $this->getActivitiesStats($before, $after);
    }

    public function getYearlyActivitiesStats() {
        $before   = time();
        $after    = strtotime('first day of january'.date('Y'));
        return $this->getActivitiesStats($before, $after);
    }

    private function getActivitiesStats($_before, $_after) {
        if ($this->isRegisteredToStrava()) {
            $page       = 1;
            $per_page   = 30;
            $completed  = false;
            $activities = [];
            while ($completed === false) {
                $rsp = $this->getRequest(
                    'GET', 
                    $this->getProvider()->getBaseApi() . '/athlete/activities'
                            .'?before='.$_before.'&after='.$_after.'&page='.$page.'&per_page='.$per_page);
                log::add('strava', 'debug', 'Add ' . count($rsp) . ' activities');
                if ($rsp != []) {
                    $activities = array_merge_recursive($activities, $rsp); 
                    $page++;
                } else {
                    $completed = true; 
                }
            } 
        } else {
            log::add('strava', 'warn', __('Vous n\'etes pas connecte a Strava', __FILE__));
        }
        log::add('strava', 'debug', 'Return ' . count($activities) . ' activities');
        return $activities;
    }


    public function setAthleteWeight() {
        if ($this->isRegisteredToStrava()) {
            $cmd = $this->getCmd(null, 'weight');
            if (!is_object($cmd)) {
                log::add('strava', 'error', $this->getHumanName()
                    . __('Impossible de mettre a jour le poids car la commande n\'existe pas', __FILE__));
            } else {
                $rsp = $this->getRequest(
                    'PUT', 
                    $this->getProvider()->getBaseApi() . '/athlete?' . $cmd->execCmd());
            }
        } else {
            log::add('strava', 'warn', __('Vous n\'etes pas connecte a Strava', __FILE__));
        }
    }


    //
    // Subscription section
    //
    // Subscription: see @links: https://developers.strava.com/docs/webhooks/
    //
    private function subscriptionsRequest($_verb, $_url, $_options = array()) {
        if ($_verb === 'GET') {
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
	    //log::add('strava', 'debug', ' url ' . $url . ', data=' . print_r($data, true));

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
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_verb);

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

        log::add('strava', 'debug', 'Response : ' . $rsp . ', code=' . $http_code);
        if (($_verb === 'DELETE' && $http_code != 204)
            || ($_verb === 'POST' && $http_code != 201)
            || ($_verb === 'GET' && $http_code != 200)) { 
            throw new Exception(__('Erreur de communication avec STRAVA: ', __FILE__) 
                . $rsp . 'http code: ' . $http_code);
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

           if ($subscriptionId > 0) {
               // Delete existing subscription
               $this->deleteSubscription();
           }
           
           // Create a new verify_token, and save it ! 
           $token = config::genKey();
           $this->setCache('subscription_token', $token);

           // and create the subscription, using webhook callback
           $rsp = $this->subscriptionsRequest(
                    'POST',
                    self::BASE_STRAVA_SUBSCRIPTIONS,
                    [
                        'callback_url' => network::getNetworkAccess('external') . '/plugins/strava/core/php/webhook.php?apikey=' . jeedom::getApiKey('strava') . '&eqLogic_id=' . $this->getId(),
                        'verify_token' => $token
                    ]);

           // The subscriptionsToken is no more used, so reset it
           $this->setCache('subscription_token', null);

           if (!isset($rsp['id'])) {
               log::add('strava', 'error', __('Impossible de creer une souscription STRAVA', __FILE__));
               throw new Exception(__('Impossible de creer une souscription STRAVA', __FILE__));
           }

           // Save the subscription information
           $subscriptionId = $rsp['id'];
           $this->setConfiguration('subscription_id', $subscriptionId);
           $this->save();
           
       }
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
        $id = $this->getConfiguration('subscription_id', -1); 
        return ($id !== -1);
    }

    //
    // Sport Management Section
    //
    private function createCommands($_order, $_logicalId, $_name) {
        // Total (week)
        log::add('strava', 'debug', 'Create commands for ' . $_logicalId . ', name=' . $_name . ', index=' . $_order);
        $cmd = $this->getCmd(null, $_logicalId . '_count');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId($_logicalId . '_count');
			$cmd->setIsVisible(1);
			$cmd->setOrder($_order);
			$cmd->setName($_name . __(' (Total)', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
            $_order++;
        }
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setEqLogic_id($this->getId());
		$cmd->save();

        // Distance (week)
        $cmd = $this->getCmd(null, $_logicalId . '_distance');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId($_logicalId . '_distance');
			$cmd->setIsVisible(1);
			$cmd->setOrder($_order);
			$cmd->setName($_name . __(' (Distance)', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
            $_order++;
        }
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setUnite('kms');
		$cmd->setEqLogic_id($this->getId());
		$cmd->save();
        
        // Elevation (week)
        $cmd = $this->getCmd(null, $_logicalId . '_elevation');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId($_logicalId . '_elevation');
			$cmd->setIsVisible(1);
			$cmd->setOrder($_order);
			$cmd->setName($_name . __(' (Denivelle)', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
            $_order++;
        }
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setUnite('m');
		$cmd->setEqLogic_id($this->getId());
		$cmd->save();

        // time 
        $cmd = $this->getCmd(null, $_logicalId . '_time');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId($_logicalId . '_time');
			$cmd->setIsVisible(1);
			$cmd->setOrder($_order);
			$cmd->setName($_name . __(' (Temps)', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
            $_order++;
        }
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setUnite('s');
		$cmd->setEqLogic_id($this->getId());
		$cmd->save();
        
        //
        // Total (year)
        $cmd = $this->getCmd(null, $_logicalId . '_count_year');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId($_logicalId . '_count_year');
			$cmd->setIsVisible(1);
			$cmd->setOrder($_order);
			$cmd->setName($_name . __(' (Total annuel)', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
            $_order++;
        }
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setEqLogic_id($this->getId());
		$cmd->save();

        // Distance (year)
        $cmd = $this->getCmd(null, $_logicalId . '_distance_year');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId($_logicalId . '_distance_year');
			$cmd->setIsVisible(1);
			$cmd->setOrder($_order);
			$cmd->setName($_name . __(' (Distance annuelle)', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
            $_order++;
        }
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setUnite('kms');
		$cmd->setEqLogic_id($this->getId());
		$cmd->save();
        
        // Elevation (year)
        $cmd = $this->getCmd(null, $_logicalId . '_elevation_year');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId($_logicalId . '_elevation_year');
			$cmd->setIsVisible(1);
			$cmd->setOrder($_order);
			$cmd->setName($_name . __(' (Denivelle annuel)', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
            $_order++;
        }
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setUnite('m');
		$cmd->setEqLogic_id($this->getId());
		$cmd->save();
        
        // time (year)
        $cmd = $this->getCmd(null, $_logicalId . '_time_year');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId($_logicalId . '_time_year');
			$cmd->setIsVisible(1);
			$cmd->setOrder($_order);
			$cmd->setName($_name . __(' (Temps annuel)', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
            $_order++;
        }
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setUnite('heures');
		$cmd->setEqLogic_id($this->getId());
		$cmd->save();
    }

    private function deleteCommands($_logicalId) {

        $extensions = ['_count', '_distance', '_elevation', '_time',
                       '_count_year', '_distance_year', '_elevation_year', '_time_year'];

        foreach ($extensions as $extension) {
            $cmd = $this->getCmd(null, $_logicalId . $extension);
		    if (is_object($cmd)) {
                $cmd->remove();
            }
        }
    }

    // Synchronized the information
    // @todo: use webhook messages instead to update the information
    private function syncStrava($_activities) {
        log::add('strava', 'debug', 'daily activities to process: ' . count($_activities));
        foreach ($_activities as $activity) {
            $type  = $activity['type'];
            $start = strtotime($activity['start_date'] + $activity['utc_offset']);
            if (($this->getConfiguration($type, 0) == 1)
                and ($start > $this->getConfiguration('last_update', 0))) {

                // This activity is monitored, let's process it !
                $distance  = round($activity['distance'] / 1000, 2);
                $elevation = $activity['total_elevation_gain'];
                $time      = $activity['moving_time'];

                // Weekly update
                $cmd = $this->getCmd(null, $type . '_count');
                $this->checkAndUpdate($type . '_count', $cmd->execCmd() + 1);
                $cmd = $this->getCmd(null, $type . '_distance');
                $this->checkAndUpdate($type . '_distance', $cmd->execCmd() + $distance);
                $cmd = $this->getCmd(null, $type . '_elevation');
                $this->checkAndUpdate($type . '_elevation', $cmd->execCmd() + $elevation);
                $cmd = $this->getCmd(null, $type . '_time');
                $this->checkAndUpdate($type . '_time', $cmd->execCmd() + $time);

                // Yearly update
                $cmd = $this->getCmd(null, $type . '_count_year');
                $this->checkAndUpdate($type . '_count_year', $cmd->execCmd() + 1);
                $cmd = $this->getCmd(null, $type . '_distance_year');
                $this->checkAndUpdate($type . '_distance_year', $cmd->execCmd() + $distance);
                $cmd = $this->getCmd(null, $type . '_elevation_year');
                $this->checkAndUpdate($type . '_elevation_year', $cmd->execCmd() + $elevation);
                $cmd = $this->getCmd(null, $type . '_time_year');
                $this->checkAndUpdate($type . '_time_year', $cmd->execCmd() + $time);

            }
        }
        $this->setConfiguration('last_update', time());
    }

    // Fonction exécutée automatiquement après la sauvegarde ecréation ou ma jour) de l'équipement 
    public function postSave() {
        # weight / read write
        $cmd = $this->getCmd(null, 'weight');
		if (!is_object($cmd)) {
			$cmd = new stravaCmd();
			$cmd->setLogicalId('weight');
			$cmd->setIsVisible(1);
			$cmd->setOrder(1);
			$cmd->setName(__('Poids', __FILE__));
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
		}
		$cmd->setType('info');
		$cmd->setSubType('numeric');
		$cmd->setUnite('kg');
		$cmd->setEqLogic_id($this->getId());
	    $cmd->save();

   		$cmd = $this->getCmd(null, 'setWeight');
		if (!is_object($cmd)) {
			$cmd = new StravaCmd();
			$cmd->setLogicalId('setWeight');
			$cmd->setIsVisible(1);
			$cmd->setName(__('Envoyer poids', __FILE__));
            $cmd->setOrder(2);
        }
		$cmd->setEqLogic_id($this->getId());
		$cmd->setType('action');
		$cmd->setSubType('numeric');
        $cmd->save();

        // Create all the sports, that are checked, remove other sports (unchecked)
        $sports = [
           'AlpineSki' => __('Ski alpin', __FILE__),
           'BackcountrySki' => __('Ski de randonnee', __FILE__),
           'Canoeing' => __('Canoe', __FILE__),
           'Crossfit' => __('Crossfit', __FILE__), 
           'EBikeRide' => __('Velo electrique', __FILE__),
           'Elliptical' => __('Elliptique', __FILE__),
           'Golf' => __('Golf', __FILE__),
           'Handcycle' => __('Handbike', __FILE__), 
           'Hike' => __('Randonnee', __FILE__),
           'Iceskate' => __('Patinage', __FILE__),
           'InlineSkate' => __('Roller', __FILE__),
           'Kayaking' => __('Kayak', __FILE__),
           'Kitesurf' => __('Kitesurf', __FILE__), 
           'NordicSki' => __('Ski nordique', __FILE__),
           'Ride' => __('Velo', __FILE__),
           'RockClimbing' => __('Escalade', __FILE__),
           'RollerSki' => __('Ski a roulettes', __FILE__),
           'Rowing' => __('Aviron', __FILE__),
           'Run' => __('Course a pied', __FILE__),
           'Sail' => __('Voile', __FILE__),
           'Skateboard' => __('Skateboard', __FILE__),
           'Snowboard' => __('Snowboard', __FILE__),
           'Snowshoe' => __('Raquettes', __FILE__),
           'Soccer' => __('Football', __FILE__),
           'StairStepper' => __('Simulateur d\'escaliers', __FILE__),
           'StandUpPaddling' => __('Standup paddle', __FILE__),
           'Surfing' => __('Surf', __FILE__), 
           'Swim' => __('Natation', __FILE__), 
           'Velomobile' => __('Velomobile', __FILE__),
           'VirtualRide' => __('Velo virtuel', __FILE__),
           'VirtualRun' => __('Course a pied virtuelle', __FILE__),
           'Walk' => __('Marche', __FILE__),
           'WeightTraining' => __('Entrainement aux poids', __FILE__),
           'Wheelchair' => __('Course en fauteuil', __FILE__),
           'Windsurf' => __('Windsurf', __FILE__),
           'Workout' => __('Entrainement', __FILE__),
           'Yoga' => __('Yoga', __FILE__)
        ];
        foreach ($sports as $key => $value) {
            $index = count($this->getCmd()) + 1;
            if ($this->getConfiguration($key, 0) == 1) {
                log::add('strava', 'debug', 'Add commands for ' . $key);
                $this->createCommands($index, $key, $value);
            } else {
                log::add('strava', 'debug', 'Remove commands for ' . $key);
                $this->deleteCommands($key);
            }
        }
    }


    // 
    public function preRemove() {

       if ($this->getConfiguration('accessToken') !== '') {
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
    }

    /*     * **********************Getteur Setteur*************************** */
}

class stravaCmd extends cmd {
    /*     * *************************Attributs****************************** */
    
    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

     // Exécution d'une commande  
     public function execute($_options = array()) {
        
     }

    /*     * **********************Getteur Setteur*************************** */
}


