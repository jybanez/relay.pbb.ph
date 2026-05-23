<?php

namespace App\Installer;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallerAdminProvisioner
{
    /**
     * @param  array{name:string,email:string}  $admin
     * @return array{email:string,password:string}
     */
    public function provision(array $admin, string $connection = InstallerDatabaseService::CONNECTION): array
    {
        $password = 'relay-'.Str::lower(Str::random(16));

        User::on($connection)->updateOrCreate(
            ['email' => (string) $admin['email']],
            [
                'name' => (string) $admin['name'],
                'password' => $password,
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
        );

        return [
            'email' => (string) $admin['email'],
            'password' => $password,
        ];
    }
}
