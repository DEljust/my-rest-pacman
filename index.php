<?php
declare(strict_types=1);

// Chip Chase — an original 8-bit maze-chase game.
// This file serves the page; the game talks to the REST API in api.php.

$scores = json_decode((string) @file_get_contents(__DIR__ . '/scores.json'), true) ?: [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chip Chase</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<style>
  :root {
    --space-4: 4px; --space-8: 8px; --space-12: 12px; --space-16: 16px;
    --space-24: 24px; --space-32: 32px;
    --fs-12: 12px; --fs-14: 14px; --fs-20: 20px;
    --radius-4: 4px;
    --color-bg: #07040f;
    --color-surface: #140b2e;
    --color-border: #2c1f5c;
    --color-text: #f1eaff;
    --color-muted: #a99fcc;
    --color-accent: #9ef01a;
    --ease: cubic-bezier(.2, .8, .2, 1);
    --dur-micro: 150ms;
    --font: "Press Start 2P", ui-monospace, monospace;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; background: var(--color-bg); color: var(--color-text);
    font-family: var(--font); font-size: var(--fs-12); line-height: 1.5;
    display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-start;
    gap: var(--space-32); padding: var(--space-32) var(--space-16);
  }
  .screen { position: relative; border: var(--space-4) solid var(--color-border); border-radius: var(--radius-4); }
  .screen::after { /* CRT scanlines */
    content: ""; position: absolute; inset: 0; pointer-events: none;
    background: repeating-linear-gradient(to bottom, transparent 0 2px, rgb(0 0 0 / .18) 2px 4px);
  }
  canvas {
    display: block; width: min(608px, calc(100vw - 40px)); height: auto;
    image-rendering: pixelated; background: var(--color-bg);
  }
  aside { width: 260px; display: flex; flex-direction: column; gap: var(--space-24); }
  h1 { font-size: var(--fs-20); line-height: 1.15; margin: 0; color: var(--color-accent); }
  h2 { font-size: var(--fs-12); margin: 0 0 var(--space-12); color: var(--color-muted); }
  ol { margin: 0; padding: 0; list-style: none; display: grid; gap: var(--space-8); }
  li { display: flex; justify-content: space-between; }
  li.empty { color: var(--color-muted); }
  .keys { color: var(--color-muted); display: grid; gap: var(--space-8); margin: 0; }
  .keys b { color: var(--color-text); font-weight: normal; }
  #entry {
    position: absolute; inset: 0; z-index: 1; display: none; place-items: center;
    background: rgb(7 4 15 / .8);
  }
  #entry.open { display: grid; }
  #entry form {
    display: grid; gap: var(--space-16); justify-items: center; text-align: center;
    padding: var(--space-24); background: var(--color-surface);
    border: var(--space-4) solid var(--color-border); border-radius: var(--radius-4);
  }
  input, button {
    font-family: var(--font); font-size: var(--fs-14); color: var(--color-text);
    background: var(--color-bg); border: 2px solid var(--color-border); border-radius: var(--radius-4);
    padding: var(--space-8) var(--space-12);
  }
  input { width: 7ch; text-align: center; text-transform: uppercase; }
  button { cursor: pointer; transition: transform var(--dur-micro) var(--ease); }
  button:hover { transform: translateY(-2px); }
  input:focus-visible, button:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
  @media (prefers-reduced-motion: reduce) { button { transition: none; } button:hover { transform: none; } }

  /* ---------- API inspector ---------- */
  :root {
    --color-get: #2ec4b6; --color-post: #9ef01a; --color-put: #c77dff; --color-patch: #ff9f1c; --color-delete: #ff6b81;
    --color-ok: #9ef01a; --color-warn: #ff9f1c; --color-err: #ff6b81;
    --font-code: ui-monospace, "SF Mono", Menlo, monospace;
  }
  .inspector {
    width: min(400px, 100%); display: flex; flex-direction: column;
    background: var(--color-surface); border: var(--space-4) solid var(--color-border); border-radius: var(--radius-4);
  }
  .win-bar {
    display: flex; justify-content: space-between; align-items: center; gap: var(--space-8);
    padding: var(--space-8) var(--space-12); border-bottom: 2px solid var(--color-border);
  }
  .win-bar button { font-size: var(--fs-12); padding: var(--space-4) var(--space-8); }
  .api-log {
    list-style: none; margin: 0; padding: var(--space-8); display: grid; gap: var(--space-4);
    max-height: 200px; overflow-y: auto; font-family: var(--font-code); font-size: var(--fs-12);
  }
  .api-log li.empty { color: var(--color-muted); padding: var(--space-8); }
  .api-log button {
    width: 100%; display: grid; grid-template-columns: 7ch 1fr auto; gap: var(--space-8); align-items: baseline;
    text-align: left; font-family: var(--font-code); font-size: var(--fs-12);
    padding: var(--space-4) var(--space-8); border-color: transparent; background: transparent;
  }
  .api-log button:hover { transform: none; border-color: var(--color-border); }
  .api-log button[aria-current="true"] { background: var(--color-bg); border-color: var(--color-border); }
  .api-log .note { grid-column: 2 / -1; color: var(--color-muted); }
  .m-GET { color: var(--color-get); } .m-POST { color: var(--color-post); }
  .m-PUT { color: var(--color-put); } .m-PATCH { color: var(--color-patch); }
  .m-DELETE { color: var(--color-delete); } .m-OPTIONS { color: var(--color-muted); }
  .s-2 { color: var(--color-ok); } .s-4 { color: var(--color-warn); } .s-5, .s-0 { color: var(--color-err); }
  .api-detail {
    padding: var(--space-12); border-top: 2px solid var(--color-border); max-height: 360px; overflow-y: auto;
    font-family: var(--font-code); font-size: var(--fs-12); color: var(--color-muted);
  }
  .api-detail .head { color: var(--color-text); font-size: var(--fs-14); }
  .api-detail p { margin: var(--space-8) 0; max-width: 70ch; }
  .api-detail h3 { font-family: var(--font); font-size: var(--fs-12); font-weight: normal; color: var(--color-text); margin: var(--space-16) 0 var(--space-8); }
  .api-detail pre {
    margin: 0; padding: var(--space-8); background: var(--color-bg); border-radius: var(--radius-4);
    color: var(--color-text); white-space: pre-wrap; word-break: break-word;
  }
  .api-try {
    display: grid; grid-template-columns: auto 1fr auto; gap: var(--space-8);
    padding: var(--space-12); border-top: 2px solid var(--color-border);
  }
  .api-try select, .api-try input, .api-try textarea {
    font-family: var(--font-code); font-size: var(--fs-12); width: auto; text-align: left; text-transform: none;
    color: var(--color-text); background: var(--color-bg); border: 2px solid var(--color-border);
    border-radius: var(--radius-4); padding: var(--space-4) var(--space-8); min-width: 0;
  }
  .api-try textarea, .api-try #try-lesson, .api-try #try-header { grid-column: 1 / -1; }
  .api-try textarea { resize: vertical; min-height: 48px; }
  .api-try button { font-size: var(--fs-12); padding: var(--space-4) var(--space-8); }
  .api-try .hint { grid-column: 1 / -1; color: var(--color-muted); font-family: var(--font-code); }
