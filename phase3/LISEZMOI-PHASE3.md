# Phase 3 — Stagiaires, lauréats, référentiels et importation Excel

## Installation (PowerShell, Windows)

1. Décompressez ce zip dans `D:\Projects\ofppt-app\phase3` (vous obtenez `phase3\backend-overlay`, `phase3\frontend`).
2. Copiez les fichiers dans le projet (le `routes\api.php` de la phase 2 est volontairement remplacé) :

```
cd D:\Projects\ofppt-app
xcopy /E /Y phase3\backend-overlay\* backend\
xcopy /E /Y phase3\frontend\* frontend\
```

3. Installez la bibliothèque Excel (lecture .xls/.xlsx et exports) :

```
cd backend
composer require phpoffice/phpspreadsheet
php artisan config:clear
```

Aucune nouvelle migration : les tables existent depuis la phase 2.

4. Dans le `php.ini` de Wamp (voir `php --ini`), vérifiez ces valeurs puis redémarrez Wamp :

```
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 512M
extension=zip
extension=gd
```

5. Relancez les deux serveurs (`php artisan serve` dans backend, `npm run dev` dans frontend).
   Aucune dépendance npm nouvelle.

## Ordre d'utilisation conseillé

1. Menu « Référentiels » (administrateur) : créez au minimum un établissement. Les niveaux existent déjà.
   Créez aussi filières, promotions, groupes, ou laissez l'importation les créer (option dédiée).
2. Menu « Importation Excel » : chargez votre fichier, associez les colonnes, contrôlez, importez.
3. Menu « Stagiaires » : recherche, filtres, fiche, modification, archivage, export Excel.
4. Fiche d'un stagiaire : bouton « Déclarer lauréat » (catégorie obligatoire).

## Tests automatiques

Avant `php artisan test`, vérifiez dans `backend\phpunit.xml` que `DB_CONNECTION` = `sqlite` et
`DB_DATABASE` = `:memory:` (lignes non commentées) et que l'extension `pdo_sqlite` est activée.
Sinon, les tests videraient votre base MySQL.

```
php artisan test
```

## Décisions techniques documentées

- Formats acceptés : .xls et .xlsx (pas de CSV : problèmes d'encodage des accents).
- Aucun nom de colonne n'est imposé. La pré-sélection des colonnes compare seulement les en-têtes à
  « Nom », « Prénom », « CEF »... ; l'utilisateur peut tout changer.
- Obligatoires : nom et prénom (imposés par la base) + l'établissement (colonne ou valeur par défaut).
  Tout autre champ est facultatif, sauf si l'utilisateur le coche.
- Catégorie absente : jamais déduite. Ligne importable avec avertissement, ou catégorie par défaut
  choisie explicitement par l'utilisateur.
- Doublons : CEF, n° d'inscription, matricule, ou nom + prénom + date de naissance (modifiable dans
  `config/import.php`). Mode « Ignorer » ou « Compléter les champs vides » : aucune valeur existante
  n'est jamais écrasée.
- Une inscription (niveau/filière/groupe/promotion/année) est une ligne d'historique : un changement
  en crée une nouvelle au lieu d'écraser l'ancienne.
- Les filtres niveau/filière/groupe/promotion s'appliquent à toutes les inscriptions du stagiaire.

## Limites connues

- Un fichier avec une seule colonne « Nom complet » n'est pas découpé automatiquement.
- Après correction manuelle d'une ligne, les doublons internes au fichier sont revérifiés au
  prochain « Relancer le contrôle » ; les doublons avec la base le sont immédiatement.
- Export PDF des listes : phase 4. Export Excel disponible dès maintenant.
- Les journaux d'audit sont enregistrés (création, archivage, changement de catégorie, import) mais
  n'ont pas encore d'écran de consultation.
