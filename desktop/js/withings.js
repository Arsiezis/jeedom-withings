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

/* Variables globales pour la sécurité */
var withingsSecurity = {
  csrfToken: null,
  requestInProgress: false,
  
  // Génération ou récupération du token CSRF
  getCSRFToken: function() {
    if (!this.csrfToken) {
      // Essayer de récupérer depuis le DOM ou générer un nouveau
      this.csrfToken = $('#csrf_token').val() || this.generateCSRFToken();
    }
    return this.csrfToken;
  },
  
  // Génération simple côté client (sera validé côté serveur)
  generateCSRFToken: function() {
    return 'csrf_' + Math.random().toString(36).substr(2, 15) + '_' + Date.now();
  },
  
  // Validation de base des entrées
  validateInput: function(input, type) {
    switch(type) {
      case 'equipment_id':
        return /^\d+$/.test(input) && parseInt(input) > 0;
      case 'client_id':
        return /^[a-zA-Z0-9_-]+$/.test(input) && input.length > 5;
      default:
        return input && input.length > 0;
    }
  },
  
  // Gestion des erreurs avec logging côté client
  handleError: function(action, error, context) {
    console.error('[Withings Security] Action:', action, 'Error:', error, 'Context:', context);
    
    // Affichage utilisateur sécurisé
    var userMessage = 'Une erreur est survenue';
    if (error.responseJSON && error.responseJSON.message) {
      userMessage = error.responseJSON.message;
    } else if (error.responseText) {
      userMessage = error.responseText;
    }
    
    $('#div_alert').showAlert({
      message: userMessage,
      level: 'danger'
    });
  }
};

/* Gestion améliorée de l'autorisation OAuth avec préservation de session */
$('#bt_authorize').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
  
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'});
    return;
  }
  
  if (withingsSecurity.requestInProgress) {
    $('#div_alert').showAlert({message: '{{Une requête est déjà en cours, veuillez patienter}}', level: 'warning'});
    return;
  }
  
  withingsSecurity.requestInProgress = true;
  $(this).prop('disabled', true);
  
  // Sauvegarder l'état de la session actuelle
  var currentSessionData = {
    timestamp: Date.now(),
    equipmentId: eqLogicId
  };
  sessionStorage.setItem('withings_oauth_state', JSON.stringify(currentSessionData));
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "getAuthUrl",
      id: eqLogicId,
      csrf_token: withingsSecurity.getCSRFToken()
    },
    dataType: 'json',
    timeout: 30000,
    error: function (request, status, error) {
      withingsSecurity.handleError('getAuthUrl', request, {eqLogicId: eqLogicId});
      withingsSecurity.requestInProgress = false;
      $('#bt_authorize').prop('disabled', false);
      sessionStorage.removeItem('withings_oauth_state');
    },
    success: function (data) {
      withingsSecurity.requestInProgress = false;
      $('#bt_authorize').prop('disabled', false);
      
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        sessionStorage.removeItem('withings_oauth_state');
        return;
      }
      
      // Validation de l'URL côté client
      if (!data.result || !data.result.startsWith('https://')) {
        $('#div_alert').showAlert({message: '{{URL d\'autorisation invalide}}', level: 'danger'});
        sessionStorage.removeItem('withings_oauth_state');
        return;
      }
      
      // Afficher un message informatif
      $('#div_alert').showAlert({
        message: '{{Ouverture de la popup d\'autorisation Withings...}}',
        level: 'info'
      });
      
      // Écouter les messages de la popup OAuth
      var messageHandler = function(event) {
        if (event.origin !== window.location.origin) {
          return; // Ignorer les messages d'autres origines
        }
        
        if (event.data && event.data.type === 'withings_oauth_success') {
          console.log('Autorisation OAuth réussie:', event.data);
          
          // Nettoyer l'écouteur
          window.removeEventListener('message', messageHandler);
          
          // Nettoyer le stockage de session
          sessionStorage.removeItem('withings_oauth_state');
          
          // Afficher un message de succès
          $('#div_alert').showAlert({
            message: '{{Autorisation Withings réussie ! Rechargement de la page...}}',
            level: 'success'
          });
          
          // Recharger la page pour voir les nouvelles données
          setTimeout(function() {
            window.location.reload();
          }, 1500);
        }
      };
      
      // Ajouter l'écouteur de messages
      window.addEventListener('message', messageHandler);
      
      // Ouvrir une popup pour l'autorisation avec paramètres sécurisés
      var popup = window.open(
        data.result, 
        'withings_auth', 
        'width=800,height=600,scrollbars=yes,resizable=yes,status=no,toolbar=no,menubar=no,location=no'
      );
      
      // Vérifier si la popup a été bloquée
      if (!popup) {
        // Nettoyer l'écouteur si la popup est bloquée
        window.removeEventListener('message', messageHandler);
        sessionStorage.removeItem('withings_oauth_state');
        
        $('#div_alert').showAlert({
          message: '{{La popup d\'autorisation a été bloquée. Veuillez autoriser les popups pour ce site.}}',
          level: 'warning'
        });
      } else {
        // Surveiller la fermeture de la popup
        var checkClosed = setInterval(function() {
          if (popup.closed) {
            clearInterval(checkClosed);
            
            // Si la popup est fermée sans message de succès, nettoyer
            setTimeout(function() {
              window.removeEventListener('message', messageHandler);
              sessionStorage.removeItem('withings_oauth_state');
              
              // Vérifier si l'autorisation a réussi en vérifiant l'état de l'équipement
              checkAuthorizationStatus(eqLogicId);
            }, 500);
          }
        }, 1000);
        
        // Timeout de sécurité pour nettoyer l'écouteur après 5 minutes
        setTimeout(function() {
          window.removeEventListener('message', messageHandler);
          sessionStorage.removeItem('withings_oauth_state');
          if (checkClosed) {
            clearInterval(checkClosed);
          }
        }, 300000); // 5 minutes
      }
    }
  });
});

