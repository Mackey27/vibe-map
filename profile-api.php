<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function profile_send_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$createTableSql = "
CREATE TABLE IF NOT EXISTS user_profiles (
    username VARCHAR(100) NOT NULL PRIMARY KEY,
    avatar LONGTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (!mysqli_query($conn, $createTableSql)) {
    profile_send_json(['ok' => false, 'error' => 'storage_unavailable'], 500);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $result = mysqli_query($conn, "SELECT username, avatar FROM user_profiles");
    if (!$result) {
        profile_send_json(['ok' => false, 'error' => 'query_failed'], 500);
    }

    $profiles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $username = isset($row['username']) ? trim((string) $row['username']) : '';
        if ($username === '') {
            continue;
        }

        $avatar = isset($row['avatar']) && $row['avatar'] !== '' ? (string) $row['avatar'] : null;
        $profiles[$username] = ['avatar' => $avatar];
    }

    profile_send_json(['ok' => true, 'profiles' => $profiles]);
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    profile_send_json(['ok' => false, 'error' => 'invalid_payload'], 400);
}

if ($method === 'POST') {
    $username = trim((string) ($payload['username'] ?? ''));
    $avatar = isset($payload['avatar']) && is_string($payload['avatar']) && $payload['avatar'] !== ''
        ? $payload['avatar']
        : null;
    $maxAvatarBytes = 850000;

    if ($username === '') {
        profile_send_json(['ok' => false, 'error' => 'invalid_username'], 400);
    }

    if ($avatar !== null && strlen($avatar) > $maxAvatarBytes) {
        profile_send_json(['ok' => false, 'error' => 'avatar_too_large'], 413);
    }

    if ($avatar === null) {
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM user_profiles WHERE username = ? LIMIT 1");
        if (!$deleteStmt) {
            profile_send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
        }
        mysqli_stmt_bind_param($deleteStmt, 's', $username);
        if (!mysqli_stmt_execute($deleteStmt)) {
            mysqli_stmt_close($deleteStmt);
            profile_send_json(['ok' => false, 'error' => 'delete_failed'], 500);
        }
        mysqli_stmt_close($deleteStmt);
        profile_send_json(['ok' => true]);
    }

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO user_profiles (username, avatar)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE avatar = VALUES(avatar)"
    );
    if (!$stmt) {
        profile_send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }

    mysqli_stmt_bind_param($stmt, 'ss', $username, $avatar);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        profile_send_json(['ok' => false, 'error' => 'save_failed'], 500);
    }
    mysqli_stmt_close($stmt);

    profile_send_json(['ok' => true]);
}

profile_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
