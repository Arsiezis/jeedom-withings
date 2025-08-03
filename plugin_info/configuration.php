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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}

// Gestion de la sauvegarde sécurisée du client secret
if (isset($_POST['client_secret']) && !empty($_POST['client_secret'])) {
    require_once dirname(__FILE__) . '/../core/class/WithingsSecurity.class.php';
    
    try {
        withings::saveClientSecret($_POST['client_secret']);
        // Ne pas réafficher le secret dans le formulaire après sauvegarde
        $_POST['client_secret'] = '';
    } catch (Exception $e) {
        log::add('withings', 'error', 'Erreur sauvegarde client secret: ' . $e->getMessage());
    }
}

// Génération d'un token CSRF pour les actions sensibles
require_once dirname(__FILE__) . '/../core/class/WithingsSecurity.class.php';
$csrfToken = WithingsSecurity::generateCSRFToken();
?>

<form class="form-horizontal">
  <input type="hidden" id="csrf_token" value="<?php echo $csrfToken; ?>">
  
  <fieldset>
    <legend><i class="fas fa-key"></i> {{Configuration API Withings}}</legend>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Client ID}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Client ID de votre application Withings Developer}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="client_id" placeholder="{{Saisissez votre Client ID Withings}}" 
               pattern="[a-zA-Z0-9_-]+" maxlength="100" required/>
        <small class="text-muted">{{Format: lettres, chiffres, _ et - uniquement}}</small>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Client Secret}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Client Secret de votre application Withings Developer (sera chiffré automatiquement)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <div class="input-group">
          <input class="form-control inputPassword" id="client_secret_input" type="password" 
                 placeholder="{{Saisissez votre Client Secret Withings}}" 
                 pattern="[a-zA-Z0-9_-]+" maxlength="200"/>
          <span class="input-group-btn">
            <button class="btn btn-default" type="button" id="toggle_secret_visibility">
              <i class="fas fa-eye"></i>
            </button>
          </span>
        </div>
        <small class="text-success"><i class="fas fa-lock"></i> {{Le secret sera automatiquement chiffré lors de la sauvegarde}}</small>
        <br>
        <button type="button" class="btn btn-sm btn-primary" id="save_client_secret">
          <i class="fas fa-save"></i> {{Sauvegarder le Client Secret}}
        </button>
        <div id="secret_status" class="mt-2"></div>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{URL de redirection}}
        <sup><i class="fas fa-question-circle tooltips" title="{{URL à configurer dans votre application Withings Developer}}"></i></sup>
      </label>
      <div class="col-md-4">
        <div class="input-group">
          <input class="form-control" readonly id="redirect_url_display" 
                 value="<?php echo htmlspecialchars(network::getNetworkAccess('external') . '/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback', ENT_QUOTES); ?>"/>
          <span class="input-group-btn">
            <button class="btn btn-default" type="button" id="copy_redirect_url" title="{{Copier l'URL}}">
              <i class="fas fa-copy"></i>
            </button>
          </span>
        </div>
        <small class="text-muted">{{Copiez cette URL dans votre application Withings Developer}}</small>
      </div>
    </div>
    
    <legend><i class="fas fa-server"></i> {{Configuration des endpoints API}}</legend>
    
    <div class="alert alert-warning">
      <h5><i class="fas fa-exclamation-triangle"></i> {{Attention}}</h5>
      <p>{{Ne modifiez ces URLs que si vous savez ce que vous faites. Les URLs par défaut sont recommandées.}}</p>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{URL API Withings}}
        <sup><i class="fas fa-question-circle tooltips" title="{{URL de base de l'API Withings (laisser vide pour utiliser la valeur par défaut)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="api_base_url" 
               placeholder="https://wbsapi.withings.net/v2/"
               pattern="https://[a-zA-Z0-9.-]+/.*"/>
        <small class="text-muted">{{Défaut: https://wbsapi.withings.net/v2/}}</small>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{URL OAuth Withings}}
        <sup><i class="fas fa-question-circle tooltips" title="{{URL de base OAuth Withings (laisser vide pour utiliser la valeur par défaut)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="oauth_base_url" 
               placeholder="https://account.withings.com/oauth2_user/"
               pattern="https://[a-zA-Z0-9.-]+/.*"/>
        <small class="text-muted">{{Défaut: https://account.withings.com/oauth2_user/}}</small>
      </div>
    </div>

    <legend><i class="fas fa-cogs"></i> {{Options de synchronisation}}</legend>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Synchronisation automatique}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Activer la synchronisation automatique toutes les heures}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input type="checkbox" class="configKey" data-l1key="auto_sync" checked/>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Historisation automatique}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Activer l'historisation automatique des nouvelles commandes}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input type="checkbox" class="configKey" data-l1key="auto_historize" checked/>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Timeout API (secondes)}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Délai d'attente pour les requêtes API (30 secondes par défaut)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="api_timeout" 
               placeholder="30" type="number" min="10" max="120"/>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Période de récupération (jours)}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Nombre de jours de données à récupérer lors de la synchronisation (30 jours par défaut)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="sync_period_days" 
               placeholder="30" type="number" min="1" max="90"/>
      </div>
    </div>
    
    <legend><i class="fas fa-shield-alt"></i> {{Sécurité et surveillance}}</legend>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Mode debug}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Activer les logs détaillés pour le dépannage (attention: peut générer beaucoup de logs)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input type="checkbox" class="configKey" data-l1key="debug"/>
        <small class="text-warning">{{⚠️ Activer uniquement pour diagnostiquer les problèmes}}</small>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Limite de débit OAuth}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Nombre maximum de tentatives OAuth par IP en 5 minutes}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="oauth_rate_limit" 
               placeholder="5" type="number" min="3" max="20"/>
        <small class="text-muted">{{Défaut: 5 tentatives par 5 minutes}}</small>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Limite de débit API}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Nombre maximum de requêtes API par IP par heure}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="api_rate_limit" 
               placeholder="100" type="number" min="50" max="1000"/>
        <small class="text-muted">{{Défaut: 100 requêtes par heure}}</small>
      </div>
    </div>
    
  </fieldset>
