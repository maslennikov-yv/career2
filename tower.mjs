#!/usr/bin/env node
// Tower — интерактивный CLI для управления локальным окружением проекта.
// Запуск: npm run tower

import { existsSync, readFileSync, copyFileSync } from 'node:fs';
import { basename } from 'node:path';
import { setTimeout as sleep } from 'node:timers/promises';
import * as p from '@clack/prompts';
import { execa } from 'execa';
import pc from 'picocolors';

process.on('SIGINT', () => {});

const SAIL = './vendor/bin/sail';
const APP_SERVICE = 'laravel.test';
const DB_SERVICE = 'pgsql';
const PROJECT = basename(process.cwd())
    .toLowerCase()
    .replace(/[^a-z0-9_-]/g, '');

async function runSail(args, { spinnerLabel } = {}) {
    if (spinnerLabel) {
        const s = p.spinner();
        s.start(spinnerLabel);
        const r = await execa(SAIL, args, { reject: false, stdio: 'pipe' });

        if (r.exitCode === 0) {
            s.stop(pc.green('✓ ') + spinnerLabel);
        } else {
            s.stop(pc.red('✗ ') + spinnerLabel, r.exitCode);

            if (r.stderr) {
                p.log.error(r.stderr.trim().split('\n').slice(-10).join('\n'));
            } else if (r.stdout) {
                p.log.error(r.stdout.trim().split('\n').slice(-10).join('\n'));
            }
        }

        return r;
    }

    return execa(SAIL, args, { reject: false, stdio: 'inherit' });
}

async function dockerAlive() {
    const r = await execa('docker', ['info'], {
        reject: false,
        stdio: 'ignore',
    });

    return r.exitCode === 0;
}

async function composerInstallViaDocker() {
    const args = ['run', '--rm', '-v', `${process.cwd()}:/app`, '-w', '/app'];

    if (process.platform !== 'win32') {
        args.push('-u', `${process.getuid()}:${process.getgid()}`);
    }

    args.push('composer:2', 'install');

    const s = p.spinner();
    s.start('composer install (через docker-образ composer:2)');
    const r = await execa('docker', args, { reject: false, stdio: 'pipe' });

    if (r.exitCode === 0) {
        s.stop(pc.green('✓ ') + 'composer install');
    } else {
        s.stop(pc.red('✗ ') + 'composer install', r.exitCode);

        if (r.stderr) {
            p.log.error(r.stderr.trim().split('\n').slice(-10).join('\n'));
        } else if (r.stdout) {
            p.log.error(r.stdout.trim().split('\n').slice(-10).join('\n'));
        }
    }

    return r.exitCode === 0;
}

async function listRunningServices() {
    const r = await execa(
        'docker-compose',
        ['ps', '--services', '--filter', 'status=running'],
        { reject: false },
    );

    if (r.exitCode !== 0) {
        return [];
    }

    return r.stdout
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean);
}

async function isUp() {
    return (await listRunningServices()).includes(APP_SERVICE);
}

async function waitHealthy(service, timeoutMs = 60_000) {
    const start = Date.now();
    const container = `${PROJECT}-${service}-1`;

    while (Date.now() - start < timeoutMs) {
        const r = await execa(
            'docker',
            ['inspect', '-f', '{{.State.Health.Status}}', container],
            { reject: false },
        );
        const status = r.stdout?.trim();

        if (status === 'healthy') {
            return true;
        }

        if (!status) {
            return false;
        }

        await sleep(1500);
    }

    return false;
}

async function ensureUp() {
    if (!(await dockerAlive())) {
        p.log.error('Docker daemon недоступен. Запустите Docker и повторите.');

        return false;
    }

    if (await isUp()) {
        return true;
    }

    const ok = await p.confirm({
        message: 'Контейнеры не запущены. Поднять сейчас?',
        initialValue: true,
    });

    if (p.isCancel(ok) || !ok) {
        return false;
    }

    const r = await runSail(['up', '-d'], {
        spinnerLabel: 'Поднимаем контейнеры',
    });

    if (r.exitCode !== 0) {
        return false;
    }

    const sp = p.spinner();
    sp.start(`Ждём готовности ${DB_SERVICE}`);
    const healthy = await waitHealthy(DB_SERVICE);
    sp.stop(
        healthy
            ? pc.green('✓ ') + `${DB_SERVICE} готов`
            : pc.yellow('⚠ ') + `${DB_SERVICE} не дождались, продолжаем`,
    );

    return true;
}

