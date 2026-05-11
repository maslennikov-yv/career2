# CLAUDE.md

Этот файл содержит указания для Claude Code (claude.ai/code) при работе с кодом в этом репозитории.

## Стек

Laravel 13 + Fortify (аутентификация) + Inertia.js на стороне сервера, Svelte 5 на стороне клиента. Фронтенд использует shadcn-svelte (стиль `new-york-v4`, базовый цвет `neutral`, иконки `lucide`), Tailwind 4 (через `@tailwindcss/vite`) и Wayfinder (типизированные маршруты/действия/формы, генерируемые в JS). Тесты через Pest 4. Линтинг PHP через Pint (пресет Laravel). Локальный стек запускается в Laravel Sail с Postgres 18, Redis и Mailpit.

## Команды

Выбирайте раннер в соответствии с тем, как работает разработчик: нативный PHP (`composer …`, `php artisan …`) или Sail (`./vendor/bin/sail …`). Не смешивайте — PHP в Sail имеет версию 8.5 внутри контейнера; хостовый PHP может быть старее.

### Повседневные
- `npm run tower` — интерактивный CLI-помощник (`tower.mjs`) поверх Sail: первичная настройка проекта, управление контейнерами, миграции/сидеры, тесты, Pint/ESLint/Prettier, артефакты сборки. Самый быстрый путь поднять локалку.
- `composer dev` — запускает 4 параллельных процесса (`artisan serve`, `queue:listen`, `pail`, `vite`). Используйте это для нативной разработки вместо запуска по отдельности.
- `./vendor/bin/sail up -d` / `./vendor/bin/sail down` — эквивалент в Sail. Приложение на `http://localhost:${APP_PORT:-80}`, Vite на `5173`, UI Mailpit на `http://localhost:8025`.
- `npm run dev` — только Vite (когда Laravel обслуживается отдельно).
- `npm run build` / `npm run build:ssr` — production-сборка (вариант SSR собирает дважды).

### Контроль качества
- `composer test` — выполняет `config:clear`, `pint --parallel --test`, затем `artisan test`.
- `composer ci:check` — полный пайплайн: `lint:check` + `format:check` + `types:check` + `test`. Зеркалит то, что делает CI.
- `composer lint` / `composer lint:check` — Pint write / dry-run.
- `npm run lint` / `npm run lint:check` — ESLint write / dry-run (плоская конфигурация в `eslint.config.js`).
- `npm run format` / `npm run format:check` — Prettier (с плагинами Tailwind + Svelte).
- `npm run types:check` — `svelte-check` относительно `tsconfig.json`.

### Запуск отдельного теста
- `./vendor/bin/pest tests/Feature/DashboardTest.php` — отдельный файл.
- `./vendor/bin/pest --filter='it redirects unauthenticated users'` — по имени.
- `./vendor/bin/pest --group=auth` — по аннотации группы.
- Sail: добавьте префикс `./vendor/bin/sail` (например, `./vendor/bin/sail pest --filter=…`).

### Тестовое окружение
`phpunit.xml` переопределяет окружение на in-memory драйверы (`array` для cache/session/mail, очередь `sync`, `DB_DATABASE=testing`). `tests/Pest.php` глобально расширяет `Tests\TestCase` и подключает `RefreshDatabase` ко всему набору `Feature` (`pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`). Дополнительно подключать `RefreshDatabase` пофайлово не нужно. Образ Postgres инициализирует базу `testing` через `vendor/laravel/sail/database/pgsql/create-testing-database.sql`.

## Архитектура

### Разрешение страниц Inertia + Svelte
`resources/js/app.ts` выбирает Svelte-лейаут на основе префикса имени страницы, возвращаемого сервером:
- `Welcome` → без лейаута (full-bleed маркетинговая страница).
- `auth/*` → `AuthLayout`.
- `settings/*` → вложенный `[AppLayout, SettingsLayout]`.
- всё остальное → `AppLayout`.

Это значит, что новые страницы достаточно разместить под нужным `resources/js/pages/<prefix>/…`, и Inertia автоматически обернёт их. Path alias `@/*` → `./resources/js/*`.

