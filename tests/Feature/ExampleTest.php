<?php

namespace Tests\Feature;

use App\Models\FurnitureProduct;
use App\Models\NotificationLog;
use App\Models\PasswordResetCode;
use App\Models\Recommendation;
use App\Models\Reservation;
use App\Models\RoomImage;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Notifications\ReservationConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_public_and_login_is_required_for_customer_features(): void
    {
        $product = $this->product();

        foreach (['/how-it-works', '/catalog', '/catalog/'.$product->slug, '/profile', '/preferences', '/room', '/reservations', '/admin'] as $path) {
            $this->get($path)->assertRedirectContains('/login');
        }

        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/')->assertOk()->assertSee('Furniture that feels right at home.')->assertSee('Log in')->assertSee('Create account');
    }

    public function test_customer_can_update_profile_information(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Customer Name',
            'email' => 'old-profile@example.com',
            'phone' => '09000000000',
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Profile settings')
            ->assertSee($user->email);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Customer Name',
            'email' => 'updated-profile@example.com',
            'phone' => '09171234567',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Customer Name',
            'email' => 'updated-profile@example.com',
            'phone' => '09171234567',
        ]);
    }

    public function test_customer_must_confirm_current_password_before_changing_it(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame($originalPassword, $user->fresh()->password);

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password-456', $user->fresh()->password));
    }

    public function test_seeded_catalog_contains_all_nine_supplied_images(): void
    {
        $this->seed();

        $this->assertSame(9, FurnitureProduct::where('image_path', 'like', 'catalog:%')->count());
        foreach (range(1, 9) as $number) {
            $filename = sprintf('drive-%02d.png', $number);
            $product = FurnitureProduct::where('image_path', 'catalog:'.$filename)->firstOrFail();
            $this->assertStringEndsWith('/images/catalog/'.$filename, $product->image_url);
        }
    }

    public function test_customer_can_register_and_admin_area_rejects_them(): void
    {
        $this->post('/register', ['name' => 'Ana Customer', 'email' => 'ana@example.com', 'phone' => '09170000001', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect('/catalog');
        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_login_returns_a_guest_to_the_featured_product_they_opened(): void
    {
        $user = User::factory()->create(['email' => 'return@example.com']);
        $product = $this->product();
        $target = route('catalog.show', $product);
        $this->get($target)->assertRedirect();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'redirect' => $target])->assertRedirect($target);
    }

    public function test_partial_catalog_search_returns_ranked_matches(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['name' => 'Family Dining Set', 'slug' => 'family-dining-set', 'category' => 'Dining Set']);
        $this->product(['name' => 'Bedroom Cabinet', 'slug' => 'bedroom-cabinet', 'category' => 'Cabinet', 'tags' => ['storage', 'bedroom']]);
        $response = $this->actingAs($user)->getJson('/catalog/search?q=dinn');
        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['name' => $product->name]);
    }

    public function test_customer_can_generate_and_revisit_hybrid_recommendations(): void
    {
        config(['services.gemini.key' => null]);
        $user = User::factory()->create();
        $this->product();
        $this->product(['name' => 'Modern Cabinet', 'slug' => 'modern-cabinet', 'category' => 'Cabinet', 'style' => 'Modern', 'price' => 18000]);
        $room = $this->room($user);

        $this->actingAs($user)->post('/preferences', [
            'budget_min' => 5000,
            'budget_max' => 40000,
            'style' => 'Modern',
            'wood_type' => 'Gmelina',
            'varnish_finish' => 'Natural',
        ])->assertRedirect(route('preferences.edit').'#room-preview');

        $response = $this->actingAs($user)->post('/recommendations', ['room_image_id' => $room->id]);
        $recommendation = Recommendation::firstOrFail();
        $response->assertRedirect(route('recommendations.show', $recommendation));
        $this->assertGreaterThan(0, $recommendation->items()->count());
        $this->actingAs($user)->get(route('recommendations.show', $recommendation))->assertOk()->assertSee('Your closest furniture matches');
    }

    public function test_recommendations_and_room_preview_share_one_customer_workflow(): void
    {
        config(['services.gemini.key' => null]);
        $user = User::factory()->create();
        $product = $this->product();
        $room = $this->room($user);

        $this->actingAs($user)->post('/preferences', [
            'budget_min' => 5000,
            'budget_max' => 40000,
            'style' => 'Modern',
        ])->assertRedirect(route('preferences.edit').'#room-preview');

        $this->actingAs($user)->get('/room')->assertRedirect(route('preferences.edit').'#room-preview');
        $this->actingAs($user)->get(route('preferences.edit', ['room' => $room->id]))
            ->assertOk()
            ->assertSee('Plan your room in three required steps')
            ->assertSee('Browse and set your preferences')
            ->assertSee('Upload a room for space analysis')
            ->assertSee('Selected');

        $this->actingAs($user)->post('/recommendations', ['room_image_id' => $room->id]);

        $recommendation = Recommendation::firstOrFail();
        $this->actingAs($user)->get(route('recommendations.show', $recommendation))
            ->assertOk()
            ->assertSee('Preview recommended furniture');
        $this->actingAs($user)->get(route('room.visualize', ['roomImage' => $room, 'recommendation' => $recommendation->id, 'product' => $product->id]))
            ->assertOk()
            ->assertSee('Original room / FurniCraft result')
            ->assertSee($product->name)
            ->assertSee('Show original')
            ->assertSee('Split view')
            ->assertSee('Show result')
            ->assertSee('Fit to room')
            ->assertSee('Rotate left')
            ->assertSee('Active Lance Furniture catalog items only')
            ->assertSee('Dimension-based fit');
    }

    public function test_room_preview_only_offers_active_lance_catalog_products(): void
    {
        $user = User::factory()->create();
        $active = $this->product(['name' => 'Active Lance Chair', 'slug' => 'active-lance-chair']);
        $inactive = $this->product(['name' => 'Retired Lance Chair', 'slug' => 'retired-lance-chair', 'status' => 'inactive']);
        $room = RoomImage::create([
            'user_id' => $user->id,
            'image_path' => 'rooms/catalog-only-room.png',
            'room_width_m' => 4,
            'room_depth_m' => 5,
            'analysis' => ['summary' => 'Room ready.', 'layout' => 'Open floor.', 'lighting' => 'Bright.', 'constraints' => 'Measure first.', 'gemini_used' => false],
            'analyzed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('room.visualize', $room))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee($inactive->name)
            ->assertSee('Active Lance Furniture catalog items only');
    }

    public function test_gemini_room_analysis_estimates_dimensions_when_not_provided(): void
    {
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);
        Storage::fake('public');
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'summary' => 'Estimated compact living room.',
                            'layout' => 'Open center floor.',
                            'lighting' => 'Bright.',
                            'constraints' => 'Confirm measurements.',
                            'estimated_width_m' => 3.8,
                            'estimated_depth_m' => 4.6,
                            'dimension_confidence' => 'low',
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/preferences', ['budget_min' => 5000, 'budget_max' => 40000]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $this->actingAs($user)->post('/room', [
            'room_image' => UploadedFile::fake()->createWithContent('estimated-room.png', $png),
        ])->assertRedirect();

        $room = $user->roomImages()->firstOrFail();
        $this->assertSame('3.80', $room->room_width_m);
        $this->assertSame('4.60', $room->room_depth_m);
        $this->assertSame('low', $room->analysis['dimension_confidence']);
        $this->assertTrue($room->analysis['gemini_used']);
        Http::assertSentCount(1);
    }

    public function test_room_image_upload_has_a_safe_local_analysis_fallback(): void
    {
        config(['services.gemini.key' => null]);
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/preferences', ['budget_min' => 5000, 'budget_max' => 40000]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $response = $this->actingAs($user)->post('/room', ['room_image' => UploadedFile::fake()->createWithContent('room.png', $png), 'room_width_m' => 3.5, 'room_depth_m' => 4.2]);
        $room = $user->roomImages()->firstOrFail();
        $response->assertRedirect(route('room.visualize', $room));
        Storage::disk('public')->assertExists($room->image_path);
        $this->assertFalse($room->analysis['gemini_used']);
        $this->assertSame('customer-provided', $room->analysis['dimension_confidence']);
        $this->assertSame('3.50', $room->room_width_m);
        $this->assertSame('4.20', $room->room_depth_m);
    }

    public function test_preferences_and_room_are_required_before_recommendations(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($user)->post('/room', [
            'room_image' => UploadedFile::fake()->createWithContent('room.png', $png),
        ])->assertRedirect(route('preferences.edit').'#preference-form')->assertSessionHasErrors('workflow');
        $this->assertSame(0, $user->roomImages()->count());

        $this->actingAs($user)->post('/recommendations', ['room_image_id' => 999])
            ->assertRedirect(route('preferences.edit').'#preference-form')
            ->assertSessionHasErrors('workflow');

        $this->actingAs($user)->post('/preferences', ['budget_min' => 5000, 'budget_max' => 40000]);
        $this->actingAs($user)->post('/recommendations', [])
            ->assertSessionHasErrors('room_image_id');
    }

    public function test_admin_confirmation_sends_one_visit_notification_and_logs_it(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $product = $this->product();
        $reservation = Reservation::create(['reference' => 'FC-TEST-001', 'user_id' => $customer->id, 'furniture_product_id' => $product->id, 'branch' => 'Lingayen', 'status' => 'pending']);

        $this->actingAs($admin)->patch(route('admin.reservations.update', $reservation), ['status' => 'confirmed'])->assertRedirect();

        $this->assertSame('confirmed', $reservation->fresh()->status);
        Notification::assertSentTo($customer, ReservationConfirmedNotification::class, function (ReservationConfirmedNotification $notification) use ($customer, $product): bool {
            $mail = $notification->toMail($customer);
            $message = implode(' ', $mail->introLines);

            return $mail->subject === ReservationConfirmedNotification::SUBJECT
                && str_contains($message, 'owner has confirmed')
                && str_contains($message, 'FC-TEST-001')
                && str_contains($message, $product->name)
                && str_contains($message, 'Lingayen branch')
                && str_contains($message, 'You may now visit');
        });

        $log = NotificationLog::where('type', 'reservation_confirmed')->firstOrFail();
        $this->assertSame(ReservationConfirmedNotification::SUBJECT, $log->subject);
        $this->assertSame('Lingayen', $log->payload['branch']);
        $this->assertSame($product->name, $log->payload['product']);
        $this->assertSame('visit_selected_branch', $log->payload['next_step']);

        $this->actingAs($admin)->patch(route('admin.reservations.update', $reservation), ['status' => 'confirmed'])->assertRedirect();
        Notification::assertSentTo($customer, ReservationConfirmedNotification::class, 1);
        $this->assertSame(1, NotificationLog::where('type', 'reservation_confirmed')->count());
    }

    public function test_password_reset_code_is_hashed_and_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/reset-password');
        $record = PasswordResetCode::firstOrFail();
        $this->assertFalse((bool) preg_match('/^\d{6}$/', $record->code_hash));
        Notification::assertSentTo($user, PasswordResetCodeNotification::class, 1);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasErrors('email');
    }

    private function room(User $user, array $overrides = []): RoomImage
    {
        return RoomImage::create(array_merge([
            'user_id' => $user->id,
            'image_path' => 'rooms/test-room.png',
            'room_width_m' => 3.5,
            'room_depth_m' => 4.2,
            'analysis' => [
                'summary' => 'Room ready.',
                'layout' => 'Open wall.',
                'lighting' => 'Bright.',
                'constraints' => 'Measure first.',
                'estimated_width_m' => 3.5,
                'estimated_depth_m' => 4.2,
                'dimension_confidence' => 'customer-provided',
                'gemini_used' => false,
            ],
            'analyzed_at' => now(),
        ], $overrides));
    }

    private function product(array $overrides = []): FurnitureProduct
    {
        return FurnitureProduct::create(array_merge([
            'name' => 'Compact Dining Table', 'slug' => 'compact-dining-table', 'category' => 'Dining Table', 'description' => 'A compact solid wood table.', 'wood_type' => 'Gmelina', 'varnish_finish' => 'Natural', 'style' => 'Modern', 'price' => 22000,
            'width_cm' => 140, 'depth_cm' => 80, 'height_cm' => 75, 'tags' => ['dining', 'table'], 'status' => 'active',
        ], $overrides));
    }
}
