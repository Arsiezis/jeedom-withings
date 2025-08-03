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

require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/WithingsSecurity.class.php';

class withings extends eqLogic {
    
    // URL par défaut
    const DEFAULT_API_BASE_URL = 'https://wbsapi.withings.net/v2/';
    const DEFAULT_OAUTH_BASE_URL = 'https://account.withings.com/oauth2_user/';
    
    // Domaines autorisés pour la sécurité
    const ALLOWED_API_DOMAINS = ['wbsapi.withings.net'];
    const ALLOWED_OAUTH_DOMAINS = ['account.withings.com'];
    const MAX_TOKEN_AGE = 7776000; // 90 jours max
    
    // Durées de vie des tokens Withings
    const ACCESS_TOKEN_DURATION = 10800; // 3 heures
    const REFRESH_TOKEN_DURATION = 31536000; // 1 an
    const RENEWAL_THRESHOLD = 1800; // Renouveler 30 min avant expiration
    
    // Types de mesures Withings (complets)
    const MEASURE_TYPES = array(
        1 => 'weight',           // Poids
        4 => 'height',           // Taille
        5 => 'fat_free_mass',    // Masse maigre
        6 => 'fat_ratio',        // Ratio de graisse (%)
        8 => 'fat_mass',         // Masse grasse
        11 => 'heart_rate',      // Fréquence cardiaque
        76 => 'muscle_mass',     // Masse musculaire
        77 => 'hydration',       // Hydratation
        88 => 'bone_mass',       // Masse osseuse
        185 => 'bmi'             // IMC (Body Mass Index)
    );
    
