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
require_once dirname(__FILE__) . '/../core/class/WithingsSecurity.class.php';

// Fonction exécutée automatiquement après l'installation du plugin
function withings_install() {
    try {
        // Log de début d'installation
        WithingsSecurity::logAction('plugin_install_start');
        
        // Vérifier la version PHP minimale
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception('Ce plugin nécessite PHP 7.4 ou supérieur (version actuelle: ' . PHP_VERSION . ')');
        }
        
        // Vérifier les extensions PHP requises
        $requiredExtensions = ['curl', 'json', 'openssl'];
        $missingExtensions = [];
        
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }
        
        if (!empty($missingExtensions)) {
            throw new Exception('Extensions PHP manquantes: ' . implode(', ', $missingExtensions));
        }
        
        // Vérifier les permissions de fichiers
        $pluginPath = dirname(__FILE__) . '/..';
        $requiredPaths = [
            $pluginPath . '/core/class',
            $pluginPath . '/core/ajax',
            $pluginPath . '/core/php'
        ];
        
        foreach ($requiredPaths as $path) {
            if (!is_readable($path)) {
                throw new Exception('Permissions insuffisantes pour le répertoire: ' . $path);
            }
        }
        
        // Configuration par défaut du plugin avec valeurs sécurisées
        config::save('auto_sync', 1, 'withings');
        config::save('auto_historize', 1, 'withings');
        config::save('api_timeout', 30, 'withings');
        config::save('sync_period_days', 30, 'withings');
        config::save('oauth_rate_limit', 5, 'withings');
        config::save('api_rate_limit', 100, 'withings');
        config::save('debug', 0, 'withings');
        
        // Activer le cron par défaut
        config::save('functionality::cron::enable', 1, 'withings');
        
        // Créer les répertoires nécessaires avec permissions sécurisées
        $securityDir = dirname(__FILE__) . '/../var';
        if (!is_dir($securityDir)) {
            if (!mkdir($securityDir, 0700, true)) {
                log::add('withings', 'warning', 'Impossible de créer le répertoire de sécurité: ' . $securityDir);
            }
        }
        
        // Créer le fichier .htaccess pour protéger les données sensibles
        $htaccessPath = $securityDir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "Order deny,allow\nDeny from all\n";
            file_put_contents($htaccessPath, $htaccessContent);
        }
        
        // Générer une clé de chiffrement si nécessaire
        try {
            // Ceci déclenchera la génération automatique de la clé
            WithingsSecurity::encryptSecret('test');
            WithingsSecurity::logAction('encryption_key_generated');
        } catch (Exception $e) {
            log::add('withings', 'warning', 'Génération clé chiffrement: ' . $e->getMessage());
        }
        
        // Nettoyer les anciens fichiers de cache si ils existent
        $cacheFiles = [
            '/tmp/withings_rate_limit.json',
            '/tmp/withings_cache.json'
        ];
        
        foreach ($cacheFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        WithingsSecurity::logAction('plugin_install_success', [
            'php_version' => PHP_VERSION,
            'jeedom_version' => jeedom::version()
        ]);
        
        log::add('withings', 'info', 'Installation du plugin Withings terminée avec succès');
        
    } catch (Exception $e) {
        WithingsSecurity::logAction('plugin_install_error', [
            'error' => $e->getMessage()
        ], 'error');
        
        log::add('withings', 'error', 'Erreur installation plugin Withings: ' . $e->getMessage());
        throw $e;
    }
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function withings_update() {
    try {
        WithingsSecurity::logAction('plugin_update_start');
        
        // Vérifier la version minimale de Jeedom
        if (version_compare(jeedom::version(), '4.2', '<')) {
            throw new Exception('Ce plugin nécessite Jeedom 4.2 ou supérieur (version actuelle: ' . jeedom::version() . ')');
        }
        
        // Vérifier la version PHP
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception('Ce plugin nécessite PHP 7.4 ou supérieur (version actuelle: ' . PHP_VERSION . ')');
        }
        
        // Migration des anciens tokens non chiffrés
        $migratedCount = 0;
        foreach (eqLogic::byType('withings') as $eqLogic) {
            try {
                $accessToken = $eqLogic->getConfiguration('access_token');
                $refreshToken = $eqLogic->getConfiguration('refresh_token');
                
                // Vérifier si les tokens sont déjà chiffrés (les tokens chiffrés commencent par une séquence base64)
                if (!empty($accessToken) && !preg_match('/^[A-Za-z0-9+\/]+=*$/', $accessToken)) {
                    // Token non chiffré, le chiffrer
                    $encryptedAccessToken = WithingsSecurity::encryptSecret($accessToken);
                    $eqLogic->setConfiguration('access_token', $encryptedAccessToken);
                    $migratedCount++;
                }
                
                if (!empty($refreshToken) && !preg_match('/^[A-Za-z0-9+\/]+=*$/', $refreshToken)) {
                    // Token non chiffré, le chiffrer
                    $encryptedRefreshToken = WithingsSecurity::encryptSecret($refreshToken);
                    $eqLogic->setConfiguration('refresh_token', $encryptedRefreshToken);
                }
                
                // Mettre à jour les commandes des équipements existants
                $eqLogic->createCommands();
                $eqLogic->save();
                
            } catch (Exception $e) {
                log::add('withings', 'warning', 'Erreur migration équipement ' . $eqLogic->getHumanName() . ': ' . $e->getMessage());
            }
        }
        
        if ($migratedCount > 0) {
            WithingsSecurity::logAction('tokens_migration_completed', [
                'migrated_equipment_count' => $migratedCount
            ]);
            log::add('withings', 'info', 'Migration de ' . $migratedCount . ' équipements vers le chiffrement sécurisé');
        }
        
        // Activer le cron si nécessaire
        if (config::byKey('functionality::cron::enable', 'withings') == '') {
            config::save('functionality::cron::enable', 1, 'withings');
        }
        
        // Mise à jour des configurations de sécurité
        if (config::byKey('oauth_rate_limit', 'withings') == '') {
            config::save('oauth_rate_limit', 5, 'withings');
        }
        
        if (config::byKey('api_rate_limit', 'withings') == '') {
            config::save('api_rate_limit', 100, 'withings');
        }
        
        if (config::byKey('api_timeout', 'withings') == '') {
            config::save('api_timeout', 30, 'withings');
        }
        
        if (config::byKey('sync_period_days', 'withings') == '') {
            config::save('sync_period_days', 30, 'withings');
        }
        
        // Créer/mettre à jour le fichier de sécurité pour les répertoires sensibles
        $protectedDirs = [
            dirname(__FILE__) . '/../core/class',
            dirname(__FILE__) . '/../core/php',
            dirname(__FILE__) . '/../var'
        ];
        
        foreach ($protectedDirs as $dir) {
            if (is_dir($dir)) {
                $htaccessFile = $dir . '/.htaccess';
                if (!file_exists($htaccessFile)) {
                    $htaccessContent = "Order deny,allow\nDeny from all\n";
                    file_put_contents($htaccessFile, $htaccessContent);
                }
            }
        }
        
        // Nettoyer les anciens fichiers de log si trop volumineux
        $logFile = log::getPathToLog('withings');
        if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) { // 10 MB
            // Garder seulement les 1000 dernières lignes
            $lines = file($logFile);
            if (count($lines) > 1000) {
                $newLines = array_slice($lines, -1000);
                file_put_contents($logFile, implode('', $newLines));
            }
        }
        
        WithingsSecurity::logAction('plugin_update_success', [
            'migrated_equipment' => $migratedCount,
            'jeedom_version' => jeedom::version(),
            'php_version' => PHP_VERSION
        ]);
        
        log::add('withings', 'info', 'Mise à jour du plugin Withings terminée avec succès');
        
    } catch (Exception $e) {
        WithingsSecurity::logAction('plugin_update_error', [
            'error' => $e->getMessage()
        ], 'error');
        
        log::add('withings', 'error', 'Erreur mise à jour plugin Withings: ' . $e->getMessage());
        throw $e;
    }
}