/* Fonction pour vérifier le statut de l'autorisation après fermeture de popup */
function checkAuthorizationStatus(eqLogicId) {
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    return;
  }
  
  // Attendre un peu pour laisser le temps au serveur de traiter
  setTimeout(function() {
    $.ajax({
      type: "POST",
      url: "plugins/withings/core/ajax/withings.ajax.php",
      data: {
        action: "getTokenInfo",
        id: eqLogicId,
        csrf_token: withingsSecurity.getCSRFToken()
      },
      dataType: 'json',
      timeout: 10000,
      success: function (data) {
        if (data.state == 'ok' && data.result && !data.result.is_expired) {
          // Token valide trouvé, l'autorisation a réussi
          $('#div_alert').showAlert({
            message: '{{Autorisation détectée ! Rechargement de la page...}}',
            level: 'success'
          });
          
          setTimeout(function() {
            window.location.reload();
          }, 1500);
        } else {
          // Pas de token valide, afficher un message neutre
          $('#div_alert').showAlert({
            message: '{{Fenêtre d\'autorisation fermée. Si vous avez autorisé l\'accès, la connexion sera effective dans quelques instants.}}',
            level: 'info'
          });
        }
      },
      error: function() {
        // En cas d'erreur, ne rien faire de spécial
        console.log('Impossible de vérifier le statut d\'autorisation');
      }
    });
  }, 2000);
}

$('#bt_testConnection').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
  
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'});
    return;
  }
  
  if (withingsSecurity.requestInProgress) {
    return;
  }
  
  withingsSecurity.requestInProgress = true;
  $(this).addClass('disabled').html('<i class="fas fa-spinner fa-spin"></i> {{Test en cours...}}');
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "testConnection",
      id: eqLogicId,
      csrf_token: withingsSecurity.getCSRFToken()
    },
    dataType: 'json',
    timeout: 30000,
    error: function (request, status, error) {
      withingsSecurity.handleError('testConnection', request, {eqLogicId: eqLogicId});
      withingsSecurity.requestInProgress = false;
      resetTestConnectionButton();
    },
    success: function (data) {
      withingsSecurity.requestInProgress = false;
      resetTestConnectionButton();
      
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        updateConnectionStatus('disconnected');
        return;
      }
      
      $('#div_alert').showAlert({message: data.result, level: 'success'});
      updateConnectionStatus('connected');
      updateTokenInfo();
      displayLastMeasures();
    }
  });
  
  function resetTestConnectionButton() {
    $('#bt_testConnection').removeClass('disabled').html('<i class="fas fa-check-circle"></i> {{Tester la connexion}}');
  }
});