function parseEnv(content) {
    const out = {};

    for (const line of content.split('\n')) {
        const m = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/);

        if (!m) {
            continue;
        }

        let val = m[2];

        if (
            (val.startsWith('"') && val.endsWith('"')) ||
            (val.startsWith("'") && val.endsWith("'"))
        ) {
            val = val.slice(1, -1);
        }

        out[m[1]] = val;
    }

    return out;
}

function readEnv() {
    if (!existsSync('.env')) {
        return {};
    }

    return parseEnv(readFileSync('.env', 'utf8'));
}

async function menu(title, items) {
    while (true) {
        const choice = await p.select({
            message: title,
            options: items.map(({ value, label, hint }) => ({
                value,
                label,
                hint,
            })),
        });

        if (p.isCancel(choice) || choice === 'back') {
            return;
        }

        const handler = items.find((i) => i.value === choice)?.handler;

        if (!handler) {
            continue;
        }

        try {
            await handler();
        } catch (e) {
            p.log.error(e?.message ?? String(e));
        }
    }
}

// ─── Первичная настройка ────────────────────────────────────────────────

async function setupFlow() {
    p.note(
        'Скопируем .env, сгенерируем APP_KEY, поднимем контейнеры,\nвыполним миграции и сидеры.',
        '🚀 Первичная настройка',
    );

    // .env
    if (existsSync('.env')) {
        const action = await p.select({
            message: '.env уже существует. Что делать?',
            options: [
                { value: 'keep', label: 'Оставить как есть' },
                { value: 'overwrite', label: 'Перезаписать из .env.example' },
                { value: 'cancel', label: 'Отменить настройку' },
            ],
            initialValue: 'keep',
        });

        if (p.isCancel(action) || action === 'cancel') {
            return;
        }

        if (action === 'overwrite') {
            copyFileSync('.env.example', '.env');
            p.log.success('.env перезаписан из .env.example');
        } else {
            p.log.info('.env оставлен без изменений');
        }
    } else if (existsSync('.env.example')) {
        copyFileSync('.env.example', '.env');
        p.log.success('.env создан из .env.example');
    } else {
        p.log.error('.env.example не найден — нечего копировать');

        return;
    }

    // Контейнеры — поднимаем заранее, чтобы дальнейшие команды шли через sail
    if (!(await ensureUp())) {
        p.log.warn('Без поднятых контейнеров продолжать не получится');

        return;
    }

    // APP_KEY
    const env = readEnv();

    if (!env.APP_KEY || env.APP_KEY === '' || env.APP_KEY === 'base64:') {
        await runSail(['artisan', 'key:generate'], {
            spinnerLabel: 'Генерируем APP_KEY',
        });
    } else {
        p.log.info('APP_KEY уже задан — пропускаем');
    }

    // Миграции
    const fresh = await p.confirm({
        message: 'Прогнать migrate:fresh (удалит существующие таблицы)?',
        initialValue: false,
    });

    if (p.isCancel(fresh)) {
        return;
    }

    if (fresh) {
        await runSail(['artisan', 'migrate:fresh'], {
            spinnerLabel: 'migrate:fresh',
        });
    } else {
        await runSail(['artisan', 'migrate', '--force'], {
            spinnerLabel: 'migrate',
        });
    }

    // Сидеры
    const seed = await p.confirm({
        message: 'Засеять данные (db:seed)?',
        initialValue: true,
    });

    if (!p.isCancel(seed) && seed) {
        await runSail(['artisan', 'db:seed', '--force'], {
            spinnerLabel: 'db:seed',
        });
    }

    // npm
    const installNpm = await p.confirm({
        message: 'Установить npm-зависимости?',
        initialValue: false,
    });

    if (!p.isCancel(installNpm) && installNpm) {
        await runSail(['npm', 'install']);
    }

    const buildAssets = await p.confirm({
        message: 'Собрать ассеты (npm run build)?',
        initialValue: false,
    });

    if (!p.isCancel(buildAssets) && buildAssets) {
        await runSail(['npm', 'run', 'build']);
    }

    showLinks();
    p.log.success('Готово. Локалка настроена.');
}

