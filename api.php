<?php
declare(strict_types=1);

// Chip Chase REST API
//
//   GET    /api.php/scores        list the top 5 high scores
//   POST   /api.php/scores        add a high score          body: {"name":"ABC","score":1200}
//   GET    /api.php/games         list running games
//   POST   /api.php/games         start a game  -> 201 + Location header
//   GET    /api.php/games/{id}    read one game
//   PATCH  /api.php/games/{id}    update some fields        body: {"score":500,"lives":2}
//   DELETE /api.php/games/{id}    end a game    -> 204 No Content

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$path   = trim($_SERVER['PATH_INFO'] ?? '', '/');
$parts  = $path === '' ? [] : explode('/', $path);
$raw    = (string) file_get_contents('php://input');
$body   = json_decode($raw, true);

function respond(int $status, mixed $data = null, array $headers = []): never
{
    http_response_code($status);
    foreach ($headers as $name => $value) header("$name: $value");
    if ($data !== null) echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $message, array $headers = []): never
{
    respond($status, ['error' => $message], $headers);
}

// Read-modify-write a JSON file under a lock. $fn receives the data and returns [newData, result].
function with_store(string $file, callable $fn): mixed
{
    $fp = fopen(__DIR__ . "/$file", 'c+');
    flock($fp, LOCK_EX);
    $data = json_decode(stream_get_contents($fp) ?: '[]', true) ?: [];
    [$new, $result] = $fn($data);
    if ($new !== $data) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($new));
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

if ($raw !== '' && !is_array($body)) fail(400, 'The request body is not valid JSON.');

[$resource, $id] = [$parts[0] ?? '', $parts[1] ?? null];
if (count($parts) > 2) fail(404, "Nothing lives at /$path.");

// ---------- /scores ----------
if ($resource === 'scores' && $id === null) {
    if ($method === 'GET') {
        respond(200, with_store('scores.json', fn($s) => [$s, $s]));
    }
    if ($method === 'POST') {
        $name  = $body['name'] ?? null;
        $score = $body['score'] ?? null;
        if (!is_string($name) || !preg_match('/^[A-Z0-9]{1,3}$/', $name)) fail(422, '"name" must be 1-3 capital letters or digits.');
        if (!is_int($score) || $score < 0) fail(422, '"score" must be a whole number of 0 or more.');
        $entry = ['name' => $name, 'score' => $score];
        with_store('scores.json', function ($s) use ($entry) {
            $s[] = $entry;
            usort($s, fn($a, $b) => $b['score'] <=> $a['score']);
            return [array_slice($s, 0, 5), null];
        });
        respond(201, $entry);
    }
    fail(405, "/scores does not support $method.", ['Allow' => 'GET, POST']);
}

// ---------- /games ----------
if ($resource === 'games' && $id === null) {
    if ($method === 'GET') {
        respond(200, array_values(with_store('games.json', fn($g) => [$g, $g])));
    }
    if ($method === 'POST') {
        $now  = date('c');
        $game = ['id' => bin2hex(random_bytes(4)), 'score' => 0, 'lives' => 3, 'level' => 1, 'started_at' => $now, 'updated_at' => $now];
        with_store('games.json', function ($g) use ($game) {
            $g = array_filter($g, fn($x) => strtotime($x['updated_at']) > time() - 86400); // drop day-old games
            $g[$game['id']] = $game;
            return [$g, null];
        });
        respond(201, $game, ['Location' => $_SERVER['SCRIPT_NAME'] . '/games/' . $game['id']]);
    }
    fail(405, "/games does not support $method.", ['Allow' => 'GET, POST']);
}

if ($resource === 'games') {
    if ($method === 'GET') {
        $game = with_store('games.json', fn($g) => [$g, $g[$id] ?? null]);
        $game ? respond(200, $game) : fail(404, "There is no game with id \"$id\".");
    }
    if ($method === 'PATCH') {
        $changes = array_intersect_key($body ?? [], array_flip(['score', 'lives', 'level']));
        if (!$changes) fail(422, 'Send at least one of "score", "lives" or "level".');
        foreach ($changes as $field => $value) {
            if (!is_int($value) || $value < 0) fail(422, "\"$field\" must be a whole number of 0 or more.");
        }
        $game = with_store('games.json', function ($g) use ($id, $changes) {
            if (!isset($g[$id])) return [$g, null];
            $g[$id] = array_merge($g[$id], $changes, ['updated_at' => date('c')]);
            return [$g, $g[$id]];
        });
        $game ? respond(200, $game) : fail(404, "There is no game with id \"$id\".");
    }
    if ($method === 'DELETE') {
        $found = with_store('games.json', function ($g) use ($id) {
            if (!isset($g[$id])) return [$g, false];
            unset($g[$id]);
            return [$g, true];
        });
        $found ? respond(204) : fail(404, "There is no game with id \"$id\".");
    }
    fail(405, "/games/{id} does not support $method.", ['Allow' => 'GET, PATCH, DELETE']);
}

fail(404, $path === '' ? 'Try /scores or /games.' : "Nothing lives at /$path.");
