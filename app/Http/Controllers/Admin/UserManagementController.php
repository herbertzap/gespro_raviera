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
        \Log::info('🔍 Cargando vendedores disponibles desde SQL Server (conexión directa)');
        
        try {
            // Consultar directamente usando la conexión de Laravel
            $empleados = DB::connection('sqlsrv_external')
                ->table('TABFU')
                ->select('KOFU', 'NOKOFU', 'EMAIL', 'RTFU')
                ->whereNotNull('KOFU')
                ->orderBy('NOKOFU')
                ->get();
            
            \Log::info('📊 Empleados encontrados:', ['count' => $empleados->count()]);
            
            // Obtener usuarios existentes para marcar los que ya tienen cuenta
            $usuariosExistentes = User::whereNotNull('codigo_vendedor')
                ->pluck('codigo_vendedor')
                ->toArray();
            
            $vendedores = $empleados->map(function($empleado) use ($usuariosExistentes) {
                return (object)[
                    'id' => trim($empleado->KOFU),
                    'KOFU' => trim($empleado->KOFU),
                    'NOKOFU' => trim($empleado->NOKOFU ?? ''),
                    'EMAIL' => trim($empleado->EMAIL ?? ''),
                    'RTFU' => trim($empleado->RTFU ?? ''),
                    'tiene_usuario' => in_array(trim($empleado->KOFU), $usuariosExistentes)
                ];
            });
            
            \Log::info('✅ Vendedores procesados:', ['count' => $vendedores->count()]);
            
        } catch (\Exception $e) {
            \Log::error('❌ Error consultando empleados: ' . $e->getMessage());
            $vendedores = collect();
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

        // Obtener datos del empleado desde SQL Server (conexión directa)
        try {
            $empleado = DB::connection('sqlsrv_external')
                ->table('TABFU')
                ->select('KOFU', 'NOKOFU', 'EMAIL', 'RTFU')
                ->where('KOFU', $request->vendedor_id)
                ->first();
            
            \Log::info('🔍 Consultando vendedor específico:', ['vendedor_id' => $request->vendedor_id, 'encontrado' => !is_null($empleado)]);
            
        } catch (\Exception $e) {
            \Log::error('❌ Error consultando empleado: ' . $e->getMessage());
            return back()->withErrors(['vendedor_id' => 'Error de conexión al consultar el empleado.'])->withInput();
        }
        
        if (!$empleado) {
            return back()->withErrors(['vendedor_id' => 'No se encontró información del empleado seleccionado.'])->withInput();
        }
        
        $vendedorData = (object)[
            'KOFU' => trim($empleado->KOFU),
            'NOKOFU' => trim($empleado->NOKOFU ?? ''),
            'EMAIL' => trim($empleado->EMAIL ?? ''),
            'RTFU' => trim($empleado->RTFU ?? '')
        ];
        
        \Log::info("✅ Datos del vendedor encontrados:", ['kofu' => $vendedorData->KOFU, 'nombre' => $vendedorData->NOKOFU]);

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

            // Enviar email con datos de acceso
            $this->enviarEmailAcceso($user, $request->password);

            DB::commit();

            return redirect()->route('admin.users.index')
                ->with('success', "Usuario creado exitosamente para {$vendedorData->NOKOFU}");

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