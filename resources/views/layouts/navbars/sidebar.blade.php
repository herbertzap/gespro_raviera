<div class="sidebar">
    <div class="sidebar-wrapper">
        <div class="logo">
            <a href="#" class="simple-text logo-mini col-12">
                @if(auth()->user()->profile_photo_path)
                    <img class="rounded-circle" src="{{ Storage::url(auth()->user()->profile_photo_path) }}" alt="{{ auth()->user()->name }}" style="width: 40px; height: 40px; object-fit: cover;">
                @else
                    <div class="avatar-placeholder" style="width: 40px; height: 40px; background: linear-gradient(45deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                @endif
            </a>
            <a href="#" class="simple-text logo-normal">
                <div style="text-align: center;">
                    <div style="font-size: 14px; font-weight: bold; margin-bottom: 2px;">{{ auth()->user()->name }}</div>
                    @if(auth()->user()->codigo_vendedor)
                        <div style="font-size: 10px; opacity: 0.6; margin-top: 2px;">Vendedor: {{ auth()->user()->codigo_vendedor }}</div>
                    @endif
                </div>
            </a>
        </div>
        <ul class="nav">
            <!-- Dashboard (Inicio) -->
            @can('ver_dashboard')
            <li @if ($pageSlug == 'dashboard') class="active " @endif>
                <a href="{{ route('dashboard') }}">
                    <i class="tim-icons icon-chart-pie-36"></i>
                    <p>{{ __('Dashboard') }}</p>
                </a>
            </li>
            @endcan

            <!-- Ventas -->
            @can('ver_ventas')
            <li>
                <a data-toggle="collapse" href="#Ventas" aria-expanded="false">
                    <i class="tim-icons icon-cart"></i>
                    <span class="nav-link-text">{{ __('Ventas') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse" id="Ventas">
                    <ul class="nav pl-4">
                        @can('ver_clientes')
                        <li @if ($pageSlug == 'buscar-clientes') class="active " @endif>
                            <a href="{{ route('cobranza.index') }}">
                                <i class="tim-icons icon-single-02"></i>
                                <p>{{ __('Clientes') }}</p>
                            </a>
                        </li>
                        @endcan
                        
                        @can('ver_cotizaciones')
                        <li @if ($pageSlug == 'cotizaciones' && request('tipo_documento') == 'cotizacion') class="active " @endif>
                            <a href="{{ route('cotizaciones.index') }}?tipo_documento=cotizacion">
                                <i class="tim-icons icon-paper"></i>
                                <p>{{ __('Cotizaciones') }}</p>
                            </a>
                        </li>
                        @endcan
                        
                        @can('ver_notas_venta')
                        <li @if ($pageSlug == 'cotizaciones' && request('tipo_documento') == 'nota_venta') class="active " @endif>
                            <a href="{{ route('cotizaciones.index') }}?tipo_documento=nota_venta">
                                <i class="tim-icons icon-notes"></i>
                                <p>{{ __('Notas de Venta') }}</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcan

            <!-- Compras - Productos (Solo para roles con permiso ver_productos) -->
            @can('ver_productos')
            <li>
                <a data-toggle="collapse" href="#ComprasMenu" aria-expanded="false">
                    <i class="tim-icons icon-bag-16"></i>
                    <span class="nav-link-text">{{ __('Compras') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse" id="ComprasMenu">
                    <ul class="nav pl-4">
                        <li @if (($pageSlug ?? '') == 'productos') class="active " @endif>
                            <a href="{{ route('productos.index') }}">
                                <i class="tim-icons icon-notes"></i>
                                <p>{{ __('Lista de Productos') }}</p>
                            </a>
                        </li>
                        @can('gestionar_multiplos_venta')
                        <li @if (($pageSlug ?? '') == 'multiplos-productos') class="active " @endif>
                            <a href="{{ route('admin.productos.multiplos') }}">
                                <i class="material-icons">inventory_2</i>
                                <p>{{ __('Múltiplos de Venta') }}</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcan

            <!-- Cobranza -->
            @can('ver_cobranza')
            <li @if ($pageSlug == 'cobranza') class="active " @endif>
                <a href="{{ route('cobranza.index') }}">
                    <i class="tim-icons icon-money-coins"></i>
                    <p>{{ __('Cobranza') }}</p>
                </a>
            </li>
            @endcan

            <!-- Manejo Stock -->
            @can('ver_manejo_stock')
            <li>
                <a data-toggle="collapse" href="#ManejoStockMenu" aria-expanded="false">
                    <i class="tim-icons icon-notes"></i>
                    <span class="nav-link-text">{{ __('Manejo Stock') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse" id="ManejoStockMenu">
                    <ul class="nav pl-4">
                        @can('ver_contabilidad_stock')
                        <li @if ($pageSlug == 'manejo-stock') class="active " @endif>
                            <a href="{{ route('manejo-stock.select') }}">
                                <i class="tim-icons icon-notes"></i>
                                <p>{{ __('Contabilidad') }}</p>
                            </a>
                        </li>
                        @endcan
                        @can('ver_historial_stock')
                        <li @if ($pageSlug == 'manejo-stock-historial') class="active " @endif>
                            <a href="{{ route('manejo-stock.historial') }}">
                                <i class="tim-icons icon-bullet-list-67"></i>
                                <p>{{ __('Historial') }}</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcan

            <!-- Mantenedor -->
            @can('ver_mantenedor')
            <li>
                <a data-toggle="collapse" href="#MantenedorMenu" aria-expanded="false">
                    <i class="tim-icons icon-settings-gear-63"></i>
                    <span class="nav-link-text">{{ __('Mantenedor') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse" id="MantenedorMenu">
                    <ul class="nav pl-4">
                        @can('gestionar_bodegas')
                        <li @if ($pageSlug == 'mantenedor-bodegas') class="active " @endif>
                            <a href="{{ route('mantenedor.bodegas') }}">
                                <i class="tim-icons icon-bank"></i>
                                <p>{{ __('Bodegas y Ubicaciones') }}</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcan

            <!-- Informes -->
            @can('ver_informes')
            <li>
                <a data-toggle="collapse" href="#Informes" aria-expanded="false">
                    <i class="tim-icons icon-chart-bar-32"></i>
                    <span class="nav-link-text">{{ __('Informes') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse" id="Informes">
                    <ul class="nav pl-4">
                        @can('ver_nvv_pendientes')
                        <li @if (($pageSlug ?? '') == 'nvv-pendientes') class="active " @endif>
                            <a href="{{ route('nvv-pendientes.index') }}">
                                <i class="tim-icons icon-notes"></i>
                                <p>{{ __('Estado Notas de Ventas por aprobadas por facturar') }}</p>
                            </a>
                        </li>
                        <hr>
                        @endcan
                        @can('ver_facturas_pendientes')
                        <li @if (($pageSlug ?? '') == 'facturas-pendientes') class="active " @endif>
                            <a href="{{ route('facturas-pendientes.index') }}">
                                <i class="tim-icons icon-money-coins"></i>
                                <p>{{ __('Estado de Facturas') }}</p>
                            </a>
                        </li>
                        <hr>
                        @endcan
                        @can('ver_facturas_emitidas')
                        <li @if (($pageSlug ?? '') == 'facturas-emitidas') class="active " @endif>
                            <a href="{{ route('facturas-emitidas.index') }}">
                                <i class="tim-icons icon-chart-bar-32"></i>
                                <p>{{ __('Informe de Facturas Emitidas') }}</p>
                            </a>
                        </li>
                        <hr>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcan

            <!-- Aprobaciones (Solo para roles con permiso ver_aprobaciones) -->
            @can('ver_aprobaciones')
            <li @if ($pageSlug == 'aprobaciones') class="active " @endif>
                <a href="{{ route('aprobaciones.index') }}">
                    <i class="tim-icons icon-check-2"></i>
                    <p>{{ __('Aprobaciones NVV') }}</p>
                </a>
            </li>
            @endcan

            <!-- Gestión de Usuarios -->
            @can('ver_usuarios')
            <li>
                <a data-toggle="collapse" href="#GestionUsuarios" aria-expanded="false">
                    <i class="tim-icons icon-single-02"></i>
                    <span class="nav-link-text">{{ __('Gestión de Usuarios') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse" id="GestionUsuarios">
                    <ul class="nav pl-4">
                        <li @if ($pageSlug == 'admin-users') class="active " @endif>
                            <a href="{{ route('admin.users.index') }}">
                                <i class="tim-icons icon-bullet-list-67"></i>
                                <p>{{ __('Lista de Usuarios') }}</p>
                            </a>
                        </li>
                        @can('crear_usuarios')
                        <li @if ($pageSlug == 'admin-users-create') class="active " @endif>
                            <a href="{{ route('admin.users.create-from-vendedor') }}">
                                <i class="tim-icons icon-simple-add"></i>
                                <p>{{ __('Crear Usuario') }}</p>
                            </a>
                        </li>
                        @endcan
                        @canany(['editar_roles', 'asignar_permisos'])
                        <li @if ($pageSlug == 'admin-roles') class="active " @endif>
                            <a href="{{ route('admin.roles.index') }}">
                                <i class="tim-icons icon-settings-gear-63"></i>
                                <p>{{ __('Roles y Permisos') }}</p>
                            </a>
                        </li>
                        @endcanany
                    </ul>
                </div>
            </li>
            @endcan





            <!-- Logs (Solo para roles con permiso ver_logs) -->
            @can('ver_logs')
            <li @if ($pageSlug == 'log') class="active " @endif>
                <a href="{{ route('logs.index') }}">
                    <i class="tim-icons icon-tag"></i>
                    <p>{{ __('Registro de eventos') }}</p>
                </a>
            </li>
            @endcan
        </ul>
    </div>
</div>
