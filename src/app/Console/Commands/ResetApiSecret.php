<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiUser;
use Illuminate\Support\Facades\Hash;

class ResetApiSecret extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'redmin:reset-api-secret';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Reset the API secret for a given API key.';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  {
    // request input
    $api_key = $this->ask('API key: ');
    $api_user = ApiUser::where('api_key', $api_key)->first();

    if (!$api_user) {
      $this->error('API key not found.');
      return;
    }

    $api_secret = str_random(16);

    $api_user->api_secret = Hash::make($api_secret);

    $api_user->save();

    $this->info('ApiSecret: ' . $api_secret);
  }
}
