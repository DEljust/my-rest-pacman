<?php
declare(strict_types=1);

// Chip Chase REST API
//
//   GET     /api.php/scores            list the top high scores   query: ?limit=3 (1-5)
//   POST    /api.php/scores            add a high score           body: {"name":"ABC","score":1200}
//   GET     /api.php/games             list running games
//   POST    /api.php/games             start a game -> 201 + Location header
//                                      body (optional): {"score":0,"lives":3,"level":1}
//   GET     /api.php/games/{id}        read one game
//   PUT     /api.php/games/{id}        replace the whole game     body: {"score":500,"lives":2,"level":3}
//   PATCH   /api.php/games/{id}        update some fields         body: {"score":500}
//   DELETE  /api.php/games/{id}        end a game -> 204 No Content
//   OPTIONS (any of the above)         list the allowed methods in the Allow header
//
//   /games/{id} sends an ETag. GET with If-None-Match gives 304 if nothing changed,
//   PUT/PATCH with If-Match gives 412 if the game changed since you read it.

header('Content-Type: application/json; charset=utf-8');

const GAME_FIELDS = ['score', 'lives', 'level'];

function respond(int $status, mixed $data = null, array $headers = []): never
{
    http_response_code($status);
    // PHP's built-in server has no name for 422, so send the full status line ourselves.
    if ($status === 422) header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 422 Unprocessable Content');
    foreach ($headers as $name => $value) header("$name: $value");
    if ($data !== null) echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $message, array $headers = []): never
{
    respond($status, ['error' => $message], $headers);
}

// Anything unexpected (a bug, a full disk, a folder we can't write to) becomes a JSON 500,
// so the client always gets the same error shape instead of a PHP error page.
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    fail(500, 'Something went wrong on the server.');
});
set_error_handler(function (int $no, string $msg, string $file, int $line) {
    throw new ErrorException($msg, 0, $no, $file, $line);
});

// Read-modify-write a JSON file under a lock. $fn receives the data and returns [newData, result].
function with_store(string $file, callable $fn): mixed
{
    $fp = fopen(__DIR__ . "/$file", 'c+');
    flock($fp, LOCK_EX);
    try {
        $data = json_decode(stream_get_contents($fp) ?: '[]', true) ?: [];
        [$new, $result] = $fn($data);
        if ($new !== $data) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($new));
        }
        return $result;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function etag(array $game): string
{
    return '"' . substr(md5(json_encode($game)), 0, 12) . '"';
}

// Check that every given field is a whole number of 0 or more, or fail with 422.
function check_counts(array $fields): void
{
    foreach ($fields as $field => $value) {
        if (!is_int($value) || $value < 0) fail(422, "\"$field\" must be a whole number of 0 or more.");
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = trim($_SERVER['PATH_INFO'] ?? '', '/');
$parts  = $path === '' ? [] : explode('/', $path);
$raw    = (string) file_get_contents('php://input');
$body   = json_decode($raw, true);

if ($raw !== '' && !is_array($body)) fail(400, 'The request body is not valid JSON.');

[$resource, $id] = [$parts[0] ?? '', $parts[1] ?? null];
if (count($parts) > 2) fail(404, "Nothing lives at /$path.");

// Which methods each kind of URL supports. Used for OPTIONS and for 405 responses.
$allowed = match (true) {
    $resource === 'scores' && $id === null => ['GET', 'POST'],
    $resource === 'games' && $id === null  => ['GET', 'POST'],
    $resource === 'games'                  => ['GET', 'PUT', 'PATCH', 'DELETE'],
    default => fail(404, $path === '' ? 'Try /scores or /games.' : "Nothing lives at /$path."),
};
$allow = implode(', ', [...$allowed, 'OPTIONS']);

if ($method === 'OPTIONS') respond(204, null, ['Allow' => $allow]);
if (!in_array($method, $allowed, true)) {
    $where = $id === null ? "/$resource" : "/$resource/{id}";
    fail(405, "$where does not support $method.", ['Allow' => $allow]);
}

// ---------- /scores ----------
if ($resource === 'scores') {
    if ($method === 'GET') {
        $limit = $_GET['limit'] ?? '5';
        if (!is_string($limit) || !preg_match('/^[1-5]$/', $limit)) fail(400, '"limit" must be a number from 1 to 5.');
        $scores = with_store('scores.json', fn($s) => [$s, $s]);
        respond(200, array_slice($scores, 0, (int) $limit));
    }
    // POST
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

// ---------- /games ----------
if ($id === null) {
    if ($method === 'GET') {
        respond(200, array_values(with_store('games.json', fn($g) => [$g, $g])));
    }
    // POST: the body is optional; any field left out gets its default.
    $start = array_intersect_key($body ?? [], array_flip(GAME_FIELDS));
    check_counts($start);
    $now  = date('c');
    $game = ['id' => bin2hex(random_bytes(4)), ...array_merge(['score' => 0, 'lives' => 3, 'level' => 1], $start),
             'started_at' => $now, 'updated_at' => $now];
    with_store('games.json', function ($g) use ($game) {
        $g = array_filter($g, fn($x) => strtotime($x['updated_at']) > time() - 86400); // drop day-old games
        $g[$game['id']] = $game;
        return [$g, null];
    });
    respond(201, $game, ['Location' => $_SERVER['SCRIPT_NAME'] . '/games/' . $game['id']]);
}

// ---------- /games/{id} ----------
$notFound = "There is no game with id \"$id\".";

if ($method === 'GET') {
    $game = with_store('games.json', fn($g) => [$g, $g[$id] ?? null]);
    if (!$game) fail(404, $notFound);
    $tag = etag($game);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? null) === $tag) respond(304, null, ['ETag' => $tag]);
    respond(200, $game, ['ETag' => $tag]);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $changes = array_intersect_key($body ?? [], array_flip(GAME_FIELDS));
    if ($method === 'PUT' && count($changes) < count(GAME_FIELDS)) {
        fail(422, 'PUT replaces the whole game, so send all of "score", "lives" and "level". To change only some, use PATCH.');
    }
    if (!$changes) fail(422, 'Send at least one of "score", "lives" or "level".');
    check_counts($changes);
    $ifMatch = $_SERVER['HTTP_IF_MATCH'] ?? null;
    $game = with_store('games.json', function ($g) use ($id, $changes, $ifMatch) {
        if (!isset($g[$id])) return [$g, null];
        if ($ifMatch !== null && $ifMatch !== etag($g[$id])) return [$g, false];
        $g[$id] = array_merge($g[$id], $changes, ['updated_at' => date('c')]);
        return [$g, $g[$id]];
    });
    if ($game === null) fail(404, $notFound);
    if ($game === false) fail(412, 'The game changed since you read it, so your If-Match ETag is out of date. GET it again for the new ETag.');
    respond(200, $game, ['ETag' => etag($game)]);
}

// DELETE
$found = with_store('games.json', function ($g) use ($id) {
    if (!isset($g[$id])) return [$g, false];
    unset($g[$id]);
    return [$g, true];
});
$found ? respond(204) : fail(404, $notFound);
