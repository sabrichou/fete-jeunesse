<?php
// ================================================================
// API REST — Inscriptions  /api/inscription.php
// ================================================================
// GET    → liste toutes les inscriptions (admin)
// POST   → soumet une nouvelle inscription
// DELETE → supprime une inscription (admin)
// ================================================================

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── POST : Nouvelle inscription ───────────────────────────────
if ($method === 'POST') {

    $data = json_decode(file_get_contents('php://input'), true);
    // Fallback $_POST si formulaire HTML classique
    if (empty($data)) $data = $_POST;

    // Validation
    $required = ['prenom', 'nom', 'age', 'email', 'region', 'activite'];
    $errors = [];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Le champ « $field » est obligatoire.";
        }
    }

    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email est invalide.";
    }
    if (!empty($data['age']) && ($data['age'] < 10 || $data['age'] > 35)) {
        $errors[] = "L'âge doit être compris entre 10 et 35 ans.";
    }

    if (!empty($errors)) {
        jsonResponse(false, implode(' ', $errors));
    }

    $pdo = getDB();

    // Vérifier doublon (même email + même activité)
    $stmt = $pdo->prepare("SELECT id FROM inscriptions WHERE email = ? AND activite = ?");
    $stmt->execute([$data['email'], $data['activite']]);
    if ($stmt->fetch()) {
        jsonResponse(false, "Vous êtes déjà inscrit(e) à cette activité avec cet email.");
    }

    // Vérifier places disponibles
    $stmt = $pdo->prepare("
        SELECT a.places_max, COUNT(i.id) AS inscrits
        FROM activites a
        LEFT JOIN inscriptions i ON i.activite = a.nom AND i.statut != 'annulé'
        WHERE a.nom = ?
        GROUP BY a.id
    ");
    $stmt->execute([$data['activite']]);
    $activite = $stmt->fetch();
    if ($activite && $activite['inscrits'] >= $activite['places_max']) {
        jsonResponse(false, "Désolé, cette activité est complète.");
    }

    // Insertion
    $stmt = $pdo->prepare("
        INSERT INTO inscriptions (prenom, nom, age, email, region, activite, message)
        VALUES (:prenom, :nom, :age, :email, :region, :activite, :message)
    ");
    $stmt->execute([
        ':prenom'   => htmlspecialchars(trim($data['prenom'])),
        ':nom'      => htmlspecialchars(trim($data['nom'])),
        ':age'      => (int) $data['age'],
        ':email'    => strtolower(trim($data['email'])),
        ':region'   => $data['region'],
        ':activite' => $data['activite'],
        ':message'  => htmlspecialchars(trim($data['message'] ?? '')),
    ]);

    $id = $pdo->lastInsertId();

    // Envoi d'email de confirmation
    envoyerEmailConfirmation($data);

    jsonResponse(true, "Inscription confirmée ! Bienvenue à la Fête de la Jeunesse 🎉", [
        'inscription_id' => $id,
        'prenom'         => $data['prenom'],
        'activite'       => $data['activite'],
    ]);
}

// ── GET : Lister les inscriptions (admin) ─────────────────────
if ($method === 'GET') {
    verifierAdmin();

    $pdo    = getDB();
    $where  = [];
    $params = [];

    // Filtres optionnels
    if (!empty($_GET['region'])) {
        $where[]  = 'region = ?';
        $params[] = $_GET['region'];
    }
    if (!empty($_GET['activite'])) {
        $where[]  = 'activite = ?';
        $params[] = $_GET['activite'];
    }
    if (!empty($_GET['statut'])) {
        $where[]  = 'statut = ?';
        $params[] = $_GET['statut'];
    }
    if (!empty($_GET['search'])) {
        $where[]  = '(prenom LIKE ? OR nom LIKE ? OR email LIKE ?)';
        $s = '%' . $_GET['search'] . '%';
        $params = array_merge($params, [$s, $s, $s]);
    }

    $sql = "SELECT * FROM inscriptions";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY created_at DESC";

    // Pagination
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Total
    $countStmt = $pdo->prepare(str_replace("SELECT *", "SELECT COUNT(*) AS total", $sql));
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    $sql .= " LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inscriptions = $stmt->fetchAll();

    // Stats globales
    $stats = getStats($pdo);

    jsonResponse(true, "OK", [
        'inscriptions' => $inscriptions,
        'pagination'   => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => ceil($total/$limit)],
        'stats'        => $stats,
    ]);
}

