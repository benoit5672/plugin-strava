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
                        <div class="col-lg-7">
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

                            <legend><i class="fas fa-cogs"></i> {{Authorisation Strava}}</legend>
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
                                <label class="col-lg-3 control-label">{{Connection Strava}}</label>
                                <div class="col-lg-4">
                                   <img id="bt_connectWithStrava" src="/plugins/strava/desktop/images/btn_strava_connectwith_orange.png" style="max-width:193;max-height:48"/>
                                </div>
                                <div class="col-lg-4">
                                   <a class="btn btn-danger roundedLeft bt_disconnectFromStrava"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Revoquer}}</span></a>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="col-lg-2">
                                   <a class="btn btn-warning roundedLeft bt_viewSubscription"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{View Subscription}}</span></a>
                                </div>
                                <div class="col-lg-2">
                                   <a class="btn btn-warning roundedLeft bt_createSubscription"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Create Subscription}}</span></a>
                                </div>
                                <div class="col-lg-2">
                                   <a class="btn btn-default roundedLeft bt_deleteSubscription"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Delete Subscription}}</span></a>
                                </div>
                            </div>
                        </div>
                        <!-- Partie droite de l'onglet "Equipement" -->
                        <!-- Affiche l'icône du plugin par défaut mais vous pouvez y afficher les informations de votre choix -->
                        <div class="col-lg-5">
                            <legend><i class="fas fa-info"></i> {{Informations}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Connection Active}}</label>
                                <div class="col-sm-1">oui
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Webhook Active}}</label>
                                <div class="col-sm-1">oui
                                </div>
                            </div> 
                            <div class="form-group">
                                <div class="text-center">
                                    <img name="icon_visu" src="<?= $plugin->getPathImgIcon(); ?>" style="max-width:160px;"/>
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
