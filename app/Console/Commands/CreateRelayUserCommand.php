<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class CreateRelayUserCommand extends Command
{
    protected $signature = 'relay:user:create
        {name : Display name of the relay user}
        {email : Email address of the relay user}
        {password : Password for the relay user}
        {--role=admin : Role to assign (admin|operator)}';

    protected $description = 'Create a relay operator user for the admin UI';

    public function handle(): int
    {
        $validator = Validator::make([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $this->argument('password'),
            'role' => $this->option('role'),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_OPERATOR])],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        $this->info('Relay user created:');
        $this->line('ID: '.$user->id);
        $this->line('Email: '.$user->email);
        $this->line('Role: '.$user->role);

        return self::SUCCESS;
    }
}