    /**
     * Configuration des commandes
     */
    public static function getCommandsConfig() {
        return array(
            'weight' => array(
                'name' => 'Poids',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'bmi' => array(
                'name' => 'IMC',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => '',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'height' => array(
                'name' => 'Taille',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'm',
                'isVisible' => 1,
                'isHistorized' => 0
            ),
            'fat_ratio' => array(
                'name' => 'Masse grasse (%)',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => '%',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'fat_mass' => array(
                'name' => 'Masse grasse',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'muscle_mass' => array(
                'name' => 'Masse musculaire',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'bone_mass' => array(
                'name' => 'Masse osseuse',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'hydration' => array(
                'name' => 'Hydratation',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => '%',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'fat_free_mass' => array(
                'name' => 'Masse maigre',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'heart_rate' => array(
                'name' => 'Fréquence cardiaque',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'bpm',
                'isVisible' => 1,
                'isHistorized' => 1
            ),
            'last_sync' => array(
                'name' => 'Dernière synchronisation',
                'type' => 'info',
                'subtype' => 'string',
                'unite' => '',
                'isVisible' => 1,
                'isHistorized' => 0
            ),
            'sync' => array(
                'name' => 'Synchroniser',
                'type' => 'action',
                'subtype' => 'other',
                'isVisible' => 1,
                'isHistorized' => 0
            )
        );
    }
    
    /**
     * URLs configurables avec validation de sécurité renforcée
     */
    public static function getApiBaseUrl() {
        $configUrl = config::byKey('api_base_url', 'withings');
        $url = !empty($configUrl) ? $configUrl : self::DEFAULT_API_BASE_URL;
        
        // Validation de sécurité renforcée
        WithingsSecurity::validateURL($url, 'api');
        
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }
    
    public static function getOAuthBaseUrl() {
        $configUrl = config::byKey('oauth_base_url', 'withings');
        $url = !empty($configUrl) ? $configUrl : self::DEFAULT_OAUTH_BASE_URL;
        
        // Validation de sécurité renforcée
        WithingsSecurity::validateURL($url, 'oauth');
        
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }
    
    /**
     * Gestion sécurisée des secrets clients
     */
    public static function saveClientSecret($secret) {
        if (!WithingsSecurity::validateInput($secret, 'client_id')) {
            throw new Exception('Format du client secret invalide');
        }
        
        try {
            $encryptedSecret = WithingsSecurity::encryptSecret($secret);
            config::save('client_secret', $encryptedSecret, 'withings');
            
            WithingsSecurity::logAction('client_secret_updated', [
                'admin_user' => isset($_SESSION['user']) ? $_SESSION['user']->getLogin() : 'unknown'
            ]);
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur sauvegarde client secret: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getClientSecret() {
        $encryptedSecret = config::byKey('client_secret', 'withings');
        if (empty($encryptedSecret)) {
            throw new Exception('Client secret non configuré');
        }
        
        try {
            return WithingsSecurity::decryptSecret($encryptedSecret);
        } catch (Exception $e) {
            // Si le déchiffrement échoue, c'est peut-être un ancien secret en clair
            // ou chiffré avec une ancienne clé
            log::add('withings', 'warning', 'Tentative de lecture du client secret en clair pour migration');
            
            // Vérifier si c'est un secret en base64 (chiffré) ou en clair
            if (base64_encode(base64_decode($encryptedSecret, true)) === $encryptedSecret) {
                // C'est probablement chiffré avec une ancienne clé, impossible à déchiffrer
                throw new Exception('Client secret chiffré avec une ancienne clé. Veuillez le reconfigurer dans les paramètres du plugin.');
            } else {
                // C'est probablement en clair, le retourner tel quel et le re-chiffrer
                log::add('withings', 'info', 'Migration du client secret vers le nouveau chiffrement');
                try {
                    self::saveClientSecret($encryptedSecret);
                } catch (Exception $saveException) {
                    log::add('withings', 'warning', 'Impossible de re-chiffrer le client secret: ' . $saveException->getMessage());
                }
                return $encryptedSecret;
            }
        }
    }
    
    /**
     * Cron automatique avec renouvellement des tokens
     */
    public static function cronHourly() {
        WithingsSecurity::logAction('cron_hourly_start');
        
        foreach (eqLogic::byType('withings', true) as $withings) {
            try {
                // Vérifier et renouveler les tokens si nécessaire
                $withings->checkAndRenewTokens();
                
                // Puis synchroniser si activé
                if ($withings->getConfiguration('auto_sync', 1) == 1) {
                    $withings->syncData();
                }
            } catch (Exception $e) {
                WithingsSecurity::logAction('cron_error', [
                    'equipment_id' => $withings->getId(),
                    'equipment_name' => $withings->getHumanName(),
                    'error' => $e->getMessage()
                ], 'error');
            }
        }
    }
    
    /**
     * Créer les commandes après sauvegarde
     */
    public function postSave() {
        $this->createCommands();
    }
    
    /**
     * Créer les commandes automatiquement
     */
    public function createCommands() {
        $commandsConfig = self::getCommandsConfig();
        
        foreach ($commandsConfig as $logicalId => $config) {
            $cmd = $this->getCmd(null, $logicalId);
            if (!is_object($cmd)) {
                $cmd = new withingsCmd();
                $cmd->setLogicalId($logicalId);
                $cmd->setEqLogic_id($this->getId());
                $cmd->setName($config['name']);
                $cmd->setType($config['type']);
                $cmd->setSubType($config['subtype']);
                if (isset($config['unite'])) {
                    $cmd->setUnite($config['unite']);
                }
                $cmd->setIsVisible($config['isVisible']);
                $cmd->setIsHistorized($config['isHistorized']);
                $cmd->save();
            }
        }
    }
    
    /**
     * Chiffrement des données avec la classe de sécurité
     */
    private function encryptData($data) {
        return WithingsSecurity::encryptSecret($data);
    }
    
    /**
     * Déchiffrement des données
     */
    private function decryptData($encryptedData) {
        if (empty($encryptedData)) {
            return '';
        }
        
        try {
            return WithingsSecurity::decryptSecret($encryptedData);
        } catch (Exception $e) {
            // Tentative de lecture en texte clair pour migration
            WithingsSecurity::logAction('token_migration_attempt', [
                'equipment_id' => $this->getId()
            ], 'warning');
            return $encryptedData;
        }
    }
    
    /**
     * Génération sécurisée de l'état OAuth (simplifiée pour compatibilité)
     */
    public function generateSecureState() {
        $state = WithingsSecurity::generateSecureState($this->getId());
        $this->setConfiguration('oauth_state', $state);
        $this->save();
        
        WithingsSecurity::logAction('oauth_state_generated', [
            'equipment_id' => $this->getId()
        ]);
        
        return $state;
    }
    
    /**
     * Renouvellement automatique du token d'accès
     */
    public function refreshAccessToken() {
        WithingsSecurity::logAction('token_refresh_start', [
            'equipment_id' => $this->getId(),
            'equipment_name' => $this->getHumanName()
        ]);
        
        $encryptedRefreshToken = $this->getConfiguration('refresh_token');
        if (empty($encryptedRefreshToken)) {
            throw new Exception('Aucun refresh token disponible. Nouvelle autorisation nécessaire.');
        }
        
        $refreshToken = $this->decryptData($encryptedRefreshToken);
        $clientId = config::byKey('client_id', 'withings');
        $clientSecret = self::getClientSecret();
        
        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception('Configuration OAuth incomplète');
        }
        
        $params = array(
            'action' => 'requesttoken',
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken
        );
        
        try {
            $oauthUrl = 'https://wbsapi.withings.net/v2/oauth2';
            
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $oauthUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'User-Agent: Jeedom-Withings-Plugin/1.0'
                ),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0
            ));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception('Erreur de connexion lors du renouvellement: ' . $curlError);
            }
            
            if ($httpCode !== 200) {
                WithingsSecurity::logAction('token_refresh_http_error', [
                    'equipment_id' => $this->getId(),
                    'http_code' => $httpCode
                ], 'error');
                throw new Exception('Erreur HTTP lors du renouvellement: ' . $httpCode);
            }
            
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Erreur JSON lors du renouvellement: ' . json_last_error_msg());
            }
            
            if (isset($decodedResponse['status']) && $decodedResponse['status'] !== 0) {
                $errorMsg = isset($decodedResponse['error']) ? $decodedResponse['error'] : 'Erreur inconnue';
                
                // Si le refresh token est invalide, il faut refaire l'autorisation complète
                if (strpos($errorMsg, 'invalid_grant') !== false || strpos($errorMsg, 'invalid refresh') !== false) {
                    WithingsSecurity::logAction('token_refresh_invalid_grant', [
                        'equipment_id' => $this->getId()
                    ], 'warning');
                    throw new Exception('Refresh token invalide. Nouvelle autorisation OAuth nécessaire.');
                }
                
                throw new Exception('Erreur API lors du renouvellement: ' . $errorMsg);
            }
            
            $body = isset($decodedResponse['body']) ? $decodedResponse['body'] : $decodedResponse;
            if (isset($body['access_token'])) {
                
                // Sauvegarder les nouveaux tokens chiffrés
                $this->setConfiguration('access_token', $this->encryptData($body['access_token']));
                
                // IMPORTANT: Toujours remplacer le refresh token par le nouveau
                if (isset($body['refresh_token'])) {
                    $this->setConfiguration('refresh_token', $this->encryptData($body['refresh_token']));
                }
                
                $this->setConfiguration('token_expires', time() + $body['expires_in']);
                $this->setConfiguration('token_renewed', time());
                $this->save();
                
                WithingsSecurity::logAction('token_refresh_success', [
                    'equipment_id' => $this->getId(),
                    'expires_in_hours' => round($body['expires_in']/3600, 1)
                ]);
                
                return true;
            }
            
            throw new Exception('Nouveaux tokens manquants dans la réponse');
            
        } catch (Exception $e) {
            WithingsSecurity::logAction('token_refresh_error', [
                'equipment_id' => $this->getId(),
                'error' => $e->getMessage()
            ], 'error');
            throw $e;
        }
    }
    
    /**
     * Récupération sécurisée du token avec renouvellement automatique
     */
    public function getSecureAccessToken() {
        $encryptedToken = $this->getConfiguration('access_token');
        if (empty($encryptedToken)) {
            throw new Exception('Aucun token disponible');
        }
        
        $tokenExpires = $this->getConfiguration('token_expires', 0);
        $now = time();
        
        // Renouveler le token 30 minutes avant expiration (1800 secondes)
        if ($tokenExpires <= ($now + self::RENEWAL_THRESHOLD)) {
            WithingsSecurity::logAction('token_auto_renewal_needed', [
                'equipment_id' => $this->getId(),
                'expires_in' => $tokenExpires - $now
            ]);
            
            try {
                $this->refreshAccessToken();
                // Récupérer le nouveau token
                $encryptedToken = $this->getConfiguration('access_token');
            } catch (Exception $e) {
                WithingsSecurity::logAction('token_auto_renewal_failed', [
                    'equipment_id' => $this->getId(),
                    'error' => $e->getMessage()
                ], 'error');
                throw new Exception('Token expiré et renouvellement échoué: ' . $e->getMessage());
            }
        }
        
        return $this->decryptData($encryptedToken);
    }
    
    /**
     * Vérifier et renouveler les tokens si nécessaire (pour le cron)
     */
    public function checkAndRenewTokens() {
        $tokenExpires = $this->getConfiguration('token_expires', 0);
        $now = time();
        
        // Si le token expire dans moins de 2 heures, le renouveler
        if ($tokenExpires <= ($now + 7200)) {
            try {
                $this->refreshAccessToken();
                return true;
            } catch (Exception $e) {
                WithingsSecurity::logAction('token_preventive_renewal_failed', [
                    'equipment_id' => $this->getId(),
                    'error' => $e->getMessage()
                ], 'warning');
                return false;
            }
        }
        
        return true; // Token encore valide
    }
    
    /**
     * Informations sur l'état du token
     */
    public function getTokenInfo() {
        $tokenExpires = $this->getConfiguration('token_expires', 0);
        $tokenCreated = $this->getConfiguration('token_created', 0);
        $tokenRenewed = $this->getConfiguration('token_renewed', 0);
        
        $now = time();
        $expiresIn = $tokenExpires - $now;
        
        return array(
            'expires_in_seconds' => $expiresIn,
            'expires_in_hours' => round($expiresIn / 3600, 1),
            'expires_at' => date('Y-m-d H:i:s', $tokenExpires),
            'created_at' => $tokenCreated > 0 ? date('Y-m-d H:i:s', $tokenCreated) : 'Inconnu',
            'renewed_at' => $tokenRenewed > 0 ? date('Y-m-d H:i:s', $tokenRenewed) : 'Jamais',
            'is_expired' => $expiresIn <= 0,
            'needs_renewal_soon' => $expiresIn <= 7200, // 2 heures
            'status' => $expiresIn <= 0 ? 'expiré' : ($expiresIn <= 1800 ? 'expire bientôt' : 'valide')
        );
    }
    
    /**
     * Obtenir l'URL d'autorisation OAuth avec sécurité renforcée
     */
    public function getAuthorizationUrl() {
        $clientId = config::byKey('client_id', 'withings');
        if (empty($clientId) || !WithingsSecurity::validateInput($clientId, 'client_id')) {
            throw new Exception('Client ID Withings non configuré ou invalide');
        }
        
        $redirectUri = network::getNetworkAccess('external') . '/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback';
        
        // Validation de sécurité de l'URL de redirection
        if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            throw new Exception('URL de redirection invalide');
        }
        
        $state = $this->generateSecureState();
        
        $params = array(
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'user.info,user.metrics,user.activity',
            'state' => $state
        );
        
        $authUrl = self::getOAuthBaseUrl() . 'authorize2?' . http_build_query($params);
        
        WithingsSecurity::logAction('oauth_url_generated', [
            'equipment_id' => $this->getId(),
            'redirect_uri' => $redirectUri
        ]);
        
        return $authUrl;
    }
    
    /**
     * Échanger le code contre un token avec sécurité renforcée
     */
    public function exchangeCodeForToken($code, $state) {
        WithingsSecurity::logAction('oauth_token_exchange_start', [
            'equipment_id' => $this->getId(),
            'equipment_name' => $this->getHumanName()
        ]);
        
        // Validation simple de l'état OAuth (sans vérification stricte du hash)
        $expectedState = $this->getConfiguration('oauth_state');
        if (empty($expectedState) || $state !== $expectedState) {
            WithingsSecurity::logAction('oauth_state_mismatch', [
                'equipment_id' => $this->getId()
            ], 'warning');
            throw new Exception('État OAuth invalide ou expiré');
        }
        
        // Validation du code d'autorisation
        if (!WithingsSecurity::validateInput($code, 'oauth_code')) {
            throw new Exception('Code d\'autorisation invalide');
        }
        
        $clientId = config::byKey('client_id', 'withings');
        $clientSecret = self::getClientSecret();
        $redirectUri = network::getNetworkAccess('external') . '/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback';
        
        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception('Configuration OAuth incomplète');
        }
        
        $params = array(
            'action' => 'requesttoken',
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri
        );
        
        try {
            $oauthUrl = 'https://wbsapi.withings.net/v2/oauth2';
            $timeout = 30;
            
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $oauthUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'User-Agent: Jeedom-Withings-Plugin/1.0'
                ),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0
            ));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception('Erreur de connexion: ' . $curlError);
            }
            
