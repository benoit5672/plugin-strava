<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('strava');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
    <div class="col-xs-12 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
        <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoSecondary" data-action="add">
            <i class="fas fa-plus-circle"></i>
                <br>
            <span>{{Ajouter}}</span>
        </div>
        <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
            <i class="fas fa-wrench"></i>
            <br>
            <span>{{Configuration}}</span>
        </div>
        <div class="cursor pluginAction logoSecondary" data-action="openLocation" data-location="<?=$plugin->getDocumentation()?>">
            <i class="fas fa-book"></i>
            <br>
            <span>{{Documentation}}</span>
        </div>
        <div class="cursor pluginAction logoSecondary" data-action="openLocation" data-location="https://community.jeedom.com/tags/plugin-<?=$plugin->getId()?>">
            <i class="fas fa-comments"></i>
            <br>
            <span>Community</span>
            </div>
        </div>
        <!-- Liste des utilisateurs du plugin -->
        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
                echo '<br>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '</div>';
            }
            ?>
        </div>
    </div> <!-- /.eqLogicThumbnailDisplay -->

    <!-- Page de présentation de l'équipement -->
    <div class="col-xs-12 eqLogic" style="display: none;">
        <!-- barre de gestion de l'équipement -->
        <div class="input-group pull-right" style="display:inline-flex;">
            <span class="input-group-btn">
                <!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
                <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
                </a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs">  {{Dupliquer}}</span>
                </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
                </a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
                </a>
            </span>
        </div>
        <!-- Onglets -->
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i><span class="hidden-xs"> {{Équipement}}</span></a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i><span class="hidden-xs"> {{Commandes}}</span></a></li>
        </ul>
        <div class="tab-content">
            <!-- Onglet de configuration de l'équipement -->
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <form class="form-horizontal">
                    <fieldset>
                        <!-- Partie gauche de l'onglet "Equipements" -->
                        <!-- Paramètres généraux de l'équipement -->
                        <div class="col-lg-6">
                            <legend><i class="fas fa-wrench"></i> {{Général}}</legend>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
                                <div class="col-sm-7">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;"/>
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label" >{{Objet parent}}</label>
                                <div class="col-sm-7">
                                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                        <option value="">{{Aucun}}</option>
                                        <?php
                                        $options = '';
                                        foreach ((jeeObject::buildTree(null, false)) as $object) {
                                            $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                        }
                                        echo $options;
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Options}}</label>
                                <div class="col-sm-7">
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
                                </div>
                            </div>
                            <br>

                            <legend><i class="fas fa-cogs"></i> {{Autorisation Strava}}</legend>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Client ID}}</label>
                                <div class="col-sm-7">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="client_id" placeholder="{{Client ID}}"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Client Secret}}</label>
                                <div class="col-sm-7">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="client_secret" placeholder="{{Client Secret}}"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label help" data-help="{{Domaine a renseigner sur la page Strava 'My API Application'}}">{{Authorization Callback Domain}}</label>
                                <div class="col-sm-6">
                                    <span>
                                        <?php 
                                            $components = parse_url(network::getNetworkAccess('external'));
                                            if (!isset($components['host'])) {
                                               echo "Remplisser la partie acces exterieur dans la configuration jeedom";
                                            } else {
                                               echo $components['host'];
                                            } 
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Connection Strava}}</label>
                                <div class="col-sm-4">
                                   <img id="bt_connectWithStrava" src="/plugins/strava/desktop/images/btn_strava_connectwith_orange.png" style="max-width:193;max-height:48"/>
                                </div>
                                <div class="col-sm-4">
                                   <a class="btn roundedLeft bt_disconnectFromStrava" style="position:relative;top:+7px;background-color:#FC5200">
                                       <i class="fas fa-cogs"></i>
                                       <span class="hidden-xs"> {{Revoquer l'acces}}</span>
                                   </a>
                                </div>
                            </div>
                            <br>

                            <legend><i class="fas fa-cogs"></i> {{API Strava}}</legend>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Connection}}</label>
                                <div class="col-sm-4">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="strava_id" style="display : none;"/>
                                    <span class="stravaConnection"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Webhook}}</label>
                                <div class="col-sm-4">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="subscription_id" style="display : none;"/>
                                    <span class="stravaSubscription"/>
                                </div>
                            </div> 
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Utilisation (15 minutes)}}</label>
                                <div id="15mUsage" class="col-sm-4 progress-bar" role="progressbar" style="border: 1px solid rgba(0, 0, 0, 1);height:20px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div> 
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Utilisation (quotidienne)}}</label>
                                <div id="dayUsage" class="col-sm-4 progress-bar" role="progressbar" style="border: 1px solid rgba(0, 0, 0, 1);height:20px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div> 
                        </div>
                        <!-- Partie droite de l'onglet "Equipement" -->
                        <div class="col-lg-6">
                            <legend><i class="fas fa-info"></i> {{Sports}}</legend>
                            <div class="form-group">
                            <?php

                                $sports = [
                                    'Ride' => '{{Velo}}',
                                    'Run' => '{{Course a pied}}',
                                    'Swim' => '{{Natation}}',
                                    'AlpineSki' => '{{Ski alpin}}',
                                    'BackcountrySki' => '{{Ski de randonnee}}',
                                    'Canoeing' => '{{Canoe}}',
                                    'Crossfit' => '{{Crossfit}}',
                                    'EBikeRide' => '{{Velo electrique}}',
                                    'Elliptical' => '{{Elliptique}}',
                                    'Golf' => '{{Golf}}',
                                    'Handcycle' => '{{Handbike}}',
                                    'Hike' => '{{Randonnee}}',
                                    'Iceskate' => '{{Patinage}}',
                                    'InlineSkate' => '{{Roller}}',
                                    'Kayaking' => '{{Kayak}}',
                                    'Kitesurf' => '{{Kitesurf}}',
                                    'NordicSki' => '{{Ski nordique}}',
                                    'RockClimbing' => '{{Escalade}}',
                                    'RollerSki' => '{{Ski a roulettes}}',
                                    'Rowing' => '{{Aviron}}',
                                    'Sail' => '{{Voile}}',
                                    'Skateboard' => '{{Skateboard}}',
                                    'Snowboard' => '{{Snowboard}}',
                                    'Snowshoe' => '{{Raquettes}}',
                                    'Soccer' => '{{Football}}',
                                    'StairStepper' => '{{Simulateur d\'escaliers}}',
                                    'StandUpPaddling' => '{{Standup paddle}}',
                                    'Surfing' => '{{Surf}}',
                                    'Velomobile' => '{{Velomobile}}',
                                    'VirtualRide' => '{{Velo virtuel}}',
                                    'VirtualRun' => '{{Course a pied virtuelle}}',
                                    'Walk' => '{{Marche}}',
                                    'WeightTraining' => '{{Entrainement aux poids}}',
                                    'Wheelchair' => '{{Course en fauteuil}}',
                                    'Windsurf' => '{{Windsurf}}',
                                    'Workout' => '{{Entrainement}}',
                                    'Yoga' => '{{Yoga}}'
                                ];
                                foreach ($sports as $key => $value) {
                                    //echo '<div id ="' . $key . '" class="form-group">';
                                    echo '   <label class="control-label col-sm-3">' . $value . '</label>';
					                echo '   <div class="col-sm-1">';
					                echo '      <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="' . $key . '"/>';
					                echo '   </div>';
					                //echo '</div>';
                                } 
                            ?>
                            </div>
                            <br>
                            <legend><i class="fas fa-wrench"></i> {{Statistiques}}</legend>
                            <div class="form-group">
                                <label class="col-sm-3 control-label help" data-help="{{derniere mise a jour des statistiques de l'athelete}}">{{Derniere mise a jour}}</label>
                                <div class="col-sm-4">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="last_update" style="display : none;"/>
                                    <span class="statsLastUpdate"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">{{Mise a jour}}</label>
		                        <div>
						           <a class="btn btn-warning tooltips bt_forceStatsUpdate" title="Force la mise a jour des informations de l'athlete depuis le debut de l'annee."><i class="fas fa-times"></i> Forcer la mise a jour</a>
                                   <a class="btn btn-danger tooltips bt_razStatistics" title="Recharge toutes les informations de l'athlete depuis le debut de l'annee."><i class="fas fa-times"></i> RaZ Statistiques</a>
			                    </div>
                            </div>
                        </div>
                    </fieldset> <!-- fieldset eqlogictab -->
                </form> <!-- form eqlogitab -->
                <hr>
            </div><!-- /.tabpanel #eqlogictab-->

            <!-- Onglet des commandes de l'équipement -->
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <a class="btn btn-default btn-sm pull-right cmdAction" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a>
                <br/><br/>
                <div class="table-responsive">
                    <table id="table_cmd" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>{{Nom}}</th>
                                <th>{{Options}}</th>
                                <th>{{Parametres}}</th>
                                <th>{{Action}}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div><!-- /.tabpanel #commandtab-->

        </div><!-- /.tab-content -->
    </div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'strava', 'js', 'strava');?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js');?>
