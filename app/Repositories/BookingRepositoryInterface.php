<?php

namespace App\Repositories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

interface BookingRepositoryInterface
{
    /**
     * Get all bookings, optionally filtered by date.
     */
    public function all(?string $date = null): Collection;

    /**
     * Find a booking by its ID.
     */
    public function find(string|int $id): ?Booking;

    /**
     * Check if a specific slot on a given date is already booked.
     */
    public function isSlotBooked(string $date, string $slot): bool;

    /**
     * Create a new booking record.
     */
    public function create(array $data): Booking;

    /**
     * Delete a booking by its ID.
     */
    public function delete(string|int $id): bool;
}