### Разделение маршрутов
`routes/web.php` — это публичная/dashboard-поверхность; он `require`-ит `routes/settings.php` (маршруты пользовательских настроек, защищённые auth + verified-email — profile/password/appearance). Добавляйте новые маршруты настроек в `settings.php`, а не в `web.php`. Health check находится по `/up` (настроен в `bootstrap/app.php`).

### Auth = Fortify, а не стандартный scaffolding
Классов `Auth\…Controller` нет. `App\Providers\FortifyServiceProvider` подключает каждый view Fortify к Inertia-рендерингу и направляет создание пользователя / сброс пароля в `App\Actions\Fortify\{CreateNewUser,ResetUserPassword}`. Правила валидации, общие для создания и сброса, живут в `App\Concerns\PasswordValidationRules` (и `ProfileValidationRules` для обновления профиля). Кастомные rate limiter'ы `login` и `two-factor` также регистрируются там.

Чтобы изменить поведение аутентификации, редактируйте Action-классы или провайдер — не добавляйте новый auth-контроллер.

### Inertia shared data + middleware
`App\Http\Middleware\HandleInertiaRequests` шарит props в каждом Inertia-ответе. `HandleAppearance` читает cookie `appearance` (light/dark/system); cookies `appearance` и `sidebar_state` исключены из шифрования cookies Laravel в `bootstrap/app.php`, чтобы клиент мог читать их напрямую. `AddLinkHeadersForPreloadedAssets` добавлен в web-стек для предзагрузки ассетов.

### Wayfinder (генерируемые фронтенд-биндинги)
Плагин `@laravel/vite-plugin-wayfinder` (с `formVariants: true`) перегенерирует `resources/js/actions/`, `resources/js/routes/` и `resources/js/wayfinder/` при dev/build. **Все три директории добавлены в gitignore** — никогда не редактируйте вручную; меняйте PHP-маршруты/контроллеры и позволяйте Vite перегенерировать. Импортируйте из `@/actions/...` или `@/routes/...` для типобезопасных URL и form-хелперов в Svelte.

### UI-компоненты
Компоненты shadcn-svelte живут в `resources/js/components/ui/<name>/`. Добавляйте новые через CLI shadcn, а не вручную — `components.json` является источником истины для путей/алиасов. Компоненты уровня приложения (не shadcn) располагаются непосредственно в `resources/js/components/`.

