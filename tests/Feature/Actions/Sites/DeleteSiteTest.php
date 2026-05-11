<?php

declare(strict_types=1);

use App\Contracts\Actions\Sites\DeletesSite;
use App\Data\Sites\DeleteSiteData;
use App\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

it('удаляет сайт по id', function () {
    $site = Site::factory()->create();

    app(DeletesSite::class)(new DeleteSiteData(id: $site->id));

    $this->assertDatabaseMissing('sites', ['id' => $site->id]);
});

it('не трогает другие сайты', function () {
    $target = Site::factory()->create();
    $other = Site::factory()->create();

    app(DeletesSite::class)(new DeleteSiteData(id: $target->id));

    $this->assertDatabaseHas('sites', ['id' => $other->id]);
});

it('бросает ModelNotFoundException, если сайта нет', function () {
    expect(fn () => app(DeletesSite::class)(new DeleteSiteData(id: 99999)))
        ->toThrow(ModelNotFoundException::class);
});

it('DeleteSiteData отвергает id меньше 1', function (int $invalid) {
    expect(fn () => DeleteSiteData::from(['id' => $invalid]))
        ->toThrow(ValidationException::class);
})->with([0, -1]);