$('#bt_refreshToken').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
  
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'});
    return;
  }
  
  if (withingsSecurity.requestInProgress) {
    return;
  }
  
  withingsSecurity.requestInProgress = true;
  $(this).addClass('disabled').html('<i class="fas fa-spinner fa-spin"></i> {{Renouvellement...}}');
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "refreshToken",
      id: eqLogicId,
      csrf_token: withingsSecurity.getCSRFToken()
    },
    dataType: 'json',
    timeout: 45000,
    error: function (request, status, error) {
      withingsSecurity.handleError('refreshToken', request, {eqLogicId: eqLogicId});
      withingsSecurity.requestInProgress = false;
      resetRefreshTokenButton();
    },
    success: function (data) {
      withingsSecurity.requestInProgress = false;
      resetRefreshTokenButton();
      
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
      } else {
        $('#div_alert').showAlert({message: data.result, level: 'success'});
        updateTokenInfo();
      }
    }
  });
  
  function resetRefreshTokenButton() {
    $('#bt_refreshToken').removeClass('disabled').html('<i class="fas fa-sync-alt"></i> {{Renouveler le token}}');
  }
});

$('#bt_testEndpoints').on('click', function () {
  if (withingsSecurity.requestInProgress) {
    return;
  }
  
  withingsSecurity.requestInProgress = true;
  $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{Test en cours...}}');
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "testEndpoints",
      csrf_token: withingsSecurity.getCSRFToken()
    },
    dataType: 'json',
    timeout: 20000,
    error: function (request, status, error) {
      withingsSecurity.handleError('testEndpoints', request, {});
      withingsSecurity.requestInProgress = false;
      resetTestEndpointsButton();
    },
    success: function (data) {
      withingsSecurity.requestInProgress = false;
      resetTestEndpointsButton();
      
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        return;
      }
      $('#div_alert').showAlert({message: data.result, level: 'success'});
    }
  });
  
  function resetTestEndpointsButton() {
    $('#bt_testEndpoints').prop('disabled', false).html('<i class="fas fa-network-wired"></i> {{Tester les endpoints}}');
  }
});

$('#bt_resetAuth').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
  
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'});
    return;
  }
  
  bootbox.confirm({
    title: '{{Confirmation de réinitialisation}}',
    message: '{{Êtes-vous sûr de vouloir réinitialiser l\'autorisation ? Vous devrez refaire l\'authentification complète.}}',
    buttons: {
      confirm: {
        label: '<i class="fas fa-trash"></i> {{Réinitialiser}}',
        className: 'btn-danger'
      },
      cancel: {
        label: '<i class="fas fa-times"></i> {{Annuler}}',
        className: 'btn-default'
      }
    },
    callback: function (result) {
      if (result) {
        if (withingsSecurity.requestInProgress) {
          return;
        }
        
        withingsSecurity.requestInProgress = true;
        
        $.ajax({
          type: "POST",
          url: "plugins/withings/core/ajax/withings.ajax.php",
          data: {
            action: "resetAuth",
            id: eqLogicId,
            csrf_token: withingsSecurity.getCSRFToken()
          },
          dataType: 'json',
          timeout: 15000,
          error: function (request, status, error) {
            withingsSecurity.handleError('resetAuth', request, {eqLogicId: eqLogicId});
            withingsSecurity.requestInProgress = false;
          },
          success: function (data) {
            withingsSecurity.requestInProgress = false;
            
            if (data.state != 'ok') {
              $('#div_alert').showAlert({message: data.result, level: 'danger'});
              return;
            }
            
            $('#div_alert').showAlert({message: data.result, level: 'success'});
            updateConnectionStatus('disconnected');
            $('#tokenInfo').html('<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{Autorisation réinitialisée. Veuillez refaire l\'autorisation OAuth.}}</div>');
            $('#lastMeasures').html('<div class="alert alert-info"><i class="fas fa-info-circle"></i> {{Effectuez une nouvelle autorisation puis une synchronisation pour voir vos données.}}</div>');
          }
        });
      }
    }
  });
});