// ─── Управление окружением ─────────────────────────────────────────────

async function envUp() {
    if (!(await dockerAlive())) {
        p.log.error('Docker daemon недоступен');

        return;
    }

    await runSail(['up', '-d'], { spinnerLabel: 'sail up -d' });
}

async function envDown() {
    const ok = await p.confirm({
        message: 'Остановить и удалить контейнеры?',
        initialValue: true,
    });

    if (p.isCancel(ok) || !ok) {
        return;
    }

    await runSail(['down'], { spinnerLabel: 'sail down' });
}

async function envRestart() {
    await runSail(['restart'], { spinnerLabel: 'sail restart' });
}

async function envStatus() {
    if (!(await dockerAlive())) {
        p.log.error('Docker daemon недоступен');

        return;
    }

    await runSail(['ps']);
}

async function envLogs() {
    if (!(await ensureUp())) {
        return;
    }

    const choice = await p.select({
        message: 'Чьи логи показывать? (Ctrl+C — выход к меню)',
        options: [
            { value: 'all', label: 'Все сервисы' },
            { value: APP_SERVICE, label: 'laravel.test (приложение)' },
            { value: 'pgsql', label: 'pgsql' },
            { value: 'redis', label: 'redis' },
            { value: 'mailpit', label: 'mailpit' },
            { value: 'back', label: '← Назад' },
        ],
    });

    if (p.isCancel(choice) || choice === 'back') {
        return;
    }

    const args =
        choice === 'all'
            ? ['logs', '-f', '--tail', '100']
            : ['logs', '-f', '--tail', '100', choice];
    await runSail(args);
}

async function envMenu() {
    await menu('🐳 Окружение', [
        { value: 'up', label: '▶  Поднять (sail up -d)', handler: envUp },
        { value: 'down', label: '⏹  Остановить (sail down)', handler: envDown },
        { value: 'restart', label: '🔁 Перезапустить', handler: envRestart },
        { value: 'status', label: '📊 Статус сервисов', handler: envStatus },
        { value: 'logs', label: '📜 Логи', handler: envLogs },
        { value: 'links', label: '🌐 Полезные ссылки', handler: showLinks },
        { value: 'back', label: '← Назад' },
    ]);
}

// ─── База данных ───────────────────────────────────────────────────────

async function dbMenu() {
    if (!(await ensureUp())) {
        return;
    }

    await menu('🗄  База данных', [
        {
            value: 'migrate',
            label: 'migrate (применить миграции)',
            handler: () =>
                runSail(['artisan', 'migrate', '--force'], {
                    spinnerLabel: 'migrate',
                }),
        },
        {
            value: 'rollback',
            label: 'migrate:rollback (откатить последний батч)',
            handler: async () => {
                const ok = await p.confirm({
                    message: 'Откатить последний батч миграций?',
                    initialValue: false,
                });

                if (!p.isCancel(ok) && ok) {
                    await runSail(['artisan', 'migrate:rollback'], {
                        spinnerLabel: 'rollback',
                    });
                }
            },
        },
        {
            value: 'fresh',
            label: 'migrate:fresh (пересоздать таблицы)',
            handler: async () => {
                const ok = await p.confirm({
                    message: pc.red('Это удалит все таблицы. Продолжить?'),
                    initialValue: false,
                });

                if (!p.isCancel(ok) && ok) {
                    await runSail(['artisan', 'migrate:fresh'], {
                        spinnerLabel: 'migrate:fresh',
                    });
                }
            },
        },
        {
            value: 'fresh-seed',
            label: 'migrate:fresh --seed (пересоздать и засеять)',
            handler: async () => {
                const ok = await p.confirm({
                    message: pc.red(
                        'Это удалит все таблицы и засеет заново. Продолжить?',
                    ),
                    initialValue: false,
                });

                if (!p.isCancel(ok) && ok) {
                    await runSail(['artisan', 'migrate:fresh', '--seed'], {
                        spinnerLabel: 'migrate:fresh --seed',
                    });
                }
            },
        },
        {
            value: 'seed',
            label: 'db:seed (только сидеры)',
            handler: () =>
                runSail(['artisan', 'db:seed', '--force'], {
                    spinnerLabel: 'db:seed',
                }),
        },
        {
            value: 'psql',
            label: 'psql (открыть консоль Postgres)',
            handler: async () => {
                const env = readEnv();
                const user = env.DB_USERNAME || 'sail';
                const db = env.DB_DATABASE || 'laravel';
                p.log.info('Выход из psql: \\q');
                await runSail([
                    'exec',
                    DB_SERVICE,
                    'psql',
                    '-U',
                    user,
                    '-d',
                    db,
                ]);
            },
        },
        {
            value: 'tinker',
            label: 'artisan tinker',
            handler: async () => {
                p.log.info('Выход из tinker: exit');
                await runSail(['artisan', 'tinker']);
            },
        },
        { value: 'back', label: '← Назад' },
    ]);
}

