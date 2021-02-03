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
require_once __DIR__  . '/strava_provider.class.php';
require_once __DIR__  . '/strava_owner.class.php';
require_once __DIR__  . '/strava_db.class.php';

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
        // @todo: check API quotas
        return $return;
    }

    /**
     * Function call by the plugin core object when the plugin is started
     */
    public static function start() {
        foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
            if (is_object($eqLogic)) {
                // Retrieve the latest information
                if ($eqLogic->isRegisteredToStrava()) {
                    $eqLogic->forceStatsUpdate();
                }
            }
        }
    }

    /*
     * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
     */
    public static function cron15() {
        // Reset the request counters
        self::setStravaQuota('15mUsage', 0);

        // Process the notitifications queue
        foreach (self::byType(__CLASS__, true) as $eqLogic) {
            if (is_object($eqLogic)) {
                $eqLogic->dequeueNotifications();
            }
        }
    }

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
     */
    public static function cronDaily() {
        // Reset the daily quota
        self::setStravaQuota('dayUsage', 0);

        // only the 'strava' object that are enabled
        foreach (self::byType(__CLASS__, true) as $eqLogic) {
            if (is_object($eqLogic)) {

                // reset counters if this is a new week
                if (1 == date('w', time())) {
                    log::add('strava', 'info', __('Re-initialisation des statistiques de la semaine', __FILE__));
                    $eqLogic->resetStats(true, false);
                }
                // reset counters if this is a new year
                if (strtotime('today GMT') == strtotime('first day of January '.date('Y'). 'GMT')) {
                    log::add('strava', 'info', __('Re-initialisation des statistiques de l\'année', __FILE__));
                    $eqLogic->resetStats(false, true);
                }

                // Remove old activities from DB
                $eqLogic->removeOldActivities();

                // Get the weight of the user
                try {
                    $rsp = $eqLogic->getAuthenticatedAthlete();
                    if (isset($rsp['weight'])) {
                        $eqLogic->checkAndUpdateCmd('weight', $rsp['weight']);
                    }
                } catch (Exception $e) {
                    log::add('strava', 'error', __('Erreur: ' + $e->getMessage()));
                }
            }
        }
    }

    // read the quota for the key. Autorized keys are '15mUsage', '15mLimit' and
    // 'dayUsage', 'dayLimit'
    private static function getStravaQuota($_key, $_default = 0) {
        $plugin = plugin::byId(__CLASS__);
		$cache  = cache::byKey('eqLogicCacheAttr' . $plugin->getId())->getValue();
		return utils::getJsonAttr($cache, $_key, $_default);
	}

    private static function setStravaQuota($_key, $_value) {
        $plugin = plugin::byId(__CLASS__);
        cache::set('eqLogicCacheAttr' . $plugin->getId(),
                   utils::setJsonAttr(cache::byKey('eqLogicCacheAttr' . $plugin->getId())->getValue(), $_key, $_value));
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
        if (self::getStravaQuota('15mUsage', 0) >= self::getStravaQuota('15Limit', self::API_LIMIT_15M)
            or self::getStravaQuota('dayUsage', 0) >= self::getStravaQuota('dayLimit', self::API_LIMIT_DAY)) {
            log::add('strava', 'error', __('Limite de requêtes atteinte pour la journée ou les 15 dernières minutes', __FILE__));
            return [];
        }

        // Let's process request
        $provider = $this->getProvider();
        try {
            self::setStravaQuota('15mUsage', self::getStravaQuota('15mUsage') + 1);
            self::setStravaQuota('dayUsage', self::getStravaQuota('dayUsage') + 1);
            $rsp = $provider->getAuthenticatedRequest(
                $_verb,
                $_url,
                $this->getAccessToken(),
                $_options);

            // Update our usage counters, available when we are close to our API call limits
            $limit = $rsp->getHeader('X-Ratelimit-Limit');
            $usage = $rsp->getHeader('X-Ratelimit-Usage');
            if (count($limit) > 0 and count($usage) > 0) {
                self::setStravaQuota('15mLimit', $limit[0]);
                self::setStravaQuota('dayLimit', $limit[1]);
                self::setStravaQuota('15mUsage', $usage[0]);
                self::setStravaQuota('dayUsage', $usage[1]);
                log::add('strava', 'debug', 'Limits: 15l=' . $limit[0] . ', dl=' . $limit[1] . ', 15u=' . $usage[0] . ', du=' . $usage[1]);
            }
            return json_decode((string)$provider->getResponse($rsp)->getBody(), true);
        } catch (Exception $e) {
            // just ignore the exception, and retry with a new (refreshed) token
            log::add('strava', 'debug', 'getRequest raised: ' . $e->getMessage());
        }
        // Try again, with a new access token
        self::setStravaQuota('15mUsage', self::getStravaQuota('15mUsage') + 1);
        self::setStravaQuota('dayUsage', self::getStravaQuota('dayUsage') + 1);
        $rsp = $provider->getAuthenticatedRequest(
            $_verb,
            $_url,
            $this->getAccessToken(true),
            $_options);

        // Update our usage counters
        if (count($limit) > 0 and count($usage) > 0) {
            self::setStravaQuota('15mLimit', $limit[0]);
            self::setStravaQuota('dayLimit', $limit[1]);
            self::setStravaQuota('15mUsage', $usage[0]);
            self::setStravaQuota('dayUsage', $usage[1]);
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
            $this->save();
        } catch (Exception $e) {
            log::add('strava', 'error', __('Impossible de se déconnecter de Strava: : ', __FILE__) . $e->getMessage());
            throw $e;
        }
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
            [self::getStravaQuota('15mLimit', self::API_LIMIT_15M), self::getStravaQuota('dayLimit', self::API_LIMIT_DAY)],
            [self::getStravaQuota('15mUsage', 0), self::getStravaQuota('dayUsage', 0)]
        ];
    }

    //
    // Set and get StravaId
    //
    public function setStravaId($_id) {
        $this->setConfiguration('strava_id', $_id);
    }

    public function getStravaId() {
        return $this->getConfiguration('strava_id', -1);
    }

    public function isRegisteredToStrava() {
        $id = $this->getConfiguration('strava_id', -1);
        return ($id !== -1);
    }


    public function getAthleteStats() {
        if ($this->isRegisteredToStrava()) {
            $rsp = $this->getRequest(
                'GET',
                $this->getProvider()->getBaseApi() . '/athletes/' . $this->getConfiguration('strava_id') . '/stats');
        } else {
            log::add('strava', 'warning', __('Vous n\'êtes pas connecté à Strava', __FILE__));
        }
    }

    public function getAuthenticatedAthlete() {
        if ($this->isRegisteredToStrava()) {
            $rsp = $this->getRequest(
                'GET',
                $this->getProvider()->getBaseApi() . '/athlete');
        } else {
            log::add('strava', 'warning', __('Vous n\'êtes pas connecté à Strava', __FILE__));
        }
    }

    private function getActivitiesStats($_before, $_after) {
        log::add('strava', 'debug', 'getActivitiesStats');
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
            log::add('strava', 'warning', __('Vous n\'etes pas connecté à Strava', __FILE__));
        }
        log::add('strava', 'debug', 'Return ' . count($activities) . ' activities');
        return $activities;
    }

    private function getActivity($_id) {
        if ($this->isRegisteredToStrava()) {
            return $rsp = $this->getRequest(
                   'GET',
                    $this->getProvider()->getBaseApi() . '/activities/' . $_id
                            .'?include_all_effort=false');
        }
        log::add('strava', 'warning', __('Vous n\'etes pas connecté à Strava', __FILE__));
        return [];
    }


    public function razStatistics() {
        if ($this->getIsEnable() == 1) {
            if ($this->getConfiguration('last_update', 0) == 0) {
                throw new Exception(__('Sauvegarder l\'athlète avant d\'appliquer cette commande', __FILE__));
            }
            if (!$this->isRegisteredToStrava()) {
                throw new Exception(__('Vous n\'etes pas connecté à Strava', __FILE__));
            }
            $this->resetStats(true, true);
            $this->forceStatsUpdate();
        }
    }


    public function forceStatsUpdate() {
        if ($this->getIsEnable() == 1) {
            if ($this->getConfiguration('last_update', 0) == 0) {
                throw new Exception(__('Sauvegarder l\'athlète avant d\'appliquer cette commande', __FILE__));
            }
            if (!$this->isRegisteredToStrava()) {
                throw new Exception(__('Vous n\'etes pas connecté à Strava', __FILE__));
            }
            $activities = $this->getActivitiesStats(time(), $this->getConfiguration('last_update'));
            $this->storeActivities($activities);
            $this->syncStats($activities);
        }
    }

    public function setAthleteWeight($_weight) {
        // Execute the commands only if we are enable
        if ($this->getIsEnable() == 1) {
            if (!$this->isRegisteredToStrava()) {
                throw new Exception(__('Vous n\'êtes pas connecté à Strava', __FILE__));
            }
            // Check that the user granted profile:write scope
            $scope = $this->getConfiguration('scope');
            if (strpos($scope, 'profile:write') === false) {
                throw new Exception(__('Vous n\'avez pas autorisé l\'écriture sur le profile', __FILE__));
            }
            $rsp = $this->getRequest(
                    'PUT',
                    $this->getProvider()->getBaseApi() . '/athlete?weight=' . $_weight);
            $this->checkAndUpdateCmd('weight', $_weight);
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
               log::add('strava', 'error', __('Impossible de créer une souscription STRAVA', __FILE__));
               throw new Exception(__('Impossible de créer une souscription STRAVA', __FILE__));
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

    /*
     * function called by the callback function, that is to say when a notification
     * is pushed from Strava. We need to answer with 2seconds, otherwise we will
     * have retransmission from Strava.
     * With the (insert/delete/update) in the database, it takes to much time.
     * We will use a queue with (action => activity) entry that will be processed
     * by the 15m cron.
     */
    public function processSubscriptionNotification($_notification) {
        if ($this->getIsEnable() == 1) {
            if (!$this->isRegisteredToStrava()) {
                throw new Exception(__('Vous n\'etes pas connecté à Strava', __FILE__));
            }
            // Process the notification
            $action = $_notification['aspect_type'];
            if (($_notification['owner_id'] == $this->getStravaId())
                and ($_notification['object_type'] === 'activity')) {

                log::add('strava', 'debug', 'Enqueuing notification: object_type: '
                    . $_notification['object_type']
                    . ', owner: ' . $_notification['owner_id'] . ', action=' . $action);

                $this->enqueueNotification($action, $_notification['object_id']);
            } else {
                log::add('strava', 'debug', 'Notification: action:' . $action
                    . ' object_type:' . $_notification['object_type']
                    . ', owner: ' . $_notification['owner_id']
                    . ' (our: ' . $this->getStravaId() . ') ignored');
            }
        }
    }

    //
    // Sport Management Section
    //
    private function createCommands($_order, $_logicalId, $_name) {
        // Total (week)
        //log::add('strava', 'debug', 'Create commands for ' . $_logicalId . ', name=' . $_name . ', index=' . $_order);
        $cmd = $this->getCmd(null, $_logicalId . '_count');
        if (!is_object($cmd)) {
            $cmd = new stravaCmd();
            $cmd->setLogicalId($_logicalId . '_count');
            $cmd->setIsVisible(1);
            $cmd->setOrder($_order);
            $cmd->setName($_name . __(' (Total Hebdo)', __FILE__));
            $cmd->setTemplate('dashboard', 'line');
            $cmd->setTemplate('mobile', 'line');
        }
        $_order++;
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
            $cmd->setName($_name . __(' (Distance Hebdo)', __FILE__));
            $cmd->setTemplate('dashboard', 'line');
            $cmd->setTemplate('mobile', 'line');
        }
        $_order++;
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
            $cmd->setName($_name . __(' (Dénivelé Hebdo)', __FILE__));
            $cmd->setTemplate('dashboard', 'line');
            $cmd->setTemplate('mobile', 'line');
        }
        $_order++;
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
            $cmd->setName($_name . __(' (Temps Hebdo)', __FILE__));
            $cmd->setTemplate('dashboard', 'stravaDuration');
            $cmd->setTemplate('mobile', 'stravaDuration');
        }
        $_order++;
        $cmd->setType('info');
        $cmd->setSubType('numeric');
        $cmd->setUnite('');
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
        }
        $_order++;
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
        }
        $_order++;
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
            $cmd->setName($_name . __(' (Dénivelé annuel)', __FILE__));
            $cmd->setTemplate('dashboard', 'line');
            $cmd->setTemplate('mobile', 'line');
        }
        $_order++;
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
            $cmd->setTemplate('dashboard', 'stravaDuration');
            $cmd->setTemplate('mobile', 'stravaDuration');
        }
        $_order++;
        $cmd->setType('info');
        $cmd->setSubType('numeric');
        $cmd->setUnite('');
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


    private static function endsWith( $str, $sub ) {
        return ( substr( $str, strlen( $str ) - strlen( $sub ) ) === $sub );
    }

    // Reinit all values to 0
    private function resetStats($_week, $_year) {

        $extensions = ['week' => ['_count', '_distance', '_elevation', '_time'],
                       'year' => ['_count_year', '_distance_year', '_elevation_year', '_time_year']];
        $cmds       = $this->getCmd();
        $exts       = [];
        if ($_week == true) {
            $exts = array_merge_recursive($exts, $extensions['week']);
        }
        if ($_year == true) {
            $exts = array_merge_recursive($exts, $extensions['year']);
        }

        foreach ($cmds as $cmd) {
            $name = $cmd->getLogicalId();
            foreach ($exts as $extension) {
                if(strava::endsWith($name, $extension)) {
                    $this->checkAndUpdateCmd($cmd, 0);
                    break;
                }
            }
        }
        $this->setConfiguration('last_update', strtotime('first day of january '.date('Y').' GMT'));
        $this->save();
    }

    // Synchronized the information
    private function syncStats($_activities) {
        log::add('strava', 'debug', 'Activities to process: ' . count($_activities));
        $start_of_this_week = strtotime('monday this week GMT');
        foreach ($_activities as $activity) {
            $type  = $activity['type'];
            $start = strtotime($activity['start_date']) - $activity['utc_offset'];
            $last  = $this->getConfiguration('last_update', 0);
            if (($this->getConfiguration($type, 0) == 1) and ($start > $last)) {

                // This activity is monitored, let's process it !
                $distance  = 0;
                $elevation = 0;
                $time      = 0;
                // Load the information, if any
                if (isset($activity['distance'])) {
                	$distance = round($activity['distance'] / 1000, 2);
                }
                if (isset($activity['total_elevation_gain'])) {
                	$elevation = $activity['total_elevation_gain'];
                }
                if (isset($activity['elapsed_time'])) {
                	$time = $activity['elapsed_time'];
                }
                // Weekly Cmd objects
                $w_c = $this->getCmd(null, $type . '_count');
                $w_d = $this->getCmd(null, $type . '_distance');
                $w_e = $this->getCmd(null, $type . '_elevation');
                $w_t = $this->getCmd(null, $type . '_time');
                // Yearly Cmd objects
                $y_c = $this->getCmd(null, $type . '_count_year');
                $y_d = $this->getCmd(null, $type . '_distance_year');
                $y_e = $this->getCmd(null, $type . '_elevation_year');
                $y_t = $this->getCmd(null, $type . '_time_year');

                // Weekly old values
                $w_o_c = ($w_c->execCmd() != null) ? $w_c->execCmd() : 0;
                $w_o_d = ($w_d->execCmd() != null) ? $w_d->execCmd() : 0;
                $w_o_e = ($w_e->execCmd() != null) ? $w_e->execCmd() : 0;
                $w_o_t = ($w_t->execCmd() != null) ? $w_t->execCmd() : 0;
                // Yearly old values
                $y_o_c = ($y_c->execCmd() != null) ? $y_c->execCmd() : 0;
                $y_o_d = ($y_d->execCmd() != null) ? $y_d->execCmd() : 0;
                $y_o_e = ($y_e->execCmd() != null) ? $y_e->execCmd() : 0;
                $y_o_t = ($y_t->execCmd() != null) ? $y_t->execCmd() : 0;

                // Week
                if ($start >= $start_of_this_week) {
                    $this->checkAndUpdateCmd($w_c, ($w_o_c + 1));
                    $this->checkAndUpdateCmd($w_d, ($w_o_d + $distance));
                    $this->checkAndUpdateCmd($w_e, ($w_o_e + $elevation));
                    $this->checkAndUpdateCmd($w_t, ($w_o_t + $time));
                }
                // Year
                $this->checkAndUpdateCmd($y_c, ($y_o_c + 1));
                $this->checkAndUpdateCmd($y_d, ($y_o_d + $distance));
                $this->checkAndUpdateCmd($y_e, ($y_o_e + $elevation));
                $this->checkAndUpdateCmd($y_t, ($y_o_t + $time));
            } else {
                log::add('strava', 'debug', 'activity ignored: type: ' . $type
                            . ', time: ' . $start . 'last: '. $last);
            }
        }
        $this->setConfiguration('last_update', time());
        $this->save();
    }

    // Enqueue notifications action
    private function enqueueNotification($_action, $_object_id) {
        $queue           = new SplQueue();
        $serializedQueue = $this->getConfiguration('dbQueue', '');
        if ($serializedQueue!=='') {
            $queue->unserialize($serializedQueue);
        }
        $queue->enqueue(['action' => $_action, 'object_id' => $_object_id]);
        $this->setConfiguration('dbQueue', $queue->serialize());
        $this->save(true);
    }

    private function dequeueNotifications() {
        try {
            $serializedQueue = $this->getConfiguration('dbQueue');
            $this->setConfiguration('dbQueue', '');
            $this->save(true);
			$queue = new SplQueue();
			$queue->unserialize($serializedQueue);
			if ($queue->isEmpty()) {
				log::add('strava', 'debug', 'message dbQueue is empty');
				return;
			}
		} catch (\Throwable $th) {
			log::add('strava', 'error', 'Error during unserialize dbQueue');
			return;
		}
        $needRefresh = false;
        while (!$queue->isEmpty()) {
			$notification = $queue->dequeue();
            $action       = $notification['action'];
            $object_id    = $notification['object_id'];
            try {
                if ($action === 'create') {
                    // Get the activity detail
                    $activity = $this->getActivity($object_id);
                    log::add('strava', 'info', 'Notification: création de l\'activitée  : ' . $object_id);
                    $this->syncStats([$activity]);
                } else if ($action === 'delete') {
                    // Delete the activity and reload the information from the database
                    log::add('strava', 'info', 'Notification: suppression de l\'activitée : ' . $object_id);
                    stravaActivity::deleteActivity($this->getId(), $object_id);
                    $needRefresh = true;
                } else if ($action === 'update') {
                    // Delete the activity, fetch the activity and re-create the activity
                    log::add('strava', 'info', 'Notification: mise à jour de l\'activitée : ' . $object_id);
                    $activity = $this->getActivity($object_id);
                    stravaActivity::deleteActivity($this->getId(), $object_id);
                    $this->storeActivity([$activity]);
                    $needRefresh = true;
                }
            } catch (Exception $e) {
                log::add('strava', 'warning', $e->getMessage());
            }
        }
        if ($needRefresh === true) {
            $this->refresh();
        }
    }

    // Store the activities in the database
    private function storeActivities($_activities) {

        log::add('strava', 'debug', 'Store activities in DB');
        foreach ($_activities as $activity) {
            // Create the activity in the database if activated
            stravaActivity::createActivity(
                    $this->getId(),
                    $activity['id'],
                    (strtotime($activity['start_date']) - $activity['utc_offset']),
                    $activity['type'],
                    $activity['distance'],
                    $activity['elapsed_time'],
                    $activity['total_elevation_gain']
            );
        }
    }

    /**
     * Remove the events that have been inserted before the retention
     * date
     */
    private function removeOldActivities() {
        $retention = $this->getConfiguration('retention', 0);
        if ($retention != 0) {
            $after     = date("m/d/Y", mktime(0, 0, 0, date("m") - $retention, date("d"), date("y")));

            log::add('strava', 'info', $this->getHumanName()
                . __(': suppression des événements anterieurs à ', __FILE__) . $after);

            stravaActivity::removeAllByIdTime($this->getId(), strtotime($after));
        }
    }


    // refresh
    public function refresh() {
        // Update the yearly and weekly activities from the database
        $activities = stravaActivity::byEqLogicIdTime(
                $this->getId(),
                strtotime('first day of january '.date('Y').' GMT'),
                time());
        resetStats(true, true);
        syncStats($activities);
    }

    //
    public function preSave() {
        // Update last_update
        if (0 == $this->getConfiguration('last_update', 0)) {
            $this->setConfiguration('last_update', strtotime('first day of january '.date('Y').' GMT'));
        }
    }

    // Remove unwanted spaces around the client_secret and client_id
    // Reported by @ngrataloup
    public function preUpdate() {
        $client_id     = $this->getConfiguration('client_id', '');
        $client_secret = $this->getConfiguration('client_secret', '');
        $this->setConfiguration('client_id', trim($client_id));
        $this->setConfiguration('client_secret', trim($client_secret));
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
            $cmd->setIsVisible(0);
            $cmd->setName(__('Envoyer poids', __FILE__));
            $cmd->setOrder(2);
        }
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('action');
        $cmd->setSubType('slider');
        $cmd->save();

        // Create all the sports, that are checked, remove other sports (unchecked)
        $sports = [
               'AlpineSki' => __('Ski alpin', __FILE__),
               'BackcountrySki' => __('Ski de randonnée', __FILE__),
               'Canoeing' => __('Canoë', __FILE__),
               'Crossfit' => __('Crossfit', __FILE__),
               'EBikeRide' => __('Vélo électrique', __FILE__),
               'Elliptical' => __('Elliptique', __FILE__),
               'Golf' => __('Golf', __FILE__),
               'Handcycle' => __('Handbike', __FILE__),
               'Hike' => __('Randonnée', __FILE__),
               'Iceskate' => __('Patinage', __FILE__),
               'InlineSkate' => __('Roller', __FILE__),
               'Kayaking' => __('Kayak', __FILE__),
               'Kitesurf' => __('Kitesurf', __FILE__),
               'NordicSki' => __('Ski nordique', __FILE__),
               'Ride' => __('Vélo', __FILE__),
               'RockClimbing' => __('Escalade', __FILE__),
               'RollerSki' => __('Ski à roulettes', __FILE__),
               'Rowing' => __('Aviron', __FILE__),
               'Run' => __('Course à pied', __FILE__),
               'Sail' => __('Voile', __FILE__),
               'Skateboard' => __('Skateboard', __FILE__),
               'Snowboard' => __('Snowboard', __FILE__),
               'Snowshoe' => __('Raquettes', __FILE__),
               'Soccer' => __('Football', __FILE__),
               'StairStepper' => __('Simulateur d\'escaliers', __FILE__),
               'StandUpPaddling' => __('Standup paddle', __FILE__),
               'Surfing' => __('Surf', __FILE__),
               'Swim' => __('Natation', __FILE__),
               'Velomobile' => __('Vélomobile', __FILE__),
               'VirtualRide' => __('Vélo virtuel', __FILE__),
               'VirtualRun' => __('Course à pied virtuelle', __FILE__),
               'Walk' => __('Marche', __FILE__),
               'WeightTraining' => __('Entraînement aux poids', __FILE__),
               'Wheelchair' => __('Course en fauteuil', __FILE__),
               'Windsurf' => __('Windsurf', __FILE__),
               'Workout' => __('Entraînement', __FILE__),
               'Yoga' => __('Yoga', __FILE__)
           ];

        foreach ($sports as $key => $value) {
            $index = count($this->getCmd()) + 1;
            if ($this->getConfiguration($key, 0) == 1) {
                //log::add('strava', 'debug', 'Add commands for ' . $key);
                $this->createCommands($index, $key, $value);
            } else {
                //log::add('strava', 'debug', 'Remove commands for ' . $key);
                $this->deleteCommands($key);
            }
        }

        // Refresh action
        $cmd = $this->getCmd(null, 'refresh');
        if (!is_object($cmd)) {
            $cmd = new StravaCmd();
            $cmd->setLogicalId('refresh');
            $cmd->setIsVisible(1);
            $cmd->setName(__('Rafraîchir', __FILE__));
        }
        $cmd->setType('action');
        $cmd->setSubType('other');
        $cmd->setEqLogic_id($this->getId());
        $cmd->save();
    }


    // Call before the object is remove.
    // in this context, delete the Strava subscription, strava connection
    // and all information from the database
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

           // Clear the information in the database
           try {
               stravaActivity::removeAllbyId($this->getId());
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
        $eqLogic = $this->getId();
        switch ($this->getLogicalId()) {
            case 'refresh':
                $eqLogic->forceStatsUpdate();
                break;
            case 'setWeight':
                if(isset($_options['slider'])) {
                    $weight = $_options['slider'];
                    $eqLogic->setAthleteWeight($weight);
                }
                break;
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}
