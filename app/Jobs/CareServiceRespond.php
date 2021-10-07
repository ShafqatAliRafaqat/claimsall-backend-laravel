<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CareServiceRespond implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use \App\Traits\EMails;

    protected $data;

    public function __construct($careService)
    {
        $this->data= $careService;
    }

    public function handle()
    {
        $data = $this->data;
        $this->sendMail([
                            'receiver_email'         => $data['receiver_email'],
                            'receiver_name'          => $data['receiver_name'],
                            'sender_name'            => $data['sender_name'],
                            'care_service_type_name' => $data['care_service_type_name'],
                            'care_service_id'        => $data['care_service_id'],
                            'start_date'             => $data['start_date'],
                            'start_time'             => $data['start_time'],
                            'name'                   => $data['name'],
                            'contact_number'         => $data['contact_number'],
                            'address'                => $data['address'],
                            'description'            => $data['description'],
                            'status'                 => $data['status'],
                            'feedback'               => $data['feedback'],
                            'cc_flag'                => false
                        ], 'Care Service Status', 'emails.careservice_respond'); 
    }
}
