# Mosaik

Un gestionnaire de favoris simple, construit avec Symfony. On peut créer des dossiers et des sous-dossiers, enregistrer des liens avec un titre, puis les retrouver dans la bibliothèque ou dans leur dossier. Un lien peut aussi rester sans dossier.

Chaque dossier affiche ses **liens directs** et ses sous-dossiers séparément ; les compteurs des dossiers ne comprennent donc pas les liens de leurs descendants.

## Prérequis

- PHP 8.4 ou supérieur avec l’extension `pdo_mysql` ;
- un serveur MySQL et un compte ayant accès à une base pour Mosaik ;
- Composer ;
- Git, npm et Node.js 22.22.2+ (branche 22), 24.11+ (branche 24), ou 26+.

## Configuration de MySQL

Renseigner les identifiants de votre serveur dans `.env.local` (ce fichier est ignoré par Git) :

```dotenv
DATABASE_URL="mysql://UTILISATEUR:MOT_DE_PASSE@127.0.0.1:3306/mosaik?serverVersion=8.0.46&charset=utf8mb4"
```

Adapter `serverVersion` à la version de votre serveur. Encoder les caractères spéciaux des identifiants dans l’URL, par exemple `@` en `%40` et `#` en `%23`. La valeur dans `.env` est un exemple : le compte `app` et son mot de passe doivent être configurés ou remplacés. La base `mosaik` doit exister avant d’appliquer les migrations ; si le compte a le droit de créer une base, la commande `php bin/console doctrine:database:create --if-not-exists` peut s’en charger.

## Installation et démarrage

Depuis la racine du projet :

```bash
composer install
npm ci
npm run build
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8000 -t public
```

Ouvrir ensuite <http://127.0.0.1:8000>. Les données sont enregistrées dans la base MySQL configurée. L’ancien fichier SQLite `var/data_dev.db`, s’il existe, n’est ni supprimé ni importé automatiquement.

## Vite, Symfony Reprise et My UI

My UI est installée depuis le dépôt Git `https://github.com/UnknownChick/my-ui.git`, branche `beta`. Le fichier `package-lock.json` fige le commit installé : `npm ci` reproduit donc la même version, sans nécessiter de copie locale du dépôt ni de clé SSH pour ce dépôt public.

Les styles, tous les utilitaires natifs (y compris `row`, `position`, `opacity` et `prose`) et les composants JavaScript de My UI sont chargés. Vite fournit l’alias `@myui-scss` vers les sources Sass du paquet installé : il permet aussi d’utiliser ses mixins et modules internes, même si le paquet Git ne les exporte pas encore individuellement. Les thèmes clair et sombre suivent les préférences gérées par My UI ; un contrôle portant `data-theme-toggle` permet de changer de thème.

My UI définit deux échelles différentes pour les classes `gap-0` à `gap-5` : la grille historique `.row` utilise des valeurs en `rem` entiers, tandis que les autres éléments utilisent l’échelle d’espacement standard. Cette distinction est conservée pour éviter les conflits ; les autres classes d’espacement de `.row` restent synchronisées avec ses colonnes.

Le dossier `vendor` de My UI (Dropzone, Select2 et Toastr) n’est pas livré dans son paquet Git et dépend de bibliothèques tierces non déclarées. Il ne peut donc pas être inclus sans adapter le dépôt My UI lui-même.

Pour récupérer une nouvelle version de My UI après l’avoir poussée sur la branche `beta` :

```bash
npm update myui
npm run build
```

Cette commande met également à jour `package-lock.json`. Les modifications locales non poussées du dépôt My UI ne sont pas reprises automatiquement.

Pour profiter du rechargement à chaud pendant le développement, lancer Symfony et Vite dans deux terminaux :

```bash
php -S 127.0.0.1:8000 -t public
```

```bash
npm run dev
```

Symfony Reprise fournit les balises Twig et fait pointer les assets vers le serveur Vite. Sans serveur Vite, utiliser `npm run build` pour générer les fichiers dans `public/build`. Après l’arrêt du serveur Vite, relancer cette compilation afin de remplacer les références au serveur de développement. Après une nouvelle compilation en production, vider le cache Symfony pour actualiser les références des assets : `APP_ENV=prod php bin/console cache:clear`.

## Tests

```bash
vendor/bin/phpunit
npm run test:assets
```

Les tests fonctionnels créent une base MySQL temporaire distincte pour chaque test, appliquent les migrations puis la suppriment ; ils ne modifient pas la base de développement. Le compte utilisé doit disposer des droits nécessaires pour créer et supprimer ces bases. Pour utiliser un compte ou un serveur de test dédié, définir `TEST_DATABASE_URL` dans `.env.test.local` avec le même format que `DATABASE_URL`.

Cette première version ne comprend pas encore de modification ou de suppression, ni d’authentification : elle est destinée à un usage local ou à un environnement dont l’accès est déjà protégé.
