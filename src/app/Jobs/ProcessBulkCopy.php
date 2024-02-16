<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessBulkCopy extends Job
{
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct()
  {
    //
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    // call the stored procedure
    $result = DB::statement('CALL processTaxpayersData(0)');
    if ($result) {
      Log::info('Data processed successfully.');

      // call the stored procedure
      $result = DB::statement('CALL getTaxpayersData(0)');

      if ($result) {
        Log::info('Data retrieved successfully.');
      } else {
        Log::error('Error retrieving data.');
      }
    } else {
      Log::error('Error processing data.');
    }
  }
}
