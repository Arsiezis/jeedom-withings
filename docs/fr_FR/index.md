# Plugin Withings

Ce plugin permet de récupérer les données de santé depuis vos balances connectées Withings et de les intégrer dans Jeedom.

## Fonctionnalités

Le plugin Withings récupère automatiquement les données suivantes depuis votre balance connectée :

- **Poids** (en kg)
- **IMC** (Indice de Masse Corporelle)
- **Masse grasse** (en kg et en %)
- **Masse musculaire** (en kg)
- **Masse osseuse** (en kg)
- **Masse maigre** (en kg)
- **Hydratation** (en %)
- **Fréquence cardiaque** (si supportée par votre balance)

## Prérequis

### Matériel supporté

Le plugin est compatible avec toutes les balances Withings connectées :
- Body
- Body+
- Body Cardio
- Body Comp
- Body Scan

### Configuration Withings Developer

Avant d'utiliser le plugin, vous devez créer une application sur le portail développeur Withings :

1. Rendez-vous sur [le portail développeur Withings](https://developer.withings.com/dashboard/)
2. Créez un compte développeur si vous n'en avez pas
3. Créez une nouvelle application
4. Configurez les paramètres suivants :
   - **Nom de l'application** : Jeedom (ou le nom de votre choix)
   - **Description** : Integration Jeedom pour récupération des données de santé
   - **URL de redirection** : `http://votre-jeedom.local/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback`
   - **Scopes requis** : `user.info`, `user.metrics`, `user.activity`

## Installation

1. Installez le plugin depuis le Market Jeedom
2. Activez le plugin dans la page de gestion des plugins
3. Configurez les paramètres globaux du plugin

## Configuration

### Configuration générale

Dans **Plugins > Santé > Withings**, cliquez sur **Configuration** :

1. **Client ID** : Saisissez le Client ID de votre application Withings Developer
2. **Client Secret** : Saisissez le Client Secret de votre application Withings Developer
3. **URL de redirection** : Copiez cette URL dans votre application Withings Developer
4. **Synchronisation automatique** : Active la synchronisation automatique toutes les heures
5. **Historisation automatique** : Active l'historisation automatique des nouvelles commandes

### Ajout d'un équipement

1. Cliquez sur **Ajouter** pour créer un nouvel équipement balance
2. Donnez un nom à votre balance (ex: "Balance Salon")
3. Configurez l'objet parent et les options classiques Jeedom
4. **Sauvegardez** l'équipement

### Authentification Withings

Une fois l'équipement créé et sauvegardé :

1. Cliquez sur **Autoriser l'accès Withings**
2. Une fenêtre popup s'ouvre vers le site Withings
3. Connectez-vous avec votre compte Withings
4. Autorisez l'application à accéder à vos données
5. La fenêtre se ferme automatiquement après autorisation
6. Testez la connexion avec le bouton **Tester la connexion**

### Description détaillée des boutons

#### 🔑 **Autoriser l'accès Withings**
- **Fonction** : Initie le processus d'authentification OAuth avec Withings
- **Quand l'utiliser** : 
  - Première configuration d'un équipement
  - Après une réinitialisation
  - Si les tokens sont corrompus ou perdus
- **Ce qu'il fait** :
  - Ouvre une popup vers le site Withings
  - Permet de se connecter avec vos identifiants Withings
  - Obtient les tokens OAuth (access_token + refresh_token)
  - Sauvegarde les tokens chiffrés dans la configuration

#### 🔍 **Tester la connexion**
- **Fonction** : Vérifie que la connexion avec l'API Withings est fonctionnelle
- **Quand l'utiliser** :
  - Pour diagnostiquer des problèmes de connexion
  - Pour vérifier l'état du token
  - Après une autorisation pour confirmer que tout fonctionne
- **Ce qu'il fait** :
  - Vérifie la validité du token d'accès
  - Fait un appel test à l'API Withings
  - Renouvelle automatiquement le token s'il expire dans moins de 30 minutes
  - Affiche l'état du token (valide, expire bientôt, expiré)

#### 🗑️ **Réinitialiser**
- **Fonction** : Efface complètement l'autorisation OAuth
- **Quand l'utiliser** :
  - En cas de problème majeur d'authentification
  - Si vous voulez changer de compte Withings
  - Avant de désinstaller le plugin
- **Ce qu'il fait** :
  - Supprime tous les tokens stockés
  - Efface les dates de création/renouvellement
  - Remet l'équipement dans l'état "non autorisé"
  - Nécessite de refaire "Autoriser l'accès Withings"

#### 🔄 **Renouveler le token**
- **Fonction** : Force le renouvellement du token d'accès
- **Quand l'utiliser** :
  - Si vous recevez des erreurs "token expiré"
  - Pour forcer un renouvellement manuel
  - En cas de problème avec le renouvellement automatique
- **Ce qu'il fait** :
  - Utilise le refresh_token pour obtenir un nouveau access_token
  - Met à jour la date d'expiration
  - Les tokens Withings expirent toutes les 3 heures
- **Note** : Le renouvellement est normalement automatique

#### 📊 **Synchroniser maintenant**
- **Fonction** : Récupère les dernières données depuis Withings
- **Quand l'utiliser** :
  - Après vous être pesé
  - Pour forcer une mise à jour des données
  - Pour tester que la récupération fonctionne
- **Ce qu'il fait** :
  - Récupère les mesures des 30 derniers jours
  - Met à jour toutes les commandes (poids, IMC, etc.)
  - Actualise la date de dernière synchronisation
  - Historise les nouvelles valeurs

#### 🌐 **Tester les endpoints**
- **Fonction** : Vérifie la connectivité réseau vers les serveurs Withings
- **Quand l'utiliser** :
  - Si vous avez des problèmes de connexion persistants
  - Pour diagnostiquer des problèmes réseau/firewall
  - Pour vérifier que les URLs de l'API sont accessibles
- **Ce qu'il fait** :
  - Teste la connectivité vers l'API Withings
  - Teste la connectivité vers le serveur OAuth
  - Ne nécessite pas de token d'authentification
  - Affiche les URLs testées et leur statut

### Synchronisation des données

- **Synchronisation automatique** : Si activée, les données sont récupérées automatiquement toutes les heures
- **Synchronisation manuelle** : Cliquez sur **Synchroniser maintenant** pour forcer une synchronisation
- **Période de récupération** : Par défaut, le plugin récupère les données des 30 derniers jours

## Utilisation

### Commandes disponibles

Chaque équipement balance dispose des commandes suivantes :

| Commande | Type | Unité | Description |
|----------|------|-------|-------------|
| Poids | Info/Numérique | kg | Poids corporel |
| IMC | Info/Numérique | - | Indice de Masse Corporelle |
| Masse grasse (%) | Info/Numérique | % | Pourcentage de masse grasse |
| Masse grasse | Info/Numérique | kg | Masse grasse en kilogrammes |
| Masse musculaire | Info/Numérique | kg | Masse musculaire |
| Masse osseuse | Info/Numérique | kg | Masse osseuse |
| Masse maigre | Info/Numérique | kg | Masse maigre (sans graisse) |
| Hydratation | Info/Numérique | % | Pourcentage d'hydratation |
| Fréquence cardiaque | Info/Numérique | bpm | Rythme cardiaque |
| Dernière synchronisation | Info/String | - | Date/heure de dernière sync |
| Synchroniser | Action/Autre | - | Déclenche une synchronisation |

### Scénarios et automatisations

Vous pouvez utiliser les commandes dans vos scénarios pour :

- **Suivi de poids** : Créer des alertes si le poids dépasse certains seuils
- **Notifications** : Envoyer une notification après chaque pesée
- **Historiques** : Suivre l'évolution de votre composition corporelle
- **Dashboard** : Afficher vos métriques de santé sur le dashboard

Exemple de scénario :
```
SI [Balance Salon][Poids] > 80 
ALORS Envoyer notification "Attention au poids !"
```

### Widget et affichage

Les données sont automatiquement historisées et peuvent être affichées :
- Sur le dashboard Jeedom
- Dans des graphiques d'évolution
- Via l'application mobile Jeedom

## Dépannage

### Erreurs courantes

**"401 - Accès non autorisé"**
- Vérifiez que le Client ID et Client Secret sont corrects
- Refaites l'autorisation Withings avec le bouton "Autoriser l'accès"

**"Aucune donnée récupérée"**
- Vérifiez que votre balance a bien synchronisé récemment avec l'app Withings
- Lancez une synchronisation manuellement
- Vérifiez la période de récupération dans la configuration

**"Token expiré"**
- Le plugin renouvelle automatiquement les tokens
- Si le problème persiste, utilisez "Renouveler le token"
- En dernier recours, utilisez "Réinitialiser" puis refaites l'autorisation

**"Erreur de déchiffrement"**
- Les tokens ont été chiffrés avec une ancienne clé
- Utilisez "Réinitialiser" puis "Autoriser l'accès" pour recréer les tokens

### Ordre de diagnostic recommandé

1. **Tester les endpoints** : Vérifier la connectivité réseau
2. **Tester la connexion** : Vérifier l'authentification
3. **Renouveler le token** : Si le token semble expiré
4. **Réinitialiser + Autoriser** : Si rien ne fonctionne

### Logs

Les logs du plugin sont disponibles dans **Analyse > Logs** > `withings`

Niveaux de log :
- **Info** : Opérations normales (synchronisation, connexion)
- **Warning** : Avertissements (token expire bientôt)
- **Error** : Erreurs (échec de connexion, token invalide)
- **Debug** : Informations détaillées (activé dans la configuration)

### Support

Pour obtenir de l'aide :
- Consultez le forum Jeedom
- Ouvrez un ticket sur GitHub
- Vérifiez la documentation Withings Developer

## Sécurité

Le plugin implémente plusieurs mesures de sécurité :

- **Chiffrement** : Tous les tokens sont chiffrés avec AES-256-GCM
- **Protection CSRF** : Protection contre les attaques de falsification de requête
- **Rate limiting** : Limitation du nombre de requêtes pour éviter les abus
- **Validation** : Toutes les entrées utilisateur sont validées
- **Logs sécurisés** : Les données sensibles sont anonymisées dans les logs

## Changelog

Voir le [changelog](changelog.md) pour l'historique des versions.

## Crédits

- Plugin développé pour la communauté Jeedom
- Utilise l'API officielle Withings
- Compatible avec OAuth 2.0
