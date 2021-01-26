
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


/*
* Permet la réorganisation des commandes dans l'équipement
*/
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

/*
* Fonction permettant l'affichage des commandes dans l'équipement
*/
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
     var _cmd = {configuration: {}};
   }
   if (!isset(_cmd.configuration)) {
     _cmd.configuration = {};
   }
   var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
   tr += '    <td style="min-width:300px;width:350px;">';
   tr += '        <input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
   tr += '        <input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 240px;" placeholder="{{Nom}}">';
   tr += '    </td>';
   tr += '    <td style="min-width:120px;width:240px;">';
   tr += '        <label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label>';
   tr += '        <label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label>';
   tr += '    </td>';
   tr += '    <td style="min-width:180px;">';
   tr += '        <input class="cmdAttr form-control input-sm" data-l1key="type" style="display : none;">';
   tr += '        <input class="cmdAttr form-control input-sm" data-l1key="subType" style="display : none;">';
   if (is_numeric(_cmd.id)) {
       tr += '    <input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;display:inline-block;"/>';
   }
   tr += '    </td>';
   tr += '    <td>';
   if (is_numeric(_cmd.id)) {
      tr += '     <a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
      tr += '     <a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
   }
   tr += '    </td>';
   tr += '    <td>';
   tr += '    </td>';

   tr += '</tr>';
   $('#table_cmd tbody').append(tr);
   var tr = $('#table_cmd tbody tr').last();
   jeedom.eqLogic.builSelectCmd({
     id:  $('.eqLogicAttr[data-l1key=id]').value(),
     filter: {type: 'info'},
     error: function (error) {
       $('#div_alert').showAlert({message: error.message, level: 'danger'});
     },
     success: function (result) {
       tr.find('.cmdAttr[data-l1key=value]').append(result);
       tr.setValues(_cmd, '.cmdAttr');
       jeedom.cmd.changeType(tr, init(_cmd.subType));
     }
   });
 }


// benoit5672 code ---
//
function updateProgressBar(_bar, _usage, _limit) {
    
    var value = 0;
    var color = 'bg-success';
    if (_limit != 0) {
       value = Math.round(_usage / _limit); 
    } 
    if (value <= 25) {
       color = 'bg-success'
    } else if (value <= 50) {
       color = 'bg-info'
    } else if (value <= 75) {
       color = 'bg-warning'
    } else if (value <= 100) {
       color = 'bg-danger'
    }
    _bar.className      += color;
    _bar.style.width     = value + '%;';
    _bar.ariaValueNow    = _usage;
    _bar.ariaValueMin    = 0;
    _bar.ariaValueMax    = _limit;
    _bar.firstChild.data = _usage + '/' + _limit;
};

function formatDateTime(input){
       var epoch = new Date(0);
       epoch.setSeconds(parseInt(input));
       var date = epoch.toISOString();
       date = date.replace('T', ' ');
       return date.split('.')[0].split(' ')[0] + ' ' + epoch.toLocaleTimeString().split(' ')[0];
};

