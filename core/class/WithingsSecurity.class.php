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

/**
 * Gestionnaire de sécurité pour le plugin Withings
 * Gère le chiffrement des secrets, CSRF, rate limiting et logging sécurisé
 */
class WithingsSecurity {
    
    const SECRET_KEY_FILE = '/var/withings_secret.key';
    const RATE_LIMIT_FILE = '/tmp/withings_rate_limit.json';
    
    private static $rateLimits = [
        'oauth' => ['requests' => 5, 'window' => 300],     // 5 tentatives OAuth en 5 min
        'api' => ['requests' => 100, 'window' => 3600],    // 100 requêtes API par heure
        'admin' => ['requests' => 1000, 'window' => 3600]  // Limite élevée pour admins
    ];
    
    /**
     * Gestion sécurisée des secrets
     */
    private static function getSecretKey() {
        $keyFile = __DIR__ . self::SECRET_KEY_FILE;
        
        if (!file_exists($keyFile)) {
            // Créer le répertoire si nécessaire
            $dir = dirname($keyFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            
            // Générer une nouvelle clé
            if (function_exists('random_bytes')) {
                $key = bin2hex(random_bytes(32));
            } else {
                $key = hash('sha256', uniqid(mt_rand(), true) . microtime());
            }
            
            if (file_put_contents($keyFile, $key, LOCK_EX) === false) {
                throw new Exception('Impossible de créer le fichier de clé de chiffrement');
            }
            
            chmod($keyFile, 0600); // Lecture seule pour le propriétaire
            log::add('withings', 'info', 'Nouvelle clé de chiffrement des secrets générée');
        }
        
        $key = file_get_contents($keyFile);
        if ($key === false) {
            throw new Exception('Impossible de lire la clé de chiffrement');
        }
        
        return $key;
    }
    
    public static function encryptSecret($secret) {
        if (empty($secret)) {
            return '';
        }
        
        try {
            $key = self::getSecretKey();
            
            if (function_exists('random_bytes')) {
                $iv = random_bytes(16);
            } else {
                $iv = openssl_random_pseudo_bytes(16);
            }
            
            $encrypted = openssl_encrypt($secret, 'AES-256-GCM', $key, 0, $iv, $tag);
            
            if ($encrypted === false) {
                throw new Exception('Erreur de chiffrement');
            }
            
            return base64_encode($iv . $tag . $encrypted);
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur chiffrement secret: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public static function decryptSecret($encryptedSecret) {
        if (empty($encryptedSecret)) {
            return '';
        }
        
        try {
            $key = self::getSecretKey();
            $data = base64_decode($encryptedSecret);
            
            if ($data === false || strlen($data) < 32) {
                throw new Exception('Données chiffrées invalides');
            }
            
            $iv = substr($data, 0, 16);
            $tag = substr($data, 16, 16);
            $encrypted = substr($data, 32);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-GCM', $key, 0, $iv, $tag);
            
            if ($decrypted === false) {
                throw new Exception('Erreur de déchiffrement');
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            log::add('withings', 'error', 'Erreur déchiffrement secret: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Protection CSRF
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (function_exists('random_bytes')) {
            $token = bin2hex(random_bytes(32));
        } else {
            $token = hash('sha256', uniqid(mt_rand(), true) . microtime());
        }
        
        $_SESSION['withings_csrf_token'] = $token;
        $_SESSION['withings_csrf_time'] = time();
        
        return $token;
    }
    
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Vérifier l'existence et la validité temporelle (10 minutes max)
        if (!isset($_SESSION['withings_csrf_token']) || 
            !isset($_SESSION['withings_csrf_time']) ||
            time() - $_SESSION['withings_csrf_time'] > 600) {
            return false;
        }
        
        // Comparaison sécurisée
        $isValid = hash_equals($_SESSION['withings_csrf_token'], $token);
        
        // Nettoyer le token après utilisation
        if ($isValid) {
            unset($_SESSION['withings_csrf_token']);
            unset($_SESSION['withings_csrf_time']);
        }
        
        return $isValid;
    }
    
    /**
     * Rate Limiting avancé
     */
    public static function checkRateLimit($ip, $type = 'api', $userId = null) {
        // Utiliser Redis si disponible, sinon fallback sur fichiers
        if (class_exists('Redis')) {
            return self::checkRateLimitRedis($ip, $type, $userId);
        } else {
            return self::checkRateLimitFile($ip, $type, $userId);
        }
    }
    
    private static function checkRateLimitRedis($ip, $type, $userId) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            
            $limit = self::$rateLimits[$type] ?? self::$rateLimits['api'];
            $key = "withings_rate_limit:{$type}:{$ip}";
            
            // Utiliser une limite plus élevée pour les admins connectés
            if ($userId && $type !== 'oauth') {
                $limit = self::$rateLimits['admin'];
                $key = "withings_rate_limit:admin:{$userId}";
            }
            
            $current = $redis->incr($key);
            
            if ($current === 1) {
                $redis->expire($key, $limit['window']);
            }
            
            if ($current > $limit['requests']) {
                $ttl = $redis->ttl($key);
                throw new Exception("Limite de débit atteinte. Réessayez dans {$ttl} secondes.");
            }
            
            return true;
            
        } catch (Exception $e) {
            // Fallback sur fichiers si Redis échoue
            log::add('withings', 'warning', 'Redis indisponible, fallback sur fichiers: ' . $e->getMessage());
            return self::checkRateLimitFile($ip, $type, $userId);
        }
    }
    
    private static function checkRateLimitFile($ip, $type, $userId) {
        $file = self::RATE_LIMIT_FILE;
        $lockFile = $file . '.lock';
        
        $lock = fopen($lockFile, 'w');
        if (!$lock) {
            throw new Exception('Impossible de créer le fichier de verrou');
        }
        
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new Exception('Impossible d\'acquérir le verrou de rate limiting');
        }
        
        try {
            $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            if (!is_array($data)) $data = [];
            
            $now = time();
            $limit = self::$rateLimits[$type] ?? self::$rateLimits['api'];
            $key = "{$type}_{$ip}";
            
            if ($userId && $type !== 'oauth') {
                $limit = self::$rateLimits['admin'];
                $key = "admin_{$userId}";
            }
            
            // Nettoyer les anciennes entrées
            $data = array_filter($data, function($entry) use ($now, $limit) {
                return $entry['time'] >= ($now - $limit['window'] * 2);
            });
            
            // Compter les requêtes dans la fenêtre
            $count = 0;
            foreach ($data as $entry) {
                if ($entry['key'] === $key && $entry['time'] > ($now - $limit['window'])) {
                    $count++;
                }
            }
            
            if ($count >= $limit['requests']) {
                throw new Exception('Limite de débit atteinte');
            }
            
            // Ajouter l'entrée actuelle
            $data[] = ['key' => $key, 'time' => $now];
            file_put_contents($file, json_encode($data), LOCK_EX);
            
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        
        return true;
    }
    
    /**
     * Validation renforcée des URLs
     */
    public static function validateURL($url, $type) {
        $allowedHosts = [
            'api' => ['wbsapi.withings.net'],
            'oauth' => ['account.withings.com']
        ];
        
        $parsed = parse_url($url);
        
        if (!$parsed) {
            throw new Exception('URL malformée');
        }
        
        // HTTPS obligatoire
        if ($parsed['scheme'] !== 'https') {
            throw new Exception('HTTPS requis');
        }
        
        // Port par défaut uniquement
        if (isset($parsed['port']) && $parsed['port'] !== 443) {
            throw new Exception('Port non autorisé');
        }
        
        // Vérifier le domaine
        if (!isset($allowedHosts[$type]) || !in_array($parsed['host'], $allowedHosts[$type])) {
            throw new Exception('Domaine non autorisé: ' . $parsed['host']);
        }
        
        // Pas de query string ou fragment suspects
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            foreach ($params as $key => $value) {
                if (preg_match('/[<>&"\']/', $key . $value)) {
                    throw new Exception('Paramètres suspects dans l\'URL');
                }
            }
        }
        
        return true;
    }
    
    /**
     * Logging sécurisé
     */
    public static function logAction($action, $details = [], $level = 'info') {
        // Anonymiser les données sensibles
        $sanitized = self::sanitizeLogData($details);
        
        $logEntry = [
            'action' => $action,
            'user_id' => isset($_SESSION['user']) ? $_SESSION['user']->getId() : 'anonymous',
            'ip' => self::getClientIP(),
            'details' => $sanitized
        ];
        
        log::add('withings', $level, json_encode($logEntry));
    }
    
    private static function sanitizeLogData($data) {
        $sensitive = ['token', 'secret', 'password', 'key', 'auth', 'code'];
        
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);
            
            foreach ($sensitive as $keyword) {
                if (stripos($keyLower, $keyword) !== false) {
                    if (is_string($value) && strlen($value) > 8) {
                        $data[$key] = substr($value, 0, 4) . '***' . substr($value, -4);
                    } else {
                        $data[$key] = '***';
                    }
                    break;
                }
            }
            
            // Récursif pour les sous-tableaux
            if (is_array($value)) {
                $data[$key] = self::sanitizeLogData($value);
            }
        }
        
        return $data;
    }
    
