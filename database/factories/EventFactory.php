<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventDate = Carbon::instance(fake()->dateTimeBetween('-3 months', '+3 months'));
        $startHour = fake()->numberBetween(8, 18);

        if (fake()->boolean(10)) {
            $status = 'cancelled';
        } elseif ($eventDate->isToday()) {
            $status = 'ongoing';
        } elseif ($eventDate->isPast()) {
            $status = 'completed';
        } else {
            $status = 'upcoming';
        }

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category_id' => Category::inRandomOrder()->value('id'),
            'organizer_id' => User::inRandomOrder()->value('id'),
            'venue' => fake()->streetAddress(),
            'event_date' => $eventDate->format('Y-m-d'),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + 2),
            'max_capacity' => fake()->numberBetween(20, 500),
            'banner_path' => null,
            'registration_deadline' => $eventDate
                ->copy()
                ->subDays(fake()->numberBetween(1, 14))
                ->format('Y-m-d H:i:s'),
            'status' => $status,
        ];
    }
}