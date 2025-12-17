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
            if (!auth()->user()->hasRole('Super Admin') && !auth()->user()->hasRole('Administrativo')) {
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
        
        $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
        $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
        
        try {
            $empleados = collect();
            
            if ($usarTSQL) {
                \Log::info('📡 Usando tsql para consultar TABFU');
                
                // Usar tsql para consultar SQL Server
                $host = env('SQLSRV_EXTERNAL_HOST');
                $username = env('SQLSRV_EXTERNAL_USERNAME');
                $password = env('SQLSRV_EXTERNAL_PASSWORD');
                $database = env('SQLSRV_EXTERNAL_DATABASE');
                $port = env('SQLSRV_EXTERNAL_PORT', '1433');
                
                if (!$host || !$database || !$username || !$password) {
                    throw new \Exception('Credenciales SQL Server no configuradas');
                }
                
                // Consulta con separador | para facilitar parsing
                $query = "
                    SELECT 
                        CAST(KOFU AS VARCHAR(10)) + '|' +
                        CAST(ISNULL(NOKOFU, '') AS VARCHAR(100)) + '|' +
                        CAST(ISNULL(EMAIL, '') AS VARCHAR(100)) + '|' +
                        CAST(ISNULL(RTFU, '') AS VARCHAR(20)) AS DATOS
                    FROM TABFU 
                    WHERE KOFU IS NOT NULL
                    ORDER BY NOKOFU
                ";
                
                // Usar archivo temporal
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $query . "\ngo\nquit");
                
                $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
                $output = shell_exec($command);
                
                unlink($tempFile);
                
                if (!$output || str_contains(strtolower($output), 'error')) {
                    throw new \Exception('Error ejecutando tsql: ' . substr($output, 0, 200));
                }
                
                // Parsear el resultado
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Filtrar líneas vacías, de configuración y cabeceras
                    if (empty($line) || 
                        strpos($line, 'locale') !== false || 
                        strpos($line, 'Setting') !== false || 
                        strpos($line, 'Msg ') !== false || 
                        strpos($line, 'rows affected') !== false ||
                        strpos($line, 'DATOS') !== false ||
                        preg_match('/^\d+>$/', $line)) {
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
                            
                            // Asegurar codificación UTF-8
                            $nombre = mb_convert_encoding($nombre, 'UTF-8', 'auto');
                            
                            $empleados->push((object)[
                                'KOFU' => $kofu,
                                'NOKOFU' => $nombre,
                                'EMAIL' => $email,
                                'RTFU' => $rut
                            ]);
                        }
                    }
                }
            } else {
                \Log::info('📡 Usando conexión directa Laravel para consultar TABFU');
                
                // Consultar directamente usando la conexión de Laravel
                $empleados = DB::connection('sqlsrv_external')
                    ->table('TABFU')
                    ->select('KOFU', 'NOKOFU', 'EMAIL', 'RTFU')
                    ->whereNotNull('KOFU')
                    ->orderBy('NOKOFU')
                    ->get();
            }
            
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
            \Log::error('Stack trace: ' . $e->getTraceAsString());
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
            'codigo_vendedor' => 'required|string|max:10',
            'email' => ['required', new \App\Rules\ValidEmailWithDomain(), 'unique:users,email'],
            'email_alternativo' => ['nullable', new \App\Rules\ValidEmailWithDomain(), 'unique:users,email_alternativo'],
            'rut' => ['nullable', new \App\Rules\ValidRut(), 'unique:users,rut'],
            'password' => 'required|min:8',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id'
        ]);

        // Obtener datos del empleado desde SQL Server
        $encrypt = env('SQLSRV_EXTERNAL_ENCRYPT', 'yes');
        $usarTSQL = ($encrypt === 'no' || $encrypt === false || $encrypt === 'false');
        
        try {
            $empleado = null;
            
            if ($usarTSQL) {
                \Log::info('📡 Usando tsql para consultar vendedor específico');
                
                // Usar tsql para consultar SQL Server
                $host = env('SQLSRV_EXTERNAL_HOST');
                $username = env('SQLSRV_EXTERNAL_USERNAME');
                $password = env('SQLSRV_EXTERNAL_PASSWORD');
                $database = env('SQLSRV_EXTERNAL_DATABASE');
                $port = env('SQLSRV_EXTERNAL_PORT', '1433');
                
                if (!$host || !$database || !$username || !$password) {
                    throw new \Exception('Credenciales SQL Server no configuradas');
                }
                
                // Escapar el código de vendedor para prevenir SQL injection
                $vendedorIdEscapado = str_replace("'", "''", trim($request->vendedor_id));
                
                // Consulta con separador | para facilitar parsing
                $query = "
                    SELECT 
                        CAST(KOFU AS VARCHAR(10)) + '|' +
                        CAST(ISNULL(NOKOFU, '') AS VARCHAR(100)) + '|' +
                        CAST(ISNULL(EMAIL, '') AS VARCHAR(100)) + '|' +
                        CAST(ISNULL(RTFU, '') AS VARCHAR(20)) AS DATOS
                    FROM TABFU 
                    WHERE KOFU = '{$vendedorIdEscapado}'
                ";
                
                // Usar archivo temporal
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $query . "\ngo\nquit");
                
                $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
                $output = shell_exec($command);
                
                unlink($tempFile);
                
                if (!$output || str_contains(strtolower($output), 'error')) {
                    throw new \Exception('Error ejecutando tsql: ' . substr($output, 0, 200));
                }
                
                // Parsear el resultado
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Filtrar líneas vacías, de configuración y cabeceras
                    if (empty($line) || 
                        strpos($line, 'locale') !== false || 
                        strpos($line, 'Setting') !== false || 
                        strpos($line, 'Msg ') !== false || 
                        strpos($line, 'rows affected') !== false ||
                        strpos($line, 'DATOS') !== false ||
                        preg_match('/^\d+>$/', $line)) {
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
                            
                            // Asegurar codificación UTF-8
                            $nombre = mb_convert_encoding($nombre, 'UTF-8', 'auto');
                            
                            $empleado = (object)[
                                'KOFU' => $kofu,
                                'NOKOFU' => $nombre,
                                'EMAIL' => $email,
                                'RTFU' => $rut
                            ];
                            break;
                        }
                    }
                }
            } else {
                \Log::info('📡 Usando conexión directa Laravel para consultar vendedor específico');
                
                $empleado = DB::connection('sqlsrv_external')
                    ->table('TABFU')
                    ->select('KOFU', 'NOKOFU', 'EMAIL', 'RTFU')
                    ->where('KOFU', $request->vendedor_id)
                    ->first();
            }
            
            \Log::info('🔍 Consultando vendedor específico:', ['vendedor_id' => $request->vendedor_id, 'encontrado' => !is_null($empleado)]);
            
        } catch (\Exception $e) {
            \Log::error('❌ Error consultando empleado: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
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
            // Nota: No usar Hash::make() aquí porque el modelo User tiene un cast 'hashed' que hashea automáticamente
            $user = User::create([
                'name' => $vendedorData->NOKOFU,
                'email' => trim($request->email),
                'email_alternativo' => $request->email_alternativo ? trim($request->email_alternativo) : null,
                'password' => $request->password, // El cast 'hashed' del modelo se encargará del hashing
                'codigo_vendedor' => $request->codigo_vendedor ?: $vendedorData->KOFU, // Usar código del formulario o del empleado
                'rut' => $request->rut ?: $vendedorData->RTFU, // Usar RUT del formulario o del empleado
                'es_vendedor' => $esVendedor,
                'primer_login' => true,
                'fecha_ultimo_cambio_password' => now()
            ]);

            // Validar que solo Super Admin puede asignar rol Super Admin
            $superAdminRole = Role::where('name', 'Super Admin')->first();
            if ($superAdminRole && in_array($superAdminRole->id, $request->roles)) {
                if (!auth()->user()->hasRole('Super Admin')) {
                    DB::rollBack();
                    return back()->withErrors(['roles' => 'Solo un Super Admin puede asignar el rol Super Admin a otro usuario.'])->withInput();
                }
            }
            
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
            'email' => ['required', new \App\Rules\ValidEmailWithDomain(), Rule::unique('users')->ignore($user->id)],
            'email_alternativo' => ['nullable', new \App\Rules\ValidEmailWithDomain(), Rule::unique('users')->ignore($user->id)],
            'rut' => 'nullable|string|max:20',
            'codigo_vendedor' => 'required|string|max:10',
            'es_vendedor' => 'boolean',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id'
        ]);

        try {
            DB::beginTransaction();

            // Validar que solo Super Admin puede asignar rol Super Admin
            $superAdminRole = Role::where('name', 'Super Admin')->first();
            if ($superAdminRole && in_array($superAdminRole->id, $request->roles)) {
                if (!auth()->user()->hasRole('Super Admin')) {
                    DB::rollBack();
                    return back()->withErrors(['roles' => 'Solo un Super Admin puede asignar el rol Super Admin a otro usuario.'])->withInput();
                }
            }

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
        try {
            $request->validate([
                'password' => 'required|min:8|confirmed'
            ], [
                'password.required' => 'La contraseña es obligatoria.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.confirmed' => 'Las contraseñas no coinciden.'
            ]);

            \Log::info('Cambiando contraseña para usuario', [
                'user_id' => $user->id,
                'email' => $user->email,
                'password_length' => strlen($request->password)
            ]);

            // Guardar el password directamente - el cast 'hashed' lo hasheará automáticamente
            $user->password = $request->password;
            $user->primer_login = true;
            $user->fecha_ultimo_cambio_password = now();
            $user->save();

            // Verificar que se guardó correctamente
            $user->refresh();
            $hashGuardado = $user->getAttributes()['password'];
            $verificacion = Hash::check($request->password, $hashGuardado);
            
            \Log::info('Contraseña actualizada', [
                'user_id' => $user->id,
                'hash_length' => strlen($hashGuardado),
                'verificacion_exitosa' => $verificacion
            ]);

            if (!$verificacion) {
                \Log::error('ERROR: La contraseña guardada no verifica correctamente después de guardar');
            }

            return back()->with('success', 'Contraseña actualizada exitosamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            \Log::error('Error al cambiar contraseña: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return back()->with('error', 'Error al actualizar la contraseña: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar usuario
     */
    public function destroy(User $user)
    {
        $currentUser = auth()->user();
        
        // No permitir eliminar el super admin específico
        if ($user->hasRole('Super Admin') && $user->email === 'herbert.zapata19@gmail.com') {
            return back()->withErrors(['error' => 'No se puede eliminar el super administrador']);
        }
        
        // Validar permisos de eliminación según el rol del usuario actual
        if ($user->hasRole('Super Admin')) {
            // Si el usuario a eliminar tiene rol Super Admin
            if (!$currentUser->hasRole('Super Admin')) {
                // Solo Super Admin puede eliminar a otro Super Admin
                return back()->withErrors(['error' => 'No tienes permisos para eliminar un usuario con rol Super Admin. Solo un Super Admin puede eliminar a otro Super Admin.']);
            }
        }
        
        // Super Admin puede eliminar a cualquiera (excepto el protegido arriba)
        // Administrativo puede eliminar a cualquiera excepto Super Admin (ya validado arriba)

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