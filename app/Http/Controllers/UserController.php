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

    // Nota: No usar Hash::make() aquí porque el modelo User tiene un cast 'hashed' que hashea automáticamente
    User::create([
        'name' => $request->name,
        'email' => trim($request->email),
        'password' => $request->password, // El cast 'hashed' del modelo se encargará del hashing
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
    $roles = \Spatie\Permission\Models\Role::all(); // Cargar todos los roles
    return view('users.edit', compact('user', 'roles'));
}


public function update(Request $request, User $user)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $user->id,
        'roles' => 'nullable|array', // Validar que roles sea un array
        'roles.*' => 'exists:roles,id', // Validar que los roles existan
    ]);

    $user->update([
        'name' => $request->name,
        'email' => $request->email,
    ]);

    // Convertir IDs de roles a nombres de roles
    if ($request->has('roles')) {
        $roleNames = \Spatie\Permission\Models\Role::whereIn('id', $request->roles)->pluck('name')->toArray();
        $user->syncRoles($roleNames);
    }

    return redirect()->route('user.index')->with('success', 'Usuario actualizado con éxito.');
}


  
}
