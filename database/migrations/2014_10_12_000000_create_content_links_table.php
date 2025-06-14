<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('content_links', static function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->text('url');
            $table->text('redirect')->nullable();
            $table->unsignedSmallInteger('status')->nullable();

            $table->timestamps();
        });

        Schema::create('content_linkables', static function (Blueprint $table): void {
            $table->unsignedBigInteger('content_link_id');
            $table->morphs('content_linkable', 'morphs_index');
            $table->string('field')->index();
        });
    }
};
