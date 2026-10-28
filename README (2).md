# Application OFPPT — Phase 2 (initialisation)

Contenu de ce dossier :

- `backend-overlay/` : fichiers à copier PAR-DESSUS une installation Laravel neuve (migrations, seeders, authentification, rôles, tableau de bord, tests).
- `frontend/` : projet React + Vite complet (connexion, menu, tableau de bord, pages réservées aux phases suivantes).

## Prérequis (Windows)

- PHP 8.2 ou plus, avec extensions `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd` (Laragon ou XAMPP les fournissent)
- Composer 2
- Node.js 20 ou plus
- MySQL 8 ou MariaDB 10.6+

## Installation du backend

Ouvrez un terminal dans le dossier `ofppt-app` :

```
composer create-project laravel/laravel backend
cd backend
php artisan install:api
```

Si `install:api` propose d'exécuter les migrations, répondez Non (la base n'est pas encore configurée).

```
cd ..
xcopy /E /Y backend-overlay\* backend\
cd backend
copy .env.example .env
```

Éditez `backend\.env` : renseignez `DB_USERNAME`, `DB_PASSWORD`, `ADMIN_EMAIL` et `ADMIN_PASSWORD` (mot de passe d'au moins 12 caractères, à vous). Créez la base `ofppt_gestion` (utf8mb4_unicode_ci), puis :

```
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

L'API tourne sur http://127.0.0.1:8000.

## Installation du frontend

Dans un second terminal :

```
cd frontend
npm install
npm run dev
```

Ouvrez http://localhost:5173 et connectez-vous avec `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
Utilisez `localhost` (et non 127.0.0.1) dans le navigateur, pour que les cookies de session fonctionnent.

## Tests backend

ATTENTION : les tests utilisent `RefreshDatabase`, qui vide la base configurée. Avant de lancer
`php artisan test`, ouvrez `backend\phpunit.xml` et vérifiez que `DB_CONNECTION` vaut `sqlite` et
`DB_DATABASE` vaut `:memory:` (lignes `<env>` non commentées). Sinon vos données MySQL seraient effacées.

## État

Non encore exécuté dans un environnement Laravel réel : voir la liste « À vérifier » communiquée avec ce livrable.