### Сервисы Sail
`compose.yaml` определяет `laravel.test` (собирается из `vendor/laravel/sail/runtimes/8.5`), `pgsql` (postgres:18-alpine), `redis`, `mailpit`. Имена хостов контейнеров совпадают с именами сервисов — в `.env` должно быть `DB_HOST=pgsql`, `REDIS_HOST=redis`, `MAIL_HOST=mailpit`. Mailpit перехватывает весь SMTP на `1025` и предоставляет UI на `8025`. Переопределяйте `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `FORWARD_MAILPIT_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT` в `.env`, чтобы избежать коллизий портов с хостом.

## Инженерные практики

Это правила, специфичные для проекта и проверенные относительно текущего кода. Соблюдайте их, а не вводите новые конвенции.

### Общее
- **Переиспользуй, а не дублируй.** Перед созданием нового хелпера/трейта/компонента сделайте grep по существующим — например, валидация пароля и профиля уже живёт в `App\Concerns\PasswordValidationRules` / `ProfileValidationRules` и переиспользуется как Form Request'ами, так и Fortify Action'ами. Дублирование этих правил — самая частая избегаемая ошибка здесь.
- **Никаких спекулятивных абстракций.** Одноразовая логика остаётся inline; три похожие строки лучше преждевременного трейта или сервиса. Если вы пишете базовый класс для одного-единственного наследника — остановитесь.
- **Никаких комментариев, объясняющих *что*.** Имена уже это делают. Добавляйте комментарий только тогда, когда *почему* неочевидно (workaround, скрытый инвариант, ограничение извне файла). Существующий код этому следует — поддерживайте такой стиль.
- **Не маскируйте ошибки.** Не добавляйте fallback'и, `try/catch`-«заглушки» или дефолтные значения, которые скрывают корень проблемы. Чините причину, а не симптом — пусть ошибка всплывёт там, где её можно осмысленно обработать или исправить.
- **Запускайте `composer ci:check` перед тем, как объявить готовность.** Это тот же гейт, что запускает CI.

### PHP
- **Используйте строгую типизацию.** Каждый PHP-файл начинается с `declare(strict_types=1);` сразу после `<?php`. Правило `declare_strict_types` включено в `pint.json`, поэтому Pint добавит директиву автоматически — но проще писать её сразу.
- **Указывайте типы параметров и возвращаемых значений** (соответствует существующему стилю). Используйте PHPDoc array shape вида `@param array<string, string> $input` (см. `App\Actions\Fortify\CreateNewUser`), когда массивы несут структуру.
- **Импорты вместо FQCN.** В коде используйте `use ...` и короткие имена классов; не пишите `\App\Models\User` или `\Illuminate\…` прямо в сигнатурах и теле методов. Исключения — конфиги/маршруты, где FQCN традиционно встречается в массивах привязок.
- **Не вызывайте `env()` вне `config/*.php`.** Кэширование конфигурации приведёт к тому, что `env()` вернёт `null` в жизненном цикле запроса. Читайте конфигурацию через `config('…')`.
- **Предпочитайте Eloquent сырым `DB::` запросам.** Опускайтесь до query builder/raw только тогда, когда выражение действительно невозможно выразить через Eloquent.
- **Не воюйте с Pint.** Пресет — `laravel`. Если форматирование кажется неправильным, исправление почти всегда — соответствовать пресету, а не переопределять его.

### Laravel
- **Маршрутизация валидации:**
  - HTTP write-эндпоинты → Form Request в `app/Http/Requests/...` (см. `Settings/ProfileUpdateRequest`).
  - Действия, вызываемые Fortify (создание пользователя, сброс пароля) → inline `Validator::make(...)->validate()` внутри Action-класса (см. `App\Actions\Fortify\CreateNewUser`).
  Не смешивайте два паттерна в рамках одного пути.
- **Изменения аутентификации идут через Fortify, никогда не через новый auth-контроллер.** Новые auth-экраны: зарегистрируйте Inertia view в `FortifyServiceProvider::configureViews()` и добавьте Svelte-страницу в `resources/js/pages/auth/`. Новое поведение на стороне auth (хуки регистрации, логика сброса пароля) идёт в Action-класс, подключённый в `configureActions()`.
- **Новые страницы — только Inertia.** `resources/views/` содержит лишь SPA-оболочку (`app.blade.php`). Используйте `Route::inertia('path', 'PageName')` для статических страниц или контроллер, возвращающий `Inertia::render(...)`, для динамических — не добавляйте Blade-views.
- **Всегда именуйте маршруты** (`->name(...)`). Wayfinder генерирует типизированные биндинги по именам маршрутов; неименованные маршруты невозможно чисто импортировать из `@/routes/...`.
- **Маршруты настроек живут в `routes/settings.php`**, а не в `routes/web.php`. Последний `require`-ит первый.
- **Допущения по очередям:** локальная очередь — `database`, тесты идут с `sync`. Не пишите feature-тесты, зависящие от асинхронности задач; если нужно проверить поведение dispatch'а, используйте `Queue::fake()`.
- **Никогда не редактируйте `resources/js/{actions,routes,wayfinder}/`** — регенерируется Vite на каждом dev/build. Меняйте PHP-маршрут/контроллер и перезапускайте.
- **Не вводите Sanctum/Passport.** Аутентификацией владеет Fortify.

### Слои и границы

- **Граница слоёв**: HTTP (`Controller` / `FormRequest`) → `Action` → `Model` / DB.
- **Контроллеры тонкие**: принять запрос, валидировать (через `FormRequest`), вызвать Action, вернуть HTTP-ответ.
- **Бизнес-логика и запросы к БД**: только в Actions; не в контроллерах, не в Form Request'ах, не в UI.
- **Модели**: структура, связи, касты, accessors/mutators; без доменных сценариев.
- **Инжект по контрактам**: зависеть от интерфейсов Actions, не от реализаций.

### Обязательные соглашения по Actions

- **У Action один публичный метод — `__invoke`.** Никаких `handle()`, `execute()` и прочих имён.
- **В `__invoke` ровно один параметр — DTO на базе `spatie/laravel-data`, уникальный для каждого Action.** Не передавайте отдельные скаляры, массивы или модели; не переиспользуйте один DTO между Action'ами — у каждого свой `Data`-класс, описывающий ровно его вход.
- **Связывайте интерфейс и реализацию через `#[Bind(...)]` на контракте.** Контейнер сам подберёт реализацию — не нужно писать привязки в `AppServiceProvider`.
- **Несколько операций с БД в одном сценарии — обернуть в `DB::transaction()`.** Иначе частичный сбой оставит несогласованное состояние.
- **Новые фичи проектируйте сначала через Action-сценарий**, потом подключайте HTTP/UI. HTTP-слой — это адаптер над уже работающим сценарием.
- **Исключение — `app/Actions/Fortify/`.** Эти Action'ы реализуют контракты Fortify (`CreatesNewUsers`, `ResetsUserPasswords` и т.п.) с фиксированными сигнатурами (`create(array $input)`, `reset(User $user, array $input)`) — они подчиняются API Fortify, а не нашим внутренним соглашениям. Не приводите их к `__invoke` + DTO и не вводите для них `#[Bind(...)]`; привязки делает сам Fortify через `Fortify::createUsersUsing(...)` / `resetUserPasswordsUsing(...)`.

### Фильтрация, роуты, enum и конфиг

- **Фильтрация списков** — только через `tucker-eric/eloquentfilter`. Соглашения по `Model::filter` и List-Actions описаны в гайде репозитория; ручные фильтрующие `where` в List-Actions запрещены.
- **Имена маршрутов**: соглашения об именах по зонам API и web (например, единый префикс для API) зафиксированы в репозитории; не смешивайте соглашения между зонами.
- **Enum в PostgreSQL**: для enum-колонок используйте нативный PG `ENUM`, а не `string`. Нюансы миграций (создание/изменение/удаление типа, ALTER TYPE) — в гайде по enum для этого репозитория.
- **Конфигурация**: значения окружения только через `.env` и `config/*`. Магические значения в код не хардкодить.
- **Ошибки конфига**: обязательные настройки должны приводить к явной ошибке старта, а не к «тихому» дефолту. Падать рано — лучше, чем работать неправильно.

### Svelte 5
- **Используйте runes для нового кода.** `$state`, `$derived`, `$effect`, `$props` — см. `resources/js/lib/theme.svelte.ts` и `resources/js/layouts/auth/*` для паттернов. **Не** пишите `export let`, `writable(...)` или `readable(...)` в коде приложения. Единственное место, где `export let` законно появляется, — `resources/js/components/ui/` (vendored shadcn-svelte) — эту директорию не трогайте.
- **`$derived` для вычисляемых значений, `$effect` только для настоящих сайд-эффектов** (DOM, подписки, interop). Если можно выразить через `$derived` — делайте.
- **Не мутируйте значения `$props()`.** Деструктурируйте с дефолтами; общайтесь обратно через callback-пропсы.
- **Snippets, не slots.** Компоненты Svelte 5 композируются через `{#snippet}` / `{@render …}`. Не добавляйте `<slot />` в новые компоненты.
- **`bind:` только для настоящего двустороннего владения** (form inputs, биндящиеся к локальному состоянию). Для однонаправленного потока данных передавайте props вниз и события/коллбэки вверх.
- **Используйте Inertia `<Link>` для внутренней навигации**, не `<a href>` — `<a>` вызывает полную перезагрузку страницы и обходит Inertia visit lifecycle. Импорты — из `@inertiajs/svelte`.
- **Используйте Wayfinder для URL и form actions.** Импортируйте из `@/routes/...` или `@/actions/...` вместо написания путей строками — в этом и весь смысл сгенерированных биндингов.
- **Файловые конвенции:** компоненты — PascalCase `.svelte`. Реактивные хелперы (всё, что использует runes вне компонента) идут в файлы `.svelte.ts`, чтобы их обработал компилятор Svelte — файлы `.ts` не получают поддержки runes.

## CI

`.github/workflows/tests.yml` запускает Pest на PHP 8.3, 8.4 и 8.5 (Node 22). `.github/workflows/lint.yml` запускает `composer lint` (Pint), `npm run format`, `npm run lint` на PHP 8.4. Оба триггерятся на `develop` и `main`. `composer ci:check` локально воспроизводит lint+test часть.
