<?php

namespace App\Http\Middleware;

use Closure;
use Auth;

class CheckUserRole {

    public function handle($request, Closure $next) {
        $user = Auth::user();
        if (is_null($user)) {
            return responseBuilder()->error('User should be authenticated', 401);
        }
        $userDataObj = $user->roles()->get();
        $userData = $userDataObj->toArray();
        $userRole = (isset($userData[0]['code'])) ? $userData[0]['code'] : 'user';
        if ($userRole === 'superAdmin'){
            goto end;
        }
        if (count($userData) <= 0) {
            return responseBuilder()->error('Access denied to this user', 403);
        }
        $allUserModules = [];
        foreach ($userDataObj as $userRoleObj) {
            $modules = $userRoleObj->modules()->get()->toArray();
            $allUserModules = array_merge($allUserModules, $modules);
        }
        $requestedRoute = $request->route()->getName();
        $allUserModuleRoutes = array_column($allUserModules, 'route');
        if (in_array($requestedRoute, $allUserModuleRoutes)) {
            goto end;
        }
        
        return responseBuilder()->error('You don\'t have access to this module', 403);
        end:
        return $next($request);
    }

}
