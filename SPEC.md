# Спецификация: счётчик посещений со статистикой за авторизацией

## Постановка задачи

Реализовать счётчик посещений страницы. Решение состоит из двух компонентов:

1. **JS-скрипт**, который подключается к любому сайту, собирает данные о посетителе (IP, город, устройство) и отправляет их на сервер.
2. **Бэк**, который хранит данные в БД и предоставляет UI-страницу со статистикой:
   - график посещений по часам — по оси X число уникальных посещений за час, по оси Y — время;
   - круговая диаграмма с разбиением по городам.

Просмотр статистики оформлен как страница за авторизацией.

## Архитектура

### 1. JS-трекер

- Файл: `public/tracker.js` — vanilla JS без зависимостей, обслуживается статикой.
- Подключение на любом стороннем сайте:
  ```html
  <script async src="https://stats.example.com/tracker.js"
          data-site-id="<public_id>"
          data-endpoint="https://stats.example.com/api/track"></script>
  ```
- Сбор данных на клиенте:
  - стабильный `visitor_uid` в `localStorage` (UUID v4, генерируется через `crypto.randomUUID` либо fallback);
  - `page_url` из `location.href`;
  - `referrer` из `document.referrer`.
- IP, город, страна, регион, устройство (desktop/mobile/tablet/bot), браузер, ОС определяются на сервере по `Request::ip()` и `User-Agent` (см. ниже) — клиент их не отсылает.
- Транспорт: `navigator.sendBeacon` с фолбэком на `fetch` (`keepalive: true`, `credentials: 'omit'`).
- Полезная нагрузка POST `/api/track`:
  ```json
  {
    "public_id": "<строка 16 lowercase>",
    "visitor_uid": "<uuid>",
    "page_url": "...",
    "referrer": "..."
  }
  ```

### 2. Бэк

#### Хранение

- СУБД: PostgreSQL (Sail/`compose.yaml`); миграции совместимы со стандартным Eloquent — допустима замена на SQLite/MySQL, специфичных PG-фич нет, кроме enum'а (см. ниже).
- Таблицы:
  - `sites` (`id`, `user_id`, `name`, `domain`, `public_id` UNIQUE, timestamps) — каждый сайт имеет владельца (User) и публичный 16-символьный идентификатор `public_id` для встраивания.
  - `visits` (`id`, `site_id`, `visitor_uid` UUID, `ip` INET, `country_code`, `country`, `region`, `city`, `device_type` (PG enum: desktop|mobile|tablet|bot), `browser`, `os`, `page_url`, `referrer`, `user_agent`, `occurred_at` TIMESTAMPTZ, `geo_resolved_at` TIMESTAMPTZ, timestamps).
  - Индексы: `(site_id, occurred_at)`, `(site_id, city)`, `(site_id, visitor_uid, occurred_at)`.

#### Приём визитов

- Маршрут: `POST /api/track` (без CSRF, с лимитером `throttle:track`).
- Контроллер: `App\Http\Controllers\Api\TrackController`.
- Сценарий: `App\Actions\Tracking\RecordVisit` (`#[Bind]` на `RecordsVisit`):
  - резолвит сайт по `public_id`; если нет — `null`/2xx без записи (трекер не должен ронять страницу);
  - парсит `User-Agent` (`jenssegers/agent`) → `device_type`, `browser`, `os`;
  - если `visitor_uid` не пришёл — детерминированный fallback из `(site_id, ip, user_agent)` (`Uuid::uuid5`);
  - сохраняет визит, ставит `ResolveVisitGeoJob` в очередь.
