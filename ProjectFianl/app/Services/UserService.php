<?php

namespace App\Services;

use App\Events\WelcomeEvent;
use App\Jobs\CheckTeacherMailJob;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Notifications\CheckTeacherMail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class UserService
{
    //register process for student or teacher
    public function register($request)
    {
         if($request['type'] == 'teacher') {
            //send email for the admin to apply this teacher
            $data = $request;
            CheckTeacherMailJob::dispatch($data);

            //sending welcome email
            $welcome= 'Your Request for processing has been registered,please wait for response the admin';
            $data = [];
            $data['name'] = $request['full_name'];
            $data['email'] = $request['email'];
             Event::dispatch(new WelcomeEvent($welcome,$data));

        }else{
            $user=User::query()->create([
                'full_name' => $request['full_name'],
                'email' => $request['email'],
                'phone' => $request['phone'],
                'password' => bcrypt($request['password']),
               // 'birthday' => $request['birthday'],
                //'address' => $request['address'],
                'type' => 'student',
                'image'=> $request['image'],
            ]);
            $studentRole = Role::query()
                ->where('name', 'student')
                ->first();
            $user->assignRole($studentRole);
            $permissions = $studentRole->permissions()->pluck('name')->toArray();
            $user->givePermissionTo($permissions);
            $user->load('roles', 'permissions');

            $user = User::query()->find($user['id']);
            $user = $this->appendRolesAndPermissions($user);
            $user['token'] = $user->createToken("token")->plainTextToken;
            $message = __('strings.your created successfully');

            //sending welcome email

            $welcome= 'welcome in our app';
             $data = [];
             $data['name'] = $request['full_name'];
             $data['email'] = $request['email'];
            Event::dispatch(new WelcomeEvent($welcome,$data));

        }
        return [
            'user' => $user ?? [],
            'message' => $message ?? __('strings.Your Request for processing has been registered,please wait for response the admin'),
        ];
    }
    //login process for student or teacher
    public function login($request) : array
    {
        $user=User::query()->where('email',$request['email'])
            ->first();
        if(!is_null($user)){
            if (Auth::attempt(['email' => $request['email'], 'password' => $request['password']])){
                $user = $this->appendRolesAndPermissions($user);
                $user['token']=$user->createToken("token")->plainTextToken;
                $message= __('strings.Your logged successfully');
                $code=200;

                //sending welcome email
                $welcome= 'welcome back';
                $data = [];
                $data['name'] = $user->full_name;
                $data['email'] = $request['email'];
                  Event::dispatch(new WelcomeEvent($welcome,$data));
            }else{

                $user = [];
                $message= __('strings.User email dose not match with password');
                $code=401;
            }

        }else{
            $message= __('strings.User not found in data you need to register first');
            $code=404;
        }
        return [
            'user' => $user,
            'message' => $message,
            'code' => $code,
        ];

    }

    //logout process for student or teacher
    public function logout() : array
    {
        $user=Auth::user();
        if(!is_null($user)){
            auth()->user()->tokens()->delete();
            $message= __('strings.Logged Out successfully');
            $code=200;

        }else{
            $message= __('strings.Invalid Token');
            $code=404;
        }
        return [
            'user' => $user,
            'message' => $message,
            'code' => $code,
        ];

    }

    //function to apply teacher in this program and create his account
    public function Accept_teacher_coming_by_email($request,$id) : array
    {
        $add = \App\Models\Notification::query()->where('id', $id) ->first();

        if ($add->apply == 0) {
            $admin =User::query()->find(Auth::id());
            if ($admin->hasRole('admin')) {
                $add->update([
                    'apply' => 1
                ]);

                $user = $this->Make_teacher($request);
                $message = __('strings.Accept this teacher in your app');
                $code = 200;

                $data = [];
                $welcome= 'welcome in our app Your request has been accept ';
                $data['name'] = $request['full_name'];
                $data['email'] = $request['email'];
                Event::dispatch(new WelcomeEvent($welcome,$data));
            } else {

                $message = __('strings.you dont have permission to accept teacher');
                $code = 403;
                $user = [];

            }
        } else {

            $message = __('strings.This teacher already registered');
            $code = 403;
            $user = [];

        }

        return [
            'user' => $user,
            'message' => $message,
            'code' => $code,
        ];
    }

    //add teacher by admin in real life
    public function admin_adding_new_teacher($request) : array
    {
        $admin =User::query()->find(Auth::id());
        if (($admin)->hasRole('admin')){
            $user = $this->Make_teacher($request);
            $message = __('strings.Accept this teacher in your app');
            $code = 200;
            $welcome= 'welcome in our app ypu have been add by the admin';
            $data = [];
            $data['name'] = $request['full_name'];
            $data['email'] = $request['email'];
            Event::dispatch(new WelcomeEvent($welcome,$data));
        }else{
            $message = __('strings.you dont have permission to accept teacher');
            $code = 403;
            $user = [];
        }
        return [
            //$user
            'user' => $user,
            'message' => $message,
            'code' => $code,
        ];
    }

    //make new row teacher after applying
    public function Make_teacher($request) : array
    {
        $user=User::query()->create([
            'full_name' => $request['full_name'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => bcrypt($request['password']),
            'birthday' => $request['birthday'],
            'address' => $request['address'],
            'type' =>'teacher',
            'image'=> $request['image'],
        ]);
        $teacherRole = Role::query()->where('name', 'teacher')->first();
        $user->assignRole($teacherRole);
        $permissions = $teacherRole->permissions()->pluck('name')->toArray();
        $user->givePermissionTo($permissions);
        $user->load('roles', 'permissions');
        $user = User::query()->find($user['id']);
        $user = $this->appendRolesAndPermissions($user);
        $user['token'] = $user->createToken("token")->plainTextToken;
        $message = __('strings.Teacher account created successfully');
        $code = 200;
        $welcome= 'welcome in our app we are sorry for make you wait for responding';
        $data = $user->full_name;
        //Event::dispatch(new WelcomeEvent($welcome,$data));
        return [
            'user' => $user,
            'message' => $message,
            'code' => $code,
        ];
    }

    //function to add roles and permissions to user array
    public function appendRolesAndPermissions($user)
    {
        $roles=[];
        foreach ($user->roles as $role){
            $roles []= $role->name;
        }
        unset($user['roles']);
        $user['roles']=$roles;

        $permissions=[];
        foreach ($user->permissions as $permission) {
            $permissions [] =$permission->name;
        }

        unset($user['permissions']);
        $user['permissions']=$permissions;

        return $user;

    }






}