</form>

<!-- Boutons d'action de diagnostic -->
<div class="alert alert-info">
  <h4><i class="fas fa-tools"></i> {{Outils de diagnostic}}</h4>
  <div class="btn-group" role="group">
    <button type="button" class="btn btn-primary" id="test_endpoints">
      <i class="fas fa-network-wired"></i> {{Tester les endpoints}}
    </button>
    <button type="button" class="btn btn-info" id="view_security_logs">
      <i class="fas fa-eye"></i> {{Voir les logs de sécurité}}
    </button>
    <button type="button" class="btn btn-warning" id="clear_rate_limits">
      <i class="fas fa-eraser"></i> {{Effacer les limites de débit}}
    </button>
  </div>
</div>

<div class="alert alert-success">
  <h4><i class="fas fa-info-circle"></i> {{Configuration Withings Developer}}</h4>
  <p>{{Pour utiliser ce plugin, vous devez créer une application sur le portail développeur Withings :}}</p>
  <ol>
    <li>{{Rendez-vous sur}} <a href="https://developer.withings.com/dashboard/" target="_blank" rel="noopener">{{le portail développeur Withings}}</a></li>
    <li>{{Créez une nouvelle application}}</li>
    <li>{{Configurez l'URL de redirection avec l'URL fournie ci-dessus}}</li>
    <li>{{Copiez le Client ID et Client Secret dans les champs correspondants}}</li>
    <li>{{Sélectionnez les scopes : user.info, user.metrics, user.activity}}</li>
  </ol>
</div>

<div class="alert alert-info">
  <h4><i class="fas fa-shield-alt"></i> {{Sécurité}}</h4>
  <ul>
    <li><strong><i class="fas fa-lock"></i> {{Chiffrement}}</strong> : {{Tous les secrets et tokens sont automatiquement chiffrés}}</li>
    <li><strong><i class="fas fa-tachometer-alt"></i> {{Rate Limiting}}</strong> : {{Protection contre les attaques par déni de service}}</li>
    <li><strong><i class="fas fa-shield-virus"></i> {{CSRF}}</strong> : {{Protection contre les attaques de falsification de requête}}</li>
    <li><strong><i class="fas fa-eye"></i> {{Logs sécurisés}}</strong> : {{Journalisation des actions avec anonymisation des données sensibles}}</li>
    <li><strong><i class="fas fa-check-circle"></i> {{Validation}}</strong> : {{Validation stricte de toutes les entrées utilisateur}}</li>
  </ul>
</div>

