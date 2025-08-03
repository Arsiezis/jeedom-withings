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

// Classe simple de limitation de débit
class SimpleRateLimiter {
    private static $file = '/tmp/withings_rate_limit.json';
    
    public static function check($ip, $maxRequests = 30, $timeWindow = 60) {
        $data = file_exists(self::$file) ? json_decode(file_get_contents(self::$file), true) : [];
        if (!is_array($data)) $data = [];
        
        $now = time();
        $key = $ip . '_' . floor($now / $timeWindow);
        
        $data[$key] = isset($data[$key]) ? $data[$key] + 1 : 1;
        
        // Nettoyer les anciennes entrées
        foreach ($data as $k => $v) {
            $parts = explode('_', $k);
            if (count($parts) > 1 && $parts[1] < ($now - $timeWindow * 2)) {
                unset($data[$k]);
            }
        }
        
        file_put_contents(self::$file, json_encode($data), LOCK_EX);
        
        if ($data[$key] > $maxRequests) {
            throw new Exception('Trop de requêtes, veuillez patienter');
        }
    }
}

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
        </style>
    </head>
    <body>
        <div class="success">
            <h2>✅ Autorisation réussie!</h2>
            <p>Votre balance Withings est maintenant connectée à Jeedom.</p>
            <p><strong>' . $eqLogicName . '</strong> est prêt à synchroniser vos données.</p>
            <p>Les tokens d\'accès ont été chiffrés et sauvegardés de manière sécurisée.</p>
            <p><small>💡 Le token sera automatiquement renouvelé toutes les 3 heures</small></p>
            <p>
                <a href="javascript:void(0)" onclick="closeWindow()" class="btn">Fermer cette fenêtre</a>
                <a href="/index.php?v=d&p=withings&m=withings&id=' . $eqLogicId . '" class="btn">Retourner à l\'équipement</a>
            </p>
        </div>
        
        <script>
        function closeWindow() {
            if (window.opener) {
                // Recharger la page parent après un délai pour voir les changements
                setTimeout(function() {
                    try {
                        window.opener.location.reload();
                    } catch(e) {
                        console.log("Impossible de recharger la fenêtre parent");
                    }
                }, 500);
                window.close();
            } else {
                window.location.href = "/index.php?v=d&p=withings&m=withings&id=' . $eqLogicId . '";
            }
        }
        
        // Fermeture automatique après 8 secondes
        setTimeout(closeWindow, 8000);
        </script>
    </body>
    </html>';
}

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    // Protection contre les attaques par déni de service
    $clientIp = getClientIp();
    SimpleRateLimiter::check($clientIp, 30, 60);
    
    $action = init('action');
    log::add('withings', 'debug', 'AJAX appelé avec action: ' . $action . ' depuis IP: ' . $clientIp);
    
    // Liste blanche des actions autorisées
    $allowedActions = [
        'oauth_callback', 'getAuthUrl', 'syncData', 'testConnection', 
        'testEndpoints', 'refreshCommands', 'resetAuth', 'refreshToken', 'getTokenInfo'
    ];
    
    if (!in_array($action, $allowedActions)) {
        log::add('withings', 'error', 'Action non autorisée: ' . $action . ' depuis IP: ' . $clientIp);
        throw new Exception('Action non autorisée');
    }
    
    // EXCEPTION: Le callback OAuth ne nécessite pas d'authentification Jeedom
    // mais on doit éviter les conflits de session
    if ($action == 'oauth_callback') {
        log::add('withings', 'debug', 'Callback OAuth reçu (traitement isolé pour éviter conflits de session)');
        
        $code = init('code');
        $state = init('state');
        $error = init('error');
        
        log::add('withings', 'debug', 'Paramètres callback - Code: ' . (!empty($code) ? 'présent' : 'absent') . 
                                     ', State: ' . (!empty($state) ? substr($state, 0, 10) . '...' : 'absent') . 
                                     ', Error: ' . $error);
        
        // Vérifier les erreurs OAuth
        if (!empty($error)) {
            $allowedErrors = ['access_denied', 'invalid_request', 'unauthorized_client', 'unsupported_response_type'];
            if (!in_array($error, $allowedErrors)) {
                log::add('withings', 'error', 'Erreur OAuth suspecte: ' . $error);
                $error = 'Erreur inconnue';
            }
            
            log::add('withings', 'error', 'Erreur OAuth reçue: ' . $error);
            echo generateErrorPage('Erreur d\'autorisation', 'Erreur: ' . $error);
            exit;
        }
        
        // Validation du format du code d'autorisation
        if (empty($code) || !preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
            log::add('withings', 'error', 'Code d\'autorisation invalide ou manquant');
            echo generateErrorPage('Erreur d\'autorisation', 'Code d\'autorisation invalide');
            exit;
        }
        
        // Validation du format du state
        if (empty($state) || !preg_match('/^[a-f0-9_]+$/', $state)) {
            log::add('withings', 'error', 'État OAuth invalide ou manquant');
            echo generateErrorPage('Erreur d\'autorisation', 'État de sécurité invalide');
            exit;
        }
        
        // Trouver l'équipement correspondant au state
        $eqLogics = eqLogic::byType('withings');
        $targetEqLogic = null;
        
        log::add('withings', 'debug', 'Recherche équipement pour state: ' . substr($state, 0, 10) . '...');
        
        foreach ($eqLogics as $eqLogic) {
            if ($eqLogic->getConfiguration('oauth_state') === $state) {
                $targetEqLogic = $eqLogic;
                log::add('withings', 'debug', 'Équipement trouvé: ' . $eqLogic->getHumanName());
                break;
            }
        }
        
        if (!is_object($targetEqLogic)) {
            log::add('withings', 'error', 'Aucun équipement trouvé pour le state fourni');
            echo generateErrorPage('Erreur d\'autorisation', 'Session expirée ou équipement non trouvé');
            exit;
        }
        
        try {
            // Effectuer l'échange de token de manière isolée
            $success = $targetEqLogic->exchangeCodeForToken($code, $state);
            log::add('withings', 'info', 'Échange code/token réussi pour ' . $targetEqLogic->getHumanName());
            
            if ($success) {
                echo generateSuccessPageSafe($targetEqLogic);
            } else {
                throw new Exception('Échec de l\'échange du code contre le token');
            }
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur échange token: ' . $e->getMessage());
            echo generateErrorPage('Erreur d\'autorisation', 'Impossible de finaliser l\'autorisation: ' . $e->getMessage());
        }
        
        exit; // Terminer proprement sans affecter la session principale
    }
    
    // POUR TOUTES LES AUTRES ACTIONS: Vérification d'authentification requise
    log::add('withings', 'debug', 'Utilisateur connecté: ' . (isConnect('admin') ? 'OUI' : 'NON'));

    if (!isConnect('admin')) {
        log::add('withings', 'error', 'Tentative d\'accès non autorisé depuis IP: ' . $clientIp);
        throw new Exception('401 - Accès non autorisé');
    }

    // Protection CSRF pour les actions sensibles
    $sensitiveActions = ['syncData', 'resetAuth', 'refreshCommands', 'refreshToken'];
    if (in_array($action, $sensitiveActions)) {
        ajax::init();
    }

    // Validation de l'ID équipement pour les actions qui en ont besoin
    $actionsNeedingId = ['getAuthUrl', 'syncData', 'testConnection', 'refreshCommands', 'resetAuth', 'refreshToken', 'getTokenInfo'];
    if (in_array($action, $actionsNeedingId)) {
        $eqLogicId = init('id');
        if (!is_numeric($eqLogicId) || $eqLogicId <= 0) {
            log::add('withings', 'error', 'ID équipement invalide: ' . $eqLogicId);
            throw new Exception('ID équipement invalide');
        }
        
        $eqLogic = withings::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            log::add('withings', 'error', 'Équipement non trouvé pour ID: ' . $eqLogicId);
            throw new Exception('Équipement non trouvé');
        }
        
        log::add('withings', 'debug', 'Équipement validé: ' . $eqLogic->getHumanName());
    }

    if ($action == 'getAuthUrl') {
        log::add('withings', 'debug', 'Action getAuthUrl - ID équipement: ' . $eqLogicId);
        
        // Vérifier la configuration
        $clientId = config::byKey('client_id', 'withings');
        if (empty($clientId)) {
            log::add('withings', 'error', 'Client ID non configuré');
            throw new Exception('Client ID Withings non configuré. Vérifiez la configuration du plugin.');
        }
        
        log::add('withings', 'debug', 'Client ID configuré: ' . substr($clientId, 0, 10) . '...');
        
        try {
            $authUrl = $eqLogic->getAuthorizationUrl();
            log::add('withings', 'debug', 'URL d\'autorisation générée');
            ajax::success($authUrl);
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur génération URL auth: ' . $e->getMessage());
            throw $e;
        }
    }

    if ($action == 'syncData') {
        log::add('withings', 'debug', 'Action syncData - ID équipement: ' . $eqLogicId);
        
        try {
            $eqLogic->syncData();
            log::add('withings', 'info', 'Synchronisation manuelle réussie pour ' . $eqLogic->getHumanName());
            ajax::success('Synchronisation effectuée avec succès');
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur synchronisation manuelle: ' . $e->getMessage());
            throw $e;
        }
    }

    if ($action == 'testConnection') {
        log::add('withings', 'debug', 'Action testConnection - ID équipement: ' . $eqLogicId);
        
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
                    log::add('withings', 'debug', 'Test de connexion réussi');
                    
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
            log::add('withings', 'error', 'Erreur test connexion: ' . $e->getMessage());
            throw new Exception('Erreur de connexion: ' . $e->getMessage());
        }
    }

    if ($action == 'refreshToken') {
        log::add('withings', 'debug', 'Action refreshToken - ID équipement: ' . $eqLogicId);
        
        try {
            $result = $eqLogic->refreshAccessToken();
            
            if ($result) {
                $tokenInfo = $eqLogic->getTokenInfo();
                log::add('withings', 'info', 'Token renouvelé manuellement pour ' . $eqLogic->getHumanName());
                ajax::success('Token renouvelé avec succès. Expire dans ' . $tokenInfo['expires_in_hours'] . ' heures.');
            } else {
                throw new Exception('Échec du renouvellement');
            }
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur renouvellement manuel: ' . $e->getMessage());
            
            // Si c'est un problème de refresh token invalide, proposer nouvelle autorisation
            if (strpos($e->getMessage(), 'Nouvelle autorisation') !== false) {
                throw new Exception('Refresh token invalide. Cliquez sur "Réinitialiser" puis "Autoriser l\'accès Withings" pour refaire l\'autorisation complète.');
            }
            
            throw $e;
        }
    }

    if ($action == 'getTokenInfo') {
        log::add('withings', 'debug', 'Action getTokenInfo - ID équipement: ' . $eqLogicId);
        
        try {
            $tokenInfo = $eqLogic->getTokenInfo();
            ajax::success($tokenInfo);
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur info token: ' . $e->getMessage());
            throw $e;
        }
    }

    if ($action == 'testEndpoints') {
        log::add('withings', 'debug', 'Action testEndpoints');
        
        try {
            $apiUrl = withings::getApiBaseUrl();
            $oauthUrl = withings::getOAuthBaseUrl();
            
            log::add('withings', 'debug', 'Test endpoints - API: ' . $apiUrl . ' | OAuth: ' . $oauthUrl);
            
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
            
            log::add('withings', 'debug', 'Test endpoint - Code HTTP: ' . $httpCode . ' | Erreur cURL: ' . $curlError);
            
            if ($curlError) {
                throw new Exception("Erreur de connexion: $curlError");
            }
            
            if ($httpCode == 200 || $httpCode == 400) { // 400 est normal sans paramètres
                log::add('withings', 'info', 'Test endpoints réussi');
                ajax::success("Endpoints OK - API: $apiUrl | OAuth: $oauthUrl");
            } else {
                throw new Exception("Erreur HTTP $httpCode pour $apiUrl");
            }
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur test endpoints: ' . $e->getMessage());
            throw new Exception('Erreur de test des endpoints: ' . $e->getMessage());
        }
    }

    if ($action == 'refreshCommands') {
        log::add('withings', 'debug', 'Action refreshCommands - ID équipement: ' . $eqLogicId);
        
        // Recréer les commandes
        $eqLogic->createCommands();
        
        ajax::success('Commandes actualisées');
    }

    if ($action == 'resetAuth') {
        log::add('withings', 'debug', 'Action resetAuth - ID équipement: ' . $eqLogicId);
        
        $eqLogic->setConfiguration('access_token', '');
        $eqLogic->setConfiguration('refresh_token', '');
        $eqLogic->setConfiguration('oauth_state', '');
        $eqLogic->setConfiguration('token_expires', 0);
        $eqLogic->setConfiguration('token_created', 0);
        $eqLogic->setConfiguration('token_renewed', 0);
        $eqLogic->save();
        
        log::add('withings', 'info', 'Autorisation réinitialisée pour ' . $eqLogic->getHumanName());
        ajax::success('Autorisation réinitialisée');
    }

    log::add('withings', 'error', 'Action AJAX inconnue: ' . $action);
    throw new Exception('Aucune méthode correspondante à : ' . $action);
    
} catch (Exception $e) {
    log::add('withings', 'error', 'Erreur AJAX: ' . $e->getMessage() . ' | IP: ' . getClientIp());
    ajax::error($e->getMessage(), $e->getCode());
}
?>
