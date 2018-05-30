<?php
namespace Ordent\RamenAuth\Manager;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AuthManager{
    public function ramenLogin(Request $request, $rules = [], $model){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $type = head(array_keys(array_except($request->all(), 'password')));
        $roles = $request->input('roles', false);
        $password = $request->input('password');
        try{

            $check = \Auth::attempt([$type => $request[$type], 'password' => $password]);
        }catch(ModelNotFoundException $e){
            return $e;
        }

        if($check){
            return $this->authenticateFromModel($model->where($type, $request[$type])->get()->first(), $roles);
        }else{
            abort(401, 'Login failed, wrong username or password');
        }
    }

    private function authenticateFromModel($user, $roles = false){
        if($roles){
            $user->roles;
        }
        $meta = new \StdClass;
        $meta->status_code = 200;
        $meta->message = 'Login is successful';
        $post = function($data) use ($user){
            $users = $data['data'];
            $data['data'] = null;
            $data['data']['users'] = $users;
            $data['data']['token'] = \JWTAuth::fromUser($user);
            return $data;
        };
        return [$user, $meta, $post];
    }

    public function ramenCheck(Request $request, $rules = [], $model){
        $type = $request->type;
        $identity = $request[$type];
        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        try{
            $model = $model->where($type, $identity)->first();
        }catch(ModelNotFoundException $e){
            return response()->error(404, 'We can\'t found any account with this credential');
        }
        if(!is_null($model)){
            $result = [ 'type' => $type, 'value' => $identity, 'status' => false ];
            $meta = [ 'status_code' => 422, 'message' => 'Account with this identity already exists.' ];
            $status = 422;
        }else{
            $result = [ 'type' => $type, 'value' => $identity, 'status' => true ];
            $meta = [ 'status_code' => 200, 'message' => 'You are allowed to use this identity as an account.' ];
            $status = 200;
        }
        return [$result, $meta, $status];        
    }

    public function ramenRefresh(Request $request, $rules){
        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $new_token = \JWTAuth::refresh($requests->token);
        $result = [ 'token' => $new_token ];
        $meta = [ 'status_code' => 200, 'message' => 'Token refresh successful.' ];
        return [$result, $meta];
    }

    public function ramenRegister(Request $request, $rules = [], $model){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $data = array_only($request->toArray(), $model->getFillable());
        $result = $model->create($data);
        $result->fresh();
        $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully registered.' ];
        return [$result, $meta];
    }

    public function ramenAssignRoleToUser(Request $request, $id, $rules = [], $model){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $model = $model->findOrFail($id);
        $role = $request->input('role_name', $request->input('role_id'));
        if(!is_array($role)){
            $role = [$role];
        }
        if(is_integer($role)){
            $role = Role::whereIn('id', $id)->get();
            abort(404, 'Role not found');
        }else{
            $role = Role::whereIn('name', $role)->get();
            if($role->count()<1){
                $temp = $request->input('role_name');
                if(!is_array($temp)){
                    $temp = [$temp];
                }
                $role = [];
                foreach ($temp as $key => $value) {
                    array_push($role, Role::create(['name'=>$value]));
                }
            }
        }
        $result = $model->fresh();
        foreach ($role as $key => $value) {
            if(!$model->hasRole($value)){
                $result = $result->assignRole($role);
            }
        }
        $result->fresh();
        $result->roles;
        $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully add Role to User.' ];
        return [$result, $meta];
    }

    public function ramenRemoveRoleFromUser(Request $request, $id, $rules = [], $model){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $model = $model->findOrFail($id);
        $role = $request->input('role_name', $request->input('role_id'));
        if(!is_array($role)){
            $role = [$role];
        }
        if(is_integer($role)){
            $role = Role::whereIn('id', $id)->get();
        }
        foreach ($role as $key => $value) {
            $model->removeRole($value);
        }
        $result = $model->fresh();
        $result->roles;

        $meta = $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully remove Role from User.' ];
        return [$result, $meta];
    }

    public function ramenAssignPermissionToRole(Request $request, $id, $rules = []){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $role = Role::findOrFail($id);
        $permission = $request->input('permission_name', $request->input('permission_id'));
        if(!is_array($permission)){
            $permission = [$permission];
        }
        if(is_integer($permission)){
            $permission = Permission::whereIn('id', $id)->get();
        }else{
            $permission = Permission::whereIn('name', $permission)->get();
            if($permission->count()<1){
                $temp = $request->input('permission_name');
                if(!is_array($temp)){
                    $temp = [$temp];
                }
                $permission = [];
                foreach ($temp as $key => $value) {
                    array_push($permission, Permission::create(['name'=>$value]));
                }
            }
        }

        $permission = $role->permissions->concat($permission);
        $role->syncPermissions($permission);
        $result = $role->fresh();
        $result->permissions;
        $meta = $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully add Permission to Roles.' ];
        return [$result, $meta];
    }

    public function ramenRemovePermissionFromRole(Request $request, $id, $rules = []){
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $role = Role::findOrFail($id);
        $permission = $request->input('permission_name', $request->input('permission_id'));
        if(!is_array($permission)){
            $permission = [$permission];
        }
        if(is_integer($permission)){
            $permission = \Permission::whereIn('id', $id)->get();
        }
        $permissions = array_diff(array_pluck($role->permissions->toArray(), 'name'), $permission);
        $role->syncPermissions($permissions);
        $result = $role->fresh();
        $result->permissions;
        $meta = $meta = [ 'status_code' => 200, 'message' => 'You have been succesfully remove Permission from Role' ];
        return [$result, $meta];
    }

    // verify
    public function ramenVerify(Request $request, $id){

    }
    // forgot password
    public function ramenForgot(Request $request, $id){
        
    }
}