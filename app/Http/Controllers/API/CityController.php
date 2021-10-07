<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use App\Models\City;
use App\Models\State;

class CityController extends Controller
{
    use \App\Traits\WebServicesDoc;
    public function countryCities() {
        //        echo '31303311f71fb04ca-33643466353238372d373833642d313165382d383061332d353235343030376164393835-1529906505';
        //        dump(___HQ('31303311f71fb04ca-33643466353238372d373833642d313165382d383061332d353235343030376164393835-1529906505'));
        //        dump(___HQ('31303233418c72c36-32373738653661632d373632392d313165382d623463302d353235343030376164393835-1529677976'));
        //        dump(___HQ('333811f71fb04cb-30666138393532352d356630632d313165382d623463302d353235343030376164393835-1525438006'));
//                dump(___HQ('3136393505a15c6fb-32623433613865632d386536612d313165382d393461372d353235343030376164393835-1532344727'));
//            dump(___HQ('31353835d792f87ff-66613635613739302d386237352d313165382d613666632d353235343030376164393835-1532020026'));
//            dump(___HQ('31383735d2e289642-32376165623331302d386632632d313165382d393461372d353235343030376164393835-1532428044'));
//        die;
        $params = Input::get();
        if(!empty($params['huid']) && env('APP_DEBUG')=== true){
            dump(___HQ($params['huid']));
            die;
        }
        $country_id = $params['country_id']  = (!empty($params['country_id']))? $params['country_id'] : 166; 
        \Validator::make($params, ['country_id'=> 'required', 'state_id' => 'array'])->validate();
        if(empty($params['state_id'])){
            $state_ids = State::where('country_id', $country_id)->pluck('id')->toArray();
        }else{
            $state_ids = $params['state_id'];
        }
        $cities = City::select(['id', 'name'])->whereIn('state_id', $state_ids)->orderBy('name', 'ASC')->get();
        $response = responseBuilder()->success('Cities list', $cities);
        $this->urlComponents('Get list of cities', $response, 'City_Management');
        return $response;
    }
    
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