// ─── Тесты ─────────────────────────────────────────────────────────────

async function testsMenu() {
    if (!(await ensureUp())) {
        return;
    }

    await menu('🧪 Тесты', [
        {
            value: 'all',
            label: 'Прогнать все',
            handler: () => runSail(['pest']),
        },
        {
            value: 'filter',
            label: 'По фильтру (имя теста)',
            handler: async () => {
                const filter = await p.text({
                    message: 'Подстрока в названии теста',
                    placeholder: 'redirects unauthenticated',
                });

                if (p.isCancel(filter) || !filter) {
                    return;
                }

                await runSail(['pest', `--filter=${filter}`]);
            },
        },
        {
            value: 'file',
            label: 'Один файл',
            handler: async () => {
                const path = await p.text({
                    message: 'Путь к файлу',
                    placeholder: 'tests/Feature/DashboardTest.php',
                });

                if (p.isCancel(path) || !path) {
                    return;
                }

                await runSail(['pest', path]);
            },
        },
        {
            value: 'coverage',
            label: 'С покрытием (--coverage)',
            handler: () => runSail(['pest', '--coverage']),
        },
        {
            value: 'parallel',
            label: 'Параллельно (--parallel)',
            handler: () => runSail(['pest', '--parallel']),
        },
        { value: 'back', label: '← Назад' },
    ]);
}

// ─── Качество кода ─────────────────────────────────────────────────────

async function qualityMenu() {
    if (!(await ensureUp())) {
        return;
    }

    await menu('✅ Качество и тесты', [
        {
            value: 'ci',
            label: '🚦 composer ci:check (полный CI)',
            handler: () => runSail(['composer', 'ci:check']),
        },
        {
            value: 'tests',
            label: '🧪 Тесты ▸',
            handler: testsMenu,
        },
        {
            value: 'pint',
            label: 'Pint — исправить',
            handler: () => runSail(['composer', 'lint']),
        },
        {
            value: 'pint-check',
            label: 'Pint — только проверка',
            handler: () => runSail(['composer', 'lint:check']),
        },
        {
            value: 'eslint',
            label: 'ESLint — исправить',
            handler: () => runSail(['npm', 'run', 'lint']),
        },
        {
            value: 'eslint-check',
            label: 'ESLint — только проверка',
            handler: () => runSail(['npm', 'run', 'lint:check']),
        },
        {
            value: 'prettier',
            label: 'Prettier — исправить',
            handler: () => runSail(['npm', 'run', 'format']),
        },
        {
            value: 'prettier-check',
            label: 'Prettier — только проверка',
            handler: () => runSail(['npm', 'run', 'format:check']),
        },
        {
            value: 'svelte',
            label: 'svelte-check (типы)',
            handler: () => runSail(['npm', 'run', 'types:check']),
        },
        { value: 'back', label: '← Назад' },
    ]);
}

// ─── Разработка ────────────────────────────────────────────────────────

