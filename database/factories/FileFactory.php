<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'file',
            'name' => $this->faker->word().'.'.$this->faker->fileExtension(),
            'path' => 'files/'.$this->faker->uuid().'.'.$this->faker->fileExtension(),
            'mime_type' => $this->faker->mimeType(),
            'size' => $this->faker->numberBetween(1024, 10_485_760),
        ];
    }

    /**
     * Indicate that the model is a folder.
     */
    public function folder(): static
    {
        return $this->state(fn (): array => [
            'type' => 'folder',
            'name' => $this->faker->word(),
            'path' => null,
            'mime_type' => null,
            'size' => null,
        ]);
    }
}
