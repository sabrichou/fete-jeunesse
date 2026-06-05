<?php
// ================================================================
// API — Authentification Admin  /api/auth.php
// POST { username, password } → { success, token }
// ================================================================

require_once __DIR__ . '/../config/database.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, "Méthode non autorisée.");
}

$data     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$password) {
    http_response_code(400);
    jsonResponse(false, "Identifiants manquants.");
}

try {
    $pdo  = getDB();
} catch (Exception $e) {
    http_response_code(500);
    jsonResponse(false, "Erreur de connexion à la base de données. Vérifiez database.php.");
}

$stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
$stmt->execute([$username]);
$admin = $stmt->fetch();

// Vérification bcrypt
if (!$admin || !password_verify($password, $admin['password'])) {
    http_response_code(401);
    sleep(1); // Anti-brute-force
    jsonResponse(false, "Identifiants incorrects.");
}

// Token (en production, remplacer par JWT)
$token = 'JEUNESSE_ADMIN_2026_SECRET';

$_SESSION['admin_id']   = $admin['id'];
$_SESSION['admin_user'] = $admin['username'];

jsonResponse(true, "Connexion réussie.", [
    'token'    => $token,
    'username' => $admin['username'],
]);
?>
