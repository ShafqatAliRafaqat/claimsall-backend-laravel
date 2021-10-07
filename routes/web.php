<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/email-test', function () {
    return view('emails.verify', ['emailContent' => '', 'actionUrl' => 'http:google.com', ]);
});

// Password reset routes...
//Route::get('password/reset/{token}', 'Auth\ResetPasswordController@showResetForm')->name('password.request');
Route::get('password/reset/{token}', 'UserController@showResetForm')->name('password.request');
Route::post('password/reset', 'UserController@postReset')->name('password.reset');

Route::get('email-verified/{token}', [
    'uses' => 'UserController@verifiedEmail',
    'as' => 'user.verified_email_address'
]);

Route::group(['prefix' => 'docs'], function(){
    Route::get('/', 'DocsController@index')->name('api-list');
    Route::get('/{id}', 'DocsController@detail')->name('api-detail');
    
});
//Auth::routes();
//Route::get('/home', 'HomeController@index')->name('home');
