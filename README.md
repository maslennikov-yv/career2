# Тестовое задание Юрий Масленников

Локальное развёртывание проекта через **Tower** — интерактивный CLI-помощник (`tower.mjs`), который оборачивает Laravel Sail и проводит через настройку проекта пошагово.

## Что понадобится

Установите эти инструменты до начала работы:

- **Docker** (Docker Desktop / Docker Engine + `docker compose`) — Tower поднимает контейнеры через Sail.
- **Node.js 20+** и **npm** — Tower написан на Node и использует ESM.
- **PHP 8.3+** и **Composer** — нужны один раз, чтобы установить пакеты Composer (включая `vendor/bin/sail`). Дальнейшие команды Tower запускает уже внутри контейнера.
- **Git** — для клонирования репозитория.

> На Windows используйте WSL2 — Sail рассчитан на Linux-окружение.

## Быстрый старт

```bash
# 1. Клонирование
git clone <repository-url>
cd career2

# 2. Зависимости (один раз)
composer install
npm install

# 3. Запуск Tower
npm run tower
```

В открывшемся меню выберите **🚀 Старт проекта** — Tower сам:

1. Скопирует `.env.example` → `.env` (или предложит перезаписать существующий).
2. Поднимет контейнеры (`laravel.test`, `pgsql`, `redis`, `mailpit`) и дождётся готовности БД.
3. Сгенерирует `APP_KEY`, если он пуст.
4. Спросит про миграции (`migrate` или `migrate:fresh`) и сидеры (`db:seed`).
5. Предложит установить npm-зависимости и собрать ассеты.
6. Покажет полезные ссылки (приложение, Mailpit, Vite, доступы к Postgres/Redis).

После завершения проект доступен по адресу из `APP_URL` (по умолчанию `http://localhost`).

## Меню Tower

Главное меню показывает текущий статус контейнеров (● запущено / ○ остановлено) и предлагает разделы:

| Раздел | Что внутри |
| --- | --- |
| 🚀 **Старт проекта** | Первичная настройка (см. выше). |
| 🐳 **Окружение** | `sail up -d`, `down`, `restart`, `ps`, логи отдельного сервиса, ссылки. |
| 🛠 **Разработка** | `composer dev` (serve+queue+pail+vite), отдельные `vite`, `serve`, `pail`, `queue:listen`, шелл в контейнере. |
| 🗄 **База данных** | `migrate`, `migrate:rollback`, `migrate:fresh`, `migrate:fresh --seed`, `db:seed`, `psql`, `artisan tinker`. |
| ✅ **Качество и тесты** | `composer ci:check` (полный CI), Pest (все/по фильтру/один файл/`--coverage`/`--parallel`), Pint, ESLint, Prettier, `svelte-check`. |
| 🧹 **Обслуживание** | `composer install` / `npm install`, обновления, очистка кешей (`optimize:clear`, `config:clear` и т.д.), сборка ассетов. |
| 🚪 **Выход** | Закрыть Tower. |

Для выхода из подменю выбирайте «← Назад» или нажимайте `Ctrl+C` (во время длительных операций `Ctrl+C` останавливает только команду, не закрывая Tower).

## Полезные адреса по умолчанию

После запуска контейнеров:

- Приложение — `http://localhost` (порт переопределяется через `APP_PORT` в `.env`).
- Mailpit UI — `http://localhost:8025` (`FORWARD_MAILPIT_DASHBOARD_PORT`).
- Vite (HMR) — `http://localhost:5173`.
- Postgres — `localhost:5432` (`FORWARD_DB_PORT`), пользователь `sail`, БД `laravel`.
- Redis — `localhost:6379` (`FORWARD_REDIS_PORT`).

Если порты заняты на хосте — переопределите соответствующие `APP_PORT` / `FORWARD_*_PORT` в `.env` и перезапустите контейнеры.

## Без Tower (на всякий случай)

Если Tower по каким-то причинам не запускается, те же шаги вручную:

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

## Фича «Statlite» — счётчик посещений

В проекте реализован встраиваемый счётчик посещений (Postgres + Inertia + Svelte 5 + LayerChart).

### Как работает

1. Авторизованный пользователь создаёт сайт на `/sites` и получает сниппет вида:
   ```html
   <script async
       src="https://stats.example.com/tracker.js"
       data-site-id="<public_id>"
       data-endpoint="https://stats.example.com/api/track"></script>
   ```
