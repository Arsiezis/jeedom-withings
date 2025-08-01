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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    log::add('withings', 'debug', 'AJAX appelé avec action: ' . init('action'));
    
    // EXCEPTION: Le callback OAuth ne nécessite pas d'authentification Jeedom
    // car il vient directement de Withings (externe)
    if (init('action') == 'oauth_callback') {
        log::add('withings', 'debug', 'Callback OAuth reçu (pas d\'auth Jeedom requise)');
        
        $code = init('code');
        $state = init('state');
        $error = init('error');
        
        log::add('withings', 'debug', 'Paramètres callback - Code: ' . (!empty($code) ? 'présent' : 'absent') . 
                                     ', State: ' . (!empty($state) ? substr($state, 0, 10) . '...' : 'absent') . 
                                     ', Error: ' . $error);
        
        if (!empty($error)) {
            log::add('withings', 'error', 'Erreur OAuth reçue: ' . $error);
            echo '<html><body><h2>Erreur d\'autorisation</h2><p>Erreur: ' . htmlspecialchars($error) . '</p></body></html>';
            return;
        }
        
        if (empty($code) || empty($state)) {
            log::add('withings', 'error', 'Code ou state manquant dans le callback');
            echo '<html><body><h2>Erreur d\'autorisation</h2><p>Paramètres manquants</p></body></html>';
            return;
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
            echo '<html><body><h2>Erreur d\'autorisation</h2><p>Équipement non trouvé</p></body></html>';
            return;
        }
        
        try {
            $success = $targetEqLogic->exchangeCodeForToken($code, $state);
            log::add('withings', 'info', 'Échange code/token réussi pour ' . $targetEqLogic->getHumanName());
            
            if ($success) {
                // Rediriger vers la page de configuration
                echo '<script>
                    if (window.opener) {
                        window.opener.location.reload();
                        window.close();
                    } else {
                        window.location.href = "/index.php?v=d&p=withings&m=withings&id=' . $targetEqLogic->getId() . '";
                    }
                </script>';
                echo '<html><body style="font-family: Arial, sans-serif; text-align: center; padding: 50px;">
                    <h2 style="color: green;">✅ Autorisation réussie!</h2>
                    <p>Votre balance Withings est maintenant connectée à Jeedom.</p>
                    <p><strong>' . $targetEqLogic->getHumanName() . '</strong> est prêt à synchroniser vos données.</p>
                    <p><small>Vous pouvez fermer cette fenêtre.</small></p>
                </body></html>';
            } else {
                throw new Exception('Échec de l\'échange du code contre le token');
            }
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur échange token: ' . $e->getMessage());
            echo '<html><body style="font-family: Arial, sans-serif; text-align: center; padding: 50px;">
                <h2 style="color: red;">❌ Erreur d\'autorisation</h2>
                <p>Impossible de finaliser l\'autorisation Withings.</p>
                <p>Erreur: ' . htmlspecialchars($e->getMessage()) . '</p>
                <p><a href="/index.php?v=d&p=withings">Retourner à la configuration</a></p>
            </body></html>';
        }
        
        return;
    }
    
    // POUR TOUTES LES AUTRES ACTIONS: Vérification d'authentification requise
    log::add('withings', 'debug', 'Utilisateur connecté: ' . (isConnect('admin') ? 'OUI' : 'NON'));

    if (!isConnect('admin')) {
        log::add('withings', 'error', 'Tentative d\'accès non autorisé depuis IP: ' . getClientIp());
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    ajax::init();

    if (init('action') == 'getAuthUrl') {
        log::add('withings', 'debug', 'Action getAuthUrl - ID équipement: ' . init('id'));
        
        $eqLogic = withings::byId(init('id'));
        if (!is_object($eqLogic)) {
            log::add('withings', 'error', 'Équipement non trouvé pour ID: ' . init('id'));
            throw new Exception(__('Équipement non trouvé', __FILE__));
        }
        
        log::add('withings', 'debug', 'Équipement trouvé: ' . $eqLogic->getHumanName());
        
        // Vérifier la configuration
        $clientId = config::byKey('client_id', 'withings');
        if (empty($clientId)) {
            log::add('withings', 'error', 'Client ID non configuré');
            throw new Exception('Client ID Withings non configuré. Vérifiez la configuration du plugin.');
        }
        
        log::add('withings', 'debug', 'Client ID configuré: ' . substr($clientId, 0, 10) . '...');
        
        try {
            $authUrl = $eqLogic->getAuthorizationUrl();
            log::add('withings', 'debug', 'URL d\'autorisation générée: ' . $authUrl);
            ajax::success($authUrl);
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur génération URL auth: ' . $e->getMessage());
            throw $e;
        }
    }

    if (init('action') == 'oauth_callback') {
        // Cette action est déjà traitée en haut du fichier sans auth
        return;
    }

    if (init('action') == 'syncData') {
        log::add('withings', 'debug', 'Action syncData - ID équipement: ' . init('id'));
        
        $eqLogic = withings::byId(init('id'));
        if (!is_object($eqLogic)) {
            log::add('withings', 'error', 'Équipement non trouvé pour synchronisation: ' . init('id'));
            throw new Exception(__('Équipement non trouvé', __FILE__));
        }
        
        try {
            $eqLogic->syncData();
            log::add('withings', 'info', 'Synchronisation manuelle réussie pour ' . $eqLogic->getHumanName());
            ajax::success('Synchronisation effectuée avec succès');
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur synchronisation manuelle: ' . $e->getMessage());
            throw $e;
        }
    }

    if (init('action') == 'testConnection') {
        log::add('withings', 'debug', 'Action testConnection - ID équipement: ' . init('id'));
        
        $eqLogic = withings::byId(init('id'));
        if (!is_object($eqLogic)) {
            log::add('withings', 'error', 'Équipement non trouvé pour test connexion: ' . init('id'));
            throw new Exception(__('Équipement non trouvé', __FILE__));
        }
        
        try {
            $accessToken = $eqLogic->getConfiguration('access_token');
            $tokenExpires = $eqLogic->getConfiguration('token_expires', 0);
            
            if (empty($accessToken)) {
                throw new Exception('Aucun token d\'accès configuré');
            }
            
            if ($tokenExpires <= time()) {
                throw new Exception('Token expiré');
            }
            
            // Test simple avec l'API Withings
            $testUrl = 'https://wbsapi.withings.net/v2/user?action=getdevice&access_token=' . $accessToken;
            
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $testUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] == 0) {
                    log::add('withings', 'debug', 'Test de connexion réussi');
                    ajax::success('Connexion OK - Token valide');
                } else {
                    throw new Exception('Réponse API invalide');
                }
            } else {
                throw new Exception('Erreur HTTP: ' . $httpCode);
            }
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur test connexion: ' . $e->getMessage());
            throw new Exception('Erreur de connexion: ' . $e->getMessage());
        }
    }

    if (init('action') == 'testEndpoints') {
        log::add('withings', 'debug', 'Action testEndpoints');
        
        try {
            $apiUrl = withings::getApiBaseUrl();
            $oauthUrl = withings::getOAuthBaseUrl();
            
            log::add('withings', 'debug', 'Test endpoints - API: ' . $apiUrl . ' | OAuth: ' . $oauthUrl);
            
            // Test de connectivité basique
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Pour éviter les problèmes SSL en local
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            log::add('withings', 'debug', 'Test endpoint - Code HTTP: ' . $httpCode . ' | Erreur cURL: ' . $curlError);
            
            if ($httpCode == 200 || $httpCode == 400) { // 400 est normal sans paramètres
                log::add('withings', 'info', 'Test endpoints réussi');
                ajax::success("Endpoints OK - API: $apiUrl | OAuth: $oauthUrl");
            } else {
                throw new Exception("Erreur HTTP $httpCode pour $apiUrl" . ($curlError ? ' - ' . $curlError : ''));
            }
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur test endpoints: ' . $e->getMessage());
            throw new Exception('Erreur de test des endpoints: ' . $e->getMessage());
        }
    }

    if (init('action') == 'refreshCommands') {
        log::add('withings', 'debug', 'Action refreshCommands - ID équipement: ' . init('id'));
        
        $eqLogic = withings::byId(init('id'));
        if (!is_object($eqLogic)) {
            throw new Exception(__('Équipement non trouvé', __FILE__));
        }
        
        // Recréer les commandes
        $eqLogic->createCommands();
        
        ajax::success('Commandes actualisées');
    }

    if (init('action') == 'resetAuth') {
        log::add('withings', 'debug', 'Action resetAuth - ID équipement: ' . init('id'));
        
        $eqLogic = withings::byId(init('id'));
        if (!is_object($eqLogic)) {
            log::add('withings', 'error', 'Équipement non trouvé pour reset auth: ' . init('id'));
            throw new Exception(__('Équipement non trouvé', __FILE__));
        }
        
        $eqLogic->setConfiguration('access_token', '');
        $eqLogic->setConfiguration('refresh_token', '');
        $eqLogic->setConfiguration('oauth_state', '');
        $eqLogic->setConfiguration('token_expires', 0);
        $eqLogic->save();
        
        log::add('withings', 'info', 'Autorisation réinitialisée pour ' . $eqLogic->getHumanName());
        ajax::success('Autorisation réinitialisée');
    }

    log::add('withings', 'error', 'Action AJAX inconnue: ' . init('action'));
    throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
    
} catch (Exception $e) {
    log::add('withings', 'error', 'Erreur AJAX: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    ajax::error(displayException($e), $e->getCode());
}
