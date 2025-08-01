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

### Synchronisation des données

- **Synchronisation automatique** : Si activée, les données sont récupérées automatiquement toutes les heures
- **Synchronisation manuelle** : Cliquez sur **Synchroniser maintenant** pour forcer une synchronisation

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
- Refaites l'autorisation Withings

**"Aucune donnée récupérée"**
- Vérifiez que votre balance a bien synchronisé récemment
- Lancez une synchronisation manuellement

**"Token expiré"**
- Le plugin renouvelle automatiquement les tokens
- Si le problème persiste, réinitialisez l'autorisation

### Logs

Les logs du plugin sont disponibles dans **Analyse > Logs** > `withings`

### Support

Pour obtenir de l'aide :
- Consultez le forum Jeedom
- Ouvrez un ticket sur GitHub
- Vérifiez la documentation Withings Developer

## Changelog

Voir le [changelog](changelog.md) pour l'historique des versions.

## Crédits

- Plugin développé pour la communauté Jeedom
- Utilise l'API officielle Withings
- Compatible avec OAuth 2.0
