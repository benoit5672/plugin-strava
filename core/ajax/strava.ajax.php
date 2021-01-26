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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }
    
  /* Fonction permettant l'envoi de l'entête 'Content-Type: application/json'
    En V3 : indiquer l'argument 'true' pour contrôler le token d'accès Jeedom
    En V4 : autoriser l'exécution d'une méthode 'action' en GET en indiquant le(s) nom(s) de(s) action(s) dans un tableau en argument
  */  
    ajax::init();

    // Added my methods here....
    if (init('action') === 'connectWithStrava') {
        $eqLogic = eqLogic::byId(init('id'));
	    if (!is_object($eqLogic)) {
	        throw new Exception(__('EqLogic non trouvé : ', __FILE__), init('id'));
	    }
	    ajax::success(array('redirect' => $eqLogic->connectWithStrava()));
    }

    if (init('action') === 'disconnectFromStrava') {
        $eqLogic = eqLogic::byId(init('id'));
	    if (!is_object($eqLogic)) {
	        throw new Exception(__('EqLogic non trouvé : ', __FILE__), init('id'));
	    }
        $eqLogic->disconnectFromStrava();
	    ajax::success();
    }

    if (init('action') === 'getUsagesAndLimits') {
        $eqLogic = eqLogic::byId(init('id'));
	    if (!is_object($eqLogic)) {
	        throw new Exception(__('EqLogic non trouvé : ', __FILE__), init('id'));
	    }
        ajax::success($eqLogic->getUsagesAndLimits());
    }

    if (init('action') === 'razStatistics') {
        $eqLogic = eqLogic::byId(init('id'));
	    if (!is_object($eqLogic)) {
	        throw new Exception(__('EqLogic non trouvé : ', __FILE__), init('id'));
	    }
        $eqLogic->razStatistics();
        ajax::success();
    }

    if (init('action') === 'forceStatsUpdate') {
        $eqLogic = eqLogic::byId(init('id'));
	    if (!is_object($eqLogic)) {
	        throw new Exception(__('EqLogic non trouvé : ', __FILE__), init('id'));
	    }
        $eqLogic->forceStatsUpdate();
        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}

