<?php

namespace App\Tests\Controller;

use App\Repository\BookmarkRepository;
use App\Repository\FolderRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class LibraryControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?Connection $serverConnection = null;
    private ?string $databaseName = null;
    private bool $databaseCreated = false;
    private string|false $previousDatabaseUrl;
    private ?string $previousEnvDatabaseUrl;
    private ?string $previousServerDatabaseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDatabaseUrl = getenv('DATABASE_URL');
        $this->previousEnvDatabaseUrl = $_ENV['DATABASE_URL'] ?? null;
        $this->previousServerDatabaseUrl = $_SERVER['DATABASE_URL'] ?? null;

        $testUrl = $_SERVER['TEST_DATABASE_URL'] ?? $_ENV['TEST_DATABASE_URL'] ?? (getenv('TEST_DATABASE_URL') ?: null);
        $baseUrl = $testUrl ?: ($this->previousServerDatabaseUrl ?? $this->previousEnvDatabaseUrl ?? $this->previousDatabaseUrl);

        if (!\is_string($baseUrl) || 'mysql' !== parse_url($baseUrl, PHP_URL_SCHEME)) {
            throw new \RuntimeException('Configurez une URL MySQL dans TEST_DATABASE_URL ou DATABASE_URL pour les tests.');
        }

        // A random database per test avoids touching the configured development database.
        $databasePrefix = 'mosaik_ft_'.bin2hex(random_bytes(8));
        $baseUrl = explode('#', $baseUrl, 2)[0];
        $url = $baseUrl.(str_contains($baseUrl, '?') ? '&' : '?').'dbname='.$databasePrefix;
        putenv('DATABASE_URL='.$url);
        $_ENV['DATABASE_URL'] = $url;
        $_SERVER['DATABASE_URL'] = $url;

        try {
            $this->client = static::createClient();
            $params = static::getContainer()->get('doctrine')->getConnection()->getParams();
            $this->databaseName = $params['dbname'] ?? null;

            // Doctrine appends its test suffix; only create and delete names we generated.
            if ('pdo_mysql' !== ($params['driver'] ?? null)
                || !\is_string($this->databaseName)
                || !preg_match('/\A'.preg_quote($databasePrefix, '/').'_test[A-Za-z0-9_]*\z/', $this->databaseName)
                || \strlen($this->databaseName) > 64) {
                throw new \RuntimeException('Nom ou connexion de base de test MySQL invalide.');
            }

            unset($params['dbname'], $params['dbname_suffix']);
            $this->serverConnection = DriverManager::getConnection($params);

            try {
                $this->serverConnection->createSchemaManager()->createDatabase($this->databaseName);
            } catch (\Throwable $exception) {
                throw new \RuntimeException('Impossible de créer la base de test MySQL : vérifiez TEST_DATABASE_URL et les droits CREATE/DROP.', 0, $exception);
            }
            $this->databaseCreated = true;

            $application = new Application(self::$kernel);
            $application->setAutoExit(false);
            $output = new BufferedOutput();
            $status = $application->run(new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
            ]), $output);

            self::assertSame(0, $status, $output->fetch());
        } catch (\Throwable $exception) {
            try {
                $this->cleanupDatabase();
            } finally {
                $this->restoreDatabaseUrl();
            }

            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupDatabase();
        } finally {
            $this->restoreDatabaseUrl();
            parent::tearDown();
        }
    }

    private function cleanupDatabase(): void
    {
        static::ensureKernelShutdown();

        if ($this->databaseCreated) {
            $this->serverConnection->createSchemaManager()->dropDatabase($this->databaseName);
            $this->databaseCreated = false;
        }

        $this->serverConnection?->close();
    }

    private function restoreDatabaseUrl(): void
    {
        if (false === $this->previousDatabaseUrl) {
            putenv('DATABASE_URL');
        } else {
            putenv('DATABASE_URL='.$this->previousDatabaseUrl);
        }

        if (null === $this->previousEnvDatabaseUrl) {
            unset($_ENV['DATABASE_URL']);
        } else {
            $_ENV['DATABASE_URL'] = $this->previousEnvDatabaseUrl;
        }

        if (null === $this->previousServerDatabaseUrl) {
            unset($_SERVER['DATABASE_URL']);
        } else {
            $_SERVER['DATABASE_URL'] = $this->previousServerDatabaseUrl;
        }
    }

    public function testEmptyLibraryHasNavigationAndCreationLinks(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tous les liens');
        self::assertSelectorTextContains('.empty-state', 'Tout commence par un lien');
        self::assertSelectorExists('a[href="/liens/nouveau"]');
        self::assertSelectorExists('a[href="/dossiers/nouveau"]');
        self::assertSelectorExists('a[href="/sans-dossier"]');
    }

    public function testNestedFoldersAndBookmarksCanBeCreatedAndListed(): void
    {
        $root = $this->createFolder('Guides');
        $child = $this->createFolder('Cuisine', $root);
        $grandchild = $this->createFolder('Végétarien', $child);

        $this->client->request('GET', '/liens/nouveau?folder='.$grandchild);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="bookmark[folder]"] option[value="'.$grandchild.'"][selected]');
        self::assertSelectorTextContains('select[name="bookmark[folder]"]', 'Guides / Cuisine / Végétarien');
        $this->client->submitForm('Enregistrer le lien', [
            'bookmark[url]' => 'https://example.com/recette?portions=2',
            'bookmark[title]' => 'Une recette',
        ]);
        self::assertResponseRedirects('/dossiers/'.$grandchild);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Votre lien a bien été enregistré.');

        $this->createBookmark('Un guide', 'https://example.org/guide', $root);
        $this->createBookmark('À lire', 'https://example.net/article');

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.bookmark-card');
        self::assertSelectorTextContains('.bookmark-list', 'Une recette');
        self::assertSelectorTextContains('.bookmark-list', 'Un guide');
        self::assertSelectorTextContains('.bookmark-list', 'À lire');
        self::assertSelectorExists('.bookmark-card[href="https://example.com/recette?portions=2"][target="_blank"][rel="noopener noreferrer"]');
        self::assertSelectorTextSame('a.sidebar-link[href="/"] .sidebar-link__count', '3');
        self::assertSelectorTextSame('a.sidebar-link[href="/sans-dossier"] .sidebar-link__count', '1');
        self::assertSelectorTextSame('a.sidebar-link[href="/dossiers/'.$child.'"] .sidebar-link__count', '0');
        self::assertSelectorTextSame('a.sidebar-link[href="/dossiers/'.$grandchild.'"] .sidebar-link__count', '1');

        $this->client->request('GET', '/dossiers/'.$child);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.folder-grid', 'Végétarien');
        self::assertSelectorTextContains('.empty-state', 'Ce dossier est encore vide');
        self::assertSelectorCount(0, '.bookmark-card');

        $this->client->request('GET', '/dossiers/'.$grandchild);
        self::assertSelectorCount(1, '.bookmark-card');
        self::assertSelectorTextContains('.bookmark-card', 'Une recette');
        self::assertSelectorExists('a[aria-current="page"][href="/dossiers/'.$grandchild.'"]');

        $this->client->request('GET', '/dossiers/'.$root);
        self::assertSelectorCount(1, '.bookmark-card');
        self::assertSelectorTextContains('.bookmark-card', 'Un guide');

        $this->client->request('GET', '/sans-dossier');
        self::assertSelectorCount(1, '.bookmark-card');
        self::assertSelectorTextContains('.bookmark-card', 'À lire');
    }

    public function testInvalidValuesAndUnknownFolderAreRejected(): void
    {
        $this->client->request('GET', '/dossiers/nouveau');
        $this->client->submitForm('Créer le dossier', ['folder[name]' => '   ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-field', 'Donnez un nom à ce dossier.');
        self::assertSelectorExists('input[name="folder[name]"][aria-invalid="true"]');
        self::assertSame(0, static::getContainer()->get(FolderRepository::class)->count([]));

        foreach (['javascript:alert(1)', 'ftp://example.com/file', 'https:///invalide'] as $url) {
            $this->client->request('GET', '/liens/nouveau');
            $this->client->submitForm('Enregistrer le lien', [
                'bookmark[url]' => $url,
                'bookmark[title]' => 'Lien invalide',
            ]);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('.form-field', 'Indiquez une adresse valide');
            self::assertSelectorExists('input[name="bookmark[url]"][aria-invalid="true"]');
        }

        $this->client->request('GET', '/liens/nouveau');
        $this->client->submitForm('Enregistrer le lien', [
            'bookmark[url]' => 'https://example.com',
            'bookmark[title]' => str_repeat('a', 256),
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('input[name="bookmark[title]"][aria-invalid="true"]');
        self::assertSelectorTextContains('[role="alert"]', 'Vérifiez les informations ci-dessous.');

        $crawler = $this->client->request('GET', '/liens/nouveau');
        $token = $crawler->filter('input[name="bookmark[_token]"]')->attr('value');
        $this->client->request('POST', '/liens/nouveau', ['bookmark' => [
            'url' => 'https://example.com',
            'title' => 'Dossier inexistant',
            'folder' => '999999',
            '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, static::getContainer()->get(BookmarkRepository::class)->count([]));

        $this->client->request('GET', '/liens/nouveau?folder=999999');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/dossiers/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCsrfAndHtmlEscaping(): void
    {
        $this->client->request('GET', '/liens/nouveau');
        $this->client->request('POST', '/liens/nouveau', ['bookmark' => [
            'url' => 'https://example.com',
            'title' => 'Ne doit pas être créé',
            'folder' => '',
            '_token' => 'incorrect',
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, static::getContainer()->get(BookmarkRepository::class)->count([]));

        $this->createBookmark('<script>alert(1)</script>', 'https://example.com');
        $this->client->request('GET', '/sans-dossier');
        self::assertSelectorTextContains('.bookmark-card strong', '<script>alert(1)</script>');
        self::assertSelectorNotExists('.bookmark-card script');
    }

    public function testMysqlForeignKeysAreEnforced(): void
    {
        $connection = static::getContainer()->get('doctrine')->getConnection();
        self::assertInstanceOf(AbstractMySQLPlatform::class, $connection->getDatabasePlatform());

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $connection->executeStatement('INSERT INTO folder (name, parent_id) VALUES (?, ?)', ['Orphelin', 999999]);
    }

    public function testMigrationCanBeRevertedAndAppliedAgain(): void
    {
        $root = $this->createFolder('Parent');
        $child = $this->createFolder('Enfant', $root);
        $this->createBookmark('Un lien', 'https://example.com', $child);

        $application = new Application($this->client->getKernel());
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $status = $application->run(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            'version' => 'prev',
            '--no-interaction' => true,
        ]), $output);
        self::assertSame(0, $status, $output->fetch());

        $connection = static::getContainer()->get('doctrine')->getConnection();
        self::assertFalse($connection->createSchemaManager()->tablesExist(['folder', 'bookmark']));

        // A new kernel mirrors running each migration command in a separate process.
        $kernel = $this->client->getKernel();
        $kernel->shutdown();
        $kernel->boot();
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $status = $application->run(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
        ]), $output);
        self::assertSame(0, $status, $output->fetch());
        $connection = $kernel->getContainer()->get('doctrine')->getConnection();
        self::assertTrue($connection->createSchemaManager()->tablesExist(['folder', 'bookmark']));
    }

    private function createFolder(string $name, ?int $parent = null): int
    {
        $url = '/dossiers/nouveau'.(null !== $parent ? '?parent='.$parent : '');
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        if (null !== $parent) {
            self::assertSelectorExists('select[name="folder[parent]"] option[value="'.$parent.'"][selected]');
        }

        $this->client->submitForm('Créer le dossier', ['folder[name]' => $name]);
        self::assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/dossiers/(\d+)$#', $location);
        preg_match('#/dossiers/(\d+)$#', $location, $matches);

        return (int) $matches[1];
    }

    private function createBookmark(string $title, string $url, ?int $folder = null): void
    {
        $path = '/liens/nouveau'.(null !== $folder ? '?folder='.$folder : '');
        $this->client->request('GET', $path);
        $this->client->submitForm('Enregistrer le lien', [
            'bookmark[url]' => $url,
            'bookmark[title]' => $title,
        ]);
        self::assertResponseRedirects(null !== $folder ? '/dossiers/'.$folder : '/sans-dossier');
    }
}
