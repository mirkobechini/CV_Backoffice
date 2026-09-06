<?php

namespace Database\Factories;

use App\Models\Group;
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
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assegna l'utente a un gruppo con il ruolo indicato.
     *
     * Mantiene la compatibilità con i test che usavano ['role' => 'admin']:
     * ora il ruolo vive nel pivot group_user, non più su users.
     * Mappa i vecchi ruoli ai nuovi: admin -> capo, manager -> sottocapo,
     * worker/volunteer -> member.
     */
    public function withRole(string $role = Group::ROLE_MEMBER): static
    {
        $mapped = match ($role) {
            'admin' => Group::ROLE_CAPO,
            'manager' => Group::ROLE_SOTTOCAPO,
            'worker', 'volunteer' => Group::ROLE_MEMBER,
            default => $role,
        };

        return $this->afterCreating(function ($user) use ($mapped) {
            $group = Group::firstOrCreate(
                ['name' => 'Associazione di default'],
                ['invite_code' => Group::generateInviteCode()]
            );
            $group->addUser($user, $mapped);
        });
    }
}
