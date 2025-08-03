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
     * URLs configurables avec validation de sécurité
     */
    public static function getApiBaseUrl() {
        $configUrl = config::byKey('api_base_url', 'withings');
        $url = !empty($configUrl) ? $configUrl : self::DEFAULT_API_BASE_URL;
        
        // Validation de sécurité
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !in_array($parsedUrl['host'], self::ALLOWED_API_DOMAINS)) {
            log::add('withings', 'error', 'URL API non autorisée: ' . $url);
            throw new Exception('URL API non autorisée');
        }
        
        if ($parsedUrl['scheme'] !== 'https') {
            throw new Exception('HTTPS requis pour l\'API');
        }
        
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }
    
    public static function getOAuthBaseUrl() {
        $configUrl = config::byKey('oauth_base_url', 'withings');
        $url = !empty($configUrl) ? $configUrl : self::DEFAULT_OAUTH_BASE_URL;
        
        // Validation de sécurité
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !in_array($parsedUrl['host'], self::ALLOWED_OAUTH_DOMAINS)) {
            log::add('withings', 'error', 'URL OAuth non autorisée: ' . $url);
            throw new Exception('URL OAuth non autorisée');
        }
        
        if ($parsedUrl['scheme'] !== 'https') {
            throw new Exception('HTTPS requis pour OAuth');
        }
        
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }
    
    /**
     * Cron automatique avec renouvellement des tokens
     */
    public static function cronHourly() {
        log::add('withings', 'debug', 'Cron horaire - Vérification des équipements Withings');
        
        foreach (eqLogic::byType('withings', true) as $withings) {
            try {
                // Vérifier et renouveler les tokens si nécessaire
                $withings->checkAndRenewTokens();
                
                // Puis synchroniser si activé
                if ($withings->getConfiguration('auto_sync', 1) == 1) {
                    $withings->syncData();
                }
            } catch (Exception $e) {
                log::add('withings', 'error', 'Erreur cron pour ' . $withings->getHumanName() . ': ' . $e->getMessage());
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
     * Génération ou récupération de la clé de chiffrement
     */
    private function getEncryptionKey() {
        // D'abord essayer la clé globale Jeedom
        $key = config::byKey('internal::cryptokey', 'core');
        
        // Si pas de clé globale, essayer de la générer
        if (empty($key)) {
            log::add('withings', 'debug', 'Génération d\'une nouvelle clé de chiffrement globale');
            
            try {
                // Générer une nouvelle clé de chiffrement
                if (function_exists('random_bytes')) {
                    $newKey = bin2hex(random_bytes(32));
                } else {
                    $newKey = hash('sha256', uniqid(mt_rand(), true) . microtime());
                }
                
                // Sauvegarder la clé dans la configuration Jeedom
                config::save('internal::cryptokey', $newKey, 'core');
                $key = $newKey;
                
                log::add('withings', 'info', 'Nouvelle clé de chiffrement globale générée');
            } catch (Exception $e) {
                log::add('withings', 'warning', 'Impossible de générer une clé globale: ' . $e->getMessage());
                
                // Fallback: utiliser une clé spécifique au plugin
                $key = config::byKey('encryption_key', 'withings');
                if (empty($key)) {
                    if (function_exists('random_bytes')) {
                        $key = bin2hex(random_bytes(32));
                    } else {
                        $key = hash('sha256', 'withings_' . uniqid(mt_rand(), true) . microtime());
                    }
                    
                    config::save('encryption_key', $key, 'withings');
                    log::add('withings', 'info', 'Clé de chiffrement spécifique au plugin générée');
                }
            }
        }
        
        return $key;
    }
    
    /**
     * Chiffrement des données avec génération de clé si nécessaire
     */
    private function encryptData($data) {
        try {
            $key = $this->getEncryptionKey();
            
            if (empty($key)) {
                log::add('withings', 'warning', 'Aucune clé de chiffrement disponible, stockage en clair');
                return $data;
            }
            
            // Chiffrement avec la clé disponible
            if (function_exists('random_bytes')) {
                $iv = random_bytes(16);
            } else {
                $iv = openssl_random_pseudo_bytes(16);
            }
            
            $encrypted = openssl_encrypt($data, 'AES-256-CBC', hash('sha256', $key), 0, $iv);
            
            if ($encrypted === false) {
                log::add('withings', 'warning', 'Erreur de chiffrement, stockage en clair');
                return $data;
            }
            
            log::add('withings', 'debug', 'Données chiffrées avec succès');
            return base64_encode($iv . $encrypted);
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur chiffrement: ' . $e->getMessage());
            return $data; // Fallback en cas d'erreur
        }
    }
    
    /**
     * Déchiffrement des données
     */
    private function decryptData($encryptedData) {
        try {
            $key = $this->getEncryptionKey();
            
            if (empty($key)) {
                // Si pas de clé, assumer que c'est du texte en clair (migration)
                return $encryptedData;
            }
            
            $data = base64_decode($encryptedData);
            if ($data === false || strlen($data) < 16) {
                // Probablement du texte en clair
                return $encryptedData;
            }
            
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', hash('sha256', $key), 0, $iv);
            return $decrypted !== false ? $decrypted : $encryptedData;
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur déchiffrement: ' . $e->getMessage());
            return $encryptedData; // Fallback
        }
    }
    
    /**
     * Génération simple de l'état OAuth
     */
    public function generateSecureState() {
        // Génération compatible toutes versions
        if (function_exists('random_bytes')) {
            $state = bin2hex(random_bytes(16)) . '_' . time();
        } else {
            $state = md5(uniqid(mt_rand(), true)) . '_' . time();
        }
        
        $this->setConfiguration('oauth_state', $state);
        $this->save();
        
        return $state;
    }
    
    /**
     * Renouvellement automatique du token d'accès
     */
    public function refreshAccessToken() {
        log::add('withings', 'info', 'Tentative de renouvellement du token pour ' . $this->getHumanName());
        
        $encryptedRefreshToken = $this->getConfiguration('refresh_token');
        if (empty($encryptedRefreshToken)) {
            throw new Exception('Aucun refresh token disponible. Nouvelle autorisation nécessaire.');
        }
        
        $refreshToken = $this->decryptData($encryptedRefreshToken);
        $clientId = config::byKey('client_id', 'withings');
        $clientSecret = config::byKey('client_secret', 'withings');
        
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
        
        log::add('withings', 'debug', 'Renouvellement token avec refresh_token...');
        
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
            
            log::add('withings', 'debug', 'Réponse renouvellement HTTP: ' . $httpCode);
            
            if ($curlError) {
                log::add('withings', 'error', 'Erreur cURL renouvellement: ' . $curlError);
                throw new Exception('Erreur de connexion lors du renouvellement: ' . $curlError);
            }
            
            if ($httpCode !== 200) {
                log::add('withings', 'error', 'Erreur HTTP renouvellement: ' . $httpCode . ' - Réponse: ' . substr($response, 0, 500));
                throw new Exception('Erreur HTTP lors du renouvellement: ' . $httpCode);
            }
            
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log::add('withings', 'error', 'Erreur JSON renouvellement: ' . json_last_error_msg());
                throw new Exception('Erreur JSON lors du renouvellement: ' . json_last_error_msg());
            }
            
            if (isset($decodedResponse['status']) && $decodedResponse['status'] !== 0) {
                $errorMsg = isset($decodedResponse['error']) ? $decodedResponse['error'] : 'Erreur inconnue';
                log::add('withings', 'error', 'Erreur API renouvellement: ' . $errorMsg);
                
                // Si le refresh token est invalide, il faut refaire l'autorisation complète
                if (strpos($errorMsg, 'invalid_grant') !== false || strpos($errorMsg, 'invalid refresh') !== false) {
                    throw new Exception('Refresh token invalide. Nouvelle autorisation OAuth nécessaire.');
                }
                
                throw new Exception('Erreur API lors du renouvellement: ' . $errorMsg);
            }
            
            $body = isset($decodedResponse['body']) ? $decodedResponse['body'] : $decodedResponse;
            if (isset($body['access_token'])) {
                
                log::add('withings', 'debug', 'Nouveaux tokens reçus, sauvegarde...');
                
                // Sauvegarder les nouveaux tokens chiffrés
                $this->setConfiguration('access_token', $this->encryptData($body['access_token']));
                
                // IMPORTANT: Toujours remplacer le refresh token par le nouveau
                if (isset($body['refresh_token'])) {
                    $this->setConfiguration('refresh_token', $this->encryptData($body['refresh_token']));
                    log::add('withings', 'debug', 'Nouveau refresh_token sauvegardé');
                }
                
                $this->setConfiguration('token_expires', time() + $body['expires_in']);
                $this->setConfiguration('token_renewed', time());
                $this->save();
                
                log::add('withings', 'info', 'Token renouvelé avec succès pour ' . $this->getHumanName() . ' (expire dans ' . round($body['expires_in']/3600, 1) . 'h)');
                return true;
            }
            
            throw new Exception('Nouveaux tokens manquants dans la réponse');
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur renouvellement token: ' . $e->getMessage());
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
            log::add('withings', 'warning', 'Token expire bientôt pour ' . $this->getHumanName() . ', renouvellement automatique...');
            
            try {
                $this->refreshAccessToken();
                // Récupérer le nouveau token
                $encryptedToken = $this->getConfiguration('access_token');
            } catch (Exception $e) {
                log::add('withings', 'error', 'Échec renouvellement automatique: ' . $e->getMessage());
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
                log::add('withings', 'info', 'Renouvellement préventif du token pour ' . $this->getHumanName());
                $this->refreshAccessToken();
                return true;
            } catch (Exception $e) {
                log::add('withings', 'warning', 'Échec renouvellement préventif pour ' . $this->getHumanName() . ': ' . $e->getMessage());
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
     * Obtenir l'URL d'autorisation OAuth
     */
    public function getAuthorizationUrl() {
        log::add('withings', 'debug', 'Génération URL autorisation pour ' . $this->getHumanName());
        
        $clientId = config::byKey('client_id', 'withings');
        if (empty($clientId)) {
            throw new Exception('Client ID Withings non configuré');
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
        log::add('withings', 'debug', 'URL autorisation générée pour ' . $this->getHumanName());
        
        return $authUrl;
    }
    
    /**
     * Échanger le code contre un token (version corrigée)
     */
    public function exchangeCodeForToken($code, $state) {
        log::add('withings', 'debug', 'Échange code/token pour ' . $this->getHumanName());
        
        // Validation de l'état OAuth
        $expectedState = $this->getConfiguration('oauth_state');
        if ($state !== $expectedState) {
            log::add('withings', 'error', 'État OAuth invalide pour ' . $this->getHumanName());
            throw new Exception('État OAuth invalide');
        }
        
        // Validation du code d'autorisation
        if (empty($code) || !preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
            log::add('withings', 'error', 'Code d\'autorisation invalide');
            throw new Exception('Code d\'autorisation invalide');
        }
        
        $clientId = config::byKey('client_id', 'withings');
        $clientSecret = config::byKey('client_secret', 'withings');
        $redirectUri = network::getNetworkAccess('external') . '/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback';
        
        if (empty($clientId) || empty($clientSecret)) {
            log::add('withings', 'error', 'Configuration OAuth incomplète');
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
        
        log::add('withings', 'debug', 'Échange de token avec paramètres validés');
        
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
            
            log::add('withings', 'debug', 'Réponse HTTP: ' . $httpCode);
            
            if ($curlError) {
                log::add('withings', 'error', 'Erreur cURL: ' . $curlError);
                throw new Exception('Erreur de connexion: ' . $curlError);
            }
            
            if ($httpCode !== 200) {
                log::add('withings', 'error', 'Erreur HTTP: ' . $httpCode . ' - Réponse: ' . substr($response, 0, 500));
                throw new Exception('Erreur HTTP: ' . $httpCode);
            }
            
            log::add('withings', 'debug', 'Réponse reçue, traitement JSON en cours...');
            
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                log::add('withings', 'error', 'Erreur JSON: ' . json_last_error_msg());
                throw new Exception('Erreur JSON: ' . json_last_error_msg());
            }
            
            if (isset($decodedResponse['status']) && $decodedResponse['status'] !== 0) {
                $errorMsg = isset($decodedResponse['error']) ? $decodedResponse['error'] : 'Erreur inconnue';
                log::add('withings', 'error', 'Erreur API Withings: ' . $errorMsg);
                throw new Exception('Erreur API Withings: ' . $errorMsg . ' (Status: ' . $decodedResponse['status'] . ')');
            }
            
            $body = isset($decodedResponse['body']) ? $decodedResponse['body'] : $decodedResponse;
            if (isset($body['access_token'])) {
                
                log::add('withings', 'debug', 'Token reçu, sauvegarde sécurisée en cours...');
                
                // Sauvegarder les nouveaux tokens chiffrés
                $this->setConfiguration('access_token', $this->encryptData($body['access_token']));
                $this->setConfiguration('refresh_token', $this->encryptData($body['refresh_token']));
                $this->setConfiguration('token_expires', time() + $body['expires_in']);
                $this->setConfiguration('token_created', time());
                $this->setConfiguration('user_id', isset($body['userid']) ? $body['userid'] : '');
                
                // Nettoyer l'état OAuth
                $this->setConfiguration('oauth_state', '');
                
                $this->save();
                
                log::add('withings', 'info', 'Token obtenu et chiffré avec succès pour ' . $this->getHumanName());
                return true;
            }
            
            log::add('withings', 'error', 'Token manquant dans la réponse');
            throw new Exception('Token manquant dans la réponse');
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur échange token: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Synchronisation des données Withings
     */
    public function syncData() {
        log::add('withings', 'info', 'Début synchronisation pour ' . $this->getHumanName());
        
        try {
            $accessToken = $this->getSecureAccessToken();
            
            // Récupérer les mesures des 30 derniers jours
            $endDate = time();
            $startDate = $endDate - (30 * 24 * 3600);
            
            $measures = $this->getMeasures($startDate, $endDate);
            
            if (!empty($measures)) {
                $this->processMeasures($measures);
                log::add('withings', 'info', 'Données synchronisées avec succès pour ' . $this->getHumanName());
            } else {
                log::add('withings', 'info', 'Aucune nouvelle donnée trouvée pour ' . $this->getHumanName());
            }
            
            // Mettre à jour la date de synchronisation
            $lastSyncCmd = $this->getCmd(null, 'last_sync');
            if (is_object($lastSyncCmd)) {
                $lastSyncCmd->setConfiguration('value', date('Y-m-d H:i:s'));
                $lastSyncCmd->event(date('Y-m-d H:i:s'));
                log::add('withings', 'debug', 'Dernière synchronisation mise à jour: ' . date('Y-m-d H:i:s'));
            }
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur synchronisation: ' . $e->getMessage());
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
            'meastype' => $measureTypes // Tous les types : 1,4,5,6,8,11,76,77,88,185
        );
        
        log::add('withings', 'debug', 'Requête mesures pour ' . $this->getHumanName() . ' - Types: ' . $measureTypes);
        
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
        
        log::add('withings', 'debug', 'Réponse mesures HTTP: ' . $httpCode);
        
        if ($curlError) {
            log::add('withings', 'error', 'Erreur cURL mesures: ' . $curlError);
            throw new Exception('Erreur de connexion mesures: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            log::add('withings', 'error', 'Erreur HTTP récupération mesures: ' . $httpCode);
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
     * Traiter les mesures reçues (corrigé pour récupérer l'IMC de l'API)
     */
    public function processMeasures($data) {
        if (!isset($data['measuregrps']) || empty($data['measuregrps'])) {
            log::add('withings', 'warning', 'Aucun groupe de mesures trouvé pour ' . $this->getHumanName());
            return;
        }
        
        log::add('withings', 'debug', 'Nombre de groupes de mesures: ' . count($data['measuregrps']));
        
        // Prendre le groupe de mesures le plus récent
        $latestMeasureGroup = $data['measuregrps'][0];
        $measures = array();
        
        log::add('withings', 'debug', 'Traitement du groupe pour ' . $this->getHumanName());
        
        foreach ($latestMeasureGroup['measures'] as $measure) {
            $type = $measure['type'];
            $value = $measure['value'];
            $unit = $measure['unit'];
            
            // Calculer la valeur réelle : value * 10^unit
            $realValue = $value * pow(10, $unit);
            
            log::add('withings', 'debug', 'Mesure type ' . $type . ': ' . $value . ' * 10^' . $unit . ' = ' . $realValue);
            
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
                    case 185: // IMC - Récupéré directement de l'API !
                        $measures['bmi'] = round($realValue, 2);
                        log::add('withings', 'info', 'IMC récupéré directement de l\'API Withings: ' . $realValue);
                        break;
                }
            } else {
                log::add('withings', 'debug', 'Type de mesure non géré: ' . $type . ' (valeur: ' . $realValue . ')');
            }
        }
        
        log::add('withings', 'debug', 'Mesures traitées: ' . count($measures) . ' valeurs - ' . implode(', ', array_keys($measures)));
        
        // Mettre à jour les commandes
        foreach ($measures as $type => $value) {
            $cmd = $this->getCmd(null, $type);
            if (is_object($cmd)) {
                $cmd->setConfiguration('value', $value);
                $cmd->event($value);
                log::add('withings', 'debug', 'Commande ' . $type . ' mise à jour: ' . $value);
            } else {
                log::add('withings', 'warning', 'Commande ' . $type . ' non trouvée');
            }
        }
        
        log::add('withings', 'info', 'Mesures traitées avec succès pour ' . $this->getHumanName());
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
                $eqLogic->syncData();
                break;
        }
    }
}
?>
