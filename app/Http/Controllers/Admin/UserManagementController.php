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
        \Log::info('🔍 Cargando vendedores disponibles desde SQL Server');
        
        try {
            // Usar conexión directa de Laravel
            $vendedoresRaw = DB::connection('sqlsrv_external')
                ->select("SELECT KOFU, NOKOFU, EMAIL, RTFU FROM TABFU WHERE KOFU IS NOT NULL ORDER BY NOKOFU");
            
            \Log::info('📊 Vendedores obtenidos desde SQL Server:', ['count' => count($vendedoresRaw)]);
            
            // Obtener usuarios existentes para verificar cuáles ya tienen usuario
            $usuariosExistentes = User::whereNotNull('codigo_vendedor')
                ->pluck('codigo_vendedor')
                ->toArray();
            
            $vendedores = collect();
            
            foreach ($vendedoresRaw as $vendedor) {
                $vendedores->push((object)[
                    'id' => $vendedor->KOFU,
                    'KOFU' => $vendedor->KOFU,
                    'NOKOFU' => $vendedor->NOKOFU ?? '',
                    'EMAIL' => $vendedor->EMAIL ?? '',
                    'RTFU' => $vendedor->RTFU ?? '',
                    'tiene_usuario' => in_array($vendedor->KOFU, $usuariosExistentes)
                ]);
            }
            
            \Log::info('✅ Vendedores procesados:', ['count' => $vendedores->count()]);
            
        } catch (\Exception $e) {
            \Log::error('❌ Error al cargar vendedores:', ['error' => $e->getMessage()]);
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
            'rut' => ['nullable', new \App\Rules\ValidRut(), 'unique:users,rut'],
            'password' => 'required|min:8',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id'
        ]);

        // Obtener datos del empleado desde SQL Server usando conexión directa
        try {
            $vendedorRaw = DB::connection('sqlsrv_external')
                ->selectOne("SELECT KOFU, NOKOFU, EMAIL, RTFU FROM TABFU WHERE KOFU = ?", [$request->vendedor_id]);
            
            if (!$vendedorRaw) {
                return back()->withErrors(['vendedor_id' => 'No se encontró información del empleado seleccionado.'])->withInput();
            }
            
            $vendedorData = (object)[
                'KOFU' => $vendedorRaw->KOFU,
                'NOKOFU' => $vendedorRaw->NOKOFU ?? '',
                'EMAIL' => $vendedorRaw->EMAIL ?? '',
                'RTFU' => $vendedorRaw->RTFU ?? ''
            ];
            
            \Log::info("✅ Datos del vendedor encontrados:", ['kofu' => $vendedorData->KOFU, 'nombre' => $vendedorData->NOKOFU]);
            
        } catch (\Exception $e) {
            \Log::error('❌ Error al obtener datos del vendedor:', ['error' => $e->getMessage()]);
            return back()->withErrors(['vendedor_id' => 'Error al obtener la información del empleado: ' . $e->getMessage()])->withInput();
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
     * Obtener datos del vendedor vía AJAX
     */
    public function getVendedorData($vendedorId)
    {
        try {
            // Obtener datos del empleado desde SQL Server usando tsql (mismo método que vendedoresDisponibles)
            $host = env('SQLSRV_EXTERNAL_HOST');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            
            if (!$host || !$database || !$username || !$password) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales SQL Server no configuradas'
                ], 500);
            }
            
            // Usar tsql para consultar SQL Server con separador | (igual que CobranzaService)
            $query = "
                SELECT 
                    CAST(KOFU AS VARCHAR(10)) + '|' +
                    CAST(NOKOFU AS VARCHAR(100)) + '|' +
                    CAST(ISNULL(EMAIL, '') AS VARCHAR(100)) + '|' +
                    CAST(ISNULL(RTFU, '') AS VARCHAR(20)) AS DATOS
                FROM TABFU 
                WHERE KOFU = '{$vendedorId}'
            ";
            
            // Usar archivo temporal como en CobranzaService
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $output = shell_exec($command);
            
            unlink($tempFile);
            
            if (!$output || str_contains($output, 'error')) {
                \Log::warning('Error obteniendo datos del vendedor: ' . $output);
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró información del empleado'
                ], 404);
            }
            
            // Parsear el resultado usando | como separador (igual que CobranzaService)
            $lines = explode("\n", $output);
            $result = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Filtrar líneas vacías, de configuración y cabeceras
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'rows affected') !== false ||
                    strpos($line, 'DATOS') !== false) {
                    continue;
                }
                
                // Separar por | (formato: KOFU|NOKOFU|EMAIL|RTFU)
                if (strpos($line, '|') !== false) {
                    $parts = explode('|', $line);
                    if (count($parts) >= 4) {
                        $kofu = trim($parts[0]);
                        $nombre = trim($parts[1]);
                        $email = trim($parts[2]);
                        $rut = trim($parts[3]);
                        
                        // Limpiar email si está vacío
                        if (empty($email)) {
                            $email = '';
                        }
                        
                        // Asegurar codificación UTF-8
                        $nombre = mb_convert_encoding($nombre, 'UTF-8', 'auto');
                        
                        $result = [
                            'kofu' => $kofu,
                            'nombre' => $nombre,
                            'email' => $email,
                            'rut' => $rut
                        ];
                        break;
                    }
                }
            }
            
            if ($result) {
                return response()->json([
                    'success' => true,
                    'data' => $result
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No se encontró información del empleado'
            ], 404);
            
        } catch (\Exception $e) {
            \Log::error('Error en getVendedorData:', ['error' => $e->getMessage(), 'vendedor_id' => $vendedorId]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
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
            'rut' => ['nullable', new \App\Rules\ValidRut(), Rule::unique('users')->ignore($user->id)],
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
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required|same:password'
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