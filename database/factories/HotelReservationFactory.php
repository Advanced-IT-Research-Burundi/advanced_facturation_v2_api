<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\HotelRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class HotelReservationFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('now', '+30 days');
        $checkOut = fake()->dateTimeBetween($checkIn, '+37 days');
        $nights = max(1, (int) $checkIn->diff($checkOut)->days);
        $pricePerNight = fake()->randomElement([30000, 50000, 75000]);
        $total = $pricePerNight * $nights;
        $advance = fake()->numberBetween(0, $total);

        return [
            'company_id' => Company::factory(),
            'hotel_room_id' => HotelRoom::factory(),
            'customer_id' => null,
            'guest_name' => fake()->name(),
            'guest_phone' => fake()->optional()->phoneNumber(),
            'guest_email' => fake()->optional()->email(),
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'actual_check_in_at' => null,
            'actual_check_out_at' => null,
            'nights' => $nights,
            'price_per_night' => $pricePerNight,
            'total_amount' => $total,
            'advance_payment' => $advance,
            'balance_due' => $total - $advance,
            'status' => 'confirmed',
            'notes' => fake()->optional()->sentence(),
            'invoice_id' => null,
        ];
    }
}
