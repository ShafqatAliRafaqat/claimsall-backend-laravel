<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use phpDocumentor\Reflection\Types\Null_;
use Carbon\Carbon;

class ResetWrongAttempt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ResetWrongPasswordAttempts:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to reset wrong password attempts';

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
        //\DB::enableQueryLog();
        $userObj = \DB::table('users')->where('attempts', '<=', 5)
//            ->where('is_active', '0')
            ->where('updated_at', '<=',  Carbon::now()->subMinutes(15)->toDateTimeString());

        //logger('ResetLogin = at:=====>'.Carbon::now()->subMinutes(15)->toDateTimeString().' <=======> Count:' .$userObj->count());

        //logger('query:', \DB::getQueryLog());
        $users = $userObj->update(['is_active' => '1', 'attempts' => Null]);
    }
}
