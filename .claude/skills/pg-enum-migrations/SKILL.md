---
name: pg-enum-migrations
description: Adds and modifies PostgreSQL enum types in Laravel migrations using PHP enums and PostgresGrammar. Use when adding a new enum, creating a column with a PG enum type, modifying an existing enum (ADD VALUE), CREATE TYPE or ALTER TYPE in Laravel migrations.
---

# PostgreSQL enum в Laravel-миграциях

Инструкция для Laravel-проекта с PostgreSQL: добавление и изменение enum-типов по цепочке PHP enum → тип в PG → регистрация в Grammar → колонки в таблицах. Пути вроде `database/migrations` и каталог enum-классов — стандартные для Laravel; при иной структуре подставляйте свои.

## Когда применять

- Пользователь просит добавить enum в PostgreSQL / колонку с enum в БД.
- Нужна миграция с `CREATE TYPE ... AS ENUM` в Laravel.
- Добавляется новая колонка или таблица с типом, соответствующим PHP enum.

## Требования

- PHP enum уже существует или его нужно создать.
- Enum имеет метод `names()` (unit enum) или `values()` (backed enum) — например через общий трейт или вручную. В PG ENUM попадают имена кейсов или значения — единообразно в рамках одного типа.
- **Нелатиница в подписи значений:** если пользователь задаёт человекочитаемые подписи не в стиле идентификаторов (в т.ч. на русском) — приведите к английским идентификаторам для имён кейсов PHP и для литералов в PG (`draft`, `published`, а не строки с пробелами или кириллицей в схеме БД).

## Порядок действий

1. **PHP enum**  
   Убедиться, что есть класс enum с методом `names()` или `values()` (через трейт или вручную) для формирования списка значений ENUM.

2. **Имя PG-типа**  
   Имя типа в PostgreSQL — в snake_case, обычно от имени enum: `ExampleKind` → `example_kind`.

3. **В `up()` миграции**
   - Выполнить `DROP TYPE IF EXISTS "snake_type"`.
   - Выполнить `CREATE TYPE "snake_type" AS ENUM (...)` со значениями из `Enum::names()` (или `Enum::values()`).
   - Зарегистрировать тип в грамматике: один раз вызвать `DB::connection()->setSchemaGrammar(new class(DB::connection()) extends PostgresGrammar { ... })` с методом для типа (см. шаблон). В конструктор анонимного класса обязательно передавать `DB::connection()` — иначе Laravel выбросит ArgumentCountError (Grammar требует Connection).
   - В `Schema::create` / `Schema::table` использовать `$table->addColumn('pg_type_name', 'column_name')` с при необходимости `->nullable()` и/или `->default(Enum::Case->name)` (или `->value` для backed).

4. **В `down()`**
   - Сначала удалить таблицы или колонки, использующие тип.
   - Затем выполнить `DROP TYPE IF EXISTS "snake_type"`.  
   Для alter-миграции: в `down()` сначала `$table->dropColumn('column_name')`, при полном откате можно затем выполнить `DROP TYPE IF EXISTS`.

5. **Имя метода в Grammar**  
   Метод в анонимном классе грамматики: `type` + snake_case имени типа с заглавной первой буквой первого слова, например `example_kind` → `typeExample_kind`, `order_state` → `typeOrder_state`. Возвращаемый тип метода: `string`.

## Шаблоны кода

### Создание PG типа (DROP + CREATE TYPE)

```php
DB::unprepared('DROP TYPE IF EXISTS "snake_type_name"');
DB::unprepared(
    sprintf(
        'CREATE TYPE "snake_type_name" AS ENUM (%s)',
        implode(
            ',',
            array_map(fn ($name) => '\''.$name.'\'', ExampleEnum::names())
        )
    )
);
```

Для backed enum с значениями в ENUM используйте `ExampleEnum::values()` вместо `names()`.

### Регистрация типа в Grammar (один тип)

В конструктор анонимного класса передаётся `DB::connection()` — без этого Laravel выбросит ArgumentCountError.

```php
DB::connection()->setSchemaGrammar(
    new class(DB::connection()) extends PostgresGrammar
    {
        protected function typeSnake_type_name(Fluent $column): string
        {
            return 'snake_type_name';
        }
    }
);
```

### Несколько типов в одной миграции

Один анонимный класс `PostgresGrammar` с несколькими методами. В конструктор передавать `DB::connection()`.