</style>
</head>
<body>
  <div class="screen">
    <canvas id="game"></canvas>
    <div id="entry">
      <form>
        <span>NEW HIGH SCORE!</span>
        <input id="name" maxlength="3" autocomplete="off" placeholder="AAA" aria-label="Your initials">
        <button type="submit">SAVE</button>
      </form>
    </div>
  </div>

  <aside>
    <h1>CHIP CHASE</h1>
    <section>
      <h2>HIGH SCORES</h2>
      <ol id="scores">
        <?php if (!$scores): ?><li class="empty">NO SCORES YET</li><?php endif; ?>
        <?php foreach ($scores as $s): ?>
          <li><span><?= htmlspecialchars((string) $s['name']) ?></span><span><?= (int) $s['score'] ?></span></li>
        <?php endforeach; ?>
      </ol>
    </section>
    <section>
      <h2>CONTROLS</h2>
      <p class="keys">
        <span><b>ARROWS/WASD</b> MOVE</span>
        <span><b>ENTER</b> START</span>
        <span><b>P</b> PAUSE</span>
        <span><b>M</b> MUTE</span>
        <span>SWIPE ON MOBILE</span>
      </p>
    </section>
  </aside>

  <section class="inspector" aria-label="REST API inspector">
    <div class="win-bar"><span>API INSPECTOR</span><button id="api-clear" type="button">CLEAR</button></div>
    <ol id="api-log" class="api-log"><li class="empty">Requests show up here as you play.</li></ol>
    <div id="api-detail" class="api-detail">Click a request to see what was sent and what came back.</div>
    <form id="api-try" class="api-try">
      <select id="try-lesson" aria-label="Pick an experiment">
        <option value="">Pick an experiment…</option>
      </select>
      <select id="try-method" aria-label="HTTP method">
        <option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option><option>OPTIONS</option>
      </select>
      <input id="try-path" value="/scores" aria-label="Path">
      <button type="submit">SEND</button>
      <input id="try-header" aria-label="Extra header" placeholder='Extra header (optional), e.g. If-Match: "abc123"'>
      <textarea id="try-body" aria-label="JSON body" placeholder='JSON body, e.g. {"name":"ABC","score":500}'></textarea>
      <span id="try-hint" class="hint">Pick an experiment above, or write your own request.</span>
    </form>
  </section>

