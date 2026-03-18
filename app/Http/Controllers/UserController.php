<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $hashedPassword = Hash::make($request->password);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $hashedPassword,
                'position' => $request->position,
                'profile' => 'common',
            ]);

            Player::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $hashedPassword,
                'position' => $request->position,
                'phone' => $request->phone,
                'nickname' => $request->nickname,
                'user_id' => $user->id,
            ]);

            return $user->load('player');
        });

        return new UserResource($user);
    }
}