- Геолокация: `App\Actions\Tracking\ResolveVisitGeo` (асинхронно из job'а) обогащает запись полями `country_code`, `country`, `region`, `city`, проставляет `geo_resolved_at`.

#### Статистика (агрегации)

- Action `App\Actions\Stats\GetHourlyVisits` (контракт `GetsHourlyVisits`, DTO `GetHourlyVisitsData`):
  - вход: `site_id`, `hours` (1..720), `timezone`;
  - выход: массив точек `{hour: ISO8601, uniques: int}` за `hours` часов до текущего, в указанной таймзоне; пустые часы заполняются нулями.
- Action `App\Actions\Stats\GetCityBreakdown` (контракт `GetsCityBreakdown`, DTO `GetCityBreakdownData`):
  - вход: `site_id`, `hours`, `top` (1..50);
  - выход: массив `{city, visits}`, отсортированный по убыванию; всё, что за пределами top, агрегируется в строку `Прочее`.

#### HTTP API статистики

- Все эндпоинты — за `auth + verified`, авторизация по `SitePolicy::view` (владельцем сайта).
- `GET /sites/{site}/stats` — Inertia-страница.
- `GET /sites/{site}/stats/hourly?hours=&timezone=` → JSON `{ data: [...] }`.
- `GET /sites/{site}/stats/cities?hours=&top=` → JSON `{ data: [...] }`.

### 3. UI

- Стек: Inertia + Svelte 5 + Tailwind 4 + shadcn-svelte; графики — `layerchart` (`BarChart`, `PieChart`).
- Список сайтов: `resources/js/pages/sites/Index.svelte` — карточка сайта; клик по карточке открывает drawer (`SiteStatsDrawer.svelte`) со статистикой.
- Drawer (`resources/js/components/SiteStatsDrawer.svelte`):
  - селектор периода: 24 часа / 7 дней / 30 дней;
  - **график посещений по часам** — `BarChart` с `orientation="horizontal"`, X = `uniques`, Y = `hour` (формат `dd.MM HH:mm`, ru-RU);
  - **круговая диаграмма по городам** — `PieChart` с легендой; центральная дырка `innerRadius=60`;
  - подгружает `/sites/{id}/stats/hourly` и `/sites/{id}/stats/cities` параллельно через `fetch` + `AbortController`; повторный запрос при смене периода отменяет предыдущий.
- Отдельная страница `resources/js/pages/sites/Stats.svelte` с теми же двумя графиками — постоянная ссылка на статистику сайта.

### 4. Авторизация

- Аутентификация — Laravel Fortify (`App\Providers\FortifyServiceProvider`); UI-страницы Inertia в `resources/js/pages/auth/`.
- Все маршруты управления сайтами и просмотра статистики — `Route::middleware(['auth', 'verified'])` (`routes/web.php`).
- Доступ к конкретному сайту — `App\Policies\SitePolicy` (`view`/`update`/`delete`: `$site->user_id === $user->id`).
- Сидер `Database\Seeders\SystemSiteSeeder` идемпотентно создаёт тестового пользователя `test@example.com` / пароль `test` и привязанный к нему системный сайт (`config('stats.self_site_public_id')`) — `id=1` на свежей БД.
- В окружении `local`/`testing` форма логина показывает подсказку с этими учётными данными (`demoCredentials` prop).

## Соответствие реализации

| Требование                                            | Файл/символ                                                              |
| ----------------------------------------------------- | ------------------------------------------------------------------------ |
| JS-трекер встраивается на любой сайт                  | `public/tracker.js`                                                      |
| Сбор IP, города, устройства                           | `app/Actions/Tracking/{RecordVisit,ResolveVisitGeo}.php`                 |
| Хранение в БД                                         | `database/migrations/2026_05_09_170000_create_sites_table.php`, `…170100_create_visits_table.php` |
| Приём визитов                                         | `app/Http/Controllers/Api/TrackController.php`, `routes/web.php`         |
| График посещений по часам (X — uniques, Y — время)    | `app/Actions/Stats/GetHourlyVisits.php`, `resources/js/components/SiteStatsDrawer.svelte` |
| Круговая диаграмма по городам                         | `app/Actions/Stats/GetCityBreakdown.php`, `resources/js/components/SiteStatsDrawer.svelte` |
| Страница статистики за авторизацией                   | `routes/web.php` (группа `auth + verified`), `resources/js/pages/sites/{Index,Stats}.svelte` |
| Учётка для входа                                      | `database/seeders/SystemSiteSeeder.php`                                  |

## Acceptance criteria

1. Чужой сайт, подключив `<script src=".../tracker.js" data-site-id=… data-endpoint=…>`, фиксирует визит в `visits` без блокировки своей страницы (sendBeacon + async).
2. После создания сайта во вкладке «Сайты» владелец видит в drawer'е графики по своим визитам; чужие сайты недоступны (403 от `SitePolicy::view`).
3. Гистограмма по часам отображает уникальных посетителей в окне 24ч/7д/30д с заполненными нулями для пустых часов; ось X — число уникальных, ось Y — время.
4. Pie chart показывает топ-10 городов и агрегат «Прочее» для остальных.
5. `composer ci:check` (Pint, ESLint, Prettier, svelte-check, Pest) проходит зелёным.

## Вне скоупа

- Реал-тайм поток событий (используется polling/перерисовка drawer при смене периода).
- Многопроектные RBAC-роли — есть только владелец сайта.
- Cookie-less точная атрибуция; `visitor_uid` хранится в `localStorage` и при его очистке посетитель считается новым.

---

# Дополнительный модуль: шутки

Главная страница (`/`) — публичная лента шуток, тянутых из внешнего API. Модуль независим от счётчика и хранится в собственной таблице.

## Постановка задачи

- По расписанию подтягивать случайную шутку из внешнего источника (Random Joke API), сохранять в локальную БД.
- Показывать ленту на главной: бесконечная прокрутка по курсору, авто-подгрузка свежих записей без перезагрузки страницы.
- Отдавать все шутки JSON-ом по публичному API.
- Панчлайн каждой шутки скрыт по умолчанию — раскрывается по клику (UX «scratch-to-reveal»).

## Архитектура

### Модель и хранение

- Таблица `jokes` (`database/migrations/2026_05_09_093457_create_jokes_table.php`):
  - `id` — внутренний;
  - `external_id` (`unsigned bigint`, nullable, **unique**) — id из внешнего API; уникальность защищает от дублей при повторных fetch'ах;
  - `type` (string), `setup` (text), `punchline` (text);
  - timestamps.
- `App\Models\Joke` — `Filterable` (eloquent-filter), `#[Fillable(['external_id','type','setup','punchline'])]`, кастует `external_id` в `integer`.
- Фильтр `App\ModelFilters\JokeFilter` — единственный публичный метод `idAfter(int $value)` для запросов «всё новее заданного id»; ручные `where` вне фильтра в List-Action'ах запрещены конвенцией репозитория.

### Слой Action'ов (CRUD-набор)

Все Action'ы — единственный публичный метод `__invoke(<Data> $data)`, контракт связывает реализацию через `#[Bind]`. Каждому Action'у — свой DTO в `app/Data/Jokes/`.

| Action          | Контракт          | DTO                  | Возврат                 | Назначение                                       |
| --------------- | ----------------- | -------------------- | ----------------------- | ------------------------------------------------ |
| `CreateJoke`    | `CreatesJoke`     | `CreateJokeData`     | `Joke`                  | Сохраняет шутку; маппит `id` внешнего API → `external_id` через `CreateJokeData::fromArray`. |
| `GetJoke`       | `GetsJoke`        | `GetJokeData`        | `Joke`                  | Показ одной записи.                              |
| `UpdateJoke`    | `UpdatesJoke`     | `UpdateJokeData`     | `Joke`                  | Полное обновление полей.                         |
| `DeleteJoke`    | `DeletesJoke`     | `DeleteJokeData`     | `void`                  | Удаление по id.                                  |
| `IndexJokes`    | `IndexesJokes`    | `IndexJokesData`     | `Collection<Joke>`      | Полный список (для JSON-API), сортировка по `created_at desc, id desc`. |
| `ListJokes`     | `ListsJokes`      | `ListJokesData`      | `CursorPaginator<Joke>` | Курсорная пагинация для ленты; принимает опциональный `after` (см. фильтр `idAfter`) и `per_page` (1..50). |

### Внешняя интеграция и расписание

- Контракт `App\Contracts\Integrations\FetchesRandomJoke` (`#[Bind(RandomJokeIntegration::class)]`) — `__invoke(): array` возвращает payload внешнего API.
- Реализация `App\Integrations\RandomJokeIntegration`:
  - читает `services.random_joke.endpoint` и `services.random_joke.timeout` из конфига (`config/services.php`); endpoint по умолчанию `https://official-joke-api.appspot.com/random_joke`;
  - `Http::timeout($t)->acceptJson()->get($endpoint)->throw()` — ошибки проваливаются (никаких заглушек);
  - кидает `RuntimeException`, если конфиг не задан или ответ не массив.
- Команда `php artisan app:random-joke` (`App\Console\Commands\RandomJokeCommand`):
  - инжектит `FetchesRandomJoke` и `CreatesJoke`, делает `CreateJokeData::from($fetchesRandomJoke())`, сохраняет, печатает setup/punchline в stdout;
  - расписание (`routes/console.php`) — каждые 5 минут с `withoutOverlapping()`, лог в `storage/logs/scheduler.log`.
- Уникальный индекс по `external_id` гарантирует идемпотентность: при повторном получении той же шутки `CreateJoke` упадёт на нарушении уникальности (это feature, не bug).

### HTTP

- `GET /` (public) → `JokesController::index` → Inertia-страница `Welcome`. Передаёт:
  - `jokes` — `Inertia::scroll(...)` поверх `ListsJokes` с `per_page: 10`, прокидывая через `JokeResource`;
  - `latest` — `Inertia::optional(...)` для partial reload: при наличии query-параметра `after` отдаёт до 50 шуток новее указанного id;
  - `selfCounter` — счётчик системного сайта (см. основной раздел спеки).
- `GET /api/jokes` (public) → `Api\JokesController::index` → JSON-массив всех шуток через `IndexesJokes` + `JokeResource`. Имя маршрута: `api.jokes.index`.
- `JokeResource` намеренно скрывает `external_id` и `updated_at`; экспонирует `id`, `type`, `setup`, `punchline`, `created_at` (ISO 8601).

### UI

- `resources/js/pages/Welcome.svelte` (full-bleed, без `AppLayout`):
  - `<InfiniteScroll data="jokes" buffer={400}>` — Inertia 2 cursor-пагинация автоматически.
  - Polling каждые 45 секунд: `router.reload({ only: ['latest', 'selfCounter'], data: { after: newestId } })`. Polling приостанавливается при `document.visibilityState !== 'visible'`.
  - Свежие шутки префиксуются к локальному `prepended` массиву, дедупликация по `id` (через `Set`); `aria-live="polite"` объявление с русской плюрализацией («Появилась 1 новая шутка», «Появилось 2 новые шутки», «Появилось 5 новых шуток»).
  - Skeleton'ы при подгрузке, экран «Это всё, что у нас есть» в конце ленты, плашка «Пока пусто, скоро прилетит» при пустом списке.
  - Шапка содержит ссылку на API (`GET /api/jokes`) и навигацию (Login/Register или Dashboard).
- `resources/js/components/JokeCard.svelte` — `<article aria-label>` + Card с Badge (`type`) и `JokeScratch` для панчлайна.
- `resources/js/components/JokeScratch.svelte` — `<button type="button" aria-expanded>` с `revealed = $state(false)`. По клику переключает; в скрытом состоянии текст замазан, sr-only сообщает «Панчлайн скрыт, нажмите чтобы показать» / «Панчлайн раскрыт».

### Конфигурация

```dotenv
RANDOM_JOKE_ENDPOINT=https://official-joke-api.appspot.com/random_joke
RANDOM_JOKE_TIMEOUT=30
```

Читаются только в `config/services.php`. В Action'ах/командах — `config('services.random_joke.*')`.

## Соответствие реализации

| Требование                                         | Файл/символ                                                                |
| -------------------------------------------------- | -------------------------------------------------------------------------- |
| Хранение шуток                                     | `database/migrations/2026_05_09_093457_create_jokes_table.php`, `app/Models/Joke.php` |
| Подтяжка случайной шутки из внешнего API           | `app/Integrations/RandomJokeIntegration.php`, `app/Contracts/Integrations/FetchesRandomJoke.php` |
| Запуск по расписанию                               | `app/Console/Commands/RandomJokeCommand.php`, `routes/console.php` (every 5 minutes) |
| CRUD-сценарии                                      | `app/Actions/Jokes/*.php`, контракты — `app/Contracts/Actions/Jokes/*.php`, DTO — `app/Data/Jokes/*.php` |
| Фильтрация лент                                    | `app/ModelFilters/JokeFilter.php` (через `tucker-eric/eloquentfilter`)     |
| Лента на главной (cursor + polling latest)         | `app/Http/Controllers/JokesController.php`, `resources/js/pages/Welcome.svelte` |
| Публичный JSON-API                                 | `app/Http/Controllers/Api/JokesController.php`, маршрут `api.jokes.index`  |
| Resource-форма ответа                              | `app/Http/Resources/JokeResource.php`                                      |
| UI карточки и scratch                              | `resources/js/components/JokeCard.svelte`, `resources/js/components/JokeScratch.svelte` |
| Тесты                                              | `tests/Feature/Actions/Jokes/*Test.php`, `tests/Feature/Api/JokesIndexTest.php`, `tests/Feature/Console/RandomJokeCommandTest.php`, `tests/Unit/Integrations/RandomJokeIntegrationTest.php`, `tests/Feature/HomePageTest.php` |

## Acceptance criteria (модуль шуток)

1. `php artisan app:random-joke` подтягивает шутку из внешнего API и сохраняет её; повторный запуск с той же `external_id` не создаёт дубликат (срабатывает unique-индекс).
2. `GET /` отдаёт первые 10 шуток по убыванию `created_at`; докрутка ленты подгружает следующие страницы по cursor; partial reload `?after=<id>` возвращает только записи новее.
3. Polling раз в 45 секунд пополняет ленту без перезагрузки страницы; новые шутки префиксуются и анонсируются screen reader'у с правильной русской плюрализацией.
4. `GET /api/jokes` возвращает JSON-массив всех шуток; `external_id` и `updated_at` в ответе отсутствуют.
5. Панчлайн скрыт до клика; повторный клик скрывает обратно; aria-expanded и sr-only текст соответствуют состоянию.