$('#bt_syncData').on('click', function () {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
  
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    $('#div_alert').showAlert({message: '{{Veuillez d\'abord sauvegarder l\'équipement}}', level: 'warning'});
    return;
  }
  
  if (withingsSecurity.requestInProgress) {
    return;
  }
  
  withingsSecurity.requestInProgress = true;
  $(this).addClass('disabled').html('<i class="fas fa-spinner fa-spin"></i> {{Synchronisation...}}');
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "syncData",
      id: eqLogicId,
      csrf_token: withingsSecurity.getCSRFToken()
    },
    dataType: 'json',
    timeout: 60000,
    error: function (request, status, error) {
      withingsSecurity.handleError('syncData', request, {eqLogicId: eqLogicId});
      withingsSecurity.requestInProgress = false;
      resetSyncButton();
    },
    success: function (data) {
      withingsSecurity.requestInProgress = false;
      resetSyncButton();
      
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
      } else {
        $('#div_alert').showAlert({message: data.result, level: 'success'});
        // Actualiser les informations après synchronisation
        updateTokenInfo();
        displayLastMeasures();
        // Actualiser les valeurs des commandes
        setTimeout(function() {
          window.location.reload();
        }, 1500);
      }
    }
  });
  
  function resetSyncButton() {
    $('#bt_syncData').removeClass('disabled').html('<i class="fas fa-sync"></i> {{Synchroniser maintenant}}');
  }
});

/* Fonction pour afficher les informations du token avec sécurité */
function updateTokenInfo() {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    return;
  }
  
  // Ne pas faire d'appel si on n'est pas connecté (éviter les erreurs 401)
  if (typeof isConnect === 'function' && !isConnect('admin')) {
    return;
  }
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "getTokenInfo",
      id: eqLogicId,
      csrf_token: withingsSecurity.getCSRFToken()
    },
    dataType: 'json',
    timeout: 10000,
    success: function (data) {
      if (data.state == 'ok') {
        var info = data.result;
        var statusClass = 'success';
        var statusIcon = '✅';
        
        if (info.is_expired) {
          statusClass = 'danger';
          statusIcon = '❌';
        } else if (info.needs_renewal_soon) {
          statusClass = 'warning';
          statusIcon = '⚠️';
        }
        
        var html = '<div class="alert alert-' + statusClass + '">';
        html += '<h4><i class="fas fa-key"></i> {{Informations du token d\'accès}}</h4>';
        html += '<p><strong>{{État}}:</strong> ' + statusIcon + ' ' + escapeHtml(info.status.toUpperCase()) + '</p>';
        html += '<p><strong>{{Expire dans}}:</strong> ' + escapeHtml(info.expires_in_hours) + ' {{heures}}</p>';
        html += '<p><strong>{{Date d\'expiration}}:</strong> ' + escapeHtml(info.expires_at) + '</p>';
        
        if (info.renewed_at !== 'Jamais') {
          html += '<p><strong>{{Dernier renouvellement}}:</strong> ' + escapeHtml(info.renewed_at) + '</p>';
        }
        
        if (info.created_at !== 'Inconnu') {
          html += '<p><strong>{{Créé le}}:</strong> ' + escapeHtml(info.created_at) + '</p>';
        }
        
        if (info.needs_renewal_soon && !info.is_expired) {
          html += '<hr><p class="text-warning"><i class="fas fa-clock"></i> <strong>{{Le token expire bientôt.}}</strong><br>';
          html += '{{Il sera automatiquement renouvelé lors de la prochaine synchronisation ou test de connexion.}}</p>';
        }
        
        if (info.is_expired) {
          html += '<hr><p class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>{{Token expiré!}}</strong><br>';
          html += '{{Cliquez sur "Renouveler le token" ou refaites l\'autorisation OAuth si le renouvellement échoue.}}</p>';
        }
        
        html += '<hr><small class="text-muted">';
        html += '<i class="fas fa-info-circle"></i> {{Les tokens Withings expirent toutes les 3 heures et sont automatiquement renouvelés.}}';
        html += '</small>';
        
        html += '</div>';
        
        $('#tokenInfo').html(html);
      }
    },
    error: function(request, status, error) {
      // Ne pas afficher d'erreur si c'est juste une question d'authentification
      if (request.status !== 401) {
        $('#tokenInfo').html('<div class="alert alert-info"><i class="fas fa-info-circle"></i> {{Informations du token non disponibles. Effectuez d\'abord l\'autorisation OAuth.}}</div>');
      }
    }
  });
}

/* Fonction pour mettre à jour le statut de connexion */
function updateConnectionStatus(status) {
  var statusElement = $('#connectionStatus');
  
  switch (status) {
    case 'connected':
      statusElement.removeClass('label-default label-danger label-warning')
                  .addClass('label-success')
                  .text('{{Connecté}}');
      break;
    case 'disconnected':
      statusElement.removeClass('label-default label-success label-warning')
                  .addClass('label-danger')
                  .text('{{Déconnecté}}');
      break;
    case 'warning':
      statusElement.removeClass('label-default label-success label-danger')
                  .addClass('label-warning')
                  .text('{{Token expire bientôt}}');
      break;
    default:
      statusElement.removeClass('label-success label-danger label-warning')
                  .addClass('label-default')
                  .text('{{Non configuré}}');
  }
}