function printEqLogic(_eqLogic) {
    // Indicate the status of the strava connection (strava_id > 01)
    var image = document.createElement('i');
	if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=strava_id').value() > 0) {
        image.setAttribute('class', 'stravaIdImg icon_green icon techno-plug2');
    } else {
        image.setAttribute('class', 'stravaIdImg icon_red icon fas fa-exclamation-triangle');
    }
    $('.stravaIdImg').remove();
    $('.stravaConnection').append(image);

    // Indicate the status of the webhook connection (subscription_id > 0)
    var image = document.createElement('i');
	if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=subscription_id').value() > 0) {
        image.setAttribute('class', 'stravaSubscriptionImg icon_green icon techno-plug2');
    } else {
        image.setAttribute('class', 'stravaSubscriptionImg icon_red icon fas fa-exclamation-triangle');
    }
    $('.stravaSubscriptionImg').remove();
    $('.stravaSubscription').append(image);

    // Indicate the number of requests 15 minutes (usage/limit), and daily (usage/limit)
    $.ajax({
        type: "POST", 
        url: "plugins/strava/core/ajax/strava.ajax.php", 
        data: {
            action: "getUsagesAndLimits",
            id: $('.eqLogic .eqLogicAttr[data-l1key=id]').value()
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            // Fill the two progress bar
            var bar   = $('#15mUsage')[0];
            var usage = data.result[1][0];
            var limit = data.result[0][0];
            updateProgressBar(bar, usage, limit);

            bar   = $('#dayUsage')[0];
            usage = data.result[1][1];
            limit = data.result[0][1];
            updateProgressBar(bar, usage, limit);
        }
    });

    // Last update
	var seconds = $('.eqLogicAttr[data-l1key=configuration][data-l2key=last_update').value();
    $('.statsLastUpdate').empty().append(formatDateTime(seconds));
}


$('#bt_connectWithStrava').on('click', function () {
    $.ajax({
        type: "POST", 
        url: "plugins/strava/core/ajax/strava.ajax.php", 
        data: {
            action: "connectWithStrava",
            id: $('.eqLogic .eqLogicAttr[data-l1key=id]').value()
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
            window.location.href = data.result.redirect;
        }
    });
});


$('body').off('click','.bt_disconnectFromStrava').on('click','.bt_disconnectFromStrava', function () {
    $.ajax({
        type: "POST", 
        url: "plugins/strava/core/ajax/strava.ajax.php", 
        data: {
            action: "disconnectFromStrava",
            id: $('.eqLogic .eqLogicAttr[data-l1key=id]').value()
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            } else {
                $('#div_alert').showAlert({message: '{{Deconnection de strava reussie}}', level: 'success'});
            }
        }
    });
});


$('body').off('click','.bt_razStatistics').on('click','.bt_razStatistics', function () {
    bootbox.confirm('{{Etes-vous sur de vouloir supprimer l\'historique des donnees et recharger toutes les donnees depuis Strava ? Assurez-vous d\'avoir selectionne les sports souhaites et d\'avoir sauvegarde l\'athlete}}', function(result) {
        if (result) {
            $.ajax({
                type: "POST", 
                url: "plugins/strava/core/ajax/strava.ajax.php", 
                data: {
                    action: "razStatistics",
                    id: $('.eqLogic .eqLogicAttr[data-l1key=id]').value()
                },
                dataType: 'json',
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                },
                success: function (data) {
                    if (data.state != 'ok') {
                        $('#div_alert').showAlert({message: data.result, level: 'danger'});
                        return;
                    } else {
                        $('#div_alert').showAlert({message: '{{Mise a jour des statisques Strava reussie}}', level: 'success'});
                    }
                }
            });
        }
    });
});


$('body').off('click','.bt_forceStatsUpdate').on('click','.bt_forceStatsUpdate', function () {
    bootbox.confirm('{{Assurez-vous d\'avoir selectionne les sports souhaites et d\'avoir sauvegarde l\'athlete}}', function(result) {
        if (result) {
            $.ajax({
                type: "POST", 
                url: "plugins/strava/core/ajax/strava.ajax.php", 
                data: {
                    action: "forceStatsUpdate",
                    id: $('.eqLogic .eqLogicAttr[data-l1key=id]').value()
                },
                dataType: 'json',
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                },
                success: function (data) {
                    if (data.state != 'ok') {
                        $('#div_alert').showAlert({message: data.result, level: 'danger'});
                        return;
                    } else {
                        $('#div_alert').showAlert({message: '{{Mise a jour des statisques Strava reussie}}', level: 'success'});
                    }
                }
            });
        }
    });
});

// ---- Thanks @mips: open documentation or community links from the desktop
$('.pluginAction[data-action=openLocation]').on('click',function(){
    window.open($(this).attr("data-location"), "_blank", null);
});