2. Скрипт `public/tracker.js` собирает `visitor_uid` (UUID, хранится в `localStorage`), URL страницы и referrer и отправляет POST на `/api/track`.
3. На сервере `App\Actions\Tracking\RecordVisit` сохраняет `Visit` (IP, UA, device/browser/OS через `jenssegers/agent`) и ставит в очередь `ResolveVisitGeoJob` для геолокации через `ip-api.com`.
4. На странице `/sites/{site}/stats` рендерятся два графика LayerChart: горизонтальная гистограмма уникальных посещений по часам и круговая диаграмма по городам.

### Деплой на VPS (Ubuntu 24.04, кратко)

Минимальный стек: nginx + PHP 8.5-FPM + Postgres 18 + supervisor.

```bash
# 1. Пакеты
sudo apt update
sudo apt install -y nginx postgresql-18 supervisor certbot python3-certbot-nginx \
    php8.5-fpm php8.5-cli php8.5-pgsql php8.5-mbstring php8.5-xml php8.5-curl \
    php8.5-bcmath php8.5-intl php8.5-redis composer

# 2. Код
sudo mkdir -p /var/www/statlite && sudo chown $USER:$USER /var/www/statlite
git clone <repo-url> /var/www/statlite
cd /var/www/statlite
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Окружение
cp .env.example .env
php artisan key:generate
# в .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://stats.example.com,
# DB_HOST=127.0.0.1, DB_PORT=5432, DB_DATABASE=statlite, DB_USERNAME=..., DB_PASSWORD=...,
# QUEUE_CONNECTION=database, CACHE_STORE=database, SESSION_SECURE_COOKIE=true

# 4. Postgres
sudo -u postgres psql -c "CREATE USER statlite WITH PASSWORD '...';"
sudo -u postgres psql -c "CREATE DATABASE statlite OWNER statlite;"
php artisan migrate --force

# 5. nginx vhost — /etc/nginx/sites-available/statlite:
#   server_name stats.example.com;
#   root /var/www/statlite/public;
#   index index.php;
#   location / { try_files $uri $uri/ /index.php?$query_string; }
#   location /tracker.js {
#       add_header Access-Control-Allow-Origin *;
#       add_header Cache-Control "public, max-age=300";
#   }
#   location ~ \.php$ { fastcgi_pass unix:/var/run/php/php8.5-fpm.sock; ... }
sudo ln -s /etc/nginx/sites-available/statlite /etc/nginx/sites-enabled/
sudo certbot --nginx -d stats.example.com
sudo systemctl reload nginx

# 6. Supervisor для очереди — /etc/supervisor/conf.d/statlite-queue.conf:
#   [program:statlite-queue]
#   command=php /var/www/statlite/artisan queue:work --queue=default --tries=3 --timeout=60
#   autostart=true autorestart=true user=www-data numprocs=1
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start statlite-queue:*

# 7. Trusted proxies — в bootstrap/app.php добавить
#    $middleware->trustProxies(at: '*');
#    чтобы request->ip() возвращал реальный IP клиента из X-Forwarded-For.
```

### Архитектурные точки

- Доменная модель: `Site` (1) ↔ (N) `Visit`. `device_type` — нативный PG `ENUM`.
- Слой Actions: каждый Action имеет один публичный метод `__invoke(SomeData $data)`, привязка через `#[Bind]` на контракте.
- Stats-Actions считают `count(distinct visitor_uid)` для часов и группировку по `city` для круговой диаграммы; пустые часы заполняются нулями.
- Защита от подделки хитов: throttle `120 req/min/IP` (`api/track`).
- CSRF: `api/track` исключён из `validateCsrfTokens`.
- CORS: настроен в `config/cors.php` для пути `api/track` (allowed_origins `*`).
- Тесты: 18 фич-тестов покрывают tracking endpoint, geo-resolve (с `Http::fake`), CRUD сайтов, политику доступа и stats-эндпоинты.

## Решение типовых проблем

- **«Docker daemon недоступен»** — запустите Docker Desktop / `systemctl start docker` и повторите.
- **«Не найден ./vendor/bin/sail»** — выполните `composer install` на хосте.
- **Порт занят** — смените `APP_PORT` или `FORWARD_*_PORT` в `.env`, затем `down` → `up -d`.
- **БД «не дождались»** — Tower продолжит работу, но миграции могут упасть. Подождите 10–20 секунд и повторите шаг.
- **Ошибки прав на `storage/` или `bootstrap/cache/`** — `./vendor/bin/sail shell` → `chown -R sail:sail storage bootstrap/cache`.
