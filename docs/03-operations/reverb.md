# Reverb (Operations WebSockets)

XFlickr uses [Laravel Reverb](https://laravel.com/docs/reverb) for push updates to the Operations console (and AppLayout sidebar live activity).

## Role

- Separate process from `php artisan serve` / Horizon (avoids long-lived PHP request streams).
- Private channel `operations` — any authenticated session user.
- Domain work emits thin events; **Operations** listeners throttle and broadcast `ops.batch.updated` / `ops.overview.changed`.
- Clients bootstrap from `GET /api/v1/operations/snapshot` and fall back to 5s polling if the socket is down >15s.

## Dev stack

Compose service `reverb` (`php artisan reverb:start`) publishes host port `REVERB_HOST_PORT` (default **8083**).

- PHP containers publish events to `reverb:8080` (`REVERB_HOST=reverb`).
- Browser / Vite use `VITE_REVERB_HOST=localhost` and `VITE_REVERB_PORT` = host publish port.

After compose/env changes (operator):

```bash
bash scripts/dev.sh up
bash scripts/dev.sh restart-frontend
docker exec xflickr-dev-horizon-1 php artisan horizon:terminate
```

## Production

- Compose service `reverb` beside Horizon.
- Nginx proxies WebSocket Upgrade on `/app` to `reverb:8080` (see `docker/nginx/entrypoint.sh`).
- Built frontend should use same-origin Echo (`VITE_REVERB_HOST` empty / page host) with `REVERB_SCHEME=https` as appropriate.
- PHP uses `REVERB_HOST=reverb` and `REVERB_PORT=8080` inside the network.

## Test stack

`BROADCAST_CONNECTION=null` — PHPUnit uses `Broadcast::fake` / no Reverb process required.

## Related

- [Operations user guide](../02-user-guide/operations.md)
- [Horizon](horizon.md)
- [Docker stacks](docker-stacks.md)
