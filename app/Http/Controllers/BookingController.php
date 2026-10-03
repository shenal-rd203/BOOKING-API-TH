<?php

namespace App\Http\Controllers;

use App\Repositories\BookingRepositoryInterface;
use App\Http\Requests\StoreBookingRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = $this->bookingRepository->all($request->date);

        return response()->json($bookings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        //check if the slot is already booked
        if($this->bookingRepository->isSlotBooked($validated['date'], $validated['slot'])){
            return response()->json([
                'message' => 'The selected date and slot is already booked.',
            ], 409);
        }

        //concurrency handling
        try {
            $booking = $this->bookingRepository->create($validated);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'The selected date and slot is already booked.'
            ], 409);
        }
        
        return response()->json($booking, 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $booking = $this->bookingRepository->find($id);
        
        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found.',
            ], 404);
        }

        $this->bookingRepository->delete($id);
        
        return response()->json([
            'message' => 'Booking deleted successfully.',
        ]);
    }
}
