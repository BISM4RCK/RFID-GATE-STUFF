# Smart Gate v37

Docker deployment fixes: PHP 8.4, persistent MySQL/MQTT volumes, Laravel `.env` bootstrap, and self-contained Vite production build.

## Docker

From the directory containing `docker-compose.yml`:

```powershell
Copy-Item .\backend\.env.example .\backend\.env
docker compose config
docker compose build --no-cache
docker compose up -d
docker compose ps
```

The local site is `http://localhost:8080/smart-gate/`. Do not run `docker compose down -v` unless you intentionally want to delete Docker volumes.