async function devMenu() {
    if (!(await ensureUp())) {
        return;
    }

    await menu('🛠  Разработка', [
        {
            value: 'composer-dev',
            label: 'composer dev (serve+queue+pail+vite)',
            hint: 'Ctrl+C — стоп',
            handler: () => runSail(['composer', 'dev']),
        },
        {
            value: 'vite',
            label: 'Только Vite (npm run dev)',
            hint: 'Ctrl+C — стоп',
            handler: () => runSail(['npm', 'run', 'dev']),
        },
        {
            value: 'serve',
            label: 'Только artisan serve',
            hint: 'Ctrl+C — стоп',
            handler: () => runSail(['artisan', 'serve', '--host=0.0.0.0']),
        },
        {
            value: 'pail',
            label: 'artisan pail (логи Laravel)',
            hint: 'Ctrl+C — стоп',
            handler: () => runSail(['artisan', 'pail']),
        },
        {
            value: 'queue',
            label: 'artisan queue:listen',
            hint: 'Ctrl+C — стоп',
            handler: () => runSail(['artisan', 'queue:listen', '--tries=1']),
        },
        {
            value: 'shell',
            label: 'Шелл в контейнере приложения',
            handler: () => runSail(['shell']),
        },
        { value: 'back', label: '← Назад' },
    ]);
}

// ─── Зависимости ───────────────────────────────────────────────────────

async function depsMenu() {
    await menu('📦 Зависимости', [
        {
            value: 'composer',
            label: 'composer install',
            handler: async () => {
                if (await ensureUp()) {
                    await runSail(['composer', 'install']);
                }
            },
        },
        {
            value: 'npm',
            label: 'npm install',
            handler: async () => {
                if (await ensureUp()) {
                    await runSail(['npm', 'install']);
                }
            },
        },
        {
            value: 'both',
            label: 'composer install + npm install',
            handler: async () => {
                if (!(await ensureUp())) {
                    return;
                }

                await runSail(['composer', 'install']);
                await runSail(['npm', 'install']);
            },
        },
        {
            value: 'composer-update',
            label: 'composer update',
            handler: async () => {
                const ok = await p.confirm({
                    message: 'Обновить composer-зависимости?',
                    initialValue: false,
                });

                if (!p.isCancel(ok) && ok && (await ensureUp())) {
                    await runSail(['composer', 'update']);
                }
            },
        },
        {
            value: 'npm-update',
            label: 'npm update',
            handler: async () => {
                const ok = await p.confirm({
                    message: 'Обновить npm-зависимости?',
                    initialValue: false,
                });

                if (!p.isCancel(ok) && ok && (await ensureUp())) {
                    await runSail(['npm', 'update']);
                }
            },
        },
        { value: 'back', label: '← Назад' },
    ]);
}

// ─── Кеши ──────────────────────────────────────────────────────────────

async function cachesMenu() {
    if (!(await ensureUp())) {
        return;
    }

    await menu('🧹 Кеши и оптимизация', [
        {
            value: 'clear-all',
            label: 'optimize:clear (всё сразу)',
            handler: () =>
                runSail(['artisan', 'optimize:clear'], {
                    spinnerLabel: 'optimize:clear',
                }),
        },
        {
            value: 'config',
            label: 'config:clear',
            handler: () =>
                runSail(['artisan', 'config:clear'], {
                    spinnerLabel: 'config:clear',
                }),
        },
        {
            value: 'route',
            label: 'route:clear',
            handler: () =>
                runSail(['artisan', 'route:clear'], {
                    spinnerLabel: 'route:clear',
                }),
        },
        {
            value: 'view',
            label: 'view:clear',
            handler: () =>
                runSail(['artisan', 'view:clear'], {
                    spinnerLabel: 'view:clear',
                }),
        },
        {
            value: 'cache',
            label: 'cache:clear (application cache)',
            handler: () =>
                runSail(['artisan', 'cache:clear'], {
                    spinnerLabel: 'cache:clear',
                }),
        },
        {
            value: 'event',
            label: 'event:clear',
            handler: () =>
                runSail(['artisan', 'event:clear'], {
                    spinnerLabel: 'event:clear',
                }),
        },
        { value: 'back', label: '← Назад' },
    ]);
}

// ─── Обслуживание ──────────────────────────────────────────────────────

async function maintenanceMenu() {
    await menu('🧹 Обслуживание', [
        { value: 'deps', label: '📦 Зависимости ▸', handler: depsMenu },
        { value: 'caches', label: '🧹 Кеши Laravel ▸', handler: cachesMenu },
        {
            value: 'build',
            label: '🏗  Собрать ассеты (npm run build)',
            handler: async () => {
                if (await ensureUp()) {
                    await runSail(['npm', 'run', 'build']);
                }
            },
        },
        {
            value: 'build-ssr',
            label: '🏗  Собрать ассеты + SSR',
            handler: async () => {
                if (await ensureUp()) {
                    await runSail(['npm', 'run', 'build:ssr']);
                }
            },
        },
        { value: 'back', label: '← Назад' },
    ]);
}

