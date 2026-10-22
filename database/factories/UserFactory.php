<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\User>
 */
class UserFactory extends Factory
{
    /**
     * Laravel 8 replaced the $factory->define() closure style with this class.
     *
     * @var class-string<\App\User>
     */
    protected $model = User::class;

    /**
     * The columns here are the ones the users table actually has. The old
     * factory set 'name' and 'email_verified_at', neither of which exists in
     * this schema — `name` is a read-only accessor over firstname/lastname —
     * so it would have failed on insert had anything used it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('080########'),
            // bcrypt('password'). The controllers hash with bcrypt() directly
            // rather than through a 'hashed' cast, so this is a finished hash.
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'admin' => 0,
            'status' => 1,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An administrator — CheckAdmin requires admin == 1.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['admin' => 1]);
    }
}
