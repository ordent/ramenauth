<?php

namespace Ordent\RamenAuth\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait AuthControllerTrait{
    public function ramenLogin(Request $request){
        $rules = [
            'email' => 'requiredWithoutAll:phone,username',
            'username' => 'requiredWithoutAll:phone,email',
            'phone' => 'requiredWithoutAll:email,username',
            'password' => 'required'
        ];
        $manager = app('AuthManager');
        list($result, $meta, $post) = $manager->ramenLogin($request, $rules, $this->model); 
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, $post);
    }

    public function ramenCheck(Request $request){
        $rules = [
            'type' => 'required',
            'username' => 'requiredWithoutAll:phone,email',
            'email' => 'requiredWithoutAll:phone,username',
            'phone' => 'requiredWithoutAll:email,username',
        ];
        $manager = app('AuthManager');
        list($result, $meta, $status) = $manager->ramenCheck($request, $rules, $this->model);
        if($status == 422){
            return response()->errorValidation($result, 'Account with this identity is already exist');
        }
        return $this->processor->wrapModel($result, null, null, $meta, null, null);

    }

    public function ramenRefresh(Request $requests){
        // the token is valid and we have found the user via the sub claim
        $rules = ['token' => 'required'];
        $manager = app('AuthManager');        
        list($result, $meta) = $manager->ramenRefresh($request, $rules);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenRegister(Request $request){
        $request = $this->preRamenRegister($request);
        $rules = $this->resolveRules();
        if(is_null($rules)){
            $rules = ['email' => 'required|unique:users.id', 'password' => 'required|min:6|confirmation'];
        }
        $manager = app('AuthManager');
        list($result, $meta) = $manager->ramenRegister($request, $rules, $this->model);
        list($result, $meta) = $this->postRamenRegister($result, $meta);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function preRamenRegister($request){
        return $request;
    }

    public function postRamenRegister($result, $meta){
        return [$result, $meta];
    }

    private function resolveRules(){
        if(!property_exists($this, 'rules') && !is_null($this->model)){
            if(method_exists($this->model, 'getRules')){
                return $this->model->getRules('store');                
            }
        }else{
            return $this->rules;
        }
        return ['email' => 'required|unique:users.id', 'password' => 'required|min:6|confirmation'];
    }

    public function ramenAssignRoleToUser(Request $request, $id){
        $manager = app('AuthManager');
        $rules = ['role_name' => 'required_without:role_id', 'role_id' => 'required_without:role_name'];
        list($result, $meta) = $manager ->ramenAssignRoleToUser($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenRemoveRoleFromUser(Request $request, $id){
        $manager = app('AuthManager');
        $rules = ['role_name' => 'required_without:role_id', 'role_id' => 'required_without:role_name'];
        list($result, $meta) = $manager ->ramenRemoveRoleFromUser($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenAssignPermissionToRole(Request $request, $id){
        $manager = app('AuthManager');
        $rules = ['permission_name' => 'required_without:permission_id', 'permission_id' => 'required_without:permission_name'];
        list($result, $meta) = $manager ->ramenAssignPermissionToRole($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }

    public function ramenRemovePermissionFromRole(Request $request, $id){
        $manager = app('AuthManager');
        $rules = ['permission_name' => 'required_without:permission_id', 'permission_id' => 'required_without:permission_name'];
        list($result, $meta) = $manager ->ramenRemovePermissionFromRole($request, $id, $rules, $this->model);
        return $this->processor->wrapModel($result, null, null, $meta, null, $request, null);
    }
}