// ── DELETE : Supprimer une inscription ────────────────────────
if ($method === 'DELETE') {
    verifierAdmin();

    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);

    if (!$id) jsonResponse(false, "ID manquant.");

    $pdo  = getDB();
    $stmt = $pdo->prepare("DELETE FROM inscriptions WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(false, "Inscription introuvable.");
    }
    jsonResponse(true, "Inscription supprimée.");
}

// ── PATCH : Changer le statut d'une inscription ───────────────
if ($method === 'PATCH') {
    verifierAdmin();

    $data   = json_decode(file_get_contents('php://input'), true);
    $id     = (int)($data['id'] ?? 0);
    $statut = $data['statut'] ?? '';

    $statutsValides = ['en_attente', 'confirmé', 'annulé'];
    if (!$id || !in_array($statut, $statutsValides)) {
        jsonResponse(false, "Données invalides.");
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare("UPDATE inscriptions SET statut = ? WHERE id = ?");
    $stmt->execute([$statut, $id]);

    jsonResponse(true, "Statut mis à jour.", ['statut' => $statut]);
}

// ── Helpers ───────────────────────────────────────────────────

function getStats(PDO $pdo): array {
    $stats = [];

    // Total inscrits
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM inscriptions")->fetchColumn();

    // Par statut
    $stmt = $pdo->query("SELECT statut, COUNT(*) AS n FROM inscriptions GROUP BY statut");
    foreach ($stmt->fetchAll() as $row) {
        $stats['par_statut'][$row['statut']] = $row['n'];
    }

    // Par région
    $stmt = $pdo->query("SELECT region, COUNT(*) AS n FROM inscriptions GROUP BY region ORDER BY n DESC");
    $stats['par_region'] = $stmt->fetchAll();

    // Par activité
    $stmt = $pdo->query("SELECT activite, COUNT(*) AS n FROM inscriptions GROUP BY activite ORDER BY n DESC");
    $stats['par_activite'] = $stmt->fetchAll();

    return $stats;
}

function verifierAdmin(): void {
    // Token simple dans le header : Authorization: Bearer MON_TOKEN_SECRET
    $token  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $secret = 'JEUNESSE_ADMIN_2026_SECRET'; // À changer en production !
    if ($token !== "Bearer $secret") {
        http_response_code(401);
        jsonResponse(false, "Accès non autorisé.");
    }
}

function envoyerEmailConfirmation(array $data): void {
    $to      = $data['email'];
    $prenom  = $data['prenom'];
    $activite= $data['activite'];

    $subject = "=?UTF-8?b?" . base64_encode("✅ Inscription confirmée — Fête de la Jeunesse 11 Février") . "?=";
    $message = "
    <html><body style='font-family:Arial,sans-serif;background:#0A0A0A;color:#F5F0E8;padding:2rem;'>
      <div style='max-width:500px;margin:0 auto;border:1px solid #FECB00;padding:2rem;'>
        <h2 style='color:#FECB00;'>🎉 Inscription Confirmée !</h2>
        <p>Bonjour <strong>{$prenom}</strong>,</p>
        <p>Votre inscription à la <strong>Fête de la Jeunesse du 11 Février</strong> est bien enregistrée.</p>
        <div style='background:#1A1A1A;padding:1rem;margin:1rem 0;border-left:3px solid #E8002D;'>
          <strong>Activité :</strong> {$activite}<br>
          <strong>Date :</strong> 11 Février 2026<br>
          <strong>Région :</strong> {$data['region']}
        </div>
        <p style='color:#009A44;font-weight:bold;'>À très bientôt ! 🇨🇲</p>
      </div>
    </body></html>
    ";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Fête Jeunesse <noreply@fete-jeunesse.cm>\r\n";

    @mail($to, $subject, $message, $headers);
}
?>
