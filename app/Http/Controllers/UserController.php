<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the users
     *
     * @param  \App\Models\User  $model
     * @return \Illuminate\View\View
     */
    public function index(User $model)
    {
        $pageSlug = 'users'; // O cualquier valor que desees asignar a esta variable
        return view('users.index', [
            'users' => $model->paginate(15),
            'pageSlug' => $pageSlug
        ]);
    }

    
}