<script>
$(document).ready(function() {
    // Gestion de la visibilité du client secret
    $('#toggle_secret_visibility').click(function() {
        var input = $('#client_secret_input');
        var icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Copie de l'URL de redirection
    $('#copy_redirect_url').click(function() {
        var input = $('#redirect_url_display');
        input.select();
        document.execCommand('copy');
        
        // Feedback visuel
        $(this).find('i').removeClass('fa-copy').addClass('fa-check');
        setTimeout(function() {
            $('#copy_redirect_url i').removeClass('fa-check').addClass('fa-copy');
        }, 2000);
        
        $('#div_alert').showAlert({
            message: '{{URL copiée dans le presse-papier}}',
            level: 'success'
        });
    });
    
    // Sauvegarde sécurisée du client secret
    $('#save_client_secret').click(function() {
        var secret = $('#client_secret_input').val();
        var csrfToken = $('#csrf_token').val();
        
        if (!secret) {
            $('#div_alert').showAlert({
                message: '{{Veuillez saisir le client secret}}',
                level: 'warning'
            });
            return;
        }
        
        // Validation côté client
        var secretRegex = /^[a-zA-Z0-9_-]+$/;
        if (!secretRegex.test(secret)) {
            $('#div_alert').showAlert({
                message: '{{Le client secret contient des caractères non autorisés}}',
                level: 'danger'
            });
            return;
        }
        
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{Sauvegarde...}}');
        
        $.ajax({
            type: "POST",
            url: "plugins/withings/plugin_info/configuration.php",
            data: {
                client_secret: secret,
                csrf_token: csrfToken
            },
            success: function(data) {
                $('#save_client_secret').prop('disabled', false).html('<i class="fas fa-save"></i> {{Sauvegarder le Client Secret}}');
                $('#client_secret_input').val('');
                $('#secret_status').html('<div class="alert alert-success alert-sm"><i class="fas fa-check"></i> {{Client Secret sauvegardé et chiffré avec succès}}</div>');
                
                setTimeout(function() {
                    $('#secret_status').html('');
                }, 5000);
            },
            error: function() {
                $('#save_client_secret').prop('disabled', false).html('<i class="fas fa-save"></i> {{Sauvegarder le Client Secret}}');
                $('#div_alert').showAlert({
                    message: '{{Erreur lors de la sauvegarde du client secret}}',
                    level: 'danger'
                });
            }
        });
    });
    
    // Test des endpoints
    $('#test_endpoints').click(function() {
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{Test en cours...}}');
        
        $.ajax({
            type: "POST",
            url: "plugins/withings/core/ajax/withings.ajax.php",
            data: {
                action: "testEndpoints",
                csrf_token: $('#csrf_token').val()
            },
            dataType: 'json',
            success: function(data) {
                $('#test_endpoints').prop('disabled', false).html('<i class="fas fa-network-wired"></i> {{Tester les endpoints}}');
                
                if (data.state == 'ok') {
                    $('#div_alert').showAlert({
                        message: '✅ ' + data.result,
                        level: 'success'
                    });
                } else {
                    $('#div_alert').showAlert({
                        message: '❌ ' + data.result,
                        level: 'danger'
                    });
                }
            },
            error: function(xhr) {
                $('#test_endpoints').prop('disabled', false).html('<i class="fas fa-network-wired"></i> {{Tester les endpoints}}');
                $('#div_alert').showAlert({
                    message: '❌ {{Erreur lors du test des endpoints}} : ' + xhr.responseText,
                    level: 'danger'
                });
            }
        });
    });
    
    // Affichage des logs de sécurité
    $('#view_security_logs').click(function() {
        window.open('/index.php?v=d&p=log&logfile=withings', '_blank');
    });
    
    // Effacement des limites de débit (admin uniquement)
    $('#clear_rate_limits').click(function() {
        bootbox.confirm('{{Êtes-vous sûr de vouloir effacer toutes les limites de débit ? Cette action peut compromettre la sécurité temporairement.}}', function(result) {
            if (result) {
                $.ajax({
                    type: "POST",
                    url: "plugins/withings/core/ajax/withings.ajax.php",
                    data: {
                        action: "clearRateLimits",
                        csrf_token: $('#csrf_token').val()
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.state == 'ok') {
                            $('#div_alert').showAlert({
                                message: '{{Limites de débit effacées}}',
                                level: 'success'
                            });
                        }
                    },
                    error: function() {
                        $('#div_alert').showAlert({
                            message: '{{Erreur lors de l\'effacement des limites}}',
                            level: 'danger'
                        });
                    }
                });
            }
        });
    });
    
    // Validation en temps réel des URLs
    $('input[data-l1key="api_base_url"], input[data-l1key="oauth_base_url"]').on('blur', function() {
        var url = $(this).val();
        if (url && !url.match(/^https:\/\/[a-zA-Z0-9.-]+\/.*/)) {
            $(this).addClass('has-error');
            $(this).after('<small class="text-danger">{{URL invalide - doit commencer par https://}}</small>');
        } else {
            $(this).removeClass('has-error');
            $(this).siblings('.text-danger').remove();
        }
    });
    
    // Validation du client ID
    $('input[data-l1key="client_id"]').on('blur', function() {
        var clientId = $(this).val();
        if (clientId && !clientId.match(/^[a-zA-Z0-9_-]+$/)) {
            $(this).addClass('has-error');
            $(this).after('<small class="text-danger">{{Format invalide - lettres, chiffres, _ et - uniquement}}</small>');
        } else {
            $(this).removeClass('has-error');
            $(this).siblings('.text-danger').remove();
        }
    });
});
</script>

<style>
.has-error {
    border-color: #d32f2f !important;
}

.alert-sm {
    padding: 8px 12px;
    margin-bottom: 10px;
    font-size: 12px;
}

.input-group-btn .btn {
    height: 34px;
}

#secret_status .alert {
    margin-top: 10px;
}

.text-success {
    color: #2e7d32 !important;
}

.text-warning {
    color: #f57c00 !important;
}
</style>