/* Fonction de sécurité pour échapper le HTML */
function escapeHtml(text) {
  if (typeof text !== 'string') {
    return text;
  }
  
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

/* Fonction améliorée pour vérifier le statut de connexion */
function checkConnectionStatus(eqLogicId) {
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    return;
  }
  
  // Ne pas faire d'appel si on n'est pas connecté
  if (typeof isConnect === 'function' && !isConnect('admin')) {
    return;
  }
  
  $.ajax({
    type: "POST",
    url: "plugins/withings/core/ajax/withings.ajax.php",
    data: {
      action: "testConnection",
      id: eqLogicId,
      csrf_token: withingsSecurity.getCSRFToken()
    },
    dataType: 'json',
    timeout: 15000,
    error: function (request, status, error) {
      updateConnectionStatus('disconnected');
      updateTokenInfo();
    },
    success: function (data) {
      if (data.state == 'ok') {
        updateConnectionStatus('connected');
        displayLastMeasures();
      } else {
        updateConnectionStatus('disconnected');
      }
      updateTokenInfo();
    }
  });
}

/* Fonction exécutée au chargement de l'équipement */
$('.eqLogicThumbnailDisplay').on('click', '.eqLogicDisplayCard', function () {
  var eqLogicId = $(this).attr('data-eqLogic_id');
  
  if (withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    setTimeout(function() {
      checkConnectionStatus(eqLogicId);
    }, 500);
  }
});

/* Charger les informations du token au chargement de la page avec sécurité */
$(document).ready(function() {
  // Vérifier si on revient d'une autorisation OAuth
  var oauthState = sessionStorage.getItem('withings_oauth_state');
  if (oauthState) {
    try {
      var stateData = JSON.parse(oauthState);
      var elapsed = Date.now() - stateData.timestamp;
      
      // Si moins de 10 minutes se sont écoulées, on considère qu'on revient d'OAuth
      if (elapsed < 600000) {
        // Nettoyer le stockage
        sessionStorage.removeItem('withings_oauth_state');
        
        // Afficher un message et vérifier le statut
        $('#div_alert').showAlert({
          message: '{{Vérification du statut de l\'autorisation...}}',
          level: 'info'
        });
        
        // Vérifier si l'autorisation a réussi
        if (stateData.equipmentId) {
          checkAuthorizationStatus(stateData.equipmentId);
        }
      } else {
        // Trop vieux, nettoyer
        sessionStorage.removeItem('withings_oauth_state');
      }
    } catch (e) {
      sessionStorage.removeItem('withings_oauth_state');
    }
  }
  
  // Protection contre les attaques XSS
  $('input, textarea').on('paste', function(e) {
    setTimeout(function() {
      var value = $(e.target).val();
      var cleanValue = value.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
      cleanValue = cleanValue.replace(/javascript:/gi, '');
      cleanValue = cleanValue.replace(/on\w+="[^"]*"/gi, '');
      
      if (value !== cleanValue) {
        $(e.target).val(cleanValue);
        $('#div_alert').showAlert({
          message: '{{Contenu potentiellement dangereux supprimé}}',
          level: 'warning'
        });
      }
    }, 10);
  });
  
  // Attendre que l'ID soit disponible et que l'utilisateur soit connecté
  setTimeout(function() {
    var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
    if (withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
      updateTokenInfo();
      displayLastMeasures();
      
      // Mise à jour périodique moins fréquente pour éviter les erreurs
      var updateInterval = setInterval(function() {
        if (!withingsSecurity.requestInProgress) {
          updateTokenInfo();
        }
      }, 300000); // 5 minutes
      
      $(window).on('beforeunload', function() {
        clearInterval(updateInterval);
      });
    }
  }, 1000);
  
  $(document).ajaxStart(function() {
    window.ajaxTimeout = setTimeout(function() {
      $('#div_alert').showAlert({
        message: '{{La requête prend plus de temps que prévu...}}',
        level: 'info'
      });
    }, 10000);
  });
  
  $(document).ajaxStop(function() {
    if (window.ajaxTimeout) {
      clearTimeout(window.ajaxTimeout);
    }
  });
});

