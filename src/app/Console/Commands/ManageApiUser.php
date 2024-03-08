<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use App\Models\ApiUser;

class ManageApiUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redmin:api-user {id?} {--action=create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage API user for Redmin DTE API. Usage redmin:api-user {id} {--action=create|delete|password}';

    /**
     * Create a new command instance.
     *
     * @return void
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
        // available actions: create, update, delete
        $action = strtolower($this->option('action'));
        $id = $this->argument('id');

        // dd($action, $id);

        if (!in_array($action, ['create', 'delete', 'password'])) {
            $this->error('Invalid action');
            return;
        }

        if ($action === 'create') {
            $this->info('Creating user');
            $api_secret = str_random(16);
            $api_user = md5(Hash::make(date('YmdHis')));

            ApiUser::create([
                'api_key' => $api_user,
                'api_secret' => Hash::make($api_secret),
            ]);

            $this->info('ApiUser: ' . $api_user);
            $this->info('ApiSecret: ' . $api_secret);
        } else if ($action === 'password') {
            $this->info('Updating user password: ' . $id);
            if (!$id) {
                $this->error('User id is required');
                return;
            }

            $user = ApiUser::where(['api_key' => $id])->first();
            if (!$user) {
                $this->error('User not found');
                return;
            }
            $password = str_random(16);
            $user->api_secret = Hash::make($password);

            $this->info('New password: ' . $password);
        } else {
            $this->info('Deleting user: ' . $id);

            if (!$id) {
                $this->error('User id is required');
                return;
            }

            $user = ApiUser::where(['api_key' => $id])->first();
            if (!$user) {
                $this->error('User not found');
                return;
            }
            $user->delete();

            $this->info('User deleted');
        }
    }
}
