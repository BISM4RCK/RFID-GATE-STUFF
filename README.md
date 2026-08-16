# Golden Homes Smart Gate

## Start

### Manual

1. Open PowerShell in the project folder.
2. Run:

```powershell
docker compose up -d --build
```

3. Check containers:

```powershell
docker compose ps
```

4. Open:

`https://gate.kunehobatumbakal.site/`

Local testing:

`http://localhost:8080/`

### Automatic

Run:

```text
START_SMART_GATE.bat
```

The script starts the Docker Compose stack. `STOP_SMART_GATE.bat` stops it.

Do not use `docker compose down -v` for normal restarts; that removes the MySQL data volume.

---

## Troubleshooting

### Containers are restarting

```powershell
docker compose ps
docker compose logs app --tail=100
```

### Frontend build fails

```powershell
docker compose build --no-cache app
docker compose up -d
```

### Laravel reports an environment-file error

Make sure `backend/.env` exists. The included Docker environment is the source for the container configuration.

### Database is not ready

Wait for MySQL to become healthy:

```powershell
docker compose ps
```

Then:

```powershell
docker compose exec app php artisan migrate --force
```

`Nothing to migrate` is normal when the migration set is already installed.

### HTTP 500

```powershell
docker compose logs app --tail=100
curl.exe -i http://localhost:8080/api/health
```

### Cloudflare 502/1033

Make sure the local stack is running and the Cloudflare Tunnel points to the Nginx port used by this Compose project:

`http://localhost:8080`

The public hostname is:

`https://gate.kunehobatumbakal.site/`

---

## Demo accounts

| Type | Email | Password |
|---|---|---|
| Resident 1 | `resident@goldenhomes.local` | `Resident123!` |
| Resident 2 | `resident2@goldenhomes.local` | `Resident123!` |
| Resident 3 | `resident3@goldenhomes.local` | `Resident123!` |
| Resident 4 | `resident4@goldenhomes.local` | `Resident123!` |
| Resident 5 | `resident5@goldenhomes.local` | `Resident123!` |
| Guard 1 | `guard@goldenhomes.local` | `Guard123!` |
| Guard 2 | `guard2@goldenhomes.local` | `Guard123!` |
| Guard 3 | `guard3@goldenhomes.local` | `Guard123!` |
| Admin 1 | `admin@goldenhomes.local` | `Admin123!` |
| Admin 2 | `admin2@goldenhomes.local` | `Admin123!` |

The protected super-admin account is intentionally not listed here.

---

## Demo vehicles

### Residents

| Account | Plate | Type | Vehicle | Color |
|---|---|---|---|---|
| Resident 1 | `ABC 1234` | Car | Toyota Vios | White |
| Resident 1 | `XYZ 7788` | Motorcycle | Honda Click | Black |
| Resident 2 | `DEF 2468` | Car | Mitsubishi Mirage | Silver |
| Resident 2 | `GHI 1357` | Motorcycle | Yamaha Mio | Blue |
| Resident 3 | `RES3 1001` | Car | Toyota Wigo | Pearl White |
| Resident 3 | `RES3 1002` | Motorcycle | Honda Click | Matte Black |
| Resident 3 | `RES3 1003` | Car | Suzuki Dzire | Ocean Blue |
| Resident 4 | `RES4 2001` | Car | Honda City | Ruby Red |
| Resident 5 | `RES5 3001` | Car | Toyota Fortuner | Midnight Blue |
| Resident 5 | `RES5 3002` | Motorcycle | Yamaha NMAX | Graphite Gray |
| Resident 5 | `RES5 3003` | Car | Mitsubishi Xpander | Forest Green |
| Resident 5 | `RES5 3004` | Car | Kia Seltos | Champagne Gold |
| Resident 5 | `RES5 3005` | Motorcycle | Honda ADV 160 | Pearl Silver |

### Staff vehicles

| Account | Plate | Type | Color |
|---|---|---|---|
| Guard 1 | `GRD 1001` | Car | Black |
| Guard 1 | `GRD 2002` | Motorcycle | Red |
| Guard 2 | `GRD2 1001` | Car | Navy Blue |
| Guard 3 | `GRD3 1001` | Motorcycle | Sunset Orange |
| Guard 3 | `GRD3 1002` | Car | Pearl White |
| Admin 1 | `ADM 3003` | Car | White |
| Admin 1 | `ADM 4004` | Motorcycle | Gray |
| Admin 2 | `ADM2 1001` | Car | Steel Gray |
| Admin 2 | `ADM2 1002` | Motorcycle | Deep Red |

---

## Tech stack

- React
- Vite
- Tailwind CSS
- Laravel 12
- PHP 8.4+
- MySQL 8.4
- Nginx
- Docker + Docker Compose
- Cloudflare Tunnel
- ESP32
- MFRC522
- MQTT
- REST/HTTPS

---

## API

### Public

- `GET /api/health`
- `POST /api/visitor`
- `GET /api/visitor/{credential}`

### Authentication

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`

### Dashboard

- `GET /api/dashboard`
- `GET /api/vehicles`
- `POST /api/vehicles`
- `DELETE /api/vehicles/{vehicle}`

### Gate and guard

- `GET /api/gate/status`
- `POST /api/gate/override`
- `GET /api/staff/overview`
- `GET /api/staff/logs`
- `GET /api/staff/blacklist`
- `POST /api/staff/blacklist`
- `DELETE /api/staff/blacklist/{id}`
- `GET /api/staff/walkins`
- `POST /api/staff/walkins`
- `POST /api/staff/visitor-scan`

### Admin

- `GET /api/admin/users`
- `GET /api/admin/vehicles`
- `GET /api/admin/rfid-cards`

### ESP32

- `POST /api/esp32/rfid/scan`
- `GET /api/esp32/gate/commands`
- `POST /api/esp32/gate/commands/{command}/complete`
- `POST /api/esp32/heartbeat`

---

## MQTT

The ESP32 publishes RFID telemetry using:

```text
smartgate/{DEVICE_ID}/rfid/entry
smartgate/{DEVICE_ID}/rfid/exit
```

The broker address and port are configured in the ESP32 firmware.

The Docker stack includes Mosquitto on the internal Compose network.

---

## ESP32 / MFRC522

The firmware is C++ for the Arduino ESP32 framework.

Hardware uses two MFRC522 readers:

- Entry reader
- Exit reader

The ESP32 sends RFID authorization requests over HTTPS, reports reader/device heartbeat, polls pending gate commands, and publishes RFID telemetry through MQTT.

Gate commands from the guard/admin dashboard are queued by Laravel and polled by the ESP32.

The firmware is in:

```text
esp32/SmartGate_RFID_MQTT/
```

PlatformIO configuration is provided in:

```text
esp32/SmartGate_RFID_MQTT/platformio.ini
```
