<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class UsersCreate extends Command
{
    protected $signature = 'users:create {email : Email address (unique)} {tier=free : free|pro|dynamic}';

    protected $description = 'Create an artifact-host user and print a one-time API key';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $tier = (string) $this->argument('tier');

        $v = Validator::make(
            ['email' => $email, 'tier' => $tier],
            ['email' => 'required|email|unique:users,email', 'tier' => 'required|in:free,pro,dynamic']
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $plain = User::generateApiKey();

        User::create([
            'email' => $email,
            'api_key_hash' => User::hashKey($plain),
            'tier' => $tier,
        ]);

        $this->info("User created: {$email} ({$tier})");
        $this->newLine();
        $this->line("API key (save now — not shown again):");
        $this->line("  {$plain}");

        return self::SUCCESS;
    }
}