/* Fonction améliorée pour afficher les dernières mesures */
function displayLastMeasures() {
  var eqLogicId = $('.eqLogicAttr[data-l1key=id]').value();
  if (!withingsSecurity.validateInput(eqLogicId, 'equipment_id')) {
    return;
  }
  
  var commands = ['weight', 'bmi', 'fat_ratio', 'muscle_mass', 'bone_mass', 'hydration', 'last_sync'];
  var html = '<div class="row">';
  var hasData = false;
  
  commands.forEach(function(cmdLogicalId, index) {
    if (index > 0 && index % 2 === 0) {
      html += '</div><div class="row">';
    }
    
    // Chercher la commande dans le tableau des commandes
    var cmd = $('#table_cmd tbody tr').find('.cmdAttr[data-l1key="logicalId"][value="' + escapeHtml(cmdLogicalId) + '"]').closest('tr');
    var value = '--';
    var unit = '';
    
    if (cmd.length > 0) {
      // Récupérer l'ID de la commande
      var cmdId = cmd.find('.cmdAttr[data-l1key="id"]').val();
      if (cmdId) {
        // Essayer de récupérer la valeur depuis l'attribut data ou le cache Jeedom
        var cachedValue = cmd.find('.cmdAttr[data-l1key="currentValue"]').text();
        if (cachedValue && cachedValue !== '') {
          value = cachedValue;
          hasData = true;
        }
      }
      
      unit = cmd.find('.cmdAttr[data-l1key="unite"]').val() || '';
    }
    
    value = escapeHtml(String(value));
    unit = escapeHtml(String(unit));
    
    var displayName = {
      'weight': '{{Poids}}',
      'bmi': '{{IMC}}',
      'fat_ratio': '{{Masse grasse}}',
      'muscle_mass': '{{Masse musculaire}}',
      'bone_mass': '{{Masse osseuse}}',
      'hydration': '{{Hydratation}}',
      'last_sync': '{{Dernière sync}}'
    };
    
    var panelClass = 'panel-default';
    var textClass = 'text-muted';
    if (value !== '--' && value !== '') {
      panelClass = 'panel-primary';
      textClass = 'text-primary';
    }
    
    html += '<div class="col-sm-6">';
    html += '<div class="panel ' + panelClass + '">';
    html += '<div class="panel-body text-center">';
    html += '<h4>' + displayName[cmdLogicalId] + '</h4>';
    
    if (cmdLogicalId === 'last_sync' && value !== '--') {
      html += '<small class="' + textClass + '">' + value + '</small>';
    } else {
      html += '<h3 class="' + textClass + '">' + value;
      if (unit && value !== '--') {
        html += ' <small>' + unit + '</small>';
      }
      html += '</h3>';
    }
    
    html += '</div></div></div>';
  });
  
  html += '</div>';
  
  if (!hasData) {
    html = '<div class="alert alert-info">';
    html += '<i class="fas fa-info-circle"></i> ';
    html += '{{Aucune donnée disponible. Effectuez une synchronisation pour voir vos dernières mesures.}}';
    html += '</div>';
  }
  
  $('#lastMeasures').html(html);
}

/* Fonction permettant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} };
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td class="hidden-xs">';
  tr += '<span class="cmdAttr" data-l1key="id"></span>';
  tr += '</td>';
  tr += '<td>';
  tr += '<div class="input-group">';
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>';
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>';
  tr += '</div>';
  tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande info liée}}">';
  tr += '<option value="">{{Aucune}}</option>';
  tr += '</select>';
  tr += '</td>';
  tr += '<td>';
  tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
  tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
  tr += '</td>';
  tr += '<td>';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> ';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label> ';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label> ';
  tr += '<div style="margin-top:7px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">';
  tr += '</div>';
  tr += '</td>';
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
  tr += '</td>';
  tr += '<td>';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i></td>';
  tr += '</tr>';
  
  $('#table_cmd tbody').append(tr);
  var tr = $('#table_cmd tbody tr').last();
  jeedom.eqLogic.buildSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' });
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result);
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });
}

/* Protection contre les attaques de clickjacking */
if (top !== self) {
  top.location = self.location;
}

/* Fonction utilitaire sécurisée pour initialiser les valeurs */
function init(value) {
  if (typeof value === 'undefined' || value === null) {
    return '';
  }
  return value;
}

