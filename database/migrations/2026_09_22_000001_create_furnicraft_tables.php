<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('furniture_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->text('description');
            $table->enum('wood_type', ['Gmelina', 'Mahogany'])->index();
            $table->enum('varnish_finish', ['Natural', 'Narra Look', 'Mahogany', 'Chocolate Brown'])->index();
            $table->string('style')->index();
            $table->decimal('price', 12, 2)->index();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('depth_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->string('image_path')->nullable();
            $table->json('tags')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('budget_min', 12, 2)->default(0);
            $table->decimal('budget_max', 12, 2);
            $table->string('style')->nullable();
            $table->string('wood_type')->nullable();
            $table->string('varnish_finish')->nullable();
            $table->decimal('room_width_m', 6, 2)->nullable();
            $table->decimal('room_depth_m', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('room_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->decimal('room_width_m', 6, 2)->nullable();
            $table->decimal('room_depth_m', 6, 2)->nullable();
            $table->json('analysis')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_preference_id')->constrained('customer_preferences')->cascadeOnDelete();
            $table->foreignId('room_image_id')->nullable()->constrained('room_images')->nullOnDelete();
            $table->text('summary');
            $table->string('algorithm_version')->default('hybrid-v1');
            $table->boolean('gemini_used')->default(false);
            $table->timestamps();
        });

        Schema::create('recommendation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('furniture_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('content_score', 6, 3)->default(0);
            $table->decimal('collaborative_score', 6, 3)->default(0);
            $table->decimal('total_score', 6, 3)->default(0);
            $table->unsignedSmallInteger('position');
            $table->text('explanation');
            $table->timestamps();
            $table->unique(['recommendation_id', 'furniture_product_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('furniture_product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'furniture_product_id']);
        });

        Schema::create('furniture_view_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('furniture_product_id')->constrained()->cascadeOnDelete();
            $table->string('session_key')->nullable();
            $table->timestamps();
            $table->index(['furniture_product_id', 'created_at']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('furniture_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommendation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('branch');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'declined', 'cancelled'])->default('pending')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reservation_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommendation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('message');
            $table->boolean('gemini_used')->default(false);
            $table->timestamps();
        });

        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('channel')->default('mail');
            $table->string('recipient');
            $table->string('subject');
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('password_reset_codes');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('reservation_transactions');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('furniture_view_events');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('recommendation_items');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('room_images');
        Schema::dropIfExists('customer_preferences');
        Schema::dropIfExists('furniture_products');
    }
};
