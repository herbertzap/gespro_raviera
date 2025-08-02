<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::paginate(15); // Paginación
        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles',
        ]);

        Role::create(['name' => $request->name]);

        return redirect()->route('roles.index')->with('success', 'Rol creado con éxito.');
    }

    public function edit(Role $role)
{
    // Obtener todos los permisos
    $permissions = Permission::all();

    // Pasar el rol y los permisos a la vista
    return view('roles.edit', compact('role', 'permissions'));
}

public function update(Request $request, Role $role)
{
    // Validar la entrada
    $request->validate([
        'name' => 'required|unique:roles,name,' . $role->id,
        'permissions' => 'array',
        'permissions.*' => 'exists:permissions,id',
    ]);

    // Actualizar el nombre del rol
    $role->update(['name' => $request->name]);

    // Sincronizar los permisos
    if ($request->has('permissions')) {
        $permissions = \Spatie\Permission\Models\Permission::whereIn('id', $request->permissions)->pluck('name');
        $role->syncPermissions($permissions); // Sincroniza usando los nombres de los permisos
    }

    // Redirigir con un mensaje de éxito
    return redirect()->route('roles.index')->with('success', 'Rol actualizado con éxito.');
}




    public function destroy(Role $role)
    {
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado con éxito.');
    }

    public function assignPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'array|exists:permissions,name',
        ]);

        if ($request->has('permissions')) {
            $permissions = \Spatie\Permission\Models\Permission::whereIn('id', $request->permissions)->pluck('name');
            $role->syncPermissions($permissions); // Sincroniza con los nombres
        }
        

        return redirect()->route('roles.index')->with('success', 'Permisos asignados con éxito.');
    }




    
}