// Fonction exécutée automatiquement après la suppression du plugin
function withings_remove() {
    try {
        WithingsSecurity::logAction('plugin_remove_start');
        
        // Nettoyer la configuration en toute sécurité
        $configKeys = [
            'client_id',
            'client_secret', 
            'auto_sync',
            'auto_historize',
            'api_base_url',
            'oauth_base_url',
            'api_timeout',
            'sync_period_days',
            'oauth_rate_limit',
            'api_rate_limit',
            'debug',
            'functionality::cron::enable'
        ];
        
        foreach ($configKeys as $key) {
            config::remove($key, 'withings');
        }
        
        // Nettoyer les fichiers temporaires de sécurité
        $tempFiles = [
            '/tmp/withings_rate_limit.json',
            '/tmp/withings_cache.json',
            dirname(__FILE__) . '/../var/withings_secret.key'
        ];
        
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    log::add('withings', 'debug', 'Fichier supprimé: ' . $file);
                } else {
                    log::add('withings', 'warning', 'Impossible de supprimer: ' . $file);
                }
            }
        }
        
        // Nettoyer le répertoire var si vide
        $varDir = dirname(__FILE__) . '/../var';
        if (is_dir($varDir) && count(scandir($varDir)) <= 2) { // Seulement . et ..
            rmdir($varDir);
        }
        
        // Compter les équipements qui seront supprimés
        $equipmentCount = count(eqLogic::byType('withings'));
        
        WithingsSecurity::logAction('plugin_remove_success', [
            'equipment_count' => $equipmentCount,
            'cleaned_files' => count($tempFiles)
        ]);
