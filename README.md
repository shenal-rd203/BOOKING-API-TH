# Booking API — Take-Home Exercise

A lightweight Booking API built with PHP and Laravel for scheduling fixed daily appointment slots.

Data access is strictly handled using the **Repository Pattern** to ensure controllers never interact directly with Eloquent models.

---

## How to Run Locally

### Prerequisites
- PHP >= 8.2 (with `pdo_sqlite` extension enabled)
- Composer

### Setup Steps
```bash
# 1. Clone repository
git clone https://github.com/shenal-rd203/BOOKING-API-TH.git
cd BOOKING-API-TH

# 2. Install dependencies
composer install

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Run migrations
php artisan migrate

# 5. Start the local development server
php artisan serve
```
The server will run at `http://127.0.0.1:8000`.

### Running Tests
Automated feature tests cover all endpoints, validation rules, double-booking prevention, simulated concurrency race conditions, and error responses:
```bash
php artisan test
```

---

## Assumptions Made

1. **Slots**: Hourly slots between `09:00` and `17:00` (`Booking::SLOTS`).
2. **Date Format**: Standard ISO `YYYY-MM-DD`. Validation forbids dates in the past (`after_or_equal:today`).
3. **Storage**: SQLite was chosen for zero-setup, portable local evaluation.
4. **URL Prefix**: Direct `/bookings` endpoints (configured with `apiPrefix: ''` in `bootstrap/app.php`) to work out-of-the-box with the provided Postman collection.

---

## Repository Layer Structure

To keep controllers completely decoupled from Eloquent:

1. **`App\Repositories\BookingRepositoryInterface`**: Declares required data access methods.
2. **`App\Repositories\Eloquent\BookingRepository`**: Concrete implementation containing all Eloquent queries.
3. **`App\Providers\AppServiceProvider`**: Binds the interface to the implementation via Laravel’s service.
4. **`BookingController`**: Injects `BookingRepositoryInterface` in the constructor. The controller never imports or calls the `Booking` model directly.

---

## The Double-Booking Rule & Concurrency

### My Approach
I implemented a two-tier defense against double-booking:
1. **Application-level check**: Before inserting, the controller checks `$this->bookingRepository->isSlotBooked($date, $slot)`. If already taken, it returns a `409 Conflict`.
2. **Database unique constraint**: A composite unique index on `['date', 'slot']` in the migration (`$table->unique(['date', 'slot']);`).
3. **Race condition safety**: If two simultaneous requests pass the application check at the exact same millisecond, the database unique constraint blocks the second insert. The controller catches the resulting `QueryException` and converts it into a `409 Conflict` response instead of an unexpected 500 error.

### How I'd Handle High Volume in Production
If this needed to safely handle high-concurrency traffic:
1. **Redis Atomic Locks**: Use distributed locks (`Cache::lock("booking:{$date}:{$slot}", 10)`) to serialize access to the specific slot before checking and inserting.
2. **Pessimistic Locking**: Use database transactions with `SELECT ... FOR UPDATE` to lock the slot row during the transaction.
3. **Queued Processing**: Route booking requests into a queue (e.g. RabbitMQ or Redis queue) with a single-worker consumer to process slot reservations sequentially.
4. **Temporary Hold / Soft Reservation**: Hold the slot in memory with a short TTL (e.g. 5 minutes) while waiting for user confirmation.

---

## What I'd Add or Improve with More Time

- **API Resources**: Use Laravel API Resources (`JsonResource`) to standardize JSON payloads and decouple database column names from the client response.
- **Timezone Support**: Store all dates/slots in UTC and support timezones for multi-location businesses.
- **Multi-Resource / Multi-Staff Support**: Add a `resource_id` (room, stylist, or doctor) so multiple appointments can happen simultaneously in the same time slot across different resources.
- **Rate Limiting**: Add Laravel route throttling to prevent spam or brute-force attempts on booking slots.