    public static function getClientIP() {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Validation des données d'entrée
     */
    public static function validateInput($input, $type) {
        switch ($type) {
            case 'oauth_code':
                return preg_match('/^[a-zA-Z0-9_-]+$/', $input) && strlen($input) > 10 && strlen($input) < 200;
                
            case 'oauth_state':
                return preg_match('/^[a-f0-9_]+$/', $input) && strlen($input) > 20;
                
            case 'client_id':
                return preg_match('/^[a-zA-Z0-9_-]+$/', $input) && strlen($input) > 10 && strlen($input) < 100;
                
            case 'access_token':
                return preg_match('/^[a-zA-Z0-9_.-]+$/', $input) && strlen($input) > 20 && strlen($input) < 500;
                
            default:
                return false;
        }
    }
    
    /**
     * Génération sécurisée d'états OAuth (simplifiée pour compatibilité)
     */
    public static function generateSecureState($equipmentId) {
        if (function_exists('random_bytes')) {
            $random = bin2hex(random_bytes(16));
        } else {
            $random = md5(uniqid(mt_rand(), true));
        }
        
        $timestamp = time();
        // État simple mais sécurisé : random_timestamp
        $state = $random . '_' . $timestamp;
        
        return $state;
    }
    
    /**
     * Validation des états OAuth (simplifiée)
     */
    public static function validateOAuthState($state, $equipmentId, $maxAge = 3600) {
        if (empty($state) || !preg_match('/^[a-f0-9_]+$/', $state)) {
            return false;
        }
        
        $parts = explode('_', $state);
        if (count($parts) < 2) {
            return false;
        }
        
        $timestamp = (int)$parts[1];
        
        // Vérifier l'âge (1 heure max)
        if (time() - $timestamp > $maxAge) {
            return false;
        }
        
        return true;
    }
}
?>
