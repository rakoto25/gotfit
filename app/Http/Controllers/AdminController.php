<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function getUsers()
    {
        $users = User::latest()->get();

        return response()->json([
            'status' => 200,
            'users' => $users
        ]);
    }
}