<script>
const SCORES = <?= json_encode($scores, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

(() => {
// ---------- maze ----------
// # wall  . chip  o battery (power)  - bug door  P player  E bug pen
const MAP = [
  "###################",
  "#o.......#.......o#",
  "#.##.###.#.###.##.#",
  "#.................#",
  "#.##.#.#####.#.##.#",
  "#....#...#...#....#",
  "####.###.#.###.####",
  "####.#.......#.####",
  "####.#.##-##.#.####",
  "#......#EEE#......#",
  "####.#.#####.#.####",
  "####.#.......#.####",
  "####.#.#####.#.####",
  "#........#........#",
  "#.##.###.#.###.##.#",
  "#o.#.....P.....#.o#",
  "##.#.#.#####.#.#.##",
  "#....#...#...#....#",
  "#.######.#.######.#",
  "#.................#",
  "###################",
];
const T = 16, W = 19, H = 21, TOP = 16, BOT = 16;
const C = {
  bg: "#07040f", wallFill: "#140b2e", wallEdge: "#7b5cff", door: "#ff7ad9",
  chip: "#ffd166", power: "#4cc9f0", text: "#f1eaff", muted: "#a99fcc",
  player: "#9ef01a", fright: "#2b2bd9", frightEye: "#ffb3c7", white: "#ffffff", black: "#07040f",
};
const BUG_COLORS = ["#ff4d6d", "#ff9f1c", "#2ec4b6", "#c77dff"];
const DIRS = { up: [0, -1], down: [0, 1], left: [-1, 0], right: [1, 0] };
const OPP = { up: "down", down: "up", left: "right", right: "left" };
const EXIT = { x: 9, y: 7 }, PEN = { x: 9, y: 9 };
const CORNERS = [[17, 1], [1, 1], [17, 19], [1, 19]];
const START = (() => { for (let y = 0; y < H; y++) { const x = MAP[y].indexOf("P"); if (x >= 0) return { x, y }; } })();

// ---------- sprites (8x8, drawn at 2x) ----------
const ROBOT = [
  ["...WW...", "..XXXX..", ".XXXXXX.", ".XKXXKX.", ".XXXXXX.", "..XKKX..", ".X.XX.X.", ".X....X."],
  ["...WW...", "..XXXX..", ".XXXXXX.", ".XKXXKX.", ".XXXXXX.", "..XXXX..", ".X.XX.X.", "X......X"],
];
const BUG = [
  ["X......X", ".X....X.", "..XXXX..", ".XWXXWX.", "XXXXXXXX", ".XXXXXX.", "X.X..X.X", ".X....X."],
  [".X....X.", "X......X", "..XXXX..", ".XWXXWX.", "XXXXXXXX", ".XXXXXX.", ".X.XX.X.", "X......X"],
];

// ---------- canvas ----------
const cv = document.getElementById("game"), ctx = cv.getContext("2d");
cv.width = W * T; cv.height = H * T + TOP + BOT;
ctx.imageSmoothingEnabled = false;

const mazeCv = document.createElement("canvas");
mazeCv.width = W * T; mazeCv.height = H * T;
(() => {
  const m = mazeCv.getContext("2d");
  const isWall = (x, y) => x < 0 || y < 0 || x >= W || y >= H || MAP[y][x] === "#";
  for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
    const c = MAP[y][x], px = x * T, py = y * T;
    if (c === "-") { m.fillStyle = C.door; m.fillRect(px, py + 7, T, 2); }
    if (c !== "#") continue;
    m.fillStyle = C.wallFill; m.fillRect(px, py, T, T);
    m.fillStyle = C.wallEdge;
    if (!isWall(x, y - 1)) m.fillRect(px, py, T, 2);
    if (!isWall(x, y + 1)) m.fillRect(px, py + T - 2, T, 2);
    if (!isWall(x - 1, y)) m.fillRect(px, py, 2, T);
    if (!isWall(x + 1, y)) m.fillRect(px + T - 2, py, 2, T);
  }
})();

// ---------- sound ----------
let actx = null, muted = false, chipFlip = false;
function tone(f, d, v = 0.05, slide = 0) {
  if (muted) return;
  actx = actx || new (window.AudioContext || window.webkitAudioContext)();
  const o = actx.createOscillator(), g = actx.createGain(), now = actx.currentTime;
  o.type = "square";
  o.frequency.setValueAtTime(f, now);
  if (slide) o.frequency.exponentialRampToValueAtTime(slide, now + d);
  g.gain.setValueAtTime(v, now);
  g.gain.exponentialRampToValueAtTime(0.0001, now + d);
  o.connect(g).connect(actx.destination);
  o.start(now); o.stop(now + d);
}
const melody = (notes, step) => notes.forEach((f, i) => setTimeout(() => tone(f, step / 1000), i * step));
const sfx = {
  chip: () => tone((chipFlip = !chipFlip) ? 520 : 660, 0.05, 0.03),
  power: () => tone(180, 0.35, 0.05, 900),
  eatBug: () => tone(300, 0.25, 0.06, 1400),
  die: () => tone(700, 1.3, 0.06, 60),
  clear: () => melody([523, 659, 784, 1046], 120),
  start: () => melody([392, 523, 659, 523, 784, 1046], 150),
};

// ---------- REST API client + inspector ----------
const API_BASE = "api.php";
const METHOD_INFO = {
  GET: "GET reads data. It never changes anything, so it is safe to repeat.",
  POST: "POST creates something new. Sending it twice would create two.",
  PUT: "PUT replaces the whole resource with what you send, so every field is required. Sending the same PUT twice gives the same result.",
  PATCH: "PATCH changes only the fields you send. Everything else stays the same.",
  DELETE: "DELETE removes the resource. Sending it again gives 404, because it is already gone.",
  OPTIONS: "OPTIONS asks which methods a URL supports. The answer is in the Allow header.",
};
const STATUS_INFO = {
  200: "200 OK: it worked, and the response body has the result.",
  201: "201 Created: something new now exists. The Location header gives its URL.",
  204: "204 No Content: it worked, and there is nothing to send back.",
  304: "304 Not Modified: your If-None-Match ETag still matches, so nothing changed and no body is sent. Your copy is up to date.",
  400: "400 Bad Request: the server could not read the body. Is it valid JSON?",
  404: "404 Not Found: there is nothing at that URL.",
  405: "405 Method Not Allowed: this URL does not support that method. The Allow header lists the ones it does.",
  412: "412 Precondition Failed: your If-Match ETag is out of date. Someone changed the resource after you read it, so the server refused to overwrite it.",
  422: "422 Unprocessable: the JSON was readable, but the data in it broke a rule.",
  500: "500 Internal Server Error: the request was fine, but the server failed. This is the server's fault, not yours.",
};

// Experiments for the "send your own request" box. {id} is filled in with the current game.
const LESSONS = [
  ["Read the high scores", "GET", "/scores", "", "200 OK, and the body is a JSON list."],
  ["Ask for only the top 3", "GET", "/scores?limit=3", "", "Everything after ? is the query string. It filters a GET without changing the URL of the resource."],
  ["Send a bad query value", "GET", "/scores?limit=99", "", "400: the server could not use what you sent."],
  ["Create a high score", "POST", "/scores", '{"name":"YOU","score":100}', "201 Created. The body echoes what was saved."],
  ["Break a rule", "POST", "/scores", '{"name":"toolong","score":-5}', "422: the JSON is fine, but the data is not allowed. Read the error message."],
  ["Send broken JSON", "POST", "/scores", '{"name": "ABC", ', "400: the server could not even read the body."],
  ["Use the wrong method", "DELETE", "/scores", "", "405, plus an Allow header listing the methods that do work."],
  ["Ask what is allowed", "OPTIONS", "/games/{id}", "", "204 with no body. Look at the Allow header."],
  ["Visit a URL that does not exist", "GET", "/nope", "", "404 Not Found."],
  ["List running games", "GET", "/games", "", "Start a game first, then send this. Your game is in the list."],
  ["Read your current game", "GET", "/games/{id}", "", "Start a game first. GET is safe: send it as often as you like."],
  ["Change one field with PATCH", "PATCH", "/games/{id}", '{"lives":9}', "Only lives changes. Watch the game: you now have 9 lives on the server, but not on screen."],
  ["PUT with a missing field", "PUT", "/games/{id}", '{"score":10}', "422: PUT replaces everything, so it needs every field."],
  ["Replace the game with PUT", "PUT", "/games/{id}", '{"score":0,"lives":1,"level":1}', "200. Send it twice: the result is the same. That is what idempotent means."],
  ["Delete the game (send it twice)", "DELETE", "/games/{id}", "", "First time 204, second time 404, because it is already gone."],
  ["Ask if the game changed (ETag)", "GET", "/games/{id}", "", "Read your game first and look at the ETag header. This sends it back in If-None-Match: 304 if nothing changed. The game saves every 10 seconds, so wait too long and you get 200 with a new ETag.", "If-None-Match: {etag}"],
  ["Update only if nobody else did", "PATCH", "/games/{id}", '{"lives":5}', "If-Match says: only change it if it still has this ETag. Send it twice: 200, then 412, because your first PATCH changed the ETag.", "If-Match: {etag}"],
];
const apiLog = [];
let selectedEntry = null, lastEtag = null;

async function api(method, path, body = null, note = "", extra = {}) {
  const headers = { Accept: "application/json", ...extra };
  const init = { method, headers };
  if (body !== null && body !== "") {
    headers["Content-Type"] = "application/json";
    init.body = typeof body === "string" ? body : JSON.stringify(body);
  }
  const entry = { method, url: "/" + API_BASE + path, fullUrl: new URL(API_BASE + path, location.href).href, note, reqHeaders: headers, reqBody: init.body ?? null,
                  status: 0, statusText: "pending…", resHeaders: {}, resBody: null, ms: 0 };
  apiLog.unshift(entry);
  if (apiLog.length > 50) apiLog.pop();
  renderLog();
  const t0 = performance.now();
  try {
    const res = await fetch(API_BASE + path, init);
    entry.status = res.status;
    // PHP's dev server doesn't know the name for 422, so fill it in
    entry.statusText = res.status === 422 ? "Unprocessable Content" : res.statusText;
    lastEtag = res.headers.get("etag") || lastEtag;
    for (const h of ["content-type", "location", "allow", "etag"]) {
      const v = res.headers.get(h);
      if (v) entry.resHeaders[h] = v;
    }
    const text = await res.text();
    try { entry.resBody = text ? JSON.parse(text) : null; } catch { entry.resBody = text; }
  } catch {
    entry.statusText = "network error";
  }
  entry.ms = Math.round(performance.now() - t0);
  renderLog();
  return entry;
}

const el = (tag, cls, txt) => { const n = document.createElement(tag); if (cls) n.className = cls; if (txt != null) n.textContent = txt; return n; };
const statusClass = s => "s-" + String(s)[0];

function renderLog() {
  const ol = document.getElementById("api-log");
  ol.replaceChildren();
  if (!apiLog.length) ol.append(el("li", "empty", "Requests show up here as you play."));
  for (const e of apiLog) {
    const btn = el("button");
    btn.type = "button";
    btn.setAttribute("aria-current", String(e === (selectedEntry || apiLog[0])));
    btn.append(el("b", "m-" + e.method, e.method), el("span", "", e.url.replace("/" + API_BASE, "")),
               el("span", statusClass(e.status), e.status || "…"));
    if (e.note) btn.append(el("span", "note", e.note));
    btn.addEventListener("click", () => { selectedEntry = e; renderLog(); });
    const li = el("li"); li.append(btn); ol.append(li);
  }
  renderDetail(selectedEntry || apiLog[0]);
}

function renderDetail(e) {
  const box = document.getElementById("api-detail");
  if (!e) { box.textContent = "Click a request to see what was sent and what came back."; return; }
  const head = el("div", "head");
  head.append(el("b", "m-" + e.method, e.method + " "), document.createTextNode(e.url));
  const status = el("div", statusClass(e.status), `${e.status || ""} ${e.statusText} · ${e.ms} ms`);
  const lines = obj => Object.entries(obj).map(([k, v]) => `${k}: ${v}`).join("\n");
  const pretty = b => b == null ? "(no body)" : typeof b === "string" ? b : JSON.stringify(b, null, 2);
  let reqBody = e.reqBody;
  try { reqBody = reqBody && JSON.parse(reqBody); } catch { /* show raw text */ }
  const statusInfo = STATUS_INFO[e.status] || (e.status >= 500 ? "5xx: something broke on the server." : "");
  const quote = s => "'" + s.replace(/'/g, "'\\''") + "'";
  const extra = Object.entries(e.reqHeaders).filter(([k]) => k !== "Accept" && k !== "Content-Type").map(([k, v]) => `-H ${quote(`${k}: ${v}`)}`);
  const curl = ["curl -i", e.method !== "GET" && `-X ${e.method}`, quote(e.fullUrl), ...extra,
                e.reqBody && `-H 'Content-Type: application/json' -d ${quote(e.reqBody)}`].filter(Boolean).join(" ");
  box.replaceChildren(head, status,
    el("p", "", METHOD_INFO[e.method] || ""), el("p", "", statusInfo),
    el("h3", "", "REQUEST"), el("pre", "", `${e.method} ${e.url}\n${lines(e.reqHeaders)}\n\n${pretty(reqBody)}`),
    el("h3", "", "RESPONSE"), el("pre", "", `${e.status} ${e.statusText}\n${lines(e.resHeaders)}\n\n${pretty(e.resBody)}`),
    el("h3", "", "SAME REQUEST WITH CURL"), el("pre", "", curl));
}

document.getElementById("api-clear").addEventListener("click", () => { apiLog.length = 0; selectedEntry = null; renderLog(); });
const lessonSelect = document.getElementById("try-lesson");
LESSONS.forEach(([title], i) => lessonSelect.append(new Option(title, i)));
lessonSelect.addEventListener("change", () => {
  const lesson = LESSONS[lessonSelect.value];
  if (!lesson) return;
  const [, method, path, body, notice, header = ""] = lesson;
  document.getElementById("try-method").value = method;
  document.getElementById("try-path").value = path.replace("{id}", gameId || "{id}");
  document.getElementById("try-body").value = body;
  document.getElementById("try-header").value = header.replace("{etag}", lastEtag || "{etag}");
  document.getElementById("try-hint").textContent = "What to notice: " + notice;
});
document.getElementById("api-try").addEventListener("submit", e => {
  e.preventDefault();
  let path = document.getElementById("try-path").value.trim() || "/";
  if (!path.startsWith("/")) path = "/" + path;
  selectedEntry = null;
  const [name, ...value] = document.getElementById("try-header").value.split(":");
  const extra = value.length && name.trim() ? { [name.trim()]: value.join(":").trim() } : {};
  api(document.getElementById("try-method").value, path, document.getElementById("try-body").value.trim(), "sent by you", extra);
});

// Game calls run one after another, so a game is always created before it is updated or deleted.
// gamePath comes from the Location header of POST /games, the way a real client finds a new resource.
let gameId = null, gamePath = null, apiQueue = Promise.resolve(), saveClock = 0;
const gameCall = fn => { apiQueue = apiQueue.then(fn).catch(() => {}); };
const patchGame = (fields, note) => gameCall(() => gamePath && api("PATCH", gamePath, fields, note));
const putGame = note => gameCall(() => gamePath && api("PUT", gamePath, { score, lives, level }, note));

// ---------- state ----------
let grid, chipsLeft, player, bugs, popups = [];
let score = 0, lives = 3, level = 1, state = "title", stateT = 0, clock = 0;
let frightT = 0, combo = 0, modeClock = 0, playClock = 0, paused = false;
let scores = SCORES.slice();

function loadMaze() {
  grid = MAP.map(r => [...r].map(c => (c === "P" || c === "E") ? " " : c));
  chipsLeft = grid.flat().filter(c => c === "." || c === "o").length;
}
const ent = (x, y) => ({ x, y, tx: x, ty: y, t: 0, dir: null, speed: 0 });
function resetActors() {
  player = Object.assign(ent(START.x, START.y), { want: "left" });
  bugs = [[9, 7], [8, 9], [9, 9], [10, 9]].map(([x, y], i) =>
    Object.assign(ent(x, y), { i, mode: i === 0 ? "active" : "pen", release: i * 3, fright: false }));
  frightT = 0; playClock = 0; modeClock = 0; popups = [];
}
function newGame() {
  score = 0; lives = 8; level = 1;
  loadMaze(); resetActors();
  state = "ready"; stateT = 0; paused = false; saveClock = 0;
  sfx.start();
  gameId = gamePath = null;
  gameCall(async () => {
    const r = await api("POST", "/games", { score, lives, level }, "new game started");
    const loc = r.resHeaders.location;
    if (r.status !== 201 || !loc) return;
    gameId = r.resBody.id;
    gamePath = loc.slice(loc.indexOf(API_BASE) + API_BASE.length);
  });
}

// ---------- movement ----------
function wall(x, y, door) {
  if (x < 0 || y < 0 || x >= W || y >= H) return true;
  const c = grid[y][x];
  return c === "#" || (c === "-" && !door);
}
function setDir(e, d) {
  e.dir = d;
  e.tx = d ? e.x + DIRS[d][0] : e.x;
  e.ty = d ? e.y + DIRS[d][1] : e.y;
}
function reverse(e) {
  if (!e.dir) return;
  [e.x, e.tx] = [e.tx, e.x];
  [e.y, e.ty] = [e.ty, e.y];
  e.t = 1 - e.t;
  e.dir = OPP[e.dir];
}
// entities move tile-to-tile; `decide` runs every time one lands on a tile
function advance(e, dt, decide) {
  if (!e.dir) { decide(e); if (!e.dir) return; }
  e.t += e.speed * dt;
  while (e.t >= 1) {
    e.x = e.tx; e.y = e.ty; e.t -= 1;
    decide(e);
    if (!e.dir) { e.t = 0; break; }
  }
}
const pos = e => [e.x + (e.tx - e.x) * e.t, e.y + (e.ty - e.y) * e.t];

function playerDecide(p) {
  eat(p.x, p.y);
  const can = d => d && !wall(p.x + DIRS[d][0], p.y + DIRS[d][1]);
  setDir(p, can(p.want) ? p.want : can(p.dir) ? p.dir : null);
}
function eat(x, y) {
  const c = grid[y][x];
  if (c !== "." && c !== "o") return;
  grid[y][x] = " ";
  chipsLeft--;
  if (c === ".") { score += 10; sfx.chip(); }
  else { score += 50; powerUp(); }
  if (chipsLeft === 0 && state === "play") { state = "clear"; stateT = 0; sfx.clear(); }
}
function powerUp() {
  frightT = Math.max(2.5, 8 - level);
  combo = 0;
  bugs.forEach(b => { if (b.mode === "active") { if (!b.fright) reverse(b); b.fright = true; } });
  sfx.power();
}

function bugTarget(b) {
  if (b.mode === "eaten") return [PEN.x, PEN.y];
  if (b.mode === "leaving") return [EXIT.x, EXIT.y];
  if (modeClock % 27 < 7) return CORNERS[b.i];            // scatter phase
  const [dx, dy] = DIRS[player.dir || player.want || "left"];
  const px = player.x, py = player.y;
  switch (b.i) {
    case 0: return [px, py];                               // hunter
    case 1: return [px + dx * 4, py + dy * 4];             // ambusher
    case 2: return [px - dx * 3, py - dy * 3];             // tailgater
    default: return (b.x - px) ** 2 + (b.y - py) ** 2 > 36 ? [px, py] : CORNERS[3]; // coward
  }
}
function bugDecide(b) {
  if (b.mode === "eaten" && b.x === PEN.x && b.y === PEN.y) b.mode = "leaving";
  if (b.mode === "leaving" && b.x === EXIT.x && b.y === EXIT.y) b.mode = "active";
  if (b.mode === "pen") return setDir(b, null);
  const door = b.mode !== "active";
  const open = d => !wall(b.x + DIRS[d][0], b.y + DIRS[d][1], door);
  let opts = Object.keys(DIRS).filter(d => open(d) && d !== OPP[b.dir]);
  if (!opts.length) opts = Object.keys(DIRS).filter(open);
  if (!opts.length) return setDir(b, null);
  if (b.fright) return setDir(b, opts[Math.random() * opts.length | 0]);
  const [tx, ty] = bugTarget(b);
  let best = null, bestD = Infinity;
  for (const d of opts) {
    const dist = (b.x + DIRS[d][0] - tx) ** 2 + (b.y + DIRS[d][1] - ty) ** 2;
    if (dist < bestD) { bestD = dist; best = d; }
  }
  setDir(b, best);
}
function bugSpeed(b) {
  if (b.mode === "eaten") return 14;
  if (b.mode === "leaving" || b.fright) return 4;
  return Math.min(6.2 + level * 0.35, 8.2);
}

function collide() {
  const [px, py] = pos(player);
  for (const b of bugs) {
    if (b.mode === "eaten" || b.mode === "pen") continue;
    const [bx, by] = pos(b);
    if (Math.abs(px - bx) > 0.6 || Math.abs(py - by) > 0.6) continue;
    if (b.fright) {
      b.fright = false; b.mode = "eaten"; combo++;
      const pts = 100 * 2 ** combo;
      score += pts;
      popups.push({ x: bx, y: by, text: String(pts), t: 1 });
      sfx.eatBug();
    } else {
      state = "dying"; stateT = 0; sfx.die();
      return;
    }
  }
}

// ---------- loop ----------
function update(dt) {
  clock += dt;
  if (paused) return;
  stateT += dt;
  popups.forEach(p => p.t -= dt);
  popups = popups.filter(p => p.t > 0);

  if (state === "ready" && stateT > 2) { state = "play"; stateT = 0; }
  if (state === "dying" && stateT > 1.6) {
    lives--;
    if (lives <= 0) gameOver();
    else { resetActors(); state = "ready"; stateT = 0; patchGame({ lives, score }, "lost a life"); }
  }
  if (state === "clear" && stateT > 2) {
    level++; loadMaze(); resetActors(); state = "ready"; stateT = 0;
    putGame("level cleared, so save the whole game");
  }
  if (state !== "play") return;

  playClock += dt;
  saveClock += dt;
  if (saveClock >= 10) { saveClock = 0; patchGame({ score }, "autosave (every 10s)"); }
  if (frightT > 0) {
    frightT -= dt;
    if (frightT <= 0) { frightT = 0; bugs.forEach(b => b.fright = false); }
  } else modeClock += dt;
  bugs.forEach(b => { if (b.mode === "pen" && playClock >= b.release) b.mode = "leaving"; });

  player.speed = frightT > 0 ? 8.5 : 7.5;
  advance(player, dt, playerDecide);
  if (state !== "play") return;
  bugs.forEach(b => { b.speed = bugSpeed(b); advance(b, dt, bugDecide); });
  collide();
}

function drawSprite(rows, x, y, pal, s = 2) {
  for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
    const col = pal[rows[r][c]];
    if (!col) continue;
    ctx.fillStyle = col;
    ctx.fillRect(Math.round(x) + c * s, Math.round(y) + r * s, s, s);
  }
}
function text(str, x, y, color = C.text, align = "left") {
  ctx.font = '8px "Press Start 2P", monospace';
  ctx.textBaseline = "top"; ctx.textAlign = align; ctx.fillStyle = color;
  ctx.fillText(str, x, y);
}
const midText = (str, row, color) => text(str, (W * T) / 2, TOP + row * T + 4, color, "center");

function draw() {
  ctx.fillStyle = C.bg; ctx.fillRect(0, 0, cv.width, cv.height);
  ctx.drawImage(mazeCv, 0, TOP);

  const blink = Math.floor(clock * 4) % 2 === 0;
  for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
    const c = grid[y][x], px = x * T, py = TOP + y * T;
    if (c === ".") { ctx.fillStyle = C.chip; ctx.fillRect(px + 7, py + 7, 2, 2); }
    if (c === "o" && (blink || state !== "play")) {
      ctx.fillStyle = C.power; ctx.fillRect(px + 5, py + 4, 6, 8);
      ctx.fillStyle = C.white; ctx.fillRect(px + 7, py + 2, 2, 2);
    }
  }

  if (state !== "title") {
    const walking = state === "play" && player.dir;
    const frame = walking ? Math.floor(clock * 10) % 2 : 0;
    const showBugs = state !== "dying" || stateT < 0.5;
    if (showBugs && state !== "over") for (const b of bugs) {
      const [bx, by] = pos(b);
      const flash = b.fright && frightT < 2 && Math.floor(clock * 8) % 2;
      const pal = b.mode === "eaten" ? { W: C.white }
        : b.fright ? { X: flash ? C.white : C.fright, W: C.frightEye }
        : { X: BUG_COLORS[b.i], W: C.white };
      drawSprite(BUG[Math.floor(clock * 6) % 2], bx * T, TOP + by * T, pal);
    }
    const dyingBlink = state === "dying" && stateT > 0.5 && Math.floor(stateT * 10) % 2;
    if (!dyingBlink && state !== "over") {
      const [px, py] = pos(player);
      drawSprite(ROBOT[frame], px * T, TOP + py * T, { X: C.player, K: C.black, W: C.power });
    }
    popups.forEach(p => text(p.text, p.x * T + 8, TOP + p.y * T + 4, C.power, "center"));
  }

  // HUD
  text("SCORE " + String(score).padStart(6, "0"), 4, 4);
  const hi = Math.max(score, scores[0] ? scores[0].score : 0);
  text("HI " + String(hi).padStart(6, "0"), W * T - 4, 4, C.text, "right");
  for (let i = 0; i < lives - (state === "title" ? 0 : 1); i++)
    drawSprite(ROBOT[0], 4 + i * 12, TOP + H * T + 4, { X: C.player, K: C.black, W: C.power }, 1);
  text("LV " + level, W * T - 4, TOP + H * T + 4, C.muted, "right");

  if (state === "title") {
    ctx.fillStyle = "rgb(7 4 15 / .85)"; ctx.fillRect(0, TOP, W * T, H * T);
    midText("CHIP CHASE", 4, C.player);
    midText("EAT EVERY CHIP.", 7, C.text);
    midText("DODGE THE BUGS.", 8, C.text);
    BUG_COLORS.forEach((col, i) =>
      drawSprite(BUG[Math.floor(clock * 6) % 2], W * T / 2 - 44 + i * 24, TOP + 10 * T, { X: col, W: C.white }));
    midText("GRAB A BATTERY", 12, C.power);
    midText("TO ZAP THEM.", 13, C.power);
    if (blink) midText("PRESS ENTER", 16, C.chip);
  }
  if (state === "ready") midText("READY!", 11, C.chip);
  if (state === "clear" && blink) midText("LEVEL CLEAR!", 11, C.player);
  if (state === "over") { midText("GAME OVER", 11, "#ff4d6d"); if (blink && !entryOpen()) midText("PRESS ENTER", 13, C.chip); }
  if (paused) midText("PAUSED", 11, C.text);
}

let last = performance.now();
function frame(now) {
  const dt = Math.min((now - last) / 1000, 0.05);
  last = now;
  update(dt);
  draw();
  requestAnimationFrame(frame);
}

// ---------- high scores ----------
const entry = document.getElementById("entry"), nameInput = document.getElementById("name");
const entryOpen = () => entry.classList.contains("open");
function renderScores() {
  const ol = document.getElementById("scores");
  ol.replaceChildren();
  if (!scores.length) {
    const li = document.createElement("li"); li.className = "empty"; li.textContent = "NO SCORES YET"; ol.append(li);
  }
  for (const s of scores) {
    const li = document.createElement("li"), n = document.createElement("span"), v = document.createElement("span");
    n.textContent = s.name; v.textContent = s.score; li.append(n, v); ol.append(li);
  }
}
function gameOver() {
  state = "over"; stateT = 0;
  gameCall(() => gamePath && api("DELETE", gamePath, null, "game over, so delete it"));
  if (score > 0 && (scores.length < 5 || score > scores[scores.length - 1].score)) {
    entry.classList.add("open");
    nameInput.value = "";
    nameInput.focus();
  }
}
entry.querySelector("form").addEventListener("submit", async e => {
  e.preventDefault();
  entry.classList.remove("open");
  const name = nameInput.value.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 3) || "AAA";
  const saved = await api("POST", "/scores", { name, score }, "save your high score");
  if (saved.status === 201) loadScores("refresh the list");
});
async function loadScores(note) {
  const r = await api("GET", "/scores", null, note);
  if (r.status === 200) { scores = r.resBody; renderScores(); }
}

// ---------- input ----------
const KEYS = { arrowup: "up", arrowdown: "down", arrowleft: "left", arrowright: "right", w: "up", s: "down", a: "left", d: "right" };
function steer(d) {
  if (state !== "play" && state !== "ready") return;
  player.want = d;
  if (player.dir && d === OPP[player.dir]) reverse(player);
}
function startKey() { if (state === "title" || (state === "over" && !entryOpen())) newGame(); }
addEventListener("keydown", e => {
  if (e.target.closest("input, textarea, select")) return;
  const k = e.key.toLowerCase();
  if (KEYS[k]) { e.preventDefault(); steer(KEYS[k]); }
  else if (k === "enter" || k === " ") { e.preventDefault(); startKey(); }
  else if (k === "p" && state === "play") paused = !paused;
  else if (k === "m") muted = !muted;
});
let touch = null;
cv.addEventListener("touchstart", e => { touch = e.touches[0]; e.preventDefault(); }, { passive: false });
cv.addEventListener("touchend", e => {
  if (!touch) return;
  const t = e.changedTouches[0], dx = t.clientX - touch.clientX, dy = t.clientY - touch.clientY;
  touch = null;
  if (Math.max(Math.abs(dx), Math.abs(dy)) < 20) return startKey();
  steer(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? "right" : "left") : (dy > 0 ? "down" : "up"));
});

loadMaze(); resetActors();
loadScores("page loaded");
(document.fonts ? document.fonts.load('8px "Press Start 2P"') : Promise.resolve())
  .catch(() => {}).finally(() => requestAnimationFrame(frame));
})();
</script>
</body>
</html>
