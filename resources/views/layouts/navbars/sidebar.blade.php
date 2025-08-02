<div class="sidebar">
    <div class="sidebar-wrapper">
        <div class="logo">
            <a href="#" class="simple-text logo-mini">
                <img class="" src="{{ asset('black') }}/img/logo.webp" alt="">
            </a>
            <a href="#" class="simple-text logo-normal">{{ __('GESPRO RAVERA') }}</a>
        </div>
        <ul class="nav">
            <li @if ($pageSlug == 'dashboard') class="active " @endif>
                <a href="{{ route('dashboard') }}">
                    <i class="tim-icons icon-chart-pie-36"></i>
                    <p>{{ __('Dashboard') }}</p>
                </a>
            </li>

            @can('gestionar usuarios')
            <li>
                <a data-toggle="collapse" href="#Usuarios" aria-expanded="true">
                    <i class="fab fa-laravel"></i>
                    <span class="nav-link-text">{{ __('Usuarios') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse show" id="Usuarios">
                    <ul class="nav pl-4">
                        <li @if ($pageSlug == 'profile') class="active " @endif>
                            <a href="{{ route('profile.edit') }}">
                                <i class="tim-icons icon-single-02"></i>
                                <p>{{ __('Perfil de usuario') }}</p>
                            </a>
                        </li>
                        @can('gestionar usuarios')
                        <li @if ($pageSlug == 'users') class="active " @endif>
                            <a href="{{ route('user.index') }}">
                                <i class="tim-icons icon-bullet-list-67"></i>
                                <p>{{ __('Usuarios') }}</p>
                            </a>
                        </li>
                        @endcan

                        @can('gestionar roles')
                        <li @if ($pageSlug == 'roles') class="active " @endif>
                            <a href="{{ route('roles.index') }}">
                                <i class="tim-icons icon-settings"></i>
                                <p>{{ __('Roles') }}</p>
                            </a>
                        </li>
                        @endcan

                        @can('gestionar permisos')
                        <li @if ($pageSlug == 'permissions') class="active " @endif>
                            <a href="{{ route('permissions.index') }}">
                                <i class="tim-icons icon-lock-circle"></i>
                                <p>{{ __('Permisos') }}</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcan

            @can('gestionar categorías')
            <li @if ($pageSlug == 'categorias') class="active " @endif>
                <a href="{{ route('categorias.index') }}">
                    <i class="tim-icons icon-tag"></i>
                    <p>{{ __('Categorías') }}</p>
                </a>
            </li>
            @endcan

            @can('gestionar bodegas')
            <li @if ($pageSlug == 'bodegas') class="active " @endif>
                <a href="{{ route('bodegas.index') }}">
                    <i class="tim-icons icon-bank"></i>
                    <p>{{ __('Bodegas') }}</p>
                </a>
            </li>
            @endcan

            @can('gestionar listas de precios')
            <li @if ($pageSlug == 'listas-precios') class="active " @endif>
                <a href="{{ route('listasPrecios.index') }}">
                    <i class="tim-icons icon-tag"></i>
                    <p>{{ __('Listas de Precios') }}</p>
                </a>
            </li>
            @endcan

            @can('ver productos')
            <li>
                <a data-toggle="collapse" href="#Productos" aria-expanded="false">
                    <i class="tim-icons icon-bag-16"></i>
                    <span class="nav-link-text">{{ __('Productos') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse" id="Productos">
                    @can('ver productos')
                    <ul class="nav pl-4">
                        <li @if ($pageSlug == 'productos-publicados') class="active " @endif>
                            <a href="{{ route('productos.publicados') }}">
                                <i class="tim-icons icon-atom"></i>
                                <p>{{ __('Productos Publicados') }}</p>
                            </a>
                        </li>
                        @endcan

                        @can('cargar productos')
                        <li @if ($pageSlug == 'cargar_productos') class="active " @endif>
                            <a href="{{ route('productos.cargar') }}">
                                <i class="tim-icons icon-upload"></i>
                                <p>{{ __('Cargar Productos') }}</p>
                            </a>
                        </li>
                        @endcan

                        @can('asignar productos')
                        <li @if ($pageSlug == 'asignar_productos') class="active " @endif>
                            <a href="{{ route('productos.lista-precios') }}">
                                <i class="tim-icons icon-send"></i>
                                <p>{{ __('Lista Precios') }}</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcan

            @can('view_clients')
            <li @if ($pageSlug == 'buscar-clientes') class="active " @endif>
                <a href="{{ route('cobranza.index') }}">
                    <i class="tim-icons icon-single-02"></i>
                    <p>{{ __('Buscar Clientes') }}</p>
                </a>
            </li>
            @endcan
            
            @can('create_quotation')
            <li @if ($pageSlug == 'cotizaciones') class="active " @endif>
                <a href="{{ route('cotizaciones.index') }}">
                    <i class="tim-icons icon-paper"></i>
                    <p>{{ __('Mis Cotizaciones') }}</p>
                </a>
            </li>
            <li @if ($pageSlug == 'nueva-cotizacion') class="active " @endif>
                <a href="{{ route('cotizacion.nueva') }}">
                    <i class="tim-icons icon-cart"></i>
                    <p>{{ __('Nueva Cotización') }}</p>
                </a>
            </li>
            @endcan

            @can('view_stock')
            <li @if ($pageSlug == 'stock') class="active " @endif>
                <a href="{{ route('stock.index') }}">
                    <i class="tim-icons icon-box-2"></i>
                    <p>{{ __('Gestión de Stock') }}</p>
                </a>
            </li>
            @endcan

            @can('ver logs')
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