/* Fonction utilitaire pour vérifier l'existence d'une variable */
function isset(variable) {
  return typeof variable !== 'undefined' && variable !== null;
}

/* Fonction utilitaire pour vérifier si une valeur est numérique */
function is_numeric(value) {
  return !isNaN(parseFloat(value)) && isFinite(value);
}

/* Validation côté client des formulaires */
$(document).on('submit', 'form', function(e) {
  var hasError = false;
  
  // Vérifier tous les champs requis
  $(this).find('input[required]').each(function() {
    if (!$(this).val().trim()) {
      $(this).addClass('has-error');
      hasError = true;
    } else {
      $(this).removeClass('has-error');
    }
  });
  
  // Vérifier les patterns
  $(this).find('input[pattern]').each(function() {
    var pattern = new RegExp($(this).attr('pattern'));
    if ($(this).val() && !pattern.test($(this).val())) {
      $(this).addClass('has-error');
      hasError = true;
    } else {
      $(this).removeClass('has-error');
    }
  });
  
  if (hasError) {
    e.preventDefault();
    $('#div_alert').showAlert({
      message: '{{Veuillez corriger les erreurs dans le formulaire}}',
      level: 'warning'
    });
  }
});

/* Fonction pour nettoyer les intervalles et timeouts lors du déchargement */
$(window).on('beforeunload', function() {
  // Nettoyer tous les intervalles actifs
  for (var i = 1; i < 99999; i++) {
    window.clearInterval(i);
    window.clearTimeout(i);
  }
});

/* Gestion des erreurs JavaScript globales */
window.addEventListener('error', function(e) {
  console.error('[Withings] JavaScript Error:', e.error);
  
  // Ne pas afficher les erreurs techniques à l'utilisateur, sauf en mode debug
  if (typeof DEBUG !== 'undefined' && DEBUG) {
    $('#div_alert').showAlert({
      message: '{{Erreur JavaScript détectée. Consultez la console pour plus de détails.}}',
      level: 'warning'
    });
  }
});

/* Fonction de sécurité pour valider les URLs */
function isValidUrl(string) {
  try {
    var url = new URL(string);
    return url.protocol === "https:";
  } catch (_) {
    return false;
  }
}

/* Fonction de limitation des requêtes côté client */
var requestLimiter = {
  requests: {},
  maxRequests: 10,
  timeWindow: 60000, // 1 minute
  
  canMakeRequest: function(action) {
    var now = Date.now();
    var key = action;
    
    if (!this.requests[key]) {
      this.requests[key] = [];
    }
    
    // Nettoyer les anciennes requêtes
    this.requests[key] = this.requests[key].filter(function(timestamp) {
      return now - timestamp < this.timeWindow;
    }.bind(this));
    
    // Vérifier la limite
    if (this.requests[key].length >= this.maxRequests) {
      $('#div_alert').showAlert({
        message: '{{Trop de requêtes. Veuillez patienter avant de réessayer.}}',
        level: 'warning'
      });
      return false;
    }
    
    // Ajouter la requête actuelle
    this.requests[key].push(now);
    return true;
  }
};

/* Intercepter toutes les requêtes AJAX pour ajouter la protection */
$(document).ajaxSend(function(event, xhr, settings) {
  // Ajouter le token CSRF automatiquement
  if (settings.data && settings.data.indexOf('csrf_token') === -1) {
    settings.data += '&csrf_token=' + encodeURIComponent(withingsSecurity.getCSRFToken());
  }
  
  // Vérifier la limitation des requêtes
  var action = '';
  if (settings.data && settings.data.indexOf('action=') !== -1) {
    var matches = settings.data.match(/action=([^&]+)/);
    if (matches) {
      action = matches[1];
    }
  }
  
  if (action && !requestLimiter.canMakeRequest(action)) {
    xhr.abort();
    return false;
  }
});

/* Style CSS pour les éléments d'erreur */
var style = document.createElement('style');
style.textContent = `
  .has-error {
    border-color: #d32f2f !important;
    background-color: #ffebee !important;
  }
  
  .security-warning {
    border-left: 4px solid #ff9800;
    padding: 10px;
    background-color: #fff3e0;
    margin: 10px 0;
  }
  
  .rate-limit-warning {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 300px;
  }
`;
document.head.appendChild(style);

console.log('[Withings] Plugin JavaScript chargé avec sécurité renforcée et gestion de session OAuth');
