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

// Fonctions utilitaires pour les pages de réponse
function generateErrorPage($title, $message) {
    return '<!DOCTYPE html>
    <html>
    <head>
        <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; margin: 0; }
            .error { background: white; padding: 30px; border-radius: 8px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; }
            .error h2 { color: #d32f2f; margin-bottom: 20px; }
            .error p { color: #666; margin-bottom: 20px; }
            .error a { color: #1976d2; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>
            <p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
            <p><a href="javascript:window.close()">Fermer cette fenêtre</a></p>
        </div>
    </body>
    </html>';
}

function generateSuccessPageSafe($eqLogic) {
    $eqLogicName = htmlspecialchars($eqLogic->getHumanName(), ENT_QUOTES, 'UTF-8');
    $eqLogicId = (int)$eqLogic->getId();
    
    return '<!DOCTYPE html>
    <html>
    <head>
        <title>Autorisation réussie</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; margin: 0; }
            .success { background: white; padding: 30px; border-radius: 8px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; }
            .success h2 { color: #2e7d32; margin-bottom: 20px; }
            .success p { color: #666; margin-bottom: 15px; }
            .btn { background: #2e7d32; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px; }
            .btn:hover { background: #1b5e20; }
            .countdown { font-weight: bold; color: #1976d2; }
        </style>
    </head>
    <body>
        <div class="success">
            <h2>✅ Autorisation réussie!</h2>
            <p>Votre balance Withings est maintenant connectée à Jeedom.</p>
            <p><strong>' . $eqLogicName . '</strong> est prêt à synchroniser vos données.</p>
            <p>Les tokens d\'accès ont été chiffrés et sauvegardés de manière sécurisée.</p>
            <p><small>💡 Le token sera automatiquement renouvelé toutes les 3 heures</small></p>
            <p><small>📊 Une première synchronisation a été effectuée automatiquement</small></p>
            <p>
                <a href="javascript:void(0)" onclick="closeWindow()" class="btn">Fermer cette fenêtre</a>
            </p>
            <p><small>Cette fenêtre se fermera automatiquement dans <span id="countdown" class="countdown">2</span> secondes</small></p>
        </div>
        
        <script>
        var countdownTimer = 2;
        
        function closeWindow() {
            // Message à la fenêtre parent pour indiquer le succès de l\'autorisation
            if (window.opener && !window.opener.closed) {
                try {
                    // Envoyer un message sécurisé à la fenêtre parent
                    window.opener.postMessage({
                        type: "withings_oauth_success",
                        equipmentId: ' . $eqLogicId . ',
                        equipmentName: "' . addslashes($eqLogicName) . '"
                    }, window.location.origin);
                } catch (e) {
                    console.log("Impossible de communiquer avec la fenêtre parent");
                }
            }
            
            // Fermer la popup
            window.close();
        }
        
        // Compte à rebours automatique
        function updateCountdown() {
            var countdownElement = document.getElementById("countdown");
            if (countdownElement) {
                countdownElement.textContent = countdownTimer;
                countdownTimer--;
                
                if (countdownTimer < 0) {
                    closeWindow();
                } else {
                    setTimeout(updateCountdown, 1000);
                }
            }
        }
        
        // Démarrer le compte à rebours
        updateCountdown();
        </script>
    </body>
    </html>';
}

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');
    require_once dirname(__FILE__) . '/../class/WithingsSecurity.class.php';

    // Protection contre les attaques par déni de service
    $clientIp = WithingsSecurity::getClientIP();
    
    $action = init('action');
    WithingsSecurity::logAction('ajax_call', [
        'action' => $action,
        'client_ip' => $clientIp
    ]);
    
    // Liste blanche des actions autorisées
    $allowedActions = [
        'oauth_callback', 'getAuthUrl', 'syncData', 'testConnection', 
        'testEndpoints', 'refreshCommands', 'resetAuth', 'refreshToken', 'getTokenInfo'
    ];
    
    if (!in_array($action, $allowedActions)) {
        WithingsSecurity::logAction('unauthorized_action_attempt', [
            'action' => $action,
            'client_ip' => $clientIp
        ], 'error');
        throw new Exception('Action non autorisée');
    }
    
    // EXCEPTION: Le callback OAuth ne nécessite pas d'authentification Jeedom
    // Traitement isolé pour éviter les conflits de session
    if ($action == 'oauth_callback') {
        // ISOLATION COMPLÈTE DE LA SESSION pour éviter les conflits
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close(); // Fermer la session active sans la détruire
        }
        
        // Ne pas démarrer de nouvelle session pour éviter les conflits
        WithingsSecurity::logAction('oauth_callback_received');
        
        // Rate limiting spécial pour OAuth (sans session)
        try {
            WithingsSecurity::checkRateLimit($clientIp, 'oauth');
        } catch (Exception $e) {
            echo generateErrorPage('Limite atteinte', $e->getMessage());
            exit;
        }
        
        $code = init('code');
        $state = init('state');
        $error = init('error');
        
        // Vérifier les erreurs OAuth
        if (!empty($error)) {
            $allowedErrors = ['access_denied', 'invalid_request', 'unauthorized_client', 'unsupported_response_type'];
            if (!in_array($error, $allowedErrors)) {
                WithingsSecurity::logAction('oauth_suspicious_error', [
                    'error' => $error,
                    'client_ip' => $clientIp
                ], 'error');
                $error = 'Erreur inconnue';
            }
            
            WithingsSecurity::logAction('oauth_error_received', [
                'error' => $error
            ], 'error');
            echo generateErrorPage('Erreur d\'autorisation', 'Erreur: ' . $error);
            exit;
        }
        
        // Validation basique du code d'autorisation
        if (empty($code) || !preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
            WithingsSecurity::logAction('oauth_invalid_code', [
                'client_ip' => $clientIp
            ], 'error');
            echo generateErrorPage('Erreur d\'autorisation', 'Code d\'autorisation invalide');
            exit;
        }
        
        // Validation basique du state
        if (empty($state) || !preg_match('/^[a-f0-9_]+$/', $state)) {
            WithingsSecurity::logAction('oauth_invalid_state_format', [
                'client_ip' => $clientIp
            ], 'error');
            echo generateErrorPage('Erreur d\'autorisation', 'État de sécurité invalide');
            exit;
        }
        
        // Trouver l'équipement correspondant au state (validation simple)
        $eqLogics = eqLogic::byType('withings');
        $targetEqLogic = null;
        
        foreach ($eqLogics as $eqLogic) {
            $expectedState = $eqLogic->getConfiguration('oauth_state');
            if (!empty($expectedState) && $expectedState === $state) {
                // Vérifier que le state n'est pas trop ancien (1 heure max)
                $parts = explode('_', $state);
                if (count($parts) >= 2) {
                    $timestamp = (int)$parts[1];
                    if (time() - $timestamp <= 3600) { // 1 heure max
                        $targetEqLogic = $eqLogic;
                        WithingsSecurity::logAction('oauth_equipment_found', [
                            'equipment_id' => $eqLogic->getId(),
                            'equipment_name' => $eqLogic->getHumanName()
                        ]);
                        break;
                    }
                }
            }
        }
        
        if (!is_object($targetEqLogic)) {
            WithingsSecurity::logAction('oauth_no_equipment_found', [
                'client_ip' => $clientIp
            ], 'error');
            echo generateErrorPage('Erreur d\'autorisation', 'Session expirée ou équipement non trouvé');
            exit;
        }
        
        try {
            // Effectuer l'échange de token de manière isolée
            $success = $targetEqLogic->exchangeCodeForToken($code, $state);
            
            if ($success) {
                echo generateSuccessPageSafe($targetEqLogic);
            } else {
                throw new Exception('Échec de l\'échange du code contre le token');
            }
        } catch (Exception $e) {
            WithingsSecurity::logAction('oauth_token_exchange_failed', [
                'equipment_id' => $targetEqLogic->getId(),
                'error' => $e->getMessage()
            ], 'error');
            
            // Messages d'erreur spécifiques pour aider l'utilisateur
            $userMessage = $e->getMessage();
            if (strpos($e->getMessage(), 'Client secret') !== false) {
                $userMessage = 'Erreur de configuration : Le Client Secret doit être reconfiguré dans les paramètres du plugin.';
            } elseif (strpos($e->getMessage(), 'chiffrement') !== false) {
                $userMessage = 'Erreur de sécurité : Veuillez reconfigurer le Client Secret dans les paramètres du plugin.';
            }
            
            echo generateErrorPage('Erreur d\'autorisation', $userMessage);
        }
        
        exit; // Terminer proprement sans affecter la session principale
    }
    
    // POUR TOUTES LES AUTRES ACTIONS: Vérification d'authentification requise
    if (!isConnect('admin')) {
        WithingsSecurity::logAction('unauthorized_access_attempt', [
            'action' => $action,
            'client_ip' => $clientIp
        ], 'error');
        throw new Exception('401 - Accès non autorisé');
    }

    // Rate limiting pour les utilisateurs authentifiés
    $userId = isset($_SESSION['user']) ? $_SESSION['user']->getId() : null;
    WithingsSecurity::checkRateLimit($clientIp, 'api', $userId);

    // Protection CSRF pour les actions sensibles
    $sensitiveActions = ['syncData', 'resetAuth', 'refreshCommands', 'refreshToken'];
    if (in_array($action, $sensitiveActions)) {
        ajax::init();
        
        // Vérification CSRF supplémentaire pour les actions critiques
        $csrfToken = init('csrf_token');
        if (!empty($csrfToken) && !WithingsSecurity::validateCSRFToken($csrfToken)) {
            WithingsSecurity::logAction('csrf_token_validation_failed', [
                'action' => $action,
                'user_id' => $userId
            ], 'error');
            throw new Exception('Token CSRF invalide');
        }
    }

    // Validation de l'ID équipement pour les actions qui en ont besoin
    $actionsNeedingId = ['getAuthUrl', 'syncData', 'testConnection', 'refreshCommands', 'resetAuth', 'refreshToken', 'getTokenInfo'];
    if (in_array($action, $actionsNeedingId)) {
        $eqLogicId = init('id');
        if (!is_numeric($eqLogicId) || $eqLogicId <= 0) {
            WithingsSecurity::logAction('invalid_equipment_id', [
                'action' => $action,
                'provided_id' => $eqLogicId,
                'user_id' => $userId
            ], 'error');
            throw new Exception('ID équipement invalide');
        }
        
        $eqLogic = withings::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            WithingsSecurity::logAction('equipment_not_found', [
                'action' => $action,
                'equipment_id' => $eqLogicId,
                'user_id' => $userId
            ], 'error');
            throw new Exception('Équipement non trouvé');
        }
    }

    if ($action == 'getAuthUrl') {
        WithingsSecurity::logAction('get_auth_url_request', [
            'equipment_id' => $eqLogicId,
            'user_id' => $userId
        ]);
        
        // Vérifier la configuration
        $clientId = config::byKey('client_id', 'withings');
        if (empty($clientId) || !WithingsSecurity::validateInput($clientId, 'client_id')) {
            WithingsSecurity::logAction('missing_client_id', [
                'user_id' => $userId
            ], 'error');
            throw new Exception('Client ID Withings non configuré ou invalide. Vérifiez la configuration du plugin.');
        }
        
        try {
            $authUrl = $eqLogic->getAuthorizationUrl();
            ajax::success($authUrl);
        } catch (Exception $e) {
            WithingsSecurity::logAction('auth_url_generation_failed', [
                'equipment_id' => $eqLogicId,
                'error' => $e->getMessage()
            ], 'error');
            throw $e;
        }
    }

    if ($action == 'syncData') {
        WithingsSecurity::logAction('manual_sync_request', [
            'equipment_id' => $eqLogicId,
            'user_id' => $userId
        ]);
        
        try {
            $eqLogic->syncData();
            ajax::success('Synchronisation effectuée avec succès');
        } catch (Exception $e) {
            WithingsSecurity::logAction('manual_sync_failed', [
                'equipment_id' => $eqLogicId,
                'error' => $e->getMessage()
            ], 'error');
            throw $e;
        }
    }

    if ($action == 'testConnection') {
        WithingsSecurity::logAction('test_connection_request', [
            'equipment_id' => $eqLogicId,
            'user_id' => $userId
        ]);
        
        try {
            // Essayer d'obtenir le token (avec renouvellement automatique si nécessaire)
            $accessToken = $eqLogic->getSecureAccessToken();
            
            if (empty($accessToken)) {
                throw new Exception('Aucun token d\'accès configuré');
            }
            
            // Test simple avec l'API Withings
            $testUrl = 'https://wbsapi.withings.net/v2/user?action=getdevice&access_token=' . urlencode($accessToken);
            
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $testUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Jeedom-Withings-Plugin/1.0',
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
            
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] == 0) {
                    WithingsSecurity::logAction('test_connection_success', [
                        'equipment_id' => $eqLogicId
                    ]);
                    
                    // Informations sur le token
                    $tokenInfo = $eqLogic->getTokenInfo();
                    $message = 'Connexion OK - Token valide et chiffré';
                    
                    if ($tokenInfo['needs_renewal_soon']) {
                        $message .= ' (renouvellement prévu dans ' . $tokenInfo['expires_in_hours'] . 'h)';
                    } else {
                        $message .= ' (expire le ' . $tokenInfo['expires_at'] . ')';
                    }
                    
                    ajax::success($message);
                } else {
                    throw new Exception('Réponse API invalide: ' . ($data['error'] ?? 'Erreur inconnue'));
                }
            } else {
                throw new Exception('Erreur HTTP: ' . $httpCode);
            }
            
        } catch (Exception $e) {
            WithingsSecurity::logAction('test_connection_failed', [
                'equipment_id' => $eqLogicId,
                'error' => $e->getMessage()
            ], 'error');
            throw new Exception('Erreur de connexion: ' . $e->getMessage());
        }
    }

    if ($action == 'refreshToken') {
        WithingsSecurity::logAction('manual_token_refresh_request', [
            'equipment_id' => $eqLogicId,
            'user_id' => $userId
        ]);
        
        try {
            $result = $eqLogic->refreshAccessToken();
            
            if ($result) {
                $tokenInfo = $eqLogic->getTokenInfo();
                ajax::success('Token renouvelé avec succès. Expire dans ' . $tokenInfo['expires_in_hours'] . ' heures.');
            } else {
                throw new Exception('Échec du renouvellement');
            }
        } catch (Exception $e) {
            WithingsSecurity::logAction('manual_token_refresh_failed', [
                'equipment_id' => $eqLogicId,
                'error' => $e->getMessage()
            ], 'error');
            
            // Si c'est un problème de refresh token invalide, proposer nouvelle autorisation
            if (strpos($e->getMessage(), 'Nouvelle autorisation') !== false) {
                throw new Exception('Refresh token invalide. Cliquez sur "Réinitialiser" puis "Autoriser l\'accès Withings" pour refaire l\'autorisation complète.');
            }
            
            throw $e;
        }
    }

    if ($action == 'getTokenInfo') {
        $eqLogicId = init('id');
        if (!is_numeric($eqLogicId) || $eqLogicId <= 0) {
            throw new Exception('ID équipement invalide');
        }
        
        $eqLogic = withings::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            throw new Exception('Équipement non trouvé');
        }
        
        try {
            $tokenInfo = $eqLogic->getTokenInfo();
            ajax::success($tokenInfo);
        } catch (Exception $e) {
            WithingsSecurity::logAction('get_token_info_failed', [
                'equipment_id' => $eqLogicId,
                'error' => $e->getMessage()
            ], 'error');
            throw $e;
        }
    }

    if ($action == 'testEndpoints') {
        WithingsSecurity::logAction('test_endpoints_request', [
            'user_id' => $userId
        ]);
        
        try {
            $apiUrl = withings::getApiBaseUrl();
            $oauthUrl = withings::getOAuthBaseUrl();
            
            // Test de connectivité basique
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_NOBODY => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Jeedom-Withings-Plugin/1.0',
                CURLOPT_FOLLOWLOCATION => false
            ));
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new Exception("Erreur de connexion: $curlError");
            }
            
            if ($httpCode == 200 || $httpCode == 400) { // 400 est normal sans paramètres
                WithingsSecurity::logAction('test_endpoints_success');
                ajax::success("Endpoints OK - API: $apiUrl | OAuth: $oauthUrl");
            } else {
                throw new Exception("Erreur HTTP $httpCode pour $apiUrl");
            }
        } catch (Exception $e) {
            WithingsSecurity::logAction('test_endpoints_failed', [
                'error' => $e->getMessage()
            ], 'error');
            throw new Exception('Erreur de test des endpoints: ' . $e->getMessage());
        }
    }

    if ($action == 'refreshCommands') {
        WithingsSecurity::logAction('refresh_commands_request', [
            'equipment_id' => $eqLogicId,
            'user_id' => $userId
        ]);
        
        // Recréer les commandes
        $eqLogic->createCommands();
        
        ajax::success('Commandes actualisées');
    }

    if ($action == 'resetAuth') {
        WithingsSecurity::logAction('reset_auth_request', [
            'equipment_id' => $eqLogicId,
            'user_id' => $userId
        ]);
        
        $eqLogic->setConfiguration('access_token', '');
        $eqLogic->setConfiguration('refresh_token', '');
        $eqLogic->setConfiguration('oauth_state', '');
        $eqLogic->setConfiguration('token_expires', 0);
        $eqLogic->setConfiguration('token_created', 0);
        $eqLogic->setConfiguration('token_renewed', 0);
        $eqLogic->save();
        
        WithingsSecurity::logAction('reset_auth_success', [
            'equipment_id' => $eqLogicId,
            'equipment_name' => $eqLogic->getHumanName()
        ]);
        
        ajax::success('Autorisation réinitialisée');
    }

    WithingsSecurity::logAction('unknown_ajax_action', [
        'action' => $action,
        'user_id' => $userId
    ], 'error');
    throw new Exception('Aucune méthode correspondante à : ' . $action);
    
} catch (Exception $e) {
    WithingsSecurity::logAction('ajax_error', [
        'action' => $action ?? 'unknown',
        'error' => $e->getMessage(),
        'client_ip' => WithingsSecurity::getClientIP()
    ], 'error');
    ajax::error($e->getMessage(), $e->getCode());
}
?>
