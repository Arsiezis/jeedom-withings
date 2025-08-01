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

// Fonction exécutée automatiquement après l'installation du plugin
function withings_install() {
    // Configuration par défaut du plugin
    config::save('auto_sync', 1, 'withings');
    config::save('auto_historize', 1, 'withings');
    
    // Activer le cron par défaut
    config::save('functionality::cron::enable', 1, 'withings');
    
    log::add('withings', 'info', 'Installation du plugin Withings terminée');
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function withings_update() {
    // Vérifier la version minimale de Jeedom
    if (version_compare(jeedom::version(), '4.2', '<')) {
        throw new Exception('Ce plugin nécessite Jeedom 4.2 ou supérieur');
    }
    
    // Mettre à jour les commandes des équipements existants
    foreach (eqLogic::byType('withings') as $eqLogic) {
        $eqLogic->createCommands();
    }
    
    // Activer le cron si nécessaire
    if (config::byKey('functionality::cron::enable', 'withings') == '') {
        config::save('functionality::cron::enable', 1, 'withings');
    }
    
    log::add('withings', 'info', 'Mise à jour du plugin Withings terminée');
}

// Fonction exécutée automatiquement après la suppression du plugin
function withings_remove() {
    // Nettoyer la configuration
    config::remove('client_id', 'withings');
    config::remove('client_secret', 'withings');
    config::remove('auto_sync', 'withings');
    config::remove('auto_historize', 'withings');
    config::remove('functionality::cron::enable', 'withings');
    
    log::add('withings', 'info', 'Plugin Withings désinstallé');
}
