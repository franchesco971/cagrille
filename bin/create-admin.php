<?php

declare(strict_types=1);

/**
 * Crée (ou ignore) le compte administrateur Sylius.
 *
 * Usage : php bin/create-admin.php <email> <username> <prenom> <nom> <motdepasse>
 */

require __DIR__ . '/_init_helpers.php';

if ($argc < 6) {
    fwrite(STDERR, "Usage: create-admin.php <email> <username> <prenom> <nom> <motdepasse>\n");
    exit(1);
}

[, $email, $username, $first, $last, $password] = $argv;

$pdo = createPdo();
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("SELECT id FROM sylius_admin_user WHERE email_canonical = ? LIMIT 1");
$stmt->execute([strtolower($email)]);
if ($stmt->fetch()) {
    echo "  ⚠ Administrateur déjà existant : $email — ignoré.\n";
    exit(0);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]);

$pdo->prepare("
    INSERT INTO sylius_admin_user
        (username, username_canonical, email, email_canonical,
         password, enabled, first_name, last_name,
         roles, locale_code, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, 'fr_FR', ?, ?)
")->execute([
    $username, strtolower($username),
    $email,    strtolower($email),
    $hash,
    $first, $last,
    json_encode(['ROLE_ADMINISTRATION_ACCESS']),
    $now, $now,
]);

echo "  ✔ Administrateur créé : $email (username: $username)\n";
