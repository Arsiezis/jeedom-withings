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
    
    // Types de mesures Withings
    const MEASURE_TYPES = array(
        1 => 'weight',
        6 => 'fat_ratio',
        76 => 'muscle_mass',
        77 => 'hydration',
        88 => 'bone_mass'
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
     * URLs configurables
     */
    public static function getApiBaseUrl() {
        $configUrl = config::byKey('api_base_url', 'withings');
        $url = !empty($configUrl) ? $configUrl : self::DEFAULT_API_BASE_URL;
        
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }
    
    public static function getOAuthBaseUrl() {
        $configUrl = config::byKey('oauth_base_url', 'withings');
        $url = !empty($configUrl) ? $configUrl : self::DEFAULT_OAUTH_BASE_URL;
        
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        
        return $url;
    }
    
    /**
     * Cron automatique
     */
    public static function cronHourly() {
        foreach (eqLogic::byType('withings', true) as $withings) {
            if ($withings->getConfiguration('auto_sync', 1) == 1) {
                try {
                    $withings->syncData();
                } catch (Exception $e) {
                    log::add('withings', 'error', 'Erreur cron: ' . $e->getMessage());
                }
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
     * Générer une signature HMAC-SHA256
     */
    private function generateSignature($params, $clientSecret) {
        ksort($params);
        
        $signatureString = '';
        foreach ($params as $key => $value) {
            $signatureString .= $key . '=' . $value . '&';
        }
        $signatureString = rtrim($signatureString, '&');
        
        return hash_hmac('sha256', $signatureString, $clientSecret);
    }
    
    /**
     * Générer un nonce unique selon les spécifications Withings
     */
    private function generateNonce() {
        // Withings semble exiger un format UUID ou un nonce plus simple
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
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
        $state = uniqid();
        
        $this->setConfiguration('oauth_state', $state);
        $this->save();
        
        $params = array(
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'user.info,user.metrics,user.activity',
            'state' => $state
        );
        
        $authUrl = self::getOAuthBaseUrl() . 'authorize2?' . http_build_query($params);
        log::add('withings', 'debug', 'URL autorisation: ' . $authUrl);
        
        return $authUrl;
    }
    
    /**
     * Échanger le code contre un token (approche simplifiée)
     */
    public function exchangeCodeForToken($code, $state) {
        log::add('withings', 'debug', 'Échange code/token pour ' . $this->getHumanName());
        
        $expectedState = $this->getConfiguration('oauth_state');
        if ($state !== $expectedState) {
            throw new Exception('État OAuth invalide');
        }
        
        $clientId = config::byKey('client_id', 'withings');
        $clientSecret = config::byKey('client_secret', 'withings');
        $redirectUri = network::getNetworkAccess('external') . '/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback';
        
        // Approche simplifiée sans nonce ni signature d'après la doc récente
        $params = array(
            'action' => 'requesttoken',
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri
        );
        
        log::add('withings', 'debug', 'Paramètres simplifiés: ' . json_encode($params));
        
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
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Jeedom-Withings-Plugin/1.0'
            ));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            log::add('withings', 'debug', 'Réponse HTTP (simplifiée): ' . $httpCode);
            log::add('withings', 'debug', 'Réponse (simplifiée): ' . $response);
            
            if ($httpCode !== 200) {
                throw new Exception('Erreur HTTP: ' . $httpCode);
            }
            
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Erreur JSON: ' . json_last_error_msg());
            }
            
            if (isset($decodedResponse['status']) && $decodedResponse['status'] !== 0) {
                $errorMsg = isset($decodedResponse['error']) ? $decodedResponse['error'] : 'Erreur inconnue';
                throw new Exception('Erreur API Withings: ' . $errorMsg . ' (Status: ' . $decodedResponse['status'] . ')');
            }
            
            $body = isset($decodedResponse['body']) ? $decodedResponse['body'] : $decodedResponse;
            if (isset($body['access_token'])) {
                $this->setConfiguration('access_token', $body['access_token']);
                $this->setConfiguration('refresh_token', $body['refresh_token']);
                $this->setConfiguration('token_expires', time() + $body['expires_in']);
                $this->setConfiguration('user_id', isset($body['userid']) ? $body['userid'] : '');
                $this->setConfiguration('oauth_state', '');
                $this->save();
                
                log::add('withings', 'info', 'Token obtenu avec succès (User ID: ' . (isset($body['userid']) ? $body['userid'] : 'N/A') . ')');
                return true;
            }
            
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
        
        $accessToken = $this->getConfiguration('access_token');
        if (empty($accessToken)) {
            throw new Exception('Aucun token d\'accès configuré');
        }
        
        try {
            // Récupérer les mesures des 30 derniers jours
            $endDate = time();
            $startDate = $endDate - (30 * 24 * 3600);
            
            $measures = $this->getMeasures($startDate, $endDate);
            
            if (!empty($measures)) {
                $this->processMeasures($measures);
                log::add('withings', 'info', 'Données synchronisées avec succès');
            } else {
                log::add('withings', 'info', 'Aucune nouvelle donnée trouvée');
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
        $accessToken = $this->getConfiguration('access_token');
        
        $params = array(
            'action' => 'getmeas',
            'access_token' => $accessToken,
            'startdate' => $startDate,
            'enddate' => $endDate,
            'meastype' => '1,5,6,8,76,77,88' // Poids, masse maigre, ratio graisse, masse grasse, muscle, hydratation, os
        );
        
        log::add('withings', 'debug', 'Requête mesures: ' . json_encode($params));
        
        $url = self::getApiBaseUrl() . 'measure';
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json'
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Jeedom-Withings-Plugin/1.0'
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        log::add('withings', 'debug', 'Réponse mesures HTTP: ' . $httpCode);
        log::add('withings', 'debug', 'Réponse mesures: ' . substr($response, 0, 500));
        
        if ($httpCode !== 200) {
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
            log::add('withings', 'warning', 'Aucun groupe de mesures trouvé');
            return;
        }
        
        log::add('withings', 'debug', 'Nombre de groupes de mesures: ' . count($data['measuregrps']));
        
        // Prendre le groupe de mesures le plus récent
        $latestMeasureGroup = $data['measuregrps'][0];
        $measures = array();
        
        log::add('withings', 'debug', 'Traitement du groupe: ' . json_encode($latestMeasureGroup));
        
        foreach ($latestMeasureGroup['measures'] as $measure) {
            $type = $measure['type'];
            $value = $measure['value'];
            $unit = $measure['unit'];
            
            // Calculer la valeur réelle : value * 10^unit
            $realValue = $value * pow(10, $unit);
            
            log::add('withings', 'debug', 'Mesure type ' . $type . ': ' . $value . ' * 10^' . $unit . ' = ' . $realValue);
            
            // Mapper les types de mesures
            switch ($type) {
                case 1:  // Poids
                    $measures['weight'] = round($realValue, 2);
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
                case 76: // Masse musculaire
                    $measures['muscle_mass'] = round($realValue, 2);
                    break;
                case 77: // Hydratation
                    $measures['hydration'] = round($realValue, 2);
                    break;
                case 88: // Masse osseuse
                    $measures['bone_mass'] = round($realValue, 2);
                    break;
            }
        }
        
        // Calculer l'IMC si on a le poids
        if (isset($measures['weight'])) {
            // Pour l'IMC, on a besoin de la taille - on peut la stocker en configuration ou utiliser une valeur par défaut
            $height = $this->getConfiguration('height', 1.75); // 1.75m par défaut
            if ($height > 0) {
                $measures['bmi'] = round($measures['weight'] / ($height * $height), 2);
            }
        }
        
        log::add('withings', 'debug', 'Mesures traitées: ' . json_encode($measures));
        
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
        
        log::add('withings', 'info', 'Mesures traitées avec succès');
    }
    
    /**
     * Vérifier si on a un token valide
     */
    public function hasValidToken() {
        $accessToken = $this->getConfiguration('access_token');
        $tokenExpires = $this->getConfiguration('token_expires', 0);
        
        return !empty($accessToken) && $tokenExpires > time();
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
