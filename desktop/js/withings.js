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

/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
})

/* Gestion des boutons spécifiques à Withings */
$('#bt_authorize').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value()
  if (eqLogicId == '') {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'})
    return
  }
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "getAuthUrl",
      id: eqLogicId
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error)
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'})
        return
      }
      // Ouvrir une popup pour l'autorisation
      window.open(data.result, 'withings_auth', 'width=800,height=600,scrollbars=yes')
    }
  })
})

$('#bt_testConnection').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value()
  if (eqLogicId == '') {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'})
    return
  }
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "testConnection",
      id: eqLogicId
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error)
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'})
        return
      }
      $('#div_alert').showAlert({message: data.result, level: 'success'})
      updateConnectionStatus('connected')
    }
  })
})

$('#bt_resetAuth').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value()
  if (eqLogicId == '') {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'})
    return
  }
  
  bootbox.confirm('{{Êtes-vous sûr de vouloir réinitialiser l\'autorisation ? Vous devrez refaire l\'authentification.}}', function (result) {
    if (result) {
      $.ajax({
        type: "POST",
        url: "plugins/withings/core/ajax/withings.ajax.php",
        data: {
          action: "resetAuth",
          id: eqLogicId
        },
        dataType: 'json',
        error: function (request, status, error) {
          handleAjaxError(request, status, error)
        },
        success: function (data) {
          if (data.state != 'ok') {
            $('#div_alert').showAlert({message: data.result, level: 'danger'})
            return
          }
          $('#div_alert').showAlert({message: data.result, level: 'success'})
          updateConnectionStatus('disconnected')
        }
      })
    }
  })
})

$('#bt_syncData').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value()
  if (eqLogicId == '') {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'})
    return
  }
  
  $('#bt_syncData').addClass('disabled')
  $('#bt_syncData').html('<i class="fas fa-spinner fa-spin"></i> {{Synchronisation...}}')
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "syncData",
      id: eqLogicId
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error)
      $('#bt_syncData').removeClass('disabled')
      $('#bt_syncData').html('<i class="fas fa-sync"></i> {{Synchroniser maintenant}}')
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'})
      } else {
        $('#div_alert').showAlert({message: data.result, level: 'success'})
        // Recharger les commandes pour afficher les nouvelles valeurs
        printEqLogic(eqLogicId)
      }
      $('#bt_syncData').removeClass('disabled')
      $('#bt_syncData').html('<i class="fas fa-sync"></i> {{Synchroniser maintenant}}')
    }
  })
})

/* Fonction pour mettre à jour le statut de connexion */
function updateConnectionStatus(status) {
  var statusElement = $('#connectionStatus')
  
  switch (status) {
    case 'connected':
      statusElement.removeClass('label-default label-danger')
      statusElement.addClass('label-success')
      statusElement.text('{{Connecté}}')
      break
    case 'disconnected':
      statusElement.removeClass('label-default label-success')
      statusElement.addClass('label-danger')
      statusElement.text('{{Déconnecté}}')
      break
    default:
      statusElement.removeClass('label-success label-danger')
      statusElement.addClass('label-default')
      statusElement.text('{{Non configuré}}')
  }
}

/* Fonction exécutée au chargement de l'équipement */
$('.eqLogicThumbnailDisplay').on('click', '.eqLogicDisplayCard', function () {
  var eqLogicId = $(this).attr('data-eqLogic_id')
  
  // Vérifier le statut de connexion
  setTimeout(function() {
    checkConnectionStatus(eqLogicId)
  }, 500)
})

function checkConnectionStatus(eqLogicId) {
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "testConnection",
      id: eqLogicId
    },
    dataType: 'json',
    error: function (request, status, error) {
      updateConnectionStatus('disconnected')
    },
    success: function (data) {
      if (data.state == 'ok') {
        updateConnectionStatus('connected')
      } else {
        updateConnectionStatus('disconnected')
      }
    }
  })
}

/* Fonction pour afficher les dernières mesures */
function displayLastMeasures() {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value()
  if (eqLogicId == '') return
  
  var commands = ['weight', 'bmi', 'fat_ratio', 'muscle_mass', 'bone_mass', 'hydration', 'last_sync']
  var html = '<div class="row">'
  
  commands.forEach(function(cmdLogicalId, index) {
    if (index > 0 && index % 2 === 0) {
      html += '</div><div class="row">'
    }
    
    var cmd = $('.cmd[data-cmd_id]').find('.cmdAttr[data-l1key="logicalId"][value="' + cmdLogicalId + '"]').closest('.cmd')
    var value = cmd.find('.cmdAttr[data-l1key="currentValue"]').text() || '--'
    var unit = cmd.find('.cmdAttr[data-l1key="unite"]').val() || ''
    
    var displayName = {
      'weight': '{{Poids}}',
      'bmi': '{{IMC}}',
      'fat_ratio': '{{Masse grasse}}',
      'muscle_mass': '{{Masse musculaire}}',
      'bone_mass': '{{Masse osseuse}}',
      'hydration': '{{Hydratation}}',
      'last_sync': '{{Dernière sync}}'
    }
    
    html += '<div class="col-sm-6">'
    html += '<div class="panel panel-default">'
    html += '<div class="panel-body text-center">'
    html += '<h4>' + displayName[cmdLogicalId] + '</h4>'
    html += '<h3 class="text-primary">' + value + ' ' + unit + '</h3>'
    html += '</div></div></div>'
  })
  
  html += '</div>'
  $('#lastMeasures').html(html)
}

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} }
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
  tr += '<td class="hidden-xs">'
  tr += '<span class="cmdAttr" data-l1key="id"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">'
  tr += '<option value="">{{Aucune}}</option>'
  tr += '</select>'
  tr += '</td>'
  tr += '<td>'
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>'
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> '
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> '
  tr += '<div style="margin-top:7px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">'
  tr += '</div>'
  tr += '</td>'
  tr += '<td>'
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>'
  tr += '</td>'
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>'
  tr += '</tr>'
  $('#table_cmd tbody').append(tr)
  var tr = $('#table_cmd tbody tr').last()
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' })
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result)
      tr.setValues(_cmd, '.cmdAttr')
      jeedom.cmd.changeType(tr, init(_cmd.subType))
    }
  })
}

/* Fonction pour gérer les erreurs AJAX */
function handleAjaxError(request, status, error) {
  $('#div_alert').showAlert({
    message: request.status + ' : ' + request.responseText,
    level: 'danger'
  })
}