            if ($httpCode !== 200) {
                WithingsSecurity::logAction('oauth_token_exchange_http_error', [
                    'equipment_id' => $this->getId(),
                    'http_code' => $httpCode
                ], 'error');
                throw new Exception('Erreur HTTP: ' . $httpCode);
            }
            
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Erreur JSON: ' . json_last_error_msg());
            }
            
            if (isset($decodedResponse['status']) && $decodedResponse['status'] !== 0) {
                $errorMsg = isset($decodedResponse['error']) ? $decodedResponse['error'] : 'Erreur inconnue';
                WithingsSecurity::logAction('oauth_api_error', [
                    'equipment_id' => $this->getId(),
                    'api_error' => $errorMsg,
                    'status' => $decodedResponse['status']
                ], 'error');
                throw new Exception('Erreur API Withings: ' . $errorMsg . ' (Status: ' . $decodedResponse['status'] . ')');
            }
            
            $body = isset($decodedResponse['body']) ? $decodedResponse['body'] : $decodedResponse;
            if (isset($body['access_token'])) {
                
                // Validation des tokens reçus
                if (!WithingsSecurity::validateInput($body['access_token'], 'access_token')) {
                    throw new Exception('Token d\'accès reçu invalide');
                }
                
                // Sauvegarder les nouveaux tokens chiffrés
                $this->setConfiguration('access_token', $this->encryptData($body['access_token']));
                $this->setConfiguration('refresh_token', $this->encryptData($body['refresh_token']));
                $this->setConfiguration('token_expires', time() + $body['expires_in']);
                $this->setConfiguration('token_created', time());
                $this->setConfiguration('user_id', isset($body['userid']) ? $body['userid'] : '');
                
                // Nettoyer l'état OAuth
                $this->setConfiguration('oauth_state', '');
                
                $this->save();
                
                WithingsSecurity::logAction('oauth_token_exchange_success', [
                    'equipment_id' => $this->getId(),
                    'equipment_name' => $this->getHumanName(),
                    'user_id' => isset($body['userid']) ? $body['userid'] : 'unknown'
                ]);
                
                // Effectuer une première synchronisation automatique après l'autorisation
                try {
                    $this->syncData();
                    WithingsSecurity::logAction('initial_sync_after_oauth_success', [
                        'equipment_id' => $this->getId()
                    ]);
                } catch (Exception $syncException) {
                    // Ne pas faire échouer l'autorisation si la sync échoue
                    WithingsSecurity::logAction('initial_sync_after_oauth_failed', [
                        'equipment_id' => $this->getId(),
                        'error' => $syncException->getMessage()
                    ], 'warning');
                }
                
                return true;
            }
            
            throw new Exception('Token manquant dans la réponse');
            
        } catch (Exception $e) {
            WithingsSecurity::logAction('oauth_token_exchange_error', [
                'equipment_id' => $this->getId(),
                'error' => $e->getMessage()
            ], 'error');
            throw $e;
        }
    }
    
    /**
     * Synchronisation des données Withings avec logging sécurisé
     */
    public function syncData() {
        WithingsSecurity::logAction('sync_data_start', [
            'equipment_id' => $this->getId(),
            'equipment_name' => $this->getHumanName()
        ]);
        
        try {
            $accessToken = $this->getSecureAccessToken();
            
            // Récupérer les mesures des 30 derniers jours
            $endDate = time();
            $startDate = $endDate - (30 * 24 * 3600);
            
            $measures = $this->getMeasures($startDate, $endDate);
            
            if (!empty($measures)) {
                $this->processMeasures($measures);
                WithingsSecurity::logAction('sync_data_success', [
                    'equipment_id' => $this->getId(),
                    'measures_count' => count($measures['measuregrps'] ?? [])
                ]);
            } else {
                WithingsSecurity::logAction('sync_data_no_new_data', [
                    'equipment_id' => $this->getId()
                ]);
            }
            
            // Mettre à jour la date de synchronisation
            $lastSyncCmd = $this->getCmd(null, 'last_sync');
            if (is_object($lastSyncCmd)) {
                $lastSyncCmd->setConfiguration('value', date('Y-m-d H:i:s'));
                $lastSyncCmd->event(date('Y-m-d H:i:s'));
            }
            
        } catch (Exception $e) {
            WithingsSecurity::logAction('sync_data_error', [
                'equipment_id' => $this->getId(),
                'error' => $e->getMessage()
            ], 'error');
            throw $e;
        }
    }
    
    /**
     * Récupérer les mesures depuis l'API Withings
     */
    public function getMeasures($startDate, $endDate) {
        $accessToken = $this->getSecureAccessToken();
        
        // Inclure TOUS les types de mesures disponibles, y compris l'IMC (185)
        $measureTypes = implode(',', array_keys(self::MEASURE_TYPES));
        
        $params = array(
            'action' => 'getmeas',
            'access_token' => $accessToken,
            'startdate' => $startDate,
            'enddate' => $endDate,
            'meastype' => $measureTypes
        );
        
        $url = self::getApiBaseUrl() . 'measure';
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'User-Agent: Jeedom-Withings-Plugin/1.0'
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('Erreur de connexion mesures: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            WithingsSecurity::logAction('api_measures_http_error', [
                'equipment_id' => $this->getId(),
                'http_code' => $httpCode
            ], 'error');
            throw new Exception('Erreur HTTP récupération mesures: ' . $httpCode);
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erreur JSON mesures: ' . json_last_error_msg());
        }
        
        if (isset($decodedResponse['status']) && $decodedResponse['status'] !== 0) {
            $errorMsg = isset($decodedResponse['error']) ? $decodedResponse['error'] : 'Erreur inconnue';
            throw new Exception('Erreur API mesures: ' . $errorMsg);
        }
        
        return isset($decodedResponse['body']) ? $decodedResponse['body'] : array();
    }
    
    /**
     * Traiter les mesures reçues
     */
    public function processMeasures($data) {
        if (!isset($data['measuregrps']) || empty($data['measuregrps'])) {
            return;
        }
        
        // Prendre le groupe de mesures le plus récent
        $latestMeasureGroup = $data['measuregrps'][0];
        $measures = array();
        
        foreach ($latestMeasureGroup['measures'] as $measure) {
            $type = $measure['type'];
            $value = $measure['value'];
            $unit = $measure['unit'];
            
            // Calculer la valeur réelle : value * 10^unit
            $realValue = $value * pow(10, $unit);
            
            // Mapper les types de mesures selon la constante MEASURE_TYPES
            if (isset(self::MEASURE_TYPES[$type])) {
                $measureKey = self::MEASURE_TYPES[$type];
                
                switch ($type) {
                    case 1:  // Poids
                        $measures['weight'] = round($realValue, 2);
                        break;
                    case 4:  // Taille
                        $measures['height'] = round($realValue, 2);
                        break;
                    case 5:  // Masse maigre
                        $measures['fat_free_mass'] = round($realValue, 2);
                        break;
                    case 6:  // Ratio de graisse (%)
                        $measures['fat_ratio'] = round($realValue, 2);
                        break;
                    case 8:  // Masse grasse
                        $measures['fat_mass'] = round($realValue, 2);
                        break;
                    case 11: // Fréquence cardiaque
                        $measures['heart_rate'] = round($realValue, 0);
                        break;
                    case 76: // Masse musculaire
                        $measures['muscle_mass'] = round($realValue, 2);
                        break;
                    case 77: // Hydratation
                        $measures['hydration'] = round($realValue, 2);
                        break;
                    case 88: // Masse osseuse
                        $measures['bone_mass'] = round($realValue, 2);
                        break;
                    case 185: // IMC
                        $measures['bmi'] = round($realValue, 2);
                        break;
                }
            }
        }
        
        // Mettre à jour les commandes
        foreach ($measures as $type => $value) {
            $cmd = $this->getCmd(null, $type);
            if (is_object($cmd)) {
                $cmd->setConfiguration('value', $value);
                $cmd->event($value);
            }
        }
        
        WithingsSecurity::logAction('measures_processed', [
            'equipment_id' => $this->getId(),
            'measures_types' => array_keys($measures),
            'measures_count' => count($measures)
        ]);
    }
    
    /**
     * Vérifier si on a un token valide
     */
    public function hasValidToken() {
        try {
            $this->getSecureAccessToken();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

class withingsCmd extends cmd {
    
    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic();
        
        switch ($this->getLogicalId()) {
            case 'sync':
                WithingsSecurity::logAction('manual_sync_triggered', [
                    'equipment_id' => $eqLogic->getId(),
                    'user_id' => isset($_SESSION['user']) ? $_SESSION['user']->getId() : 'unknown'
                ]);
                $eqLogic->syncData();
                break;
        }
    }
}
?>
