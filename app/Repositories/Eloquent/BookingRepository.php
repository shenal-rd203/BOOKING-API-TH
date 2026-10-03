<?php

namespace App\Repositories\Eloquent;

use App\Models\Booking;
use App\Repositories\BookingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BookingRepository implements BookingRepositoryInterface
{
    //get all booking or by date
    public function all(?string $date = null): Collection
    {
        $query = Booking::query();
        if ($date) {
            $query->where('date', $date);
        }
        return $query->orderBy('date')->orderBy('slot')->get();
    }

    //find one booking
    public function find(string|int $id): ?Booking
    {
        return Booking::find($id);
    }

    public function isSlotBooked(string $date, string $slot): bool
    {
        return Booking::where('date', $date)->where('slot', $slot)->exists();
    }

    //create a booking
    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    public function delete(int|string $id): bool
    {
        $booking = $this->find($id);
        if (! $booking) {
            return false;
        }
        return (bool) $booking->delete();
    }
}