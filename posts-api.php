<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
const MAX_PHOTO_DATA_LENGTH = 700000;
const POST_TTL_MS = 86400000; // 24 hours

function send_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function current_timestamp_ms(): int
{
    return (int) round(microtime(true) * 1000);
}

function normalize_viewer_key(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        return (string) mb_strtolower($trimmed);
    }
    return strtolower($trimmed);
}

function purge_expired_posts(mysqli $conn, int $cutoffTimestampMs): bool
{
    $cutoffString = (string) max(0, $cutoffTimestampMs);
    $stmt = mysqli_prepare($conn, "DELETE FROM map_posts WHERE post_timestamp > 0 AND post_timestamp < ?");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $cutoffString);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function purge_orphan_post_views(mysqli $conn): bool
{
    return (bool) mysqli_query(
        $conn,
        "DELETE pv
         FROM map_post_views pv
         LEFT JOIN map_posts p ON p.post_id = pv.post_id
         WHERE p.post_id IS NULL"
    );
}

$createTableSql = "
CREATE TABLE IF NOT EXISTS map_posts (
    username VARCHAR(100) NOT NULL PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL DEFAULT '',
    post_id VARCHAR(64) NOT NULL,
    lat DOUBLE NOT NULL,
    lng DOUBLE NOT NULL,
    note TEXT NOT NULL,
    photo LONGTEXT NULL,
    music_json LONGTEXT NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    post_timestamp BIGINT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (!mysqli_query($conn, $createTableSql)) {
    send_json(['ok' => false, 'error' => 'storage_unavailable'], 500);
}

$checkFullNameColumnResult = mysqli_query($conn, "SHOW COLUMNS FROM map_posts LIKE 'full_name'");
if ($checkFullNameColumnResult === false) {
    send_json(['ok' => false, 'error' => 'storage_unavailable'], 500);
}
if (mysqli_num_rows($checkFullNameColumnResult) === 0) {
    if (!mysqli_query($conn, "ALTER TABLE map_posts ADD COLUMN full_name VARCHAR(150) NOT NULL DEFAULT '' AFTER username")) {
        mysqli_free_result($checkFullNameColumnResult);
        send_json(['ok' => false, 'error' => 'storage_unavailable'], 500);
    }
}
mysqli_free_result($checkFullNameColumnResult);

$createViewsTableSql = "
CREATE TABLE IF NOT EXISTS map_post_views (
    post_id VARCHAR(64) NOT NULL,
    viewer_key VARCHAR(100) NOT NULL,
    viewed_at BIGINT NOT NULL,
    PRIMARY KEY (post_id, viewer_key),
    KEY idx_post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (!mysqli_query($conn, $createViewsTableSql)) {
    send_json(['ok' => false, 'error' => 'storage_unavailable'], 500);
}

$checkViewCountColumnResult = mysqli_query($conn, "SHOW COLUMNS FROM map_posts LIKE 'view_count'");
if ($checkViewCountColumnResult === false) {
    send_json(['ok' => false, 'error' => 'storage_unavailable'], 500);
}
if (mysqli_num_rows($checkViewCountColumnResult) === 0) {
    if (!mysqli_query($conn, "ALTER TABLE map_posts ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER music_json")) {
        mysqli_free_result($checkViewCountColumnResult);
        send_json(['ok' => false, 'error' => 'storage_unavailable'], 500);
    }
}
mysqli_free_result($checkViewCountColumnResult);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$nowTimestampMs = current_timestamp_ms();
$postExpiryCutoffMs = $nowTimestampMs - POST_TTL_MS;

if (!purge_expired_posts($conn, $postExpiryCutoffMs)) {
    send_json(['ok' => false, 'error' => 'cleanup_failed'], 500);
}
if (!purge_orphan_post_views($conn)) {
    send_json(['ok' => false, 'error' => 'cleanup_failed'], 500);
}

if ($method === 'GET') {
    $cutoffSql = (string) max(0, $postExpiryCutoffMs);
    $result = mysqli_query(
        $conn,
        "SELECT username, full_name, post_id, lat, lng, note, photo, music_json, view_count, post_timestamp
         FROM map_posts
         WHERE post_timestamp >= {$cutoffSql}
         ORDER BY post_timestamp DESC"
    );

    if (!$result) {
        send_json(['ok' => false, 'error' => 'query_failed'], 500);
    }

    $posts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $music = null;
        if (isset($row['music_json']) && $row['music_json'] !== null && $row['music_json'] !== '') {
            $decodedMusic = json_decode($row['music_json'], true);
            if (is_array($decodedMusic)) {
                $music = $decodedMusic;
            }
        }

        $posts[] = [
            'id' => (string) ($row['post_id'] ?? ''),
            'username' => (string) ($row['username'] ?? ''),
            'fullName' => (string) ($row['full_name'] ?? ''),
            'lat' => (float) ($row['lat'] ?? 0),
            'lng' => (float) ($row['lng'] ?? 0),
            'note' => (string) ($row['note'] ?? ''),
            'photo' => isset($row['photo']) && $row['photo'] !== '' ? (string) $row['photo'] : null,
            'music' => $music,
            'views' => (int) ($row['view_count'] ?? 0),
            'timestamp' => (int) ($row['post_timestamp'] ?? 0),
        ];
    }

    send_json(['ok' => true, 'posts' => $posts]);
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
$rawBody = file_get_contents('php://input');
if (($method === 'POST' || $method === 'DELETE' || $method === 'PATCH') && $contentLength > 0 && (!is_string($rawBody) || $rawBody === '')) {
    send_json(['ok' => false, 'error' => 'payload_too_large'], 413);
}
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    send_json(['ok' => false, 'error' => 'invalid_payload'], 400);
}

if ($method === 'POST') {
    $username = trim((string) ($payload['username'] ?? ''));
    $postId = trim((string) ($payload['id'] ?? ''));
    $lat = (float) ($payload['lat'] ?? NAN);
    $lng = (float) ($payload['lng'] ?? NAN);

    if ($username === '' || $postId === '' || !is_finite($lat) || !is_finite($lng)) {
        send_json(['ok' => false, 'error' => 'invalid_post_data'], 400);
    }

    $note = isset($payload['note']) && is_string($payload['note']) ? $payload['note'] : '';
    $fullName = isset($payload['fullName']) && is_string($payload['fullName'])
        ? trim($payload['fullName'])
        : '';
    if ($fullName === '') {
        $fullName = $username;
    }
    $fullNameLength = function_exists('mb_strlen') ? mb_strlen($fullName) : strlen($fullName);
    if ($fullNameLength > 150) {
        $fullName = function_exists('mb_substr') ? mb_substr($fullName, 0, 150) : substr($fullName, 0, 150);
    }
    $photo = isset($payload['photo']) && is_string($payload['photo']) && $payload['photo'] !== ''
        ? $payload['photo']
        : null;
    if ($photo !== null && strlen($photo) > MAX_PHOTO_DATA_LENGTH) {
        send_json(['ok' => false, 'error' => 'photo_too_large'], 413);
    }
    $music = isset($payload['music']) && is_array($payload['music']) ? $payload['music'] : null;
    $musicJson = $music ? json_encode($music, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $timestamp = (int) ($payload['timestamp'] ?? round(microtime(true) * 1000));
    if ($timestamp <= 0) {
        $timestamp = $nowTimestampMs;
    }
    if ($timestamp < $postExpiryCutoffMs) {
        $timestamp = $nowTimestampMs;
    }
    $timestampString = (string) $timestamp;

    mysqli_begin_transaction($conn);

    $findStmt = mysqli_prepare($conn, "SELECT post_id FROM map_posts WHERE username = ? LIMIT 1");
    if (!$findStmt) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }
    mysqli_stmt_bind_param($findStmt, 's', $username);
    mysqli_stmt_execute($findStmt);
    mysqli_stmt_bind_result($findStmt, $existingPostId);
    $hadPreviousPost = mysqli_stmt_fetch($findStmt);
    $replacedPostId = $hadPreviousPost ? (string) $existingPostId : null;
    mysqli_stmt_close($findStmt);

    $deleteStmt = mysqli_prepare($conn, "DELETE FROM map_posts WHERE username = ?");
    if (!$deleteStmt) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }
    mysqli_stmt_bind_param($deleteStmt, 's', $username);
    if (!mysqli_stmt_execute($deleteStmt)) {
        mysqli_stmt_close($deleteStmt);
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'delete_failed'], 500);
    }
    mysqli_stmt_close($deleteStmt);

    if ($hadPreviousPost && $replacedPostId !== null && $replacedPostId !== '') {
        $deleteViewsStmt = mysqli_prepare($conn, "DELETE FROM map_post_views WHERE post_id = ?");
        if (!$deleteViewsStmt) {
            mysqli_rollback($conn);
            send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
        }
        mysqli_stmt_bind_param($deleteViewsStmt, 's', $replacedPostId);
        if (!mysqli_stmt_execute($deleteViewsStmt)) {
            mysqli_stmt_close($deleteViewsStmt);
            mysqli_rollback($conn);
            send_json(['ok' => false, 'error' => 'delete_failed'], 500);
        }
        mysqli_stmt_close($deleteViewsStmt);
    }

    $insertStmt = mysqli_prepare(
        $conn,
        "INSERT INTO map_posts (username, full_name, post_id, lat, lng, note, photo, music_json, post_timestamp)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$insertStmt) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }
    mysqli_stmt_bind_param(
        $insertStmt,
        'sssddssss',
        $username,
        $fullName,
        $postId,
        $lat,
        $lng,
        $note,
        $photo,
        $musicJson,
        $timestampString
    );
    if (!mysqli_stmt_execute($insertStmt)) {
        $stmtErrno = mysqli_stmt_errno($insertStmt);
        $connErrno = mysqli_errno($conn);
        mysqli_stmt_close($insertStmt);
        mysqli_rollback($conn);
        if (
            in_array($stmtErrno, [1153, 1406, 2006, 2013], true) ||
            in_array($connErrno, [1153, 1406, 2006, 2013], true)
        ) {
            send_json(['ok' => false, 'error' => 'payload_too_large'], 413);
        }
        send_json(['ok' => false, 'error' => 'save_failed'], 500);
    }
    mysqli_stmt_close($insertStmt);

    mysqli_commit($conn);
    send_json([
        'ok' => true,
        'replaced' => $hadPreviousPost ? true : false,
        'replaced_post_id' => $replacedPostId,
    ]);
}

