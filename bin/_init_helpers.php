<?php

declare(strict_types=1);

/**
 * Helpers partagés pour les scripts bin/init-cagrille.
 * À inclure depuis la racine du projet.
 */

// ── Chargement de l'environnement ────────────────────────────────────────────

function loadDotEnv(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Crée et retourne une connexion PDO à partir de DATABASE_URL (.env / .env.local).
 */
function createPdo(): PDO
{
    loadDotEnv(__DIR__ . '/../.env');
    loadDotEnv(__DIR__ . '/../.env.local');

    $rawUrlEnv = $_ENV['DATABASE_URL'] ?? null;
    $rawUrl = is_string($rawUrlEnv) ? $rawUrlEnv : (getenv('DATABASE_URL') ?: '');
    if ($rawUrl === '') {
        fwrite(STDERR, "[ERR] DATABASE_URL introuvable dans les fichiers .env.\n");
        exit(1);
    }

    $appEnvEnv = $_ENV['APP_ENV'] ?? null;
    $appEnv = is_string($appEnvEnv) ? $appEnvEnv : (getenv('APP_ENV') ?: 'prod');
    $rawUrl = str_replace('%kernel.environment%', $appEnv, $rawUrl);

    $parsed = parse_url($rawUrl);
    if (
        $parsed === false
        || !is_string($parsed['host'] ?? null)
        || !is_string($parsed['path'] ?? null)
    ) {
        fwrite(STDERR, "[ERR] DATABASE_URL mal formée : $rawUrl\n");
        exit(1);
    }

    /** @var array{host: string, path: string, port?: int, user?: string, pass?: string} $parsed */
    $dbHost = $parsed['host'];
    $dbPort = $parsed['port'] ?? 3306;
    $dbName = (string) strtok(ltrim($parsed['path'], '/'), '?');
    $dbUser = isset($parsed['user']) ? urldecode($parsed['user']) : 'root';
    $dbPass = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';

    try {
        return new PDO(
            "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        fwrite(STDERR, "[ERR] Connexion PDO impossible : " . $e->getMessage() . "\n");
        exit(1);
    }
}

// ── Helpers idempotents ───────────────────────────────────────────────────────

/**
 * Retourne l'id si $uniqueCol=$value existe, sinon insère et retourne le nouvel id.
 *
 * @param array<mixed> $insertParams
 */
function find_or_create(
    PDO    $pdo,
    string $table,
    string $uniqueCol,
    string $value,
    string $insertSql,
    array  $insertParams,
    bool   &$created = false,
): int {
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE $uniqueCol = ? LIMIT 1");
    $stmt->execute([$value]);
    /** @var array{id: string}|false $row */
    $row = $stmt->fetch();
    if ($row !== false) {
        $created = false;
        return (int) $row['id'];
    }
    $pdo->prepare($insertSql)->execute($insertParams);
    $created = true;
    return (int) $pdo->lastInsertId();
}

/**
 * Récupère l'id en base, lève une RuntimeException si absent.
 */
function fetch_id(PDO $pdo, string $table, string $column, string $value): int
{
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE $column = ? LIMIT 1");
    $stmt->execute([$value]);
    /** @var array{id: string}|false $row */
    $row = $stmt->fetch();
    if ($row === false) {
        throw new RuntimeException("Introuvable : $table.$column='$value'");
    }
    return (int) $row['id'];
}

/**
 * INSERT IGNORE sur une table pivot (clé composite).
 * Retourne true si inséré, false si la ligne existait déjà.
 */
function insert_pivot(PDO $pdo, string $table, string $col1, int $val1, string $col2, int $val2): bool
{
    $stmt = $pdo->prepare("INSERT IGNORE INTO $table ($col1, $col2) VALUES (?, ?)");
    $stmt->execute([$val1, $val2]);
    return $stmt->rowCount() > 0;
}

/**
 * Vérifie si un membre de zone (code + zone_id) existe déjà.
 */
function zone_member_exists(PDO $pdo, string $code, int $zoneId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM sylius_zone_member WHERE code = ? AND belongs_to = ? LIMIT 1"
    );
    $stmt->execute([$code, $zoneId]);
    return (bool) $stmt->fetch();
}

/**
 * Vérifie si une traduction de taxon (taxon_id + locale) existe déjà.
 */
function taxon_translation_exists(PDO $pdo, int $taxonId, string $locale): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM sylius_taxon_translation WHERE translatable_id = ? AND locale = ? LIMIT 1"
    );
    $stmt->execute([$taxonId, $locale]);
    return (bool) $stmt->fetch();
}
