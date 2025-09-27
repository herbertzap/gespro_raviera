<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendedor;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasRole('Super Admin')) {
                abort(403, 'No tienes permisos para acceder a esta sección');
            }
            return $next($request);
        });
    }

    /**
     * Mostrar lista de usuarios
     */
    public function index()
    {
        $users = User::with(['roles', 'vendedor'])->paginate(20);
        $roles = Role::all();
        $pageSlug = 'admin-users';
        
        return view('admin.users.index', compact('users', 'roles', 'pageSlug'));
    }

    /**
     * Mostrar vendedores disponibles para crear usuario
     */
    public function vendedoresDisponibles()
    {
        // Consultar directamente desde SQL Server para obtener todos los empleados
        $host = env('SQLSRV_EXTERNAL_HOST');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        
        $query = "SELECT KOFU, NOKOFU, EMAIL, RTFU FROM TABFU WHERE KOFU IS NOT NULL ORDER BY NOKOFU";
        
        $command = "echo \"{$query}\" | tsql -S {$host} -U {$username} -P {$password} -D {$database}";
        
        $output = shell_exec($command);
        
        $vendedores = collect();
        
        if ($output) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, 'KOFU') === 0 || strpos($line, 'locale') === 0 || strpos($line, 'using') === 0 || strpos($line, 'rows affected') !== false) continue;
                
                // El formato es con tabs, separar por tabs
                $parts = explode("\t", $line);
                if (count($parts) >= 4) {
                    $vendedores->push((object)[
                        'id' => trim($parts[0]), // KOFU como ID
                        'KOFU' => trim($parts[0]),
                        'NOKOFU' => trim($parts[1]),
                        'EMAIL' => trim($parts[2]),
                        'RTFU' => trim($parts[3]),
                        'tiene_usuario' => false // Por ahora asumimos que no tienen usuario
                    ]);
                }
            }
        }
        
        $roles = Role::all();
        $pageSlug = 'admin-users-create';
        
        return view('admin.users.create-from-vendedor', compact('vendedores', 'roles', 'pageSlug'));
    }

    /**
     * Crear usuario desde vendedor
     */
    public function createFromVendedor(Request $request)
    {
        $request->validate([
            'vendedor_id' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'email_alternativo' => 'nullable|email|unique:users,email_alternativo',
            'rut' => 'nullable|string|max:20|unique:users,rut',
            'password' => 'required|min:8',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id'
        ]);

        // Obtener datos del empleado desde SQL Server
        $host = env('SQLSRV_EXTERNAL_HOST');
        $username = env('SQLSRV_EXTERNAL_USERNAME');
        $password = env('SQLSRV_EXTERNAL_PASSWORD');
        $database = env('SQLSRV_EXTERNAL_DATABASE');
        
        $query = "SELECT KOFU, NOKOFU, EMAIL, RTFU FROM TABFU WHERE KOFU = '{$request->vendedor_id}'";
        
        $command = "echo \"{$query}\" | tsql -S {$host} -U {$username} -P {$password} -D {$database}";
        
        $output = shell_exec($command);
        
        if (!$output) {
            return back()->withErrors(['vendedor_id' => 'No se pudo obtener la información del empleado.'])->withInput();
        }
        
        $lines = explode("\n", trim($output));
        $vendedorData = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'KOFU') === 0 || strpos($line, 'locale') === 0 || strpos($line, 'using') === 0 || strpos($line, 'rows affected') !== false) continue;
            
            // El formato es con tabs, separar por tabs
            $parts = explode("\t", $line);
            if (count($parts) >= 4) {
                $vendedorData = (object)[
                    'KOFU' => trim($parts[0]),
                    'NOKOFU' => trim($parts[1]),
                    'EMAIL' => trim($parts[2]),
                    'RTFU' => trim($parts[3])
                ];
                break;
            }
        }
        
        if (!$vendedorData) {
            return back()->withErrors(['vendedor_id' => 'No se encontró información del empleado seleccionado.'])->withInput();
        }

        try {
            DB::beginTransaction();

            // Determinar si es vendedor basado en los roles seleccionados
            $esVendedor = false;
            foreach ($request->roles as $roleId) {
                $role = Role::find($roleId);
                if ($role && $role->name === 'Vendedor') {
                    $esVendedor = true;
                    break;
                }
            }

            // Crear usuario
            $user = User::create([
                'name' => $vendedorData->NOKOFU,
                'email' => $request->email,
                'email_alternativo' => $request->email_alternativo,
                'password' => Hash::make($request->password),
                'codigo_vendedor' => $esVendedor ? $vendedorData->KOFU : null, // Solo si es vendedor
                'rut' => $request->rut ?: $vendedorData->RTFU, // Usar RUT del formulario o del empleado
                'es_vendedor' => $esVendedor,
                'primer_login' => true,
                'fecha_ultimo_cambio_password' => now()
            ]);

            // Asignar roles
            foreach ($request->roles as $roleId) {
                $role = Role::find($roleId);
                if ($role) {
                    $user->assignRole($role->name);
                }
            }

            // Marcar vendedor como que tiene usuario
            $vendedor->marcarConUsuario($user->id);

            // Enviar email con datos de acceso
            $this->enviarEmailAcceso($user, $request->password);

            DB::commit();

            return redirect()->route('admin.users.index')
                ->with('success', "Usuario creado exitosamente para {$vendedor->NOKOFU}");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al crear usuario: ' . $e->getMessage()]);
        }
    }

    /**
     * Mostrar formulario de edición de usuario
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $userRoles = $user->roles->pluck('id')->toArray();
        $pageSlug = 'admin-users-edit';
        
        return view('admin.users.edit', compact('user', 'roles', 'userRoles', 'pageSlug'));
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'email_alternativo' => ['nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'rut' => 'nullable|string|max:20',
            'codigo_vendedor' => 'nullable|string|max:10',
            'es_vendedor' => 'boolean',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id'
        ]);

        try {
            DB::beginTransaction();

            // Actualizar datos del usuario
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'email_alternativo' => $request->email_alternativo,
                'rut' => $request->rut,
                'codigo_vendedor' => $request->codigo_vendedor,
                'es_vendedor' => $request->boolean('es_vendedor')
            ]);

            // Actualizar roles
            $user->roles()->sync($request->roles);

            DB::commit();

            return redirect()->route('admin.users.index')
                ->with('success', 'Usuario actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al actualizar usuario: ' . $e->getMessage()]);
        }
    }

    /**
     * Cambiar contraseña de usuario
     */
    public function changePassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed'
        ]);

        $user->update([
            'password' => Hash::make($request->password),
            'primer_login' => true,
            'fecha_ultimo_cambio_password' => now()
        ]);

        return back()->with('success', 'Contraseña actualizada exitosamente');
    }

    /**
     * Eliminar usuario
     */
    public function destroy(User $user)
    {
        // No permitir eliminar el super admin
        if ($user->hasRole('Super Admin') && $user->email === 'herbert.zapata19@gmail.com') {
            return back()->withErrors(['error' => 'No se puede eliminar el super administrador']);
        }

        try {
            DB::beginTransaction();

            // Si es vendedor, marcar como sin usuario
            if ($user->vendedor) {
                $user->vendedor->update([
                    'tiene_usuario' => false,
                    'user_id' => null
                ]);
            }

            // Eliminar usuario
            $user->delete();

            DB::commit();

            return redirect()->route('admin.users.index')
                ->with('success', 'Usuario eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al eliminar usuario: ' . $e->getMessage()]);
        }
    }

    /**
     * Enviar email con datos de acceso
     */
    private function enviarEmailAcceso(User $user, string $password)
    {
        try {
            Mail::send('emails.user-created', [
                'user' => $user,
                'password' => $password,
                'loginUrl' => url('/login')
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Acceso al Sistema - Comercial Higuera');
            });
        } catch (\Exception $e) {
            \Log::error('Error enviando email de acceso: ' . $e->getMessage());
        }
    }
}