# My REST Pacman

An 8-bit maze-chase game in PHP with a live **API Inspector** that shows every REST request the game makes while you play. It's a fun way to see how a REST API works.

Eat every chip, dodge the bugs, and grab a battery to zap them.

## Run it

You need PHP 8.1 or newer.

```bash
php -S localhost:8000
```

Then open http://localhost:8000 and press **Enter**.

**Controls:** arrows or WASD to move, P to pause, M to mute, swipe on mobile.

## The REST API

The game talks to `api.php` while you play:

| Method | Path | What it does | Success |
|---|---|---|---|
| `GET` | `/api.php/scores` | List the high scores. Add `?limit=3` to get fewer | 200 |
| `POST` | `/api.php/scores` | Add a high score: `{"name":"ABC","score":1200}` | 201 |
| `GET` | `/api.php/games` | List running games | 200 |
| `POST` | `/api.php/games` | Start a game, returns a `Location` header. Body is optional: `{"lives":3}` | 201 |
| `GET` | `/api.php/games/{id}` | Read one game | 200 |
| `PUT` | `/api.php/games/{id}` | Replace the whole game: `{"score":500,"lives":2,"level":3}` | 200 |
| `PATCH` | `/api.php/games/{id}` | Update some fields: `{"score":500}` | 200 |
| `DELETE` | `/api.php/games/{id}` | End a game | 204 |
| `OPTIONS` | any of the above | List the allowed methods in the `Allow` header | 204 |

`/games/{id}` also sends an `ETag` header, a short fingerprint of the game. Send it back to make a request conditional:

| Header | On | What happens |
|---|---|---|
| `If-None-Match: "<etag>"` | `GET` | `304 Not Modified` with no body if the game has not changed |
| `If-Match: "<etag>"` | `PUT`, `PATCH` | `412 Precondition Failed` if someone changed the game since you read it |

Errors always come back as JSON, like `{"error": "..."}`:

| Status | When |
|---|---|
| `400` | The body is not valid JSON, or a query value is wrong (`?limit=99`) |
| `404` | Nothing lives at that URL, or the game id does not exist |
| `405` | The URL does not support that method. The `Allow` header lists the ones it does |
| `412` | Your `If-Match` ETag is out of date |
| `422` | The JSON is valid, but the data breaks a rule (a negative score, PUT with a missing field) |
| `500` | Something failed on the server, for example it cannot write the data files |

### When the game calls it

- Page load: `GET /scores`
- New game: `POST /games`, sending the starting score, lives and level
- Every 10 seconds and on losing a life: `PATCH /games/{id}` with only the fields that changed
- Clearing a level: `PUT /games/{id}` with the whole game
- Game over: `DELETE /games/{id}`
- Saving a high score: `POST /scores`, then `GET /scores`

After `POST /games`, the game reads the `Location` header to find the URL of the new game, the same way a real client would.

## Learn by experimenting

Click any request in the API Inspector to see the full request and response, a short explanation of the method and status code, and the same request as a `curl` command you can paste into a terminal.

At the bottom of the inspector, **Pick an experiment** fills in a ready-made request and tells you what to look for. Start a game first so the experiments that use `{id}` have a game to work on. Try them in this order:

1. **GET vs POST.** Read the scores, then create one. GET never changes anything. POST creates something new each time.
2. **Query strings.** `GET /scores?limit=3` filters the list. `?limit=99` gives 400.
3. **Validation.** Send a 7-letter name or a negative score and get 422. Send half a JSON object and get 400. Can you tell the difference?
4. **Methods a URL allows.** `DELETE /scores` gives 405. `OPTIONS /games/{id}` tells you what is allowed.
5. **PUT vs PATCH.** PATCH with only `{"lives":9}` keeps everything else. PUT with only `{"score":10}` is refused, because PUT replaces the whole game.
6. **Idempotency.** Send the same PUT twice: same result. Send the same DELETE twice: 204, then 404. Send the same POST twice: two new things.
7. **ETags.** Read your game and note the `ETag`. Ask again with `If-None-Match` and get 304. PATCH with `If-Match` twice: 200, then 412, because the first one changed the game.

Then try it outside the browser with curl:

```bash
curl -i localhost:8000/api.php/scores
curl -i -X POST localhost:8000/api.php/games -d '{"lives":5}'
curl -i -X OPTIONS localhost:8000/api.php/games
```

The `-i` flag shows the status line and headers, which is where most of the REST lessons are.

## Files

- `index.php`: the game page and the API Inspector
- `api.php`: the REST API
- `scores.json` and `games.json`: created automatically to store data

## License

[MIT](LICENSE)
