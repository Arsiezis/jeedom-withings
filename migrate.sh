#!/bin/bash

# Script de migration corrigé - A utiliser APRÈS avoir copié manuellement le code
echo "========================================="
echo "Migration finale template → withings"
echo "========================================="

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "plugin_info/info.json" ]; then
    echo "Erreur: Ce script doit être exécuté depuis la racine du plugin"
    exit 1
fi

echo "1. Renommage des fichiers template → withings..."

# Renommer SEULEMENT les fichiers qui existent encore avec 'template' dans le nom
if [ -f "core/class/template.class.php" ]; then
    mv "core/class/template.class.php" "core/class/withings.class.php"
    echo "   ✓ template.class.php → withings.class.php"
fi

if [ -f "core/ajax/template.ajax.php" ]; then
    mv "core/ajax/template.ajax.php" "core/ajax/withings.ajax.php"
    echo "   ✓ template.ajax.php → withings.ajax.php"
fi

if [ -f "desktop/php/template.php" ]; then
    mv "desktop/php/template.php" "desktop/php/withings.php"
    echo "   ✓ template.php → withings.php"
fi

if [ -f "desktop/js/template.js" ]; then
    mv "desktop/js/template.js" "desktop/js/withings.js"
    echo "   ✓ template.js → withings.js"
fi

if [ -f "core/php/template.inc.php" ]; then
    mv "core/php/template.inc.php" "core/php/withings.inc.php"
    echo "   ✓ template.inc.php → withings.inc.php"
fi

echo "2. Suppression des dossiers inutiles..."

# Supprimer les dossiers/fichiers template non nécessaires
if [ -d "resources" ]; then
    rm -rf resources/
    echo "   ✓ Dossier resources supprimé (pas de démon)"
fi

if [ -f "plugin_info/helperConfiguration.php" ]; then
    rm -f plugin_info/helperConfiguration.php
    echo "   ✓ helperConfiguration.php supprimé"
fi

if [ -d "core/template" ]; then
    rm -rf core/template/
    echo "   ✓ Dossier core/template supprimé"
fi

if [ -f "plugin_info/packages.json" ]; then
    rm -f plugin_info/packages.json
    echo "   ✓ packages.json supprimé (pas de dépendances)"
fi

if [ -f "plugin_info/pre_install.php" ]; then
    rm -f plugin_info/pre_install.php
    echo "   ✓ pre_install.php supprimé (pas nécessaire)"
fi

echo "3. Vérification des fichiers modifiés..."

# Vérifier que les fichiers principaux existent
files_to_check=(
    "core/class/withings.class.php"
    "core/ajax/withings.ajax.php" 
    "desktop/php/withings.php"
    "desktop/js/withings.js"
    "plugin_info/info.json"
    "plugin_info/configuration.php"
    "plugin_info/install.php"
    "desktop/css/withings.css"
    "docs/fr_FR/index.md"
)

all_good=true
for file in "${files_to_check[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✓ $file"
    else
        echo "   ❌ MANQUANT: $file"
        all_good=false
    fi
done

echo "4. Configuration des permissions..."
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type f -name "*.js" -exec chmod 644 {} \;
find . -type f -name "*.json" -exec chmod 644 {} \;
find . -type f -name "*.md" -exec chmod 644 {} \;
find . -type f -name "*.css" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
echo "   ✓ Permissions configurées"

echo "========================================="
if [ "$all_good" = true ]; then
    echo "✅ Migration terminée avec succès!"
    echo ""
    echo "Prochaines étapes:"
    echo "1. Testez le plugin dans Jeedom"
    echo "2. Configurez vos clés API Withings"
    echo "3. Créez un commit Git si tout fonctionne"
else
    echo "❌ Migration incomplète - Vérifiez les fichiers manquants ci-dessus"
fi
echo "========================================="
