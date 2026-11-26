<?php

namespace ItmoSoft\Pages\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\Pages\PagesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PagesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('pages', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('title'); $table->string('slug', 180); $table->string('type', 64)->default('standard');
            $table->string('template', 100)->default('default'); $table->longText('content')->nullable();
            $table->json('content_blocks')->nullable(); $table->string('status', 20)->default('draft');
            $table->boolean('is_system')->default(false); $table->boolean('show_in_navigation')->default(false);
            $table->string('navigation_location', 32)->nullable(); $table->unsignedInteger('navigation_order')->default(0);
            $table->json('metadata')->nullable(); $table->timestamp('published_at')->nullable(); $table->timestamp('scheduled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps(); $table->softDeletes(); $table->unique(['store_id', 'slug']);
        });
        Schema::create('page_revisions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedBigInteger('version'); $table->json('snapshot'); $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps(); $table->unique(['page_id', 'version']);
        });
    }
}
