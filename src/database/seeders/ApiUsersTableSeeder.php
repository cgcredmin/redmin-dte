<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

use App\Models\ApiUser;

class ApiUsersTableSeeder extends Seeder
{
  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run()
  {
    $api_secret = str_random(16);
    $api_user = md5(Hash::make('redmin-dte-user'));
    ApiUser::truncate();
    ApiUser::create([
      'api_key' => $api_user,
      'api_secret' => Hash::make($api_secret),
    ]);

    $this->command->info('ApiUser: ' . $api_user);

    $this->command->info('ApiSecret: ' . $api_secret);
  }
}
