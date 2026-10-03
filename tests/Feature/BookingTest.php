<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful booking creation.
     */
    public function test_can_create_a_booking_successfully(): void
    {
        $payload = [
            'name'  => 'Jane Silva',
            'email' => 'jane@example.com',
            'date'  => now()->addDays(5)->format('Y-m-d'),
            'slot'  => '10:00',
        ];

        $response = $this->postJson('/bookings', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'date',
                'slot',
                'created_at',
                'updated_at',
            ])
            ->assertJson([
                'name'  => 'Jane Silva',
                'email' => 'jane@example.com',
                'date'  => $payload['date'],
                'slot'  => '10:00',
            ]);

        $this->assertDatabaseHas('bookings', [
            'email' => 'jane@example.com',
            'date'  => $payload['date'],
            'slot'  => '10:00',
        ]);
    }

    /**
     * Test double-booking rule: second request for same date and slot must return 409 Conflict.
     */
    public function test_rejects_duplicate_booking_for_same_date_and_slot_with_409(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');

        // First booking should succeed
        $firstResponse = $this->postJson('/bookings', [
            'name'  => 'Alice Smith',
            'email' => 'alice@example.com',
            'date'  => $date,
            'slot'  => '14:00',
        ]);
        $firstResponse->assertStatus(201);

        // Second booking for the identical date + slot must be rejected with 409
        $secondResponse = $this->postJson('/bookings', [
            'name'  => 'Bob Jones',
            'email' => 'bob@example.com',
            'date'  => $date,
            'slot'  => '14:00',
        ]);

        $secondResponse->assertStatus(409)
            ->assertJson([
                'message' => 'The selected date and slot is already booked.',
            ]);
    }

    /**
     * Test concurrency race condition simulation
     */
    public function test_concurrency_race_condition_is_caught_and_returns_409(): void
    {
        $date = now()->addDays(5)->format('Y-m-d');
        $slot = '16:00';

        // Simulate a mock repository where isSlotBooked() returns false (as if both threads checked simultaneously)
        // but create() throws a QueryException due to the database unique constraint
        $mockRepo = \Mockery::mock(\App\Repositories\BookingRepositoryInterface::class);
        $mockRepo->shouldReceive('isSlotBooked')
            ->once()
            ->with($date, $slot)
            ->andReturn(false); // Thread passed the check!

        $pdoException = new \PDOException('Integrity constraint violation: UNIQUE constraint failed: bookings.date, bookings.slot', 23000);
        $queryException = new \Illuminate\Database\QueryException('sqlite', 'insert into "bookings"...', [], $pdoException);

        $mockRepo->shouldReceive('create')
            ->once()
            ->andThrow($queryException); // DB unique constraint triggered!

        // Swap the container binding to use our simulated race-condition mock
        $this->app->instance(\App\Repositories\BookingRepositoryInterface::class, $mockRepo);

        $response = $this->postJson('/bookings', [
            'name'  => 'Concurrent User',
            'email' => 'concurrent@example.com',
            'date'  => $date,
            'slot'  => $slot,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'The selected date and slot is already booked.',
            ]);
    }

    /**
     * Test validation failure on missing fields.
     */
    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/bookings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'date', 'slot']);
    }

    /**
     * Test validation failure on invalid email format.
     */
    public function test_validates_invalid_email(): void
    {
        $response = $this->postJson('/bookings', [
            'name'  => 'John Doe',
            'email' => 'not-an-email',
            'date'  => now()->addDays(2)->format('Y-m-d'),
            'slot'  => '09:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test validation failure for past dates.
     */
    public function test_rejects_past_dates(): void
    {
        $response = $this->postJson('/bookings', [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'date'  => now()->subDay()->format('Y-m-d'),
            'slot'  => '09:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    /**
     * Test validation failure for slots outside the defined allowed list.
     */
    public function test_rejects_invalid_slot(): void
    {
        $response = $this->postJson('/bookings', [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'date'  => now()->addDay()->format('Y-m-d'),
            'slot'  => '22:00', // Invalid slot outside 09:00 - 17:00
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slot']);
    }

    /**
     * Test listing all bookings without filter.
     */
    public function test_can_list_all_bookings(): void
    {
        Booking::create([
            'name'  => 'Client One',
            'email' => 'one@example.com',
            'date'  => now()->addDays(1)->format('Y-m-d'),
            'slot'  => '09:00',
        ]);

        Booking::create([
            'name'  => 'Client Two',
            'email' => 'two@example.com',
            'date'  => now()->addDays(2)->format('Y-m-d'),
            'slot'  => '10:00',
        ]);

        $response = $this->getJson('/bookings');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    /**
     * Test filtering bookings by date query parameter.
     */
    public function test_can_filter_bookings_by_date(): void
    {
        $targetDate = now()->addDays(3)->format('Y-m-d');
        $otherDate  = now()->addDays(4)->format('Y-m-d');

        Booking::create([
            'name'  => 'Target Client',
            'email' => 'target@example.com',
            'date'  => $targetDate,
            'slot'  => '09:00',
        ]);

        Booking::create([
            'name'  => 'Other Client',
            'email' => 'other@example.com',
            'date'  => $otherDate,
            'slot'  => '09:00',
        ]);

        $response = $this->getJson('/bookings?date=' . $targetDate);

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['email' => 'target@example.com'])
            ->assertJsonMissing(['email' => 'other@example.com']);
    }

    /**
     * Test deleting an existing booking.
     */
    public function test_can_delete_an_existing_booking(): void
    {
        $booking = Booking::create([
            'name'  => 'To Be Deleted',
            'email' => 'delete@example.com',
            'date'  => now()->addDays(1)->format('Y-m-d'),
            'slot'  => '11:00',
        ]);

        $response = $this->deleteJson('/bookings/' . $booking->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Booking deleted successfully.']);

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    /**
     * Test deleting a non-existent booking returns 404.
     */
    public function test_returns_404_when_deleting_non_existent_booking(): void
    {
        $response = $this->deleteJson('/bookings/nonexistent-id-9999');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Booking not found.']);
    }
}

