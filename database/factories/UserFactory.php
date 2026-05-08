<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Configure the model factory.
     * Handles guarded fields (role, landlord_id, is_archived) via afterCreating.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (\App\Models\User $user) {
            // afterMaking allows setting guarded attributes before save
        });
    }

    /**
     * Override create to handle guarded fields that can't go through $fillable.
     */
    public function create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
    {
        $guarded = ['role', 'landlord_id', 'is_archived'];
        $guardedValues = [];

        if (is_array($attributes)) {
            foreach ($guarded as $field) {
                if (array_key_exists($field, $attributes)) {
                    $guardedValues[$field] = $attributes[$field];
                    unset($attributes[$field]);
                }
            }
        }

        $result = parent::create($attributes, $parent);

        if (! empty($guardedValues)) {
            $models = $result instanceof \App\Models\User ? collect([$result]) : $result;
            foreach ($models as $model) {
                foreach ($guardedValues as $field => $value) {
                    $model->{$field} = $value;
                }
                $model->save();
            }
        }

        return $result;
    }
}
