<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PasswordReset;
use App\User;

class UserController extends Controller
{
    use \App\Traits\FCM;
    public function showResetForm(Request $request, $token) {
        //$passwordReset = PasswordReset::where('token', $token)->where('created_at', '>=', now()->subDay())->first();
        $passwordReset = User::where('reset_token', $token)->where('reset_time', '>=', now()->subDay())->first();
        if(!$passwordReset){
            return view('auth.passwords.reset')->with('error', 'Your token either is invalid or has been expired');
        }
        session(['resetPwdEmail'=> $passwordReset->email, 'token' => $token]);
        return view('auth.passwords.reset')->with(
            ['token' => $token, 'email' => $passwordReset->email]
        );
    }
    
    public function postReset(Request $request) {
        $request->validate(['password' => 'required', 'password_confirmation'=> 'required|same:password']);
        $post = $request->all();
        $usermail= (!empty($post['email'])) ? $post['email'] :  session('userPwdEmail');
        $user = \App\User::where(['email' => $usermail, 'is_deleted' => '0', 'is_active' =>'1'])->first();
        $user->password = bcrypt($post['password']);
        $res = $user->save();
        if($res){
            $message = ['success' => 'Password has been reset successfully'];
            session($message);
            $message['token'] = $post['token'];
        }else{
            $message = ['error'=> 'Something went wrong! unable to reset password'];
        }
        return redirect()->back()->with($message);
    }
    
    public function verifiedEmail(Request $request, $token) {
//        session()->forget('success.warning.error.info');
        $msgTxt = '';
        $request->session()->forget(['success', 'warning', 'error', 'info']);
        $status = 'Failed';
        $resp = ['type'=> 'success', 'msg' => 'You have successfully verified your email address, Now you may login via this email also!'];
        $user = User::where('verification_code', 'LIKE', '%email-verified/'.$token)->where([ 'is_deleted' => '0'])->first();
        if(empty($user)){
            $msgTxt = ' Failed';
            $resp = ['type'=> 'error', 'msg' => 'Either token is invlid or has been expired'];
            goto end;
        }
        if($user->email_verified=='Y'){
            $resp = ['type'=> 'warning', 'msg' => 'You have already confirmed your email address'];
            goto end;
        }
        $status = 'Successfull!';
        $user->email_verified = 'Y';
        $user->activation_code = null;
        $user->updated_at = now();
        $user->save(); 
         //================== push notification
        $userDevice = $user->user_device;
        if(!empty($userDevice)){
            //$push4User = User::formatUserData($user);
            $pushNote = ['data' => ['title' => env('APP_NAME')." - Email verified successfully",
                'body' => "Your email has been verified successfully.", 
            'click_action' => 'VERIFIED_EMAIL_ACTIVITY',
                        'subTitle' => 'Email verified', //'user' => $push4User
                        ],
            'to' => $userDevice->fcm_token, 'user_id' => $user->id, 'created_by' => $user->id];
            $this->pushFCMNotification($pushNote);
        }
        
        end:
        \Session::put($resp['type'], $resp['msg']);
        return view('welcome')->with('message', "Email Verified{$msgTxt}!");
    }
    
}
