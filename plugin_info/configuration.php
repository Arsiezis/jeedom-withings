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
?>
<form class="form-horizontal">
  <fieldset>
    <legend><i class="fas fa-key"></i> {{Configuration API Withings}}</legend>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Client ID}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Client ID de votre application Withings Developer}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="client_id" placeholder="{{Saisissez votre Client ID Withings}}"/>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Client Secret}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Client Secret de votre application Withings Developer}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control inputPassword" data-l1key="client_secret" placeholder="{{Saisissez votre Client Secret Withings}}"/>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{URL de redirection}}
        <sup><i class="fas fa-question-circle tooltips" title="{{URL à configurer dans votre application Withings Developer}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="form-control" readonly value="<?php echo network::getNetworkAccess('external'); ?>/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback"/>
        <small class="text-muted">{{Copiez cette URL dans votre application Withings Developer}}</small>
      </div>
    </div>
    
    <legend><i class="fas fa-server"></i> {{Configuration des endpoints API}}</legend>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{URL API Withings}}
        <sup><i class="fas fa-question-circle tooltips" title="{{URL de base de l'API Withings (laisser vide pour utiliser la valeur par défaut)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="api_base_url" placeholder="https://wbsapi.withings.net/v2/"/>
        <small class="text-muted">{{Défaut: https://wbsapi.withings.net/v2/}}</small>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{URL OAuth Withings}}
        <sup><i class="fas fa-question-circle tooltips" title="{{URL de base OAuth Withings (laisser vide pour utiliser la valeur par défaut)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="oauth_base_url" placeholder="https://account.withings.com/oauth2_user/"/>
        <small class="text-muted">{{Défaut: https://account.withings.com/oauth2_user/}}</small>
      </div>
    </div>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Mode debug}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Activer les logs détaillés pour le dépannage}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input type="checkbox" class="configKey" data-l1key="debug"/>
        <small class="text-muted">{{Activer pour diagnostiquer les problèmes}}</small>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Synchronisation automatique}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Activer la synchronisation automatique toutes les heures}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input type="checkbox" class="configKey" data-l1key="auto_sync" checked/>
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
        <input class="configKey form-control" data-l1key="api_timeout" placeholder="30" type="number" min="10" max="120"/>
      </div>
    </div>
    
    <div class="form-group">
      <label class="col-md-4 control-label">{{Période de récupération (jours)}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Nombre de jours de données à récupérer lors de la synchronisation (7 jours par défaut)}}"></i></sup>
      </label>
      <div class="col-md-4">
        <input class="configKey form-control" data-l1key="sync_period_days" placeholder="7" type="number" min="1" max="30"/>
      </div>
    </div>
    
  </fieldset>
</form>

<div class="alert alert-info">
  <h4><i class="fas fa-info-circle"></i> {{Configuration Withings Developer}}</h4>
  <p>{{Pour utiliser ce plugin, vous devez créer une application sur le portail développeur Withings :}}</p>
  <ol>
    <li>{{Rendez-vous sur}} <a href="https://developer.withings.com/dashboard/" target="_blank">{{le portail développeur Withings}}</a></li>
    <li>{{Créez une nouvelle application}}</li>
    <li>{{Configurez l'URL de redirection avec l'URL fournie ci-dessus}}</li>
    <li>{{Copiez le Client ID et Client Secret dans les champs correspondants}}</li>
    <li>{{Sélectionnez les scopes : user.info, user.metrics, user.activity}}</li>
  </ol>
</div>

<div class="alert alert-warning">
  <h4><i class="fas fa-exclamation-triangle"></i> {{Configuration des endpoints}}</h4>
  <p>{{Les URLs des endpoints peuvent être personnalisées en cas de changement de l'API Withings :}}</p>
  <ul>
    <li><strong>{{URL API}}</strong> : {{Endpoint principal pour récupérer les données}}</li>
    <li><strong>{{URL OAuth}}</strong> : {{Endpoint pour l'authentification OAuth}}</li>
  </ul>
  <p><strong>{{Attention}}</strong> : {{Ne modifiez ces URLs que si vous savez ce que vous faites !}}</p>
</div>