if ($method === 'PATCH') {
    $postId = trim((string) ($payload['id'] ?? ''));
    $viewerKey = normalize_viewer_key((string) ($payload['viewer'] ?? ''));
    if ($postId === '' || $viewerKey === '') {
        send_json(['ok' => false, 'error' => 'invalid_view_data'], 400);
    }

    mysqli_begin_transaction($conn);

    $postExistsStmt = mysqli_prepare($conn, "SELECT post_id FROM map_posts WHERE post_id = ? LIMIT 1");
    if (!$postExistsStmt) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }
    mysqli_stmt_bind_param($postExistsStmt, 's', $postId);
    mysqli_stmt_execute($postExistsStmt);
    mysqli_stmt_bind_result($postExistsStmt, $foundPostId);
    $postFound = mysqli_stmt_fetch($postExistsStmt);
    mysqli_stmt_close($postExistsStmt);
    if (!$postFound) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'post_not_found'], 404);
    }

    $viewedAtString = (string) $nowTimestampMs;
    $insertViewStmt = mysqli_prepare(
        $conn,
        "INSERT IGNORE INTO map_post_views (post_id, viewer_key, viewed_at) VALUES (?, ?, ?)"
    );
    if (!$insertViewStmt) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }
    mysqli_stmt_bind_param($insertViewStmt, 'sss', $postId, $viewerKey, $viewedAtString);
    if (!mysqli_stmt_execute($insertViewStmt)) {
        mysqli_stmt_close($insertViewStmt);
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'update_failed'], 500);
    }
    $isNewUniqueView = mysqli_stmt_affected_rows($insertViewStmt) > 0;
    mysqli_stmt_close($insertViewStmt);

    if ($isNewUniqueView) {
        $updateStmt = mysqli_prepare(
            $conn,
            "UPDATE map_posts
             SET view_count = CASE WHEN view_count < 2147483647 THEN view_count + 1 ELSE view_count END
             WHERE post_id = ?
             LIMIT 1"
        );
        if (!$updateStmt) {
            mysqli_rollback($conn);
            send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
        }

        mysqli_stmt_bind_param($updateStmt, 's', $postId);
        if (!mysqli_stmt_execute($updateStmt)) {
            mysqli_stmt_close($updateStmt);
            mysqli_rollback($conn);
            send_json(['ok' => false, 'error' => 'update_failed'], 500);
        }
        mysqli_stmt_close($updateStmt);
    }

    $selectStmt = mysqli_prepare($conn, "SELECT view_count FROM map_posts WHERE post_id = ? LIMIT 1");
    if (!$selectStmt) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }
    mysqli_stmt_bind_param($selectStmt, 's', $postId);
    mysqli_stmt_execute($selectStmt);
    mysqli_stmt_bind_result($selectStmt, $viewCountValue);
    $hasRow = mysqli_stmt_fetch($selectStmt);
    mysqli_stmt_close($selectStmt);

    if (!$hasRow) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'post_not_found'], 404);
    }

    mysqli_commit($conn);
    send_json([
        'ok' => true,
        'id' => $postId,
        'views' => (int) $viewCountValue,
        'new_view' => $isNewUniqueView,
    ]);
}

if ($method === 'DELETE') {
    $username = trim((string) ($payload['username'] ?? ''));
    $postId = trim((string) ($payload['id'] ?? ''));

    if ($username === '' || $postId === '') {
        send_json(['ok' => false, 'error' => 'invalid_delete_data'], 400);
    }

    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, "DELETE FROM map_posts WHERE post_id = ? AND username = ? LIMIT 1");
    if (!$stmt) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }

    mysqli_stmt_bind_param($stmt, 'ss', $postId, $username);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'delete_failed'], 500);
    }
    mysqli_stmt_close($stmt);

    $deleteViewsStmt = mysqli_prepare($conn, "DELETE FROM map_post_views WHERE post_id = ?");
    if (!$deleteViewsStmt) {
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'prepare_failed'], 500);
    }
    mysqli_stmt_bind_param($deleteViewsStmt, 's', $postId);
    if (!mysqli_stmt_execute($deleteViewsStmt)) {
        mysqli_stmt_close($deleteViewsStmt);
        mysqli_rollback($conn);
        send_json(['ok' => false, 'error' => 'delete_failed'], 500);
    }
    mysqli_stmt_close($deleteViewsStmt);

    mysqli_commit($conn);
    send_json(['ok' => true]);
}

send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