```php
DB::connection()->setSchemaGrammar(
    new class(DB::connection()) extends PostgresGrammar
    {
        protected function typeFirst_type_name(Fluent $column): string
        {
            return 'first_type_name';
        }

        protected function typeSecond_type_name(Fluent $column): string
        {
            return 'second_type_name';
        }
    }
);
```

### Использование в таблице

```php
// без default
$table->addColumn('snake_type_name', 'column_name');

// с default (unit enum — name; backed — name или value в зависимости от того, что в ENUM)
$table->addColumn('snake_type_name', 'column_name')->default(ExampleEnum::SomeCase->name);

// nullable
$table->addColumn('snake_type_name', 'column_name')->nullable();
```

## Модификация типа после создания

В PostgreSQL в существующий enum можно только **добавлять** значения (`ALTER TYPE ... ADD VALUE`). Удалить или изменить порядок значений без пересоздания типа нельзя.

### Добавление нового значения

Используйте отдельную миграцию. Имя PG-типа — то же, что при создании (snake_case). Grammar менять не нужно.

```php
DB::unprepared('ALTER TYPE "snake_type_name" ADD VALUE \'new_value\'');
```

Несколько значений — несколько `ADD VALUE` (в одной транзакции нельзя добавлять и использовать новое значение в одной транзакции; обычно достаточно нескольких отдельных `DB::unprepared` в одном `up()`).

Чтобы миграция была идемпотентной при повторном запуске (значение уже есть), оберните в try/catch и игнорируйте ошибку «already exists»:

```php
try {
    DB::unprepared('ALTER TYPE "snake_type_name" ADD VALUE \'new_value\'');
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'already exists') === false) {
        throw $e;
    }
}
```

В `down()` ничего не делайте: в PG нельзя удалить значение из enum. В комментарии к `down()` укажите: «В PostgreSQL нельзя удалить значение из enum».

### Когда нужна полная замена типа

Если нужно удалить значение, переименовать или изменить порядок — тип пересоздают: в одной миграции (в транзакции) переименовать старый тип, создать новый с полным списком, в колонках заменить тип (через `USING`), удалить старый тип. Это сложнее и требует аккуратной миграции данных; по возможности ограничивайтесь добавлением значений через `ADD VALUE`.

## Полный абстрактный пример

Подставьте свои имена: enum-класс, PG-тип (snake_case), таблица, колонка, кейсы.

### Трейт для `names()` / `values()` (если в проекте нет аналога)

```php
<?php

namespace App\Enums\Traits;

trait EnumToArray
{
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### PHP enum (unit)

```php
<?php

namespace App\Enums\YourNamespace;

use App\Enums\Traits\EnumToArray;

enum ExampleEnum
{
    use EnumToArray;

    case first_case;
    case second_case;
    case third_case;
}
```

PG-тип для `ExampleEnum`: например `example_enum`.

### Миграция: создание типа и таблицы

```php
<?php

declare(strict_types=1);

use App\Enums\YourNamespace\ExampleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TYPE IF EXISTS "example_enum"');
        DB::unprepared(
            sprintf(
                'CREATE TYPE "example_enum" AS ENUM (%s)',
                implode(
                    ',',
                    array_map(fn ($name) => '\'' . $name . '\'', ExampleEnum::names())
                )
            )
        );
        DB::connection()->setSchemaGrammar(
            new class(DB::connection()) extends PostgresGrammar
            {
                protected function typeExample_enum(Fluent $column): string
                {
                    return 'example_enum';
                }
            }
        );

        Schema::create('examples_table', function (Blueprint $table) {
            $table->id();
            $table->addColumn('example_enum', 'status')->default(ExampleEnum::first_case->name);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examples_table');
        DB::unprepared('DROP TYPE IF EXISTS "example_enum"');
    }
};
```

Имя метода в Grammar: `type` + snake_case типа с заглавной первой буквой первого слова (`example_enum` → `typeExample_enum`).

### Миграция: добавление значения в существующий enum

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::unprepared('ALTER TYPE "example_enum" ADD VALUE \'new_value\'');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        // В PostgreSQL нельзя удалить значение из enum
    }
};
```

## Важно

- **Идентификаторы в коде и БД:** человекочитаемые подписи на любом языке сводите к стабильным английским идентификаторам для кейсов и литералов ENUM.
- Grammar задаётся один раз на миграцию; при нескольких enum в одной миграции — один класс с несколькими методами `type*`.
- Default в миграции: для unit enum обычно `Enum::Case->name`; для backed — `->name` или `->value` в зависимости от того, что передано в ENUM (`names()` или `values()`).
- В `down()` сначала удалять таблицы/колонки, затем выполнять `DROP TYPE IF EXISTS`.
