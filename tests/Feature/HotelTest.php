<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\HotelReservation;
use App\Models\HotelRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);
    }

    // =============================================
    // ROOMS
    // =============================================

    public function test_can_list_hotel_rooms(): void
    {
        HotelRoom::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/hotel/rooms');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_hotel_room(): void
    {
        $payload = [
            'room_number' => '101',
            'type' => 'standard',
            'capacity' => 2,
            'price_per_night' => 50000,
        ];

        $response = $this->postJson('/api/hotel/rooms', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_number', '101');

        $this->assertDatabaseHas('hotel_rooms', [
            'company_id' => $this->company->id,
            'room_number' => '101',
        ]);
    }

    public function test_cannot_create_duplicate_room_number(): void
    {
        HotelRoom::factory()->create([
            'company_id' => $this->company->id,
            'room_number' => '101',
        ]);

        $response = $this->postJson('/api/hotel/rooms', [
            'room_number' => '101',
            'type' => 'standard',
            'capacity' => 2,
            'price_per_night' => 50000,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_update_hotel_room(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson("/api/hotel/rooms/{$room->id}", [
            'price_per_night' => 75000,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('hotel_rooms', ['id' => $room->id, 'price_per_night' => 75000]);
    }

    public function test_can_delete_hotel_room(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/hotel/rooms/{$room->id}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('hotel_rooms', ['id' => $room->id]);
    }

    public function test_cannot_delete_room_with_active_reservations(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);
        HotelReservation::factory()->create([
            'company_id' => $this->company->id,
            'hotel_room_id' => $room->id,
            'status' => 'checked_in',
        ]);

        $response = $this->deleteJson("/api/hotel/rooms/{$room->id}");

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    // =============================================
    // RESERVATIONS
    // =============================================

    public function test_can_list_reservations(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);
        HotelReservation::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'hotel_room_id' => $room->id,
        ]);

        $response = $this->getJson('/api/hotel/reservations');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_can_create_reservation(): void
    {
        $room = HotelRoom::factory()->create([
            'company_id' => $this->company->id,
            'price_per_night' => 50000,
            'status' => 'available',
        ]);

        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();

        $response = $this->postJson('/api/hotel/reservations', [
            'hotel_room_id' => $room->id,
            'guest_name' => 'Jean Dupont',
            'guest_phone' => '+257 70 000 000',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'advance_payment' => 20000,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('hotel_reservations', [
            'hotel_room_id' => $room->id,
            'guest_name' => 'Jean Dupont',
            'nights' => 2,
            'total_amount' => 100000,
            'balance_due' => 80000,
        ]);
    }

    public function test_cannot_create_reservation_on_unavailable_room(): void
    {
        $room = HotelRoom::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'available',
        ]);

        $checkIn = now()->addDay()->toDateString();
        $checkOut = now()->addDays(3)->toDateString();

        HotelReservation::factory()->create([
            'company_id' => $this->company->id,
            'hotel_room_id' => $room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'status' => 'confirmed',
        ]);

        $response = $this->postJson('/api/hotel/reservations', [
            'hotel_room_id' => $room->id,
            'guest_name' => 'Autre Client',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
        ]);

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_can_check_in_reservation(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);
        $reservation = HotelReservation::factory()->create([
            'company_id' => $this->company->id,
            'hotel_room_id' => $room->id,
            'status' => 'confirmed',
        ]);

        $response = $this->postJson("/api/hotel/reservations/{$reservation->id}/check-in");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('hotel_reservations', ['id' => $reservation->id, 'status' => 'checked_in']);
        $this->assertDatabaseHas('hotel_rooms', ['id' => $room->id, 'status' => 'occupied']);
    }

    public function test_cannot_check_in_non_confirmed_reservation(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);
        $reservation = HotelReservation::factory()->create([
            'company_id' => $this->company->id,
            'hotel_room_id' => $room->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/hotel/reservations/{$reservation->id}/check-in");

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_can_check_out_reservation(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);
        $reservation = HotelReservation::factory()->create([
            'company_id' => $this->company->id,
            'hotel_room_id' => $room->id,
            'status' => 'checked_in',
            'total_amount' => 100000,
            'advance_payment' => 50000,
        ]);

        $response = $this->postJson("/api/hotel/reservations/{$reservation->id}/check-out", [
            'advance_payment' => 100000,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('hotel_reservations', [
            'id' => $reservation->id,
            'status' => 'checked_out',
            'balance_due' => 0,
        ]);
    }

    public function test_can_cancel_reservation(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);
        $reservation = HotelReservation::factory()->create([
            'company_id' => $this->company->id,
            'hotel_room_id' => $room->id,
            'status' => 'confirmed',
        ]);

        $response = $this->postJson("/api/hotel/reservations/{$reservation->id}/cancel");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('hotel_reservations', ['id' => $reservation->id, 'status' => 'cancelled']);
    }

    public function test_cannot_cancel_checked_in_reservation(): void
    {
        $room = HotelRoom::factory()->create(['company_id' => $this->company->id]);
        $reservation = HotelReservation::factory()->create([
            'company_id' => $this->company->id,
            'hotel_room_id' => $room->id,
            'status' => 'checked_in',
        ]);

        $response = $this->postJson("/api/hotel/reservations/{$reservation->id}/cancel");

        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    public function test_dashboard_returns_correct_stats(): void
    {
        HotelRoom::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'available',
        ]);
        HotelRoom::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'occupied',
        ]);

        $response = $this->getJson('/api/hotel/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rooms.total', 3)
            ->assertJsonPath('data.rooms.available', 2)
            ->assertJsonPath('data.rooms.occupied', 1);
    }
}
