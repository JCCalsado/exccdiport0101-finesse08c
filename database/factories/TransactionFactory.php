<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference' => $this->faker->uuid(),
            'payment_channel' => $this->faker->randomElement(['paymongo','gcash','manual']),
            'type' => $this->faker->randomElement(['charge','payment']),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'status' => 'pending',
            'paid_at' => null,
            'meta' => null,
            'fee_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
