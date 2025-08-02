<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all(); // Obtener todos los permisos
        return view('permissions.index', compact('permissions'));
    }
    

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name',
            'guard_name' => 'required',
        ]);

        Permission::create($request->only('name', 'guard_name'));

        return redirect()->route('permissions.index')->with('success', 'Permiso creado con éxito.');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return redirect()->route('permissions.index')->with('success', 'Permiso eliminado con éxito.');
    }
}
