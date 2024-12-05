<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\UserRequest;
use Illuminate\Http\Request;
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
        $users = $model->paginate(15);

        return view('users.index', compact('users', 'pageSlug'));
    }




    public function assignRole(Request $request, User $user)
{
    $request->validate([
        'role' => 'required|exists:roles,name',
    ]);

    $user->syncRoles([$request->role]); // Asigna el rol al usuario, eliminando otros roles existentes

    return redirect()->route('user.index')->with('success', 'Rol asignado con éxito.');
}

public function create()
{
    return view('users.create'); // Crea una vista llamada 'users/create.blade.php'
}

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8',
    ]);

    User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    return redirect()->route('user.index')->with('success', 'Usuario creado con éxito.');
}

public function destroy(User $user)
{
    $user->delete();

    return redirect()->route('user.index')->with('success', 'Usuario eliminado con éxito.');
}

public function edit(User $user)
{
    return view('users.edit', compact('user'));
}


public function update(Request $request, User $user)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $user->id,
    ]);

    $user->update([
        'name' => $request->name,
        'email' => $request->email,
    ]);
    return redirect()->route('user.index')->with('success', 'Usuario actualizado con éxito.');
}
  
}
