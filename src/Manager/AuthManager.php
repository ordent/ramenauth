<?php
namespace Ordent\RamenAuth\Manager;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Ordent\RamenAuth\Mail\ForgotPassword;
use Ordent\RamenAuth\Mail\VerifyAccount;
use Ordent\RamenAuth\Mail\ChangeEmail;
use Ordent\RamenAuth\Model\RamenForgotten;
use Ordent\RamenAuth\Model\RamenVerification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Validator;

class AuthManager
{
    protected $phone;
    protected $model;
    protected $forgot;
    public function __construct($phone = null, RamenVerification $model, RamenForgotten $forgot)
    {
        if (config('ramenauth.verification')) {
            $this->phone = ($phone == null) ? app('Nexmo\Client') : $phone;
        } else {
            $this->phone = null;
        }
        $this->model = $model;
        $this->forgot = $forgot;
    }

    public function ramenAskPhoneForLoginVerification(Request $request, $rules = [], $model)
    {
        $rules = array_merge($rules, ['phone' => 'required']);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }

        $user = $model->where('phone', $request->input('phone'))->firstOrFail();

        $verification = $this->phone->verify()->start([
            'number' => $this->resolvePhoneNumber($user->phone),
            'brand' => 'Phone Login',
        ]);
        $user->authentication_code = $verification->getRequestId();
        $user->save();
        return [200, 'We send the login code to ' . $this->resolvePhoneNumber($request->input('phone'))];
    }

    public function ramenLogin(Request $request, $rules = [], $model)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $temp = array_keys(array_except($request->all(), 'password'));
        foreach($temp as $t){
            if(in_array($t, ['email', 'phone'])){
                $type = $t;
            }
        }
        $roles = $request->input('roles', false);
        $password = $request->input('password');
        $credentials = $request->only($type, 'password');
        $check = null;

        $user = $model->where($type, $request[$type])->get()->first();

        if (is_null($user)) {
            abort(401, 'Login failed, wrong username or password');
        }

        if ($user->status > 1) {
            if ($type == 'email' || $type == 'username') {
                $check = $this->authenticateByEmail($credentials);
            } else if ($type == 'phone') {
                $check = $this->authenticateByPhone($credentials, $user);
            }

            if ($check) {
                return $this->authenticateFromModel($user, $roles);
            }
        } else {
            return [null, ['is_verified' => false, 'status_code' => 401, 'message' => 'The account hasn\'t been verified'], null];
        }
        abort(401, 'Login failed, wrong username or password');
    }

    private function authenticateByEmail($credentials)
    {
        $check = false;
        try {
            $check = \Auth::attempt($credentials);
        } catch (ModelNotFoundException $e) {
            return $e;
        }
        return $check;
    }

    private function authenticateByPhone($credentials, $user)
    {
        if (is_null($user->authentication_code)) {
            abort(401, 'You are not eligible to login with phone, request to send the pin to users phone first');
        }
        $check = false;
        $answer = null;
        $answer = $this->phone->verify()->check($user->authentication_code, $credentials['password']);

        $check = true;
        if ($answer->getStatus() == 0) {
            $user->authentication_code = null;
            $user->save();
        }
        return $check;
    }

    private function authenticateFromModel($user, $roles = false)
    {
        if ($roles) {
            $user->roles;
        }
        $meta = new \StdClass;
        $meta->status_code = 200;
        $meta->message = 'Login is successful';
        $post = function ($data) use ($user) {
            $users = $data['data'];
            $data['data'] = null;
            $data['data']['users'] = $users;
            $data['data']['token'] = \JWTAuth::fromUser($user);
            return $data;
        };
        return [$user, $meta, $post];
    }

    public function ramenCheck(Request $request, $rules = [], $model)
    {
        $type = $request->type;
        $identity = $request[$type];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        try {
            $model = $model->where($type, $identity)->first();
        } catch (ModelNotFoundException $e) {
            return response()->error(404, 'We can\'t found any account with this credential');
        }
        if (!is_null($model)) {
            $result = ['type' => $type, 'value' => $identity, 'status' => false];
            $meta = ['status_code' => 422, 'message' => 'Account with this identity already exists.'];
            $status = 422;
        } else {
            $result = ['type' => $type, 'value' => $identity, 'status' => true];
            $meta = ['status_code' => 200, 'message' => 'You are allowed to use this identity as an account.'];
            $status = 200;
        }
        return [$result, $meta, $status];
    }

    public function ramenRefresh(Request $request, $rules)
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $new_token = \JWTAuth::refresh($requests->token);
        $result = ['token' => $new_token];
        $meta = ['status_code' => 200, 'message' => 'Token refresh successful.'];
        return [$result, $meta];
    }

    private function identityExistButNotVerified($type, $model)
    {
        $message = 'Your email is exist but hasn\'t been verified yet';
        $verification = $this->ramenAskVerification($type, $model);
        $is_verified = false;
        return [$message, $verification, $is_verified];
    }

    private function identityExistButNotVerifiedResolver($type, $request, $model, $error, $is_verified)
    {
        $model = $model->where($type, $request->input($type))->first();
        if (!is_null($model)) {
            if (array_search('status', $model->getFillable()) !== false) {
                if ($model->status < 2) {
                    list($error[$type], $verification, $is_verified) = $this->identityExistButNotVerified($type, $model);
                }
            } else {
                // check verification table
                $verify = $this->model->where('user_id', $model->id)->first();
                // if record exist but havent been verified
                if ($verify != null) {
                    if ($verify->verified_at === null) {
                        list($error[$type], $verification, $is_verified) = $this->identityExistButNotVerified($type, $model);
                    }
                    // if no record exist
                } else {
                    list($error[$type], $verification, $is_verified) = $this->identityExistButNotVerified($type, $model);
                }
            }
        }
        return [$error, $is_verified];
    }
    private function getSecondaryVerification($primary)
    {
        if ($primary == 'email') {
            return 'phone';
        } else {
            return 'email';
        }
    }

    public function ramenRegister(Request $request, $rules = [], $model)
    {
        $is_verified = true;
        // check all rules for validation
        $validator = Validator::make($request->all(), $rules);
        // if validator fail
        if ($validator->fails()) {
            $error = $validator->errors()->getMessages();
            // check if the user email has been taken
            // $list_verification = ['email', 'phone'];
            $secondary_verification = $this->getSecondaryVerification(config('ramenauth.primary_verification'));

            if (array_key_exists(config('ramenauth.primary_verification'), $error) && $is_verified) {
                list($error, $is_verified) = $this->identityExistButNotVerifiedResolver(config('ramenauth.primary_verification'), $request, $model, $error, $is_verified);
            }

            if (array_key_exists($secondary_verification, $error) && $is_verified) {
                list($error, $is_verified) = $this->identityExistButNotVerifiedResolver($secondary_verification, $request, $model, $error, $is_verified);
            }

            return [null, [
                'status_code' => 422,
                'message' => "The given data was invalid",
                'is_verified' => $is_verified,
                "detail" => array_flatten($error),
            ]];
        }
        // insert data to database
        $data = array_only($request->toArray(), $model->getFillable());
        $result = $model->create($data)->refresh();
        // send verification after record created
        $verification = $this->postRamenRegister($result);
        // send resulted record and the meta
        $meta = ['status_code' => 200, 'message' => 'You have been succesfully registered.'];
        return [$result, $meta];
    }

    public function postRamenRegister($result)
    {
        $is_verified = false;
        $verification = null;
        if (config('ramenauth.verification')) {
            if (array_search('status', $result->getFillable()) !== false) {
                if ($result->status < 2) {
                    $secondary_verification = $this->getSecondaryVerification(config('ramenauth.primary_verification'));
                    if (!is_null($result->{config('ramenauth.primary_verification')}) && !$is_verified) {
                        $verification = $this->ramenAskVerification(config('ramenauth.primary_verification'), $result);
                        $is_verified = true;
                    }

                    if (!$is_verified) {
                        $verification = $this->ramenAskVerification($secondary_verification, $result);
                        $is_verified = true;
                    }
                }
            } else {
                $secondary_verification = $this->getSecondaryVerification(config('ramenauth.primary_verification'));
                if (!is_null($result->{config('ramenauth.primary_verification')}) && !$is_verified) {
                    $verification = $this->ramenAskVerification(config('ramenauth.primary_verification'), $result);
                    $is_verified = true;
                }

                if (!$is_verified) {
                    $verification = $this->ramenAskVerification($secondary_verification, $result);
                    $is_verified = true;
                }
            }
        }
        return $verification;
    }

    public function ramenAssignRoleToUser(Request $request, $id, $rules = [], $model)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $model = $model->findOrFail($id);
        $role = array_wrap($request->input('role_name', $request->input('role_id')));

        $role_id = [];
        $role_name = [];

        foreach ($role as $r) {
            if (is_numeric($r)) {
                array_push($role_id, $r);
            } else {
                array_push($role_name, $r);
            }
        }
        $role_id = Role::whereIn('id', $role_id)->get();
        $role_name = Role::whereIn('', $role_name)->get();

        $role = $role_id->merge($role_name);

        if ($role->count() <= 0) {
            abort(404, 'Role not found');
        }

        $result = $model->fresh();
        foreach ($role as $key => $value) {
            if (!$result->hasRole($value)) {
                $result = $result->assignRole($value);
            }
        }
        $result->fresh();
        $result->load('roles');
        $meta = ['status_code' => 200, 'message' => 'You have been succesfully add Role to User.'];
        return [$result, $meta];
    }

    public function ramenRemoveRoleFromUser(Request $request, $id, $rules = [], $model)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $model = $model->findOrFail($id);
        $role = array_wrap($request->input('role_name', $request->input('role_id')));

        $role_id = [];
        $role_name = [];

        foreach ($role as $r) {
            if (is_numeric($r)) {
                array_push($role_id, $r);
            } else {
                array_push($role_name, $r);
            }
        }
        $role_id = Role::whereIn('id', $role_id)->get();
        $role_name = Role::whereIn('', $role_name)->get();

        $role = $role_id->merge($role_name);

        if ($role->count() <= 0) {
            abort(404, 'Role not found');
        }
        $result = $model->fresh();
        foreach ($role as $key => $value) {
            if ($result->hasRole($value)) {
                $result->removeRole($value);
            }
        }
        $result->fresh();
        $result->load('roles');
        $meta = ['status_code' => 200, 'message' => 'You have been succesfully remove Role to User.'];
        return [$result, $meta];

    }

    public function ramenAssignPermissionToRole(Request $request, $id, $rules = [])
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $role = Role::findOrFail($id);
        $permission = $request->input('permission_name', $request->input('permission_id'));
        if (!is_array($permission)) {
            $permission = [$permission];
        }
        if (is_integer($permission)) {
            $permission = Permission::whereIn('id', $id)->get();
        } else {
            $permission = Permission::whereIn('name', $permission)->get();
            if ($permission->count() < 1) {
                $temp = $request->input('permission_name');
                if (!is_array($temp)) {
                    $temp = [$temp];
                }
                $permission = [];
                foreach ($temp as $key => $value) {
                    array_push($permission, Permission::create(['name' => $value]));
                }
            }
        }

        $permission = $role->permissions->concat($permission);
        $role->syncPermissions($permission);
        $result = $role->fresh();
        $result->permissions;
        $meta = $meta = ['status_code' => 200, 'message' => 'You have been succesfully add Permission to Roles.'];
        return [$result, $meta];
    }

    public function ramenRemovePermissionFromRole(Request $request, $id, $rules = [])
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->getMessages());
        }
        $role = Role::findOrFail($id);
        $permission = $request->input('permission_name', $request->input('permission_id'));
        if (!is_array($permission)) {
            $permission = [$permission];
        }
        if (is_integer($permission)) {
            $permission = \Permission::whereIn('id', $id)->get();
        }
        $permissions = array_diff(array_pluck($role->permissions->toArray(), 'name'), $permission);
        $role->syncPermissions($permissions);
        $result = $role->fresh();
        $result->permissions;
        $meta = $meta = ['status_code' => 200, 'message' => 'You have been succesfully remove Permission from Role'];
        return [$result, $meta];
    }

    // verify
    public function ramenAskVerification($type, $model)
    {
        switch ($type) {
            case 'phone':
                return $this->ramenAskVerificationByPhone($model);
                break;
            case 'email':
                return $this->ramenAskVerificationByEmail($model);
                break;
            default:
                # code... =
                break;
        }
    }

    protected function ramenAskLoginByPhone($model)
    {

    }

    protected function ramenAskVerificationByPhone($model)
    {
        $verification = $this->phone->verify()->start([
            'number' => $this->resolvePhoneNumber($model->phone),
            'brand' => 'Phone Verification',
        ]);

        $this->model->user_id = $model->id;
        $this->model->code = $verification->getRequestId();
        $this->model->verified_by = 'phone';
        $this->model->save();

        return $model;
    }

    protected function ramenAskVerificationByEmail($model)
    {
        $result = $this->model->where('user_id', $model->id)->where('verified_by', 'email')->first();
        if (is_null($result)) {
            $this->model->user_id = $model->id;
            $digit = config('ramenauth.verification_digit', 4);
            $this->model->code = str_pad(rand(0, pow(10, $digit) - 1), $digit, '0', STR_PAD_LEFT);
            $this->model->verified_by = 'email';
            $this->model->save();
            \Mail::to($model->email)->send(new VerifyAccount($this->model->code));
            $meta = ['status_code' => 200, 'detail' => 'Please check your email for verification code'];
        } else {

            if (is_null($result->verified_at)) {
                \Mail::to($model->email)->send(new VerifyAccount($result->code));
                $meta = ['status_code' => 200, 'detail' => 'We send another email to your account'];
            } else {
                $meta = ['status_code' => 400, 'error_message' => 'Your code has been verified already'];
            }
        }
        return [$model, $meta];
    }

    protected function resolvePhoneNumber($number)
    {

        if (substr($number, 0, 1) == '0') {
            $number = "62" . substr($number, 1);
        }

        if (substr($number, 0, 1) != "+") {
            $number = "+" . $number;
        }

        return $number;
    }

    public function ramenCompleteVerification($type, Request $request, $model)
    {
        switch ($type) {
            case 'phone':
                list($model, $meta) = $this->ramenCompleteVerificationByPhone($request, $model);
                break;
            case 'email':
                list($model, $meta) = $this->ramenCompleteVerificationByEmail($request, $model);
                break;
        }
        $post = null;

        if (!is_null($model)) {
            list($model, $m, $post) = $this->authenticateFromModel($model);
        }

        return [$model, $meta, $post];
    }

    public function ramenCompleteForgotten($type, Request $request, $model)
    {
        switch ($type) {
            case 'phone':
                return $this->ramenCompleteForgottenByPhone($request, $model);
                break;
            case 'email':
                return $this->ramenCompleteForgottenByEmail($request, $model);
                break;
        }
    }

    public function ramenCompleteVerificationByPhone($request, $model)
    {
        $model = $model->where('phone', $request->input('identity'))->firstOrFail();
        $verification = $this->model->where('user_id', $model->id)->where('verified_by', 'phone')->where('verified_at', null)->orderBy('created_at','desc')->firstOrFail();
        $answer = $this->phone->verify()->check($verification->code, $request->input('answer'));
        if ($verification->verified_by === 'phone') {
            if ($answer->getStatus() == 0) {
                $verification->verified_at = date('Y-m-d H:i:s');
                $verification->response = $request->input('answer');
                if (array_search('status', $model->getFillable()) !== false) {
                    $model->status = 2;
                }
                $verification->save();
                $model->save();
                $model->fresh();
                return [$model, ['verification' => 'success', 'status_code' => 200]];
            } else {
                return [null, ['verification' => 'failed', 'status_code' => 400, 'error_message' => 'The answer cannot be verified']];
            }
        }
        return [null, ['verification' => 'failed', 'status_code' => 400, 'error_message' => 'Sorry but your account hasn\'t been asked to be verified by phone']];
    }

    public function ramenCompleteVerificationByEmail($request, $model)
    {
        $model = $model->where('email', $request->input('identity'))->firstOrFail();
        $verification = $this->model->where('user_id', $model->id)->where('verified_by', 'email')->where('verified_at', null)->orderBy('created_at','desc')->first();
        if (is_null($verification)) {
            $temp = $this->model->where('user_id', $model->id)->where('verified_by', 'email')->orderBy('created_at','desc')->first();
            if (!is_null($temp)) {
                return [null, ['verification' => 'failed', 'status_code' => 400, 'error_message' => 'You already complete the verification process.']];
            } else {
                return [null, ['verification' => 'failed', 'status_code' => 400, 'error_message' => 'Sorry but your account hasn\'t been asked to be verified by phone']];
            }
        }
        if ($verification->verified_by === 'email') {
            if ($verification->code === $request->input('answer')) {
                $verification->verified_at = date('Y-m-d H:i:s');
                $verification->response = $request->input('answer');
                if (array_search('status', $model->getFillable()) !== false) {
                    $model->status = 2;
                }
                $verification->save();
                $model->save();
                $model->fresh();
                return [$model, ['verification' => 'success', 'status_code' => 200]];
            } else {
                return [null, ['verification' => 'failed', 'status_code' => 400, 'error_message' => 'The answer cannot be verified']];
            }
        }
        return [null, ['verification' => 'failed', 'status_code' => 400, 'error_message' => 'Sorry but your account hasn\'t been asked to be verified by phone']];
    }

    // forgot password
    public function ramenForgot($type, $model)
    {
        switch ($type) {
            case 'email':
                return $this->ramenForgotByEmail($model);
                break;
            case 'phone':
                return $this->ramenForgotByPhone($model);
                break;
        }
    }

    public function ramenForgotByEmail($model)
    {
        $forgot = $this->forgot->where('user_id', $model->id)->where('remember_by', 'email')->where('remember_at', null)->first();
        if ($forgot == null) {
            $this->forgot->user_id = $model->id;
            $digit = config('ramenauth.forgotten_digit', 4);
            $this->forgot->code = str_pad(rand(0, pow(10, $digit) - 1), $digit, '0', STR_PAD_LEFT);
            \Mail::to($model->email)->send(new ForgotPassword($this->forgot->code));
            $this->forgot->remember_by = 'email';
            $this->forgot->save();
            $meta = ['status_code' => 200, 'message' => 'Please check your email for authentication code'];
        } else {
            \Mail::to($model->email)->send(new VerifyAccount($forgot->code));
            $meta = ['status_code' => 200, 'message' => 'We send another email to your account'];
        }
        return [$model, $meta];
    }

    public function ramenForgotByPhone($model)
    {
        $verification = $this->phone->verify()->start([
            'number' => $this->resolvePhoneNumber($model->phone),
            'brand' => 'Phone Verification',
        ]);

        $this->forgot->user_id = $model->id;
        $this->forgot->code = $verification->getRequestId();
        $this->forgot->remember_by = 'phone';
        $this->forgot->save();
        $meta = ['status_code' => 200, 'message' => 'Please check your your phone for authentication code'];
        return [$model, $meta];
    }

    public function ramenCheckByIdentity($type = 'email', $request, $model){
        switch ($type) {
            case 'email':
                return $this->ramenCheckByEmail($request, $model);
                break;
            case 'phone':
                return $this->ramenCheckByPhone($request, $model);
                break;
        }
    }
    // list($result, $meta) = $manager->ramenCheckByIdentity($type, $request, $this->model);
    

    public function ramenCheckByEmail($request, $model)
    {
        $model = $model->where('email', $request->input('identity'))->firstOrFail();
        $verification = $this->forgot->where('user_id', $model->id)->where('remember_by', 'email')->where('remember_at', null)->orderBy('created_at','desc')->firstOrFail();
        if ($verification->remember_by === 'email') {
            if ($verification->code === $request->input('answer')) {
                return [$model, ['forgot' => 'success', 'status_code' => 200]];
            }
            return [null, ['forgot' => 'failed', 'status_code' => 400, 'error_message' => 'The answer cannot be verified']];
        }
        return [null, ['forgot' => 'failed', 'status_code' => 400, 'error_message' => 'Sorry but your account hasn\'t been asked to be change the password by email']];
    }

    public function ramenCompleteForgottenByEmail($request, $model)
    {
        $model = $model->where('email', $request->input('identity'))->firstOrFail();
        $verification = $this->forgot->where('user_id', $model->id)->where('remember_by', 'email')->where('remember_at', null)->orderBy('created_at', 'desc')->firstOrFail();
        if ($verification->remember_by === 'email') {
            if ($verification->code === $request->input('answer')) {
                $verification->remember_at = date('Y-m-d H:i:s');
                $model->password = $request->input('password');
                $verification->response = $request->input('answer');
                $verification->save();
                $model->save();
                $model->fresh();
                return [$model, ['forgot' => 'success', 'status_code' => 200]];
            } else {
                return [null, ['forgot' => 'failed', 'status_code' => 400, 'error_message' => 'The answer cannot be verified']];
            }
        }
        return [null, ['forgot' => 'failed', 'status_code' => 400, 'error_message' => 'Sorry but your account hasn\'t been asked to be change the password by email']];
    }

    public function ramenCheckByPhone($request, $model)
    {
        $model = $model->where('phone', $request->input('identity'))->firstOrFail();
        $verification = $this->forgot->where('user_id', $model->id)->where('remember_by', 'phone')->where('remember_at', null)->orderBy('created_at', 'desc')->firstOrFail();
        $answer = $this->phone->verify()->check($verification->code, $request->input('answer'));
        if ($verification->remember_by === 'phone') {
            if ($answer->getStatus() == 0) {
                $verification->response = $request->input('answer');
                // $model->password = $request->input('password');
                $verification->save();
                // $model->save();
                // $model->fresh();
                return [$model, ['forgot' => 'success', 'status_code' => 200]];
            } else {
                return [null, ['forgot' => 'failed', 'status_code' => 400, 'error_message' => 'The answer cannot be verified']];
            }
        }
        return [null, ['forgot' => 'failed', 'status_code' => 400, 'error_message' => 'Sorry but your account hasn\'t been asked to be change the password by phone']];
    }

    public function ramenCompleteForgottenByPhone($request, $model)
    {
        $model = $model->where('phone', $request->input('identity'))->firstOrFail();
        $verification = $this->forgot->where('user_id', $model->id)->where('remember_by', 'phone')->where('remember_at', null)->where('response', $request->input('answer'))->orderBy('created_at')->firstOrFail();
        // $answer = $this->phone->verify()->check($verification->code, $request->input('answer'));
        if (!is_null($verification)) {
            // if ($answer->getStatus() == 0) {
            $verification->remember_at = date('Y-m-d H:i:s');
            $verification->response = $request->input('answer');
            $model->password = $request->input('password');
            $verification->save();
            $model->save();
            $model->fresh();
            return [$model, ['forgot' => 'success', 'status_code' => 200]];
        } else {
            return [null, ['forgot' => 'failed', 'status_code' => 400, 'error_message' => 'The answer cannot be verified']];
        }
        return [null, ['forgot' => 'failed', 'status_code' => 400, 'error_message' => 'Sorry but your account hasn\'t been asked to be change the password by phone']];
    }

    public function ramenChangeIdentity($type, $request, $model){
        switch ($type) {
            case 'email':
                return $this->ramenChangeByEmail($request, $model);
                break;
            case 'phone':
                return $this->ramenChangeByPhone($request, $model);
                break;
        }
    }

    public function ramenChangeByEmail($request, $model){
        $model = $model->where('email', $request->input('old_identity'))->firstOrFail();
        $digit = 4;
        $model->identity_token = str_pad(rand(0, pow(10, $digit) - 1), $digit, '0', STR_PAD_LEFT);
        $model->identity_pending = $request->input('identity');
        \Mail::to($request->input('identity'))->send(new ChangeEmail($model->identity_token));
        $model->save();
        $meta = ['status_code' => 200, 'message' => 'Please check your email for identity code'];
        return [$model, $meta];
    }

    public function ramenChangeByPhone($request, $model){
        $model = $model->where('phone', $request->input('old_identity'))->firstOrFail();
        $verification = $this->phone->verify()->start([
            'number' => $this->resolvePhoneNumber($request->input('identity')),
            'brand' => 'Phone Verification',
        ]);
        $model->identity_token  = $verification->getRequestId();
        $model->identity_pending = $request->input('identity');
        $model->save();
        $meta = ['status_code' => 200, 'message' => 'Please check your your phone for identity code'];
        return [$model, $meta];
    }

    public function ramenCompleteChangeIdentity($type, $request, $model){
        switch ($type) {
            case 'email':
                return $this->ramenCompleteChangeByEmail($request, $model);
                break;
            case 'phone':
                return $this->ramenCompleteChangeByPhone($request, $model);
                break;
        }
    }

    public function ramenCompleteChangeByEmail($request, $model){
        $model = $model->where('email', $request->input('old_identity'))->whereNotNull('identity_token')->first();
        if(is_null($model)){
            return [null, [
                'status_code' => 400,
                'message' => 'This account hasn\'t been requested for email change'
            ]];
        }
        if($model->identity_token == $request->input('answer') && $model->identity_pending == $request->input('identity')){
            $model->identity_token = null;
            $model->email = $model->identity_pending;
            $model->identity_pending = null;
            $model->save();
            return [$model, [
                'status_code' => 200,
                'message' => 'Email change is completed'
            ]];
        }else{
            return [null, [
                'status_code' => 400,
                'message' => 'Your answer can\'t be verified or the new identity is not the same with the one you are verified'
            ]];
        }
    }

    public function ramenCompleteChangeByPhone($request, $model){
        $model = $model->where('phone', $request->input('old_identity'))->whereNotNull('identity_token')->first();
        if(is_null($model)){
            return [null, [
                'status_code' => 400,
                'message' => 'This account hasn\'t been requested for email change'
            ]];
        }
        $answer = $this->phone->verify()->check($model->identity_token, $request->input('answer'));
        if ($answer->getStatus() == 0 && $model->identity_pending == $request->input('identity')) {
            $model->identity_token = null;
            $model->phone = $request->identity_pending;
            $model->identity_pending = null;
            $model->save();
            return [$model, [
                'status_code' => 200,
                'message' => 'Phone change is completed'
            ]];
        } else {
            return [null, [
                'status_code' => 400,
                'message' => 'Your answer can\'t be verified or the new identity is not the same with the one you are verified'
            ]];
        }
    }
}