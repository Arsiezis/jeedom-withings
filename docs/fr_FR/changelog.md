# Changelog plugin Withings

>**IMPORTANT**
>
>S'il n'y a pas d'information sur la mise à jour, c'est que celle-ci concerne uniquement de la mise à jour de documentation, de traduction ou de texte.

# 01/08/2025

## Version 1.0.0 - Première release

### Fonctionnalités
- **Authentification OAuth 2.0** avec Withings
- **Récupération automatique** des données de santé
- **Support complet** de toutes les métriques des balances Withings :
  - Poids (kg)
  - IMC (Indice de Masse Corporelle)
  - Masse grasse (kg et %)
  - Masse musculaire (kg)
  - Masse osseuse (kg)
  - Masse maigre (kg)
  - Hydratation (%)
  - Fréquence cardiaque (bpm)

### Interface utilisateur
- **Interface intuitive** pour la configuration
- **Boutons d'autorisation** et test de connexion
- **Synchronisation manuelle** et automatique
- **Affichage des dernières mesures** dans l'interface
- **Gestion complète des commandes** Jeedom

### Automatisation
- **Synchronisation automatique** toutes les heures
- **Historisation automatique** des données
- **Gestion des tokens** OAuth avec renouvellement automatique
- **Gestion d'erreurs** robuste avec logs détaillés

### Compatibilité
- **Toutes les balances Withings** : Body, Body+, Body Cardio, Body Comp, Body Scan
- **Jeedom 4.2+** requis
- **Support multi-utilisateurs** avec équipements séparés

### Sécurité
- **Chiffrement des tokens** d'accès
- **Validation des états OAuth** pour éviter les attaques
- **Gestion sécurisée** des secrets client

### Documentation
- **Guide complet** d'installation et configuration
- **Documentation API** Withings Developer
- **Exemples de scénarios** d'utilisation
- **Dépannage** et résolution des erreurs courantes