// ─── Ссылки ────────────────────────────────────────────────────────────

function showLinks() {
    const env = readEnv();
    const appUrl = env.APP_URL || `http://localhost:${env.APP_PORT || '80'}`;
    const mailpit = `http://localhost:${env.FORWARD_MAILPIT_DASHBOARD_PORT || '8025'}`;
    const vite = `http://localhost:${env.VITE_PORT || '5173'}`;
    const pgPort = env.FORWARD_DB_PORT || '5432';
    const redisPort = env.FORWARD_REDIS_PORT || '6379';

    p.note(
        [
            `Приложение:  ${pc.cyan(appUrl)}`,
            `Mailpit UI:  ${pc.cyan(mailpit)}`,
            `Vite (HMR):  ${pc.cyan(vite)}`,
            `Postgres:    ${pc.dim(`localhost:${pgPort}, user=${env.DB_USERNAME || 'sail'}, db=${env.DB_DATABASE || 'laravel'}`)}`,
            `Redis:       ${pc.dim(`localhost:${redisPort}`)}`,
        ].join('\n'),
        '🌐 Полезные ссылки',
    );
}

// ─── Главное меню ──────────────────────────────────────────────────────

async function main() {
    p.intro(pc.bgCyan(pc.black(' 🗼 Tower ')) + pc.dim(`  проект: ${PROJECT}`));

    if (!existsSync(SAIL)) {
        p.log.warn('vendor/ ещё не установлен — Sail недоступен.');

        if (!(await dockerAlive())) {
            p.log.error(
                'Docker daemon недоступен. Запустите Docker и повторите.',
            );
            process.exit(1);
        }

        const ok = await p.confirm({
            message:
                'Выполнить composer install через docker-образ composer:2?',
            initialValue: true,
        });

        if (p.isCancel(ok) || !ok) {
            p.log.error('Без vendor/bin/sail продолжать нельзя.');
            process.exit(1);
        }

        if (!(await composerInstallViaDocker())) {
            process.exit(1);
        }
    }

    while (true) {
        const up = await isUp().catch(() => false);
        const status = up ? pc.green('● запущено') : pc.dim('○ остановлено');

        const choice = await p.select({
            message: `Главное меню  ${pc.dim('—')}  ${status}`,
            options: [
                {
                    value: 'setup',
                    label: '🚀 Старт проекта',
                    hint: 'первичная настройка',
                },
                {
                    value: 'env',
                    label: '🐳 Окружение',
                    hint: 'up / down / status / logs / ссылки',
                },
                {
                    value: 'dev',
                    label: '🛠  Разработка',
                    hint: 'composer dev / vite / serve / pail / shell',
                },
                {
                    value: 'db',
                    label: '🗄  База данных',
                    hint: 'миграции / сидеры / psql / tinker',
                },
                {
                    value: 'quality',
                    label: '✅ Качество и тесты',
                    hint: 'pest / pint / eslint / prettier / types',
                },
                {
                    value: 'maint',
                    label: '🧹 Обслуживание',
                    hint: 'зависимости / кеши / сборка',
                },
                { value: 'exit', label: '🚪 Выход' },
            ],
        });

        if (p.isCancel(choice) || choice === 'exit') {
            p.outro(pc.dim('До встречи.'));

            return;
        }

        try {
            switch (choice) {
                case 'setup':
                    await setupFlow();
                    break;
                case 'env':
                    await envMenu();
                    break;
                case 'dev':
                    await devMenu();
                    break;
                case 'db':
                    await dbMenu();
                    break;
                case 'quality':
                    await qualityMenu();
                    break;
                case 'maint':
                    await maintenanceMenu();
                    break;
            }
        } catch (e) {
            p.log.error(e?.message ?? String(e));
        }
    }
}

main().catch((e) => {
    console.error(pc.red(e?.stack ?? String(e)));
    process.exit(1);
});
