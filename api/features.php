<?php
declare(strict_types=1);

require __DIR__ . '/session.php';
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        gis_send(200, gis_collection(gis_pdo()));
    }
    if ($method === 'POST') {
        gis_require_token();
        $feature = gis_insert(gis_pdo(), gis_body());
        gis_send(201, $feature);
    }
    if ($method === 'DELETE') {
        gis_require_token();
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) {
            gis_send(400, ['error' => 'Choose a saved shape to remove.']);
        }
        gis_delete(gis_pdo(), $id);
        gis_send(200, ['ok' => true, 'id' => $id]);
    }
    header('Allow: GET, POST, DELETE');
    gis_send(405, ['error' => 'This address loads, saves, and removes shapes.']);
} catch (PDOException $e) {
    gis_send(500, ['error' => 'The database did not answer.']);
} catch (RuntimeException $e) {
    gis_send(400, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    gis_send(500, ['error' => 'The database did not answer.']);
}

function gis_require_token(): void
{
    if (!gis_token_ok()) {
        gis_send(403, ['error' => 'Reload the map, then try that again.']);
    }
}

function gis_send(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gis_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 65536) {
        throw new RuntimeException('That shape is too large to save.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('The shape was not valid JSON.');
    }
    return $data;
}

function gis_collection(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT id, kind, name, player, geojson, updated_at
         FROM features
         ORDER BY id
         LIMIT 500'
    )->fetchAll();

    $features = [];
    foreach ($rows as $row) {
        $feature = gis_feature_from_row($row);
        if ($feature !== null) {
            $features[] = $feature;
        }
    }

    return [
        'type' => 'FeatureCollection',
        'features' => $features,
    ];
}

function gis_insert(PDO $pdo, array $input): array
{
    $kind = (string) ($input['kind'] ?? '');
    if (!in_array($kind, ['polygon', 'path', 'pin'], true)) {
        throw new RuntimeException('A shape needs a kind: polygon, path, or pin.');
    }

    $name = gis_label((string) ($input['name'] ?? ''), 80);
    if ($name === '') {
        throw new RuntimeException('Give the shape a name.');
    }
    $player = gis_label((string) ($input['player'] ?? ''), 40);
    $geometry = gis_geometry($kind, $input['geometry'] ?? null);
    $encoded = json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('That geometry could not be stored.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO features (kind, name, geojson, player) VALUES (:kind, :name, :geojson, :player)'
    );
    $insert->bindValue(':kind', $kind);
    $insert->bindValue(':name', $name);
    $insert->bindValue(':geojson', $encoded);
    if ($player === '') {
        $insert->bindValue(':player', null, PDO::PARAM_NULL);
    } else {
        $insert->bindValue(':player', $player);
    }
    $insert->execute();

    $id = (int) $pdo->lastInsertId();
    $select = $pdo->prepare(
        'SELECT id, kind, name, player, geojson, updated_at FROM features WHERE id = :id'
    );
    $select->execute(['id' => $id]);
    $row = $select->fetch();
    $feature = is_array($row) ? gis_feature_from_row($row) : null;
    if ($feature === null) {
        throw new RuntimeException('The shape saved, but it could not be read back.');
    }
    return $feature;
}

function gis_delete(PDO $pdo, int $id): void
{
    $delete = $pdo->prepare('DELETE FROM features WHERE id = :id');
    $delete->execute(['id' => $id]);
    if ($delete->rowCount() === 0) {
        throw new RuntimeException('That shape is already gone.');
    }
}

function gis_feature_from_row(array $row): ?array
{
    $geometry = json_decode((string) $row['geojson'], true);
    if (!is_array($geometry)) {
        return null;
    }
    if (($geometry['type'] ?? '') === 'Feature' && isset($geometry['geometry'])) {
        $geometry = $geometry['geometry'];
    }
    $kind = (string) $row['kind'];
    if (!is_array($geometry) || !gis_geometry_ok($kind, $geometry)) {
        return null;
    }

    return [
        'type' => 'Feature',
        'id' => (int) $row['id'],
        'properties' => [
            'kind' => $kind,
            'name' => (string) $row['name'],
            'player' => $row['player'] !== null ? (string) $row['player'] : '',
            'updated_at' => (string) $row['updated_at'],
        ],
        'geometry' => $geometry,
    ];
}

function gis_label(string $value, int $max): string
{
    $value = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '');
    if (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }
    return $value;
}

function gis_geometry(string $kind, mixed $geometry): array
{
    if (!is_array($geometry) || !gis_geometry_ok($kind, $geometry)) {
        throw new RuntimeException('That geometry does not match the shape.');
    }
    return $geometry;
}

function gis_geometry_ok(string $kind, array $geometry): bool
{
    $type = $geometry['type'] ?? '';
    $coordinates = $geometry['coordinates'] ?? null;
    if ($kind === 'pin') {
        return $type === 'Point' && gis_position($coordinates);
    }
    if ($kind === 'path') {
        return $type === 'LineString'
            && is_array($coordinates)
            && count($coordinates) >= 2
            && count($coordinates) <= 1000
            && gis_positions($coordinates);
    }
    if ($kind === 'polygon') {
        return $type === 'Polygon'
            && is_array($coordinates)
            && count($coordinates) >= 1
            && count($coordinates) <= 5
            && gis_rings($coordinates);
    }
    return false;
}

function gis_rings(array $rings): bool
{
    foreach ($rings as $ring) {
        if (!is_array($ring) || count($ring) < 4 || count($ring) > 500 || !gis_positions($ring)) {
            return false;
        }
        $first = $ring[0];
        $last = $ring[count($ring) - 1];
        if (abs((float) $first[0] - (float) $last[0]) > 0.0000001
            || abs((float) $first[1] - (float) $last[1]) > 0.0000001) {
            return false;
        }
    }
    return true;
}

function gis_positions(array $positions): bool
{
    foreach ($positions as $position) {
        if (!gis_position($position)) {
            return false;
        }
    }
    return true;
}

function gis_position(mixed $position): bool
{
    if (!is_array($position) || count($position) < 2) {
        return false;
    }
    if (!is_int($position[0]) && !is_float($position[0]) && !is_numeric($position[0])) {
        return false;
    }
    if (!is_int($position[1]) && !is_float($position[1]) && !is_numeric($position[1])) {
        return false;
    }
    $lon = (float) $position[0];
    $lat = (float) $position[1];
    return $lon >= -180 && $lon <= 180 && $lat >= -90 && $lat <= 90;
}
