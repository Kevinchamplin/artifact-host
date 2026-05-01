<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UsersRotateKey extends Command
{
    protected $signature = 'users:rotate-key {email : Email address of the user}';

    protected $description = 'Generate a new API key for an existing user (invalidates the old one)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        $plain = User::generateApiKey();
        $user->update(['api_key_hash' => User::hashKey($plain)]);

        $this->info("Rotated key for: {$email}");
        $this->newLine();
        $this->line("New API key (save now — not shown again):");
        $this->line("  {$plain}");

        return self::SUCCESS;
    }
}
