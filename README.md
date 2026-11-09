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
| `GET` | `/api.php/scores` | List the top 5 high scores | 200 |
| `POST` | `/api.php/scores` | Add a high score: `{"name":"ABC","score":1200}` | 201 |
| `GET` | `/api.php/games` | List running games | 200 |
| `POST` | `/api.php/games` | Start a game, returns a `Location` header | 201 |
| `GET` | `/api.php/games/{id}` | Read one game | 200 |
| `PATCH` | `/api.php/games/{id}` | Update some fields: `{"score":500,"lives":2}` | 200 |
| `DELETE` | `/api.php/games/{id}` | End a game | 204 |

Errors come back as JSON: `400` for invalid JSON, `404` for an unknown URL, `405` for an unsupported method (with an `Allow` header), and `422` for invalid data.

### When the game calls it

- Page load: `GET /scores`
- New game: `POST /games`
- Every 10 seconds, on losing a life and on clearing a level: `PATCH /games/{id}`
- Game over: `DELETE /games/{id}`
- Saving a high score: `POST /scores`, then `GET /scores`

Click any request in the API Inspector to see the full request and response, with a short explanation of the method and status code. You can also send your own requests from the box at the bottom, for example `GET /nope` or `DELETE /scores`.

## Files

- `index.php`: the game page
- `api.php`: the REST API
- `scores.json` and `games.json`: created automatically to store data

## License

[MIT](LICENSE)
