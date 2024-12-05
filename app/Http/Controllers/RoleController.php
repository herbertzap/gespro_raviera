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
        'name' => 'required|unique:roles,name,' . $role->id, // El nombre debe ser único, excepto para el rol actual
        'permissions' => 'array', // Asegurarse de que sea un arreglo
        'permissions.*' => 'exists:permissions,id', // Cada permiso debe existir en la tabla de permisos
    ]);

    // Actualizar el nombre del rol
    $role->update(['name' => $request->name]);

    // Sincronizar los permisos
    if ($request->has('permissions')) {
        $role->syncPermissions($request->permissions); // Sincroniza los permisos con los seleccionados
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

        $role->syncPermissions($request->permissions);

        return redirect()->route('roles.index')->with('success', 'Permisos asignados con éxito.');
    }

    public function __construct()
{
    $this->middleware('permission:ver roles')->only('index');
    $this->middleware('permission:crear roles')->only(['create', 'store']);
    $this->middleware('permission:editar roles')->only(['edit', 'update']);
    $this->middleware('permission:eliminar roles')->only('destroy');
}


    
}
