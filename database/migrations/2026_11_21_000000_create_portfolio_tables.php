<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RECORDS = ['educations', 'skills', 'projects', 'certificates', 'achievements', 'experiences', 'resumes', 'social_links'];

    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('owner_type', 24)->index();
            $table->unsignedBigInteger('owner_id');
            $table->string('disk');
            $table->string('path')->unique();
            $table->string('mime');
            $table->unsignedInteger('bytes');
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('details')->nullable();
            $table->timestamps();
        });

        foreach (['skill_categories', 'technologies'] as $catalog) {
            Schema::create($catalog, function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->timestamps();
            });
        }

        foreach (self::RECORDS as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('title', 160);
                $table->json('details')->nullable();
                $table->boolean('published')->default(false)->index();
                $table->timestamps();
                if ($name === 'skills') {
                    $table->foreignId('skill_category_id')->constrained()->restrictOnDelete();
                }
                if ($name === 'projects') {
                    $table->string('slug', 190)->unique();
                    $table->boolean('featured')->default(false);
                }
            });
        }

        Schema::create('project_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->unique()->constrained('media')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('project_technology', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->restrictOnDelete();
            $table->primary(['project_id', 'technology_id']);
        });

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('type', 40);
            $table->string('subject', 160);
            $table->string('status', 16)->default('New')->index();
            $table->timestamps();
        });

        Schema::create('inquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 12);
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('email_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_message_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);
            $table->string('status', 16)->default('Queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['inquiry_message_id', 'kind']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('subject', 60);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'email_delivery_logs', 'inquiry_messages', 'inquiries', 'project_technology', 'project_images', ...array_reverse(self::RECORDS), 'technologies', 'skill_categories', 'profiles', 'media', 'site_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
