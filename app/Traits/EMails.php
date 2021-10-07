<?php

namespace App\Traits;

trait EMails {
    
    public function sendMail($data, $subject, $view = 'emails.common', $replyTo = 'support@hospitall.com', $from = 'support@hospitall.com') {
        \Mail::send($view, $data, function($message) use ($data, $subject, $replyTo, $from) {
            $message->getHeaders()->addTextHeader('Reply-To', $replyTo);
            $message->getHeaders()->addTextHeader('Return-Path', $replyTo);
            $message->to($data['receiver_email'], $data['receiver_name']);
            if (isset($data['cc_email'])) {
                $message->cc($data['cc_email'], $data['cc_name']);
            }
            if (isset($data['cc_requester_flag'])) {
                $message->cc($data['cc_requester_email'], $data['name']);
            }
            $message->subject($subject);
            $data['sender_name'] = (!empty($data['sender_name'])) ? $data['sender_name'] : env('APP_NAME');
            $message->from($from, $data['sender_name']);
            $message->sender($from, $data['sender_name']);
        });
    }
    
}