<?php
// ================================================================
// CONFIG BASE DE DONNÉES — Fête de la Jeunesse 11 Février
// ================================================================
// Modifiez ces valeurs selon votre hébergement

define('DB_HOST', 'localhost');
define('DB_NAME', 'fete_jeunesse');
define('DB_USER', 'root');       // Votre utilisateur MySQL
define('DB_PASS', '');           // Votre mot de passe MySQL
define('DB_CHARSET', 'utf8mb4');

// ── Connexion PDO ──────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Erreur de connexion BDD : ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

// ── Réponse JSON helper ────────────────────────────────────────
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ── CORS (autoriser requêtes frontend + header Authorization) ──
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
?>
