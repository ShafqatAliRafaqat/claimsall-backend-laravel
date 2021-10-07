<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Mail;
use App\Mail\EmailVerification;

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use \App\Traits\EMails;

    
protected $user;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user= $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
//        $email = new EmailVerification($this->user);
//        Mail::to($this->user->email)->send($email);
        $user = $this->user;
        $emailContent = '';
        if(!empty($user->activation_code)){
            $emailContent .= "<p>Your system genrated password is: <strong>{$user->activation_code}</strong></p>";
        }
        //$emailContent = '<h3>Click the Link To Verify Your Email</h3>'; 
        $data = [ 'receiver_email'=> $user->email, 'receiver_name' => $user->fist_name, 'verification_code' => $user->verification_code,
            'activation_code' => $user->activation_code,
             'actionUrl' =>  $user->verification_code, //config('app.url').'/email-verified/'. $user->verification_code, //route('user.verified_email_address', ['token' => $user->verification_code]), 
            'actionText' => 'Verify Email Address', 'emailData'=> $emailContent];
        $this->sendMail($data, 'Email verification required', 'emails.verify');
        
    }
}
