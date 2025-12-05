<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleManagementController extends Controller
{
    /**
     * Definición de módulos con sus permisos, colores e iconos
     */
    private function getModulos(): array
    {
        return [
            'dashboard' => [
                'nombre' => 'Dashboard',
                'icon' => 'dashboard',
                'color' => 'primary',
                'permisos' => [
                    'ver_dashboard',
                    'ver_dashboard_resumen_usuarios',
                    'ver_dashboard_resumen_stock',
                    'ver_dashboard_resumen_cobranza',
                    'ver_dashboard_resumen_nvv',
                    'ver_dashboard_graficos',
                    'ver_dashboard_tablas_vendedores',
                ],
            ],
            'ventas' => [
                'nombre' => 'Ventas',
                'icon' => 'shopping_cart',
                'color' => 'success',
                'permisos' => [
                    'ver_ventas',
                    'ver_clientes',
                    'crear_clientes',
                    'editar_clientes',
                    'ver_cotizaciones',
                    'crear_cotizaciones',
                    'editar_cotizaciones',
                    'eliminar_cotizaciones',
                    'ver_notas_venta',
                    'crear_notas_venta',
                    'editar_notas_venta',
                    'aprobar_notas_venta',
                ],
            ],
            'cobranza' => [
                'nombre' => 'Cobranza',
                'icon' => 'account_balance',
                'color' => 'warning',
                'permisos' => [
                    'ver_cobranza',
                    'gestionar_cobranza',
                    'ver_facturas',
                    'ver_cheques',
                ],
            ],
            'manejo_stock' => [
                'nombre' => 'Manejo Stock',
                'icon' => 'inventory',
                'color' => 'info',
                'permisos' => [
                    'ver_manejo_stock',
                    'crear_manejo_stock',
                    'editar_manejo_stock',
                    'ver_historial_stock',
                    'ver_contabilidad_stock',
                    'ver_barrido_stock',
                    'ver_reporte_inventario',
                ],
            ],
            'mantenedor' => [
                'nombre' => 'Mantenedor',
                'icon' => 'settings',
                'color' => 'secondary',
                'permisos' => [
                    'ver_mantenedor',
                    'gestionar_bodegas',
                    'gestionar_ubicaciones',
                ],
            ],
            'productos' => [
                'nombre' => 'Productos',
                'icon' => 'inventory_2',
                'color' => 'dark',
                'permisos' => [
                    'ver_productos',
                    'crear_productos',
                    'editar_productos',
                    'eliminar_productos',
                    'cargar_productos',
                    'ver_stock_productos',
                    'gestionar_multiplos_venta',
                ],
            ],
            'informes' => [
                'nombre' => 'Informes',
                'icon' => 'assessment',
                'color' => 'info',
                'permisos' => [
                    'ver_informes',
                    'ver_nvv_pendientes',
                    'ver_facturas_pendientes',
                    'ver_facturas_emitidas',
                    'exportar_informes',
                ],
            ],
            'aprobaciones' => [
                'nombre' => 'Aprobaciones',
                'icon' => 'approval',
                'color' => 'success',
                'permisos' => [
                    'ver_aprobaciones',
                    'aprobar_supervisor',
                    'aprobar_compras',
                    'aprobar_picking',
                ],
            ],
            'usuarios' => [
                'nombre' => 'Gestión Usuarios',
                'icon' => 'people',
                'color' => 'primary',
                'permisos' => [
                    'ver_usuarios',
                    'crear_usuarios',
                    'editar_usuarios',
                    'eliminar_usuarios',
                    'asignar_roles',
                ],
            ],
            'roles_permisos' => [
                'nombre' => 'Roles y Permisos',
                'icon' => 'admin_panel_settings',
                'color' => 'danger',
                'permisos' => [
                    'ver_roles',
                    'crear_roles',
                    'editar_roles',
                    'eliminar_roles',
                    'ver_permisos',
                    'asignar_permisos',
                ],
            ],
            'configuracion' => [
                'nombre' => 'Configuración',
                'icon' => 'settings_applications',
                'color' => 'secondary',
                'permisos' => [
                    'ver_configuracion',
                    'editar_configuracion',
                    'ver_logs',
                ],
            ],
        ];
    }

    /**
     * Lista de roles
     */
    public function index()
    {
        $roles = Role::with(['permissions', 'users'])->orderBy('name')->get();
        $modulos = $this->getModulos();

        return view('admin.roles.index', compact('roles', 'modulos'));
    }

    /**
     * Guardar nuevo rol
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rol creado exitosamente. Ahora puede asignarle permisos.');
    }

    /**
     * Editar rol
     */
    public function edit(Role $role)
    {
        if ($role->name === 'Super Admin') {
            return redirect()->route('admin.roles.index')
                ->with('error', 'No se puede editar el rol Super Admin.');
        }

        // Limpiar cache de permisos primero
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $modulos = $this->getModulos();
        
        // Recargar el rol y sus permisos
        $role->refresh();
        $role->load('permissions');

        // Asegurar que todos los permisos de los módulos existan en la BD
        foreach ($modulos as $moduloKey => &$modulo) {
            foreach ($modulo['permisos'] as $permiso) {
                Permission::firstOrCreate([
                    'name' => $permiso,
                    'guard_name' => 'web'
                ]);
            }
        }
        unset($modulo); // Liberar referencia

        // Limpiar cache nuevamente después de crear permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Recargar permisos del rol después de limpiar cache
        $role->refresh();
        $role->load('permissions');

        return view('admin.roles.edit', compact('role', 'modulos'));
    }

    /**
     * Actualizar rol
     */
    public function update(Request $request, Role $role)
    {
        if ($role->name === 'Super Admin') {
            return redirect()->route('admin.roles.index')
                ->with('error', 'No se puede modificar el rol Super Admin.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permisos' => 'nullable|array',
        ]);

        // Actualizar nombre
        $role->update(['name' => $request->name]);

        // Sincronizar permisos
        $permisos = $request->input('permisos', []);
        
        // Asegurar que los permisos existan
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }

        $role->syncPermissions($permisos);

        // Limpiar cache de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('admin.roles.edit', $role)
            ->with('success', 'Rol actualizado exitosamente.');
    }

    /**
     * Eliminar rol
     */
    public function destroy(Role $role)
    {
        if ($role->name === 'Super Admin') {
            return redirect()->route('admin.roles.index')
                ->with('error', 'No se puede eliminar el rol Super Admin.');
        }

        if ($role->users->count() > 0) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'No se puede eliminar un rol que tiene usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rol eliminado exitosamente.');
    }
}

