<?php

namespace Database\Factories;

use App\Models\Secret;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Secret>
 */
class SecretFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message' => $this->generateEncryptedMessage(50),
            'expires_at' => now()->addHours(4),
            'user_id' => null,
            'filepath' => null,
            'filename' => null,
            'file_size' => null,
            'file_mime_type' => null,
        ];
    }

    /**
     * Indicate that the secret belongs to a user.
     */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    /**
     * Indicate that the secret has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Indicate that the secret is ready to prune (expired + message cleared + past prune threshold).
     */
    public function readyToPrune(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(config('secrets.prune_after') + 1),
            'message' => null,
            'filepath' => null,
        ]);
    }

    /**
     * Indicate that the secret is a file secret.
     */
    public function fileSecret(?string $filepath = null): static
    {
        return $this->state(fn (array $attributes) => [
            'message' => null,
            'filepath' => $filepath ?? 'secrets/'.fake()->uuid().'.bin',
            'filename' => $this->generateEncryptedMessage(20),
            'file_size' => fake()->numberBetween(1024, 1048576),
            'file_mime_type' => 'application/pdf',
        ]);
    }

    /**
     * Indicate that the secret has been retrieved (message cleared).
     */
    public function retrieved(): static
    {
        return $this->state(fn (array $attributes) => [
            'message' => null,
            'retrieved_at' => now(),
        ]);
    }

    /**
     * Generate a message matching the encrypted format: 16-char salt + base64(28-byte header + plaintext).
     */
    public function generateEncryptedMessage(int $plaintextLength = 50): string
    {
        $salt = str_repeat('a', 16);
        $header = str_repeat("\0", 28);
        $plaintext = str_repeat('x', $plaintextLength);
        $binary = $header.$plaintext;

        return $salt.base64_encode($binary);
    }

    /**
     * Create a secret with a specific plaintext length (for validation testing).
     */
    public function withPlaintextLength(int $length): static
    {
        return $this->state(fn (array $attributes) => [
            'message' => $this->generateEncryptedMessage($length),
        ]);
    }
}
