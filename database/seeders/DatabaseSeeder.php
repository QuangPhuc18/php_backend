<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $this->call([
            ProductSeeder::class,
        ]);
        $this->call([
        CategoriesTableSeeder::class,
    ]);
       $this->call([
        ProductImagesTableSeeder::class,
    ]);
    $this->call([
    UsersTableSeeder::class,
    ]);
    $this->call([
        BannersTableSeeder::class,
    ]);
    $this->call([ContactsTableSeeder::class]);
    $this->call([
    PostsTableSeeder::class,
]);
$this->call(OrdersTableSeeder::class);



    }
    
}
