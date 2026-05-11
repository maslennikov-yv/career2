<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TYPE IF EXISTS visit_device_type CASCADE');
        DB::statement("CREATE TYPE visit_device_type AS ENUM ('desktop', 'mobile', 'tablet', 'bot')");

        Schema::create('visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->uuid('visitor_uid');
            $table->string('ip', 45);
            $table->char('country_code', 2)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('browser', 60)->nullable();
            $table->string('os', 60)->nullable();
            $table->text('page_url')->nullable();
            $table->text('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('geo_resolved_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'occurred_at']);
            $table->index(['site_id', 'city']);
            $table->index(['site_id', 'visitor_uid', 'occurred_at']);
        });

        DB::statement('ALTER TABLE visits ADD COLUMN device_type visit_device_type NULL');
        DB::statement('ALTER TABLE visits ALTER COLUMN ip TYPE inet USING ip::inet');
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
        DB::statement('DROP TYPE IF EXISTS visit_device_type');
    }
};
