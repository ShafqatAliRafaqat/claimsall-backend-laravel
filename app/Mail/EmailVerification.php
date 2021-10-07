<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels, \App\Traits\EMails;
    
protected $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
//        $introLines = ['<h3>Click the Link To Verify Your Email</h3>', 'After email verification you can login with your email as well.']; 
//        return $this->view('emails.verify')->with(['token' => $this->user->email_token, 'level' => 'success',
//            'introLines'=>$introLines, 'actionUrl' => $this->user->email_token, 'actionText' => 'Verify Email Address']);
        $user = $this->user;
        $emailContent = '<h3>Click the Link To Verify Your Email</h3>'; 
        $data = [ 'receiver_email'=> $user->email, 'receiver_name' => $user->fist_name, 'verification_code' => $user->verification_code,
             'actionUrl' => route('user.verified_email_address', ['token' => $user->verification_code]), 
            'actionText' => 'Verify Email Address', 'emailContent'=> ''];
        $this->sendMail($data, 'Email verification required', 'emails.verify');
    }
}
