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
    
    // URL par défaut (peuvent être surchargées par la configuration)
    const DEFAULT_API_BASE_URL = 'https://wbsapi.withings.net/v2/';
    const DEFAULT_OAUTH_BASE_URL = 'https://account.withings.com/oauth2_user/';
    
    // Types de mesures Withings
    const MEASURE_TYPES = [
        1 => 'weight',          // Poids (kg)
        4 => 'height',          // Taille (m)
        5 => 'fat_free_mass',   // Masse maigre (kg)
        6 => 'fat_ratio',       // Ratio de graisse (%)
        8 => 'fat_mass',        // Masse grasse (kg)
        9 => 'diastolic_bp',    // Tension diastolique
        10 => 'systolic_bp',    // Tension systolique
        11 => 'heart_rate',     // Fréquence cardiaque
        12 => 'temperature',    // Température
        54 => 'spo2',          // SpO2
        71 => 'body_temp',     // Température corporelle
        73 => 'skin_temp',     // Température de la peau
        76 => 'muscle_mass',   // Masse musculaire (kg)
        77 => 'hydration',     // Hydratation (kg)
        88 => 'bone_mass',     // Masse osseuse (kg)
        91 => 'pulse_wave',    // Vélocité de l'onde de pouls
        123 => 'vo2_max',      // VO2 max
        135 => 'qrs_interval', // Intervalle QRS
        136 => 'pr_interval',  // Intervalle PR
    ];
    
    /**
     * Récupérer l'URL de base de l'API
     */
    public static function getApiBaseUrl() {
        $configUrl = config::byKey('api_base_url', 'withings');
        $url = !empty($configUrl) ? $configUrl : self::DEFAULT_API_BASE_URL;
        
        // S'assurer que l'URL se termine par un slash
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }
    
    /**
     * Récupérer l'URL de base OAuth
     */
    public static function getOAuthBaseUrl() {
        $configUrl = config::byKey('oauth_base_url', 'withings');
        $url = !empty($configUrl) ? $configUrl : self::DEFAULT_OAUTH_BASE_URL;
        
        // S'assurer que l'URL se termine par un slash
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }
    
    // Configuration automatique des commandes
    public static function getCommandsConfig() {
        return [
            'weight' => [
                'name' => 'Poids',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'generic_type' => 'WEIGHT',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Poids en kilogrammes'
            ],
            'bmi' => [
                'name' => 'IMC',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => '',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Indice de Masse Corporelle'
            ],
            'fat_ratio' => [
                'name' => 'Masse grasse (%)',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => '%',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Pourcentage de masse grasse'
            ],
            'fat_mass' => [
                'name' => 'Masse grasse',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Masse grasse en kilogrammes'
            ],
            'muscle_mass' => [
                'name' => 'Masse musculaire',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Masse musculaire en kilogrammes'
            ],
            'bone_mass' => [
                'name' => 'Masse osseuse',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Masse osseuse en kilogrammes'
            ],
            'hydration' => [
                'name' => 'Hydratation',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => '%',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Pourcentage d\'hydratation'
            ],
            'fat_free_mass' => [
                'name' => 'Masse maigre',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'kg',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Masse maigre en kilogrammes'
            ],
            'heart_rate' => [
                'name' => 'Fréquence cardiaque',
                'type' => 'info',
                'subtype' => 'numeric',
                'unite' => 'bpm',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 1,
                'description' => 'Fréquence cardiaque en battements par minute'
            ],
            'last_sync' => [
                'name' => 'Dernière synchronisation',
                'type' => 'info',
                'subtype' => 'string',
                'unite' => '',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 0,
                'description' => 'Date et heure de la dernière synchronisation'
            ],
            'sync' => [
                'name' => 'Synchroniser',
                'type' => 'action',
                'subtype' => 'other',
                'unite' => '',
                'generic_type' => '',
                'isVisible' => 1,
                'isHistorized' => 0,
                'description' => 'Déclencher une synchronisation manuelle'
            ]
        ];
    }

    /*     * ***********************Méthodes static*************************** */

    /**
     * Fonction exécutée automatiquement toutes les heures par Jeedom
     */
    public static function cronHourly() {
        foreach (eqLogic::byType('withings', true) as $withings) {
            if ($withings->getConfiguration('auto_sync', 1) == 1) {
                try {
                    $withings->syncData();
                } catch (Exception $e) {
                    log::add('withings', 'error', 'Erreur lors de la synchronisation automatique pour ' . $withings->getHumanName() . ' : ' . $e->getMessage());
                }
            }
        }
    }

    /*     * *********************Méthodes d'instance************************* */

    // Fonction exécutée automatiquement après la création de l'équipement
    public function postInsert() {
        $this->createCommands();
    }

    // Fonction exécutée automatiquement après la mise à jour de l'équipement
    public function postUpdate() {
        $this->createCommands();
    }

    // Fonction exécutée automatiquement après la sauvegarde de l'équipement
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
                $cmd->setName(__($config['name'], __FILE__));
                $cmd->setType($config['type']);
                $cmd->setSubType($config['subtype']);
                $cmd->setUnite($config['unite']);
                $cmd->setGeneric_type($config['generic_type']);
                $cmd->setIsVisible($config['isVisible']);
                $cmd->setIsHistorized($config['isHistorized']);
                $cmd->setConfiguration('description', $config['description']);
                $cmd->save();
            }
        }
    }

    /**
     * Obtenir l'URL d'autorisation OAuth
     */
    public function getAuthorizationUrl() {
        $clientId = config::byKey('client_id', 'withings');
        $redirectUri = network::getNetworkAccess('external') . '/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback';
        $state = uniqid();
        
        // Sauvegarder le state pour vérification
        $this->setConfiguration('oauth_state', $state);
        $this->save();
        
        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'user.info,user.metrics,user.activity',
            'state' => $state
        ];
        
        return self::getOAuthBaseUrl() . 'authorize2?' . http_build_query($params);
    }

    /**
     * Échanger le code d'autorisation contre un token d'accès
     */
    public function exchangeCodeForToken($code, $state) {
        // Vérifier le state
        if ($state !== $this->getConfiguration('oauth_state')) {
            throw new Exception('État OAuth invalide');
        }
        
        $clientId = config::byKey('client_id', 'withings');
        $clientSecret = config::byKey('client_secret', 'withings');
        $redirectUri = network::getNetworkAccess('external') . '/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback';
        
        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri
        ];
        
        $response = $this->makeRequest('oauth2', $data, 'POST');
        
        if (isset($response['access_token'])) {
            $this->setConfiguration('access_token', $response['access_token']);
            $this->setConfiguration('refresh_token', $response['refresh_token']);
            $this->setConfiguration('token_expires', time() + $response['expires_in']);
            $this->save();
            
            return true;
        }
        
        throw new Exception('Impossible d\'obtenir le token d\'accès');
    }

    /**
     * Rafraîchir le token d'accès
     */
    public function refreshToken() {
        $refreshToken = $this->getConfiguration('refresh_token');
        if (empty($refreshToken)) {
            throw new Exception('Aucun refresh token disponible');
        }
        
        $clientId = config::byKey('client_id', 'withings');
        $clientSecret = config::byKey('client_secret', 'withings');
        
        $data = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken
        ];
        
        try {
            // Utiliser l'endpoint OAuth spécifique
            $oauthUrl = 'https://wbsapi.withings.net/v2/oauth2';
            $timeout = config::byKey('api_timeout', 'withings', 30);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $oauthUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => intval($timeout),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Jeedom-Withings-Plugin/1.0'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                throw new Exception('Erreur HTTP refresh token: ' . $httpCode);
            }
            
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Erreur JSON refresh token');
            }
            
            $body = $decodedResponse['body'] ?? $decodedResponse;
            if (isset($body['access_token'])) {
                $this->setConfiguration('access_token', $body['access_token']);
                $this->setConfiguration('refresh_token', $body['refresh_token']);
                $this->setConfiguration('token_expires', time() + $body['expires_in']);
                $this->save();
                
                return true;
            }
            
            throw new Exception('Token manquant dans refresh response');
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur refresh token: ' . $e->getMessage());
            throw new Exception('Impossible de rafraîchir le token');
        }
    }

    /**
     * Vérifier et rafraîchir le token si nécessaire
     */
    public function ensureValidToken() {
        $tokenExpires = $this->getConfiguration('token_expires', 0);
        
        // Si le token expire dans moins de 5 minutes, le rafraîchir
        if (time() >= ($tokenExpires - 300)) {
            $this->refreshToken();
        }
    }

    /**
     * Synchroniser les données depuis Withings
     */
    public function syncData() {
        log::add('withings', 'info', 'Début de synchronisation pour ' . $this->getHumanName());
        
        try {
            $this->ensureValidToken();
            
            // Récupérer la période configurable (défaut: 7 jours)
            $syncPeriodDays = config::byKey('sync_period_days', 'withings', 7);
            $endDate = time();
            $startDate = $endDate - (intval($syncPeriodDays) * 24 * 3600);
            
            $measures = $this->getMeasures($startDate, $endDate);
            
            if (!empty($measures)) {
                $this->processMeasures($measures);
                $this->updateLastSync();
                log::add('withings', 'info', 'Synchronisation réussie pour ' . $this->getHumanName());
            } else {
                log::add('withings', 'info', 'Aucune nouvelle donnée pour ' . $this->getHumanName());
            }
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur lors de la synchronisation pour ' . $this->getHumanName() . ' : ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer les mesures depuis l'API Withings
     */
    public function getMeasures($startDate, $endDate) {
        $params = [
            'action' => 'getmeas',
            'startdate' => $startDate,
            'enddate' => $endDate,
            'meastype' => implode(',', array_keys(self::MEASURE_TYPES))
        ];
        
        return $this->makeApiRequest('measure', $params);
    }

    /**
     * Traiter les mesures reçues
     */
    public function processMeasures($data) {
        if (!isset($data['measuregrps']) || empty($data['measuregrps'])) {
            return;
        }
        
        // Prendre la mesure la plus récente
        $latestMeasure = $data['measuregrps'][0];
        $measures = [];
        
        foreach ($latestMeasure['measures'] as $measure) {
            $type = $measure['type'];
            $value = $measure['value'];
            $unit = $measure['unit'];
            
            // Calculer la valeur réelle : value * 10^unit
            $realValue = $value * pow(10, $unit);
            
            if (isset(self::MEASURE_TYPES[$type])) {
                $measureType = self::MEASURE_TYPES[$type];
                $measures[$measureType] = $realValue;
            }
        }
        
        // Calculer l'IMC si on a le poids et la taille
        if (isset($measures['weight']) && isset($measures['height']) && $measures['height'] > 0) {
            $measures['bmi'] = round($measures['weight'] / ($measures['height'] * $measures['height']), 2);
        }
        
        // Mettre à jour les commandes
        foreach ($measures as $type => $value) {
            $cmd = $this->getCmd(null, $type);
            if (is_object($cmd)) {
                $cmd->execCmd(null, $value);
            }
        }
    }

    /**
     * Mettre à jour la date de dernière synchronisation
     */
    public function updateLastSync() {
        $cmd = $this->getCmd(null, 'last_sync');
        if (is_object($cmd)) {
            $cmd->execCmd(null, date('Y-m-d H:i:s'));
        }
    }

    /**
     * Faire un appel à l'API Withings
     */
    public function makeApiRequest($endpoint, $params) {
        $accessToken = $this->getConfiguration('access_token');
        if (empty($accessToken)) {
            throw new Exception('Aucun token d\'accès configuré');
        }
        
        $params['access_token'] = $accessToken;
        
        return $this->makeRequest($endpoint, $params);
    }

    /**
     * Faire une requête HTTP
     */
    public function makeRequest($endpoint, $data, $method = 'GET') {
        $url = self::getApiBaseUrl() . $endpoint;  // Utilise l'URL configurable
        
        // Récupérer le timeout depuis la configuration
        $timeout = config::byKey('api_timeout', 'withings', 30);
        
        $ch = curl_init();
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            $url .= '?' . http_build_query($data);
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => intval($timeout),  // Timeout configurable
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Erreur cURL: ' . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erreur HTTP: ' . $httpCode);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erreur de décodage JSON: ' . json_last_error_msg());
        }
        
        if (isset($decodedResponse['status']) && $decodedResponse['status'] !== 0) {
            throw new Exception('Erreur API Withings: ' . ($decodedResponse['error'] ?? 'Erreur inconnue'));
        }
        
        return $decodedResponse['body'] ?? $decodedResponse;
    }
}

class withingsCmd extends cmd {
    
    // Exécution d'une commande
    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic();
        
        switch ($this->getLogicalId()) {
            case 'sync':
                $eqLogic->syncData();
                break;
        }
    }
}
