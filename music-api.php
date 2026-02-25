<?php
header('Content-Type: application/json; charset=utf-8');

function music_send_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function music_strlen(string $value): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value);
    }
    return strlen($value);
}

function music_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return (string) mb_strtolower($value);
    }
    return strtolower($value);
}

function music_fetch_remote_json(string $url): ?array
{
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'VibeMap/1.0 (+http://localhost/Vibe-Map)',
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($response) || $statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        $raw = $response;
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => "User-Agent: VibeMap/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || $response === '') {
            return null;
        }

        $raw = $response;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    music_send_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$query = trim((string) ($_GET['q'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 8);
if ($limit < 1) {
    $limit = 1;
}
if ($limit > 20) {
    $limit = 20;
}

if ($query === '') {
    music_send_json(['ok' => true, 'tracks' => []]);
}

if (music_strlen($query) < 2) {
    music_send_json(['ok' => true, 'tracks' => []]);
}

$params = http_build_query([
    'term' => $query,
    'country' => 'US',
    'media' => 'music',
    'entity' => 'song',
    'limit' => $limit,
]);

$remote = music_fetch_remote_json('https://itunes.apple.com/search?' . $params);
if (!is_array($remote) || !isset($remote['results']) || !is_array($remote['results'])) {
    music_send_json(['ok' => false, 'error' => 'music_provider_unavailable'], 502);
}

$tracks = [];
$seen = [];
foreach ($remote['results'] as $item) {
    if (!is_array($item)) {
        continue;
    }

    $title = isset($item['trackName']) ? trim((string) $item['trackName']) : '';
    $artist = isset($item['artistName']) ? trim((string) $item['artistName']) : '';
    $previewUrl = isset($item['previewUrl']) ? trim((string) $item['previewUrl']) : '';
    if ($previewUrl !== '' && !preg_match('/^https?:\\/\\//i', $previewUrl)) {
        $previewUrl = '';
    }
    if ($title === '') {
        continue;
    }

    $key = music_lower($title . '|' . $artist);
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;

    $track = [
        'title' => $title,
        'artist' => $artist,
    ];
    if ($previewUrl !== '') {
        $track['previewUrl'] = $previewUrl;
    }
    $tracks[] = $track;

    if (count($tracks) >= $limit) {
        break;
    }
}

music_send_json([
    'ok' => true,
    'tracks' => $tracks,
    'source' => 'itunes',
]);
