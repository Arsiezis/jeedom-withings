# Plugin Withings pour Jeedom

Plugin permettant d'intégrer les données de vos balances connectées Withings dans Jeedom.

## 🏥 Fonctionnalités

- **Récupération automatique** des données de santé depuis l'API Withings
- **Support complet** de toutes les métriques des balances Withings
- **Authentification OAuth 2.0** sécurisée
- **Synchronisation programmée** (automatique et manuelle)
- **Historisation** automatique des données
- **Interface intuitive** pour la configuration

## 📊 Données récupérées

- Poids (kg)
- IMC (Indice de Masse Corporelle)  
- Masse grasse (kg et %)
- Masse musculaire (kg)
- Masse osseuse (kg)
- Masse maigre (kg)
- Hydratation (%)
- Fréquence cardiaque (bpm, si supportée)

## 🔧 Installation

### Depuis le Market Jeedom (recommandé)
1. Allez dans **Plugins > Gestion des plugins**
2. Recherchez "Withings"
3. Installez et activez le plugin

### Installation manuelle
1. Téléchargez le plugin depuis GitHub
2. Décompressez dans `/var/www/html/plugins/withings/`
3. Activez le plugin dans **Plugins > Gestion des plugins**

## ⚙️ Configuration

### 1. Créer une application Withings Developer

1. Rendez-vous sur [Withings Developer Portal](https://developer.withings.com/dashboard/)
2. Créez un compte développeur
3. Créez une nouvelle application avec les paramètres :
   - **Nom** : Jeedom Integration
   - **Description** : Récupération données de santé pour Jeedom
   - **Callback URL** : `http://votre-jeedom/plugins/withings/core/ajax/withings.ajax.php?action=oauth_callback`
   - **Scopes** : `user.info`, `user.metrics`, `user.activity`

### 2. Configurer le plugin

1. Allez dans **Plugins > Santé > Withings > Configuration**
2. Saisissez votre **Client ID** et **Client Secret** Withings
3. Sauvegardez la configuration

### 3. Ajouter un équipement

1. Cliquez sur **Ajouter** pour créer une nouvelle balance
2. Donnez un nom à votre balance
3. **Sauvegardez** l'équipement
4. Cliquez sur **Autoriser l'accès Withings**
5. Autorisez l'application dans la popup Withings
6. Testez la connexion

## 🚀 Utilisation

### Synchronisation
- **Automatique** : Toutes les heures (configurable)
- **Manuelle** : Bouton "Synchroniser maintenant"

### Commandes disponibles
Chaque balance dispose des commandes suivantes :

| Commande | Type | Unité | Description |
|----------|------|-------|-------------|
| Poids | Info | kg | Poids corporel |
| IMC | Info | - | Indice de Masse Corporelle |
| Masse grasse (%) | Info | % | Pourcentage de graisse |
| Masse grasse | Info | kg | Masse grasse absolue |
| Masse musculaire | Info | kg | Masse musculaire |
| Masse osseuse | Info | kg | Masse osseuse |
| Hydratation | Info | % | Niveau d'hydratation |
| Synchroniser | Action | - | Synchronisation manuelle |

### Scénarios
Exemples d'utilisation dans les scénarios :

```php
// Alerte si poids trop élevé
SI [Balance][Poids] > 80 
ALORS Notification "Attention au poids!"

// Suivi quotidien
SI Programmation = "Tous les jours à 08h00"
ALORS [Balance][Synchroniser]
```

## 🛠️ Développement

### Structure du plugin
```
withings/
├── core/
│   ├── ajax/withings.ajax.php      # Requêtes AJAX
│   ├── class/withings.class.php    # Classe principale
│   └── php/withings.inc.php        # Includes
├── desktop/
│   ├── js/withings.js              # JavaScript interface
│   ├── php/withings.php            # Interface utilisateur
│   └── modal/                      # Modales
├── docs/                           # Documentation
├── plugin_info/
│   ├── info.json                   # Métadonnées plugin
│   ├── install.php                 # Scripts installation
│   └── configuration.php          # Configuration globale
└── README.md
```

### API Withings utilisée
- **Authentification** : OAuth 2.0
- **Endpoint principal** : `https://wbsapi.withings.net/v2/`
- **Données** : Mesures corporelles via `measure?action=getmeas`

### Contribuer
1. Forkez le repository
2. Créez une branche feature (`git checkout -b feature/amelioration`)
3. Committez vos changements (`git commit -am 'Ajout fonctionnalité'`)
4. Pushez sur la branche (`git push origin feature/amelioration`)
5. Créez une Pull Request

## 🐛 Dépannage

### Erreurs courantes

**"401 - Accès non autorisé"**
- Vérifiez Client ID/Secret dans la configuration
- Refaites l'autorisation OAuth

**"Aucune donnée récupérée"**
- Vérifiez que la balance a synchronisé récemment
- Testez la connexion API

**"Token expiré"**
- Le renouvellement est automatique
- En cas de problème, réinitialisez l'autorisation

### Logs
Consultez les logs dans **Analyse > Logs > withings**

### Support
- [Forum Jeedom](https://community.jeedom.com/)
- [Issues GitHub](https://github.com/votre-repo/jeedom-withings/issues)
- [Documentation Jeedom](https://doc.jeedom.com/fr_FR/dev/)

## 📋 Compatibilité

### Balances supportées
- Body
- Body+
- Body Cardio
- Body Comp
- Body Scan

### Versions Jeedom
- **Minimum** : Jeedom 4.2
- **Recommandé** : Jeedom 4.4+
- **Testé sur** : Atlas, Luna, Smart

## 📄 Licence

Ce plugin est distribué sous licence [AGPL v3](LICENSE).

## 🙏 Remerciements

- Équipe Jeedom pour le framework
- Withings pour l'API publique
- Communauté Jeedom pour les tests et retours

---

**Made with ❤️ for the Jeedom community**
