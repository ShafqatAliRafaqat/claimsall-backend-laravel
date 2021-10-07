<?php

    use App\Http\Libraries\ResponseBuilder;
    function imageUrl($path, $width = NULL, $height = NULL, $quality = NULL, $crop = NULL) {

        if (!$width && !$height) {
            $url = env('IMAGE_URL') . $path;
        } else {
            $url = url('/') . '/images/random123?src=' . env('IMAGE_URL') . $path;
            if (isset($width)) {
                $url .= '&w=' . $width;
            }
            if (isset($height) && $height > 0) {
                $url .= '&h=' . $height;
            }
            if (isset($crop)) {
                $url .= "&zc=" . $crop;
            } else {
                $url .= "&zc=1";
            }
            if (isset($quality)) {
                $url .= '&q=' . $quality . '&s=1';
            } else {
                $url .= '&q=95&s=1';
            }
        }

        return $url;
    }

    function responseBuilder(){
        $responseBuilder = new ResponseBuilder();
        return $responseBuilder;
    }

    function ___HQ($_) {
        $tempArr = explode('-', $_);
        $enc_cn = substr($tempArr[0], -11);
        $enc_pk = str_replace($enc_cn, '', $tempArr[0]);
        try {
            $where['id'] = hex2bin($enc_pk);
        } catch (Exception $ex) {
            
        }
        $where[env('H_')] = base_convert($enc_cn, 16, 10);
        $where[env('H__')] = hex2bin($tempArr[1]);
//        $where[env('H___')] = $tempArr[2];
        if(!isset($where['id']) || is_numeric($where['id'])===false){
            $user = App\User::fetchConstraints($where);
            $where = (!empty($user)) ? $user->toArray() : [];
        }
       return $where;
    }

    
    function getDataByCURL($url, $method='GET') {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            return ['status' => false, 'code' => 422, 'message' => 'An Error occured while fetching data from request URL'];
        } 
        return json_decode($response);
    }
    
    
    function getUserDocumentPath($user, $storag=true) {
        $cnic='';
        if(is_object($user)){
            $cnic = (!empty($user->cnic)) ?  $user->cnic : '0000000000000';
        }else{
            $cnic = (!empty($user['cnic'])) ?  $user['cnic'] : '0000000000000';
        }
        $bucketID = 'bucket_' . substr($cnic, -2);
        $temp = explode('_', $cnic);
        $cnic = end($temp);
        $path = ($storag===true)? storage_path('app/public').'/user_documents/' . $bucketID . '/' . $cnic : asset('/storage/user_documents/' . $bucketID . '/' . $cnic);
        if($storag===false){
            return $path;
        }
        File::isDirectory($path) or File::makeDirectory($path, 0777, true, true);
        if (!File::isDirectory($path)) {
            return responseBuilder()->error('Create directory permissions denied!', 400);
        } 
        return $path;
    }

    function getUserDocPathforClaimTransaction($user, $storag=true) {
        $cnic='';
        if(is_object($user)){
            $cnic = (!empty($user->cnic)) ?  $user->cnic : '0000000000000';
        }else{
            $cnic = (!empty($user['cnic'])) ?  $user['cnic'] : '0000000000000';
        }
        $bucketID = 'bucket_' . substr($cnic, -2);
        $temp = explode('_', $cnic);
        $cnic = end($temp);
        $path = '/storage/user_documents/' . $bucketID . '/' . $cnic;
        if($storag===false){
            return $path;
        }
        File::isDirectory($path) or File::makeDirectory($path, 0777, true, true);
        if (!File::isDirectory($path)) {
            return responseBuilder()->error('Create directory permissions denied!', 400);
        } 
        return $path;
    }

    function getDocPath($storag=true, $type= 'health_monitoring_types') {
        $path = ($storag===true)? storage_path('app/public')."/common/{$type}/" : asset("/storage/common/{$type}/");
        if($storag===false){
            return $path;
        }
        File::isDirectory($path) or File::makeDirectory($path, 0777, true, true);
        if (!File::isDirectory($path)) {
            return responseBuilder()->error('Create directory permissions denied!', 400);
        } 
        return $path;
    }

    function getDocRelativePath($storag=true, $type= 'health_monitoring_types') {
        $path = "/storage/common/{$type}/";
        if($storag===false){
            return $path;
        }
        File::isDirectory($path) or File::makeDirectory($path, 0777, true, true);
        if (!File::isDirectory($path)) {
            return responseBuilder()->error('Create directory permissions denied!', 400);
        } 
        return $path;
    }
    
    function getCareServiceTypeDocumentPath($storag=true) {
        $path = ($storag===true)? storage_path('app/public').'/common/care_service_type_docs/' : asset('/storage/common/care_service_type_docs/');
        if($storag===false){
            return $path;
        }
        File::isDirectory($path) or File::makeDirectory($path, 0777, true, true);
        if (!File::isDirectory($path)) {
            return responseBuilder()->error('Create directory permissions denied!', 400);
        } 
        return $path;
    }

    function getCareServiceDocumentPath($storag=true) //relative path or url
    {
        $path = '/storage/common/care_service_type_docs/';
        if($storag===false){
            return $path;
        }
        File::isDirectory($path) or File::makeDirectory($path, 0777, true, true);
        if (!File::isDirectory($path)) {
            return responseBuilder()->error('Create directory permissions denied!', 400);
        } 
        return $path;
    }
    
    function getUserName($user) {
        if(is_object($user)){
            $user = $user->toArray();
        }
        $user['first_name'] = (!empty($user['first_name'])) ? $user['first_name'] : '';
        $user['last_name'] = (!empty($user['last_name'])) ? $user['last_name'] : '';
        $fullName = $user['first_name'].' '.$user['last_name'];
        $fullName = trim($fullName);
        return (!empty($fullName)) ? $fullName : 'Anonymous';
    }
    
    function ageCalculator($dob) {
        if(!empty($dob)){
            $from = new DateTime($dob);
            $to   = new DateTime('today');
            return $from->diff($to)->y;
        }
        return 0;
    }
    define('DEFAULT_AVATAR', 'default-avatar.png');
    function getUserDP($user) {
    $baseImgPath = getUserDocumentPath($user, false);
        $profilePic = '';
        if(!isset($user->profile_pic)){
            $user->profile_pic =DEFAULT_AVATAR;
            $user->gender = 'Male';
        }
        $thumb = null;
        if ($user->profile_pic == DEFAULT_AVATAR) {
            $profilePic = asset('/storage/i') . '/' . strtolower($user->gender) . '_' . $user->profile_pic;
            $thumb = $profilePic;
        } else {
            $profilePic = $baseImgPath . '/' . $user->profile_pic;
            $thumb = $baseImgPath . '/s_' . $user->profile_pic;
        }
        return ['pic' => $profilePic, 'thumb' => $thumb];
}