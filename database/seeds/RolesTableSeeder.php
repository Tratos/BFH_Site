<?php

use App\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $normaluser = Role::updateOrCreate(
            ['title' => 'Normal User'],
            ['slug'  => 'normalUser']
        );
        $normaluser->permissions()->sync([6, 7, 8]);

        $administrator = Role::updateOrCreate(
            ['title' => 'Administrator'],
            ['slug'  => 'administrator'],
            ['priority'  => 1000]
        );
        $administrator->permissions()->sync([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 18]);

        $staff = Role::updateOrCreate(
            ['title' => 'Staff'],
            ['slug'  => 'staff'],
            ['priority'  => 500]
        );
        $staff->permissions()->sync([6, 7, 8, 9, 10, 11, 17]);

        $moderator = Role::updateOrCreate(
            ['title' => 'Moderator'],
            ['slug'  => 'moderator'],
            ['priority'  => 400]
        );
        $moderator->permissions()->sync([6, 7, 8, 9, 10, 11, 17]);

        $leads = Role::updateOrCreate(
            ['title' => 'Awoken Lead'],
            ['slug'  => 'awokenlead'],
            ['priority'  => 900]
        );

        $dev = Role::updateOrCreate(
            ['title' => 'Awoken Dev'],
            ['slug'  => 'awokendev'],
            ['priority'  => 450]
        );
    }
}