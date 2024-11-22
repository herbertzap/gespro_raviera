<div class="sidebar">
    <div class="sidebar-wrapper">
        <div class="logo">
            <a href="#" class="simple-text logo-mini">
            <img class="" src="{{ asset('black') }}/img/logo.webp" alt="">
            </a>
            <a href="#" class="simple-text logo-normal">{{ __('GESPRO RIVERA') }}</a>
        </div>
        <ul class="nav">
            <li @if ($pageSlug == 'dashboard') class="active " @endif>
                <a href="{{ route('home') }}">
                    <i class="tim-icons icon-chart-pie-36"></i>
                    <p>{{ __('Dashboard') }}</p>
                </a>
            </li>
            <li>
                <a data-toggle="collapse" href="#Usuarios" aria-expanded="true">
                    <i class="fab fa-laravel" ></i>
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
                        <li @if ($pageSlug == 'users') class="active " @endif>
                            <a href="{{ route('user.index') }}">
                                <i class="tim-icons icon-bullet-list-67"></i>
                                <p>{{ __('Usuarios') }}</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            
            <!-- Nueva sección: Categorías -->
            <li @if ($pageSlug == 'categorias') class="active " @endif>
                <a href="{{ route('categorias.index') }}">
                    <i class="tim-icons icon-tag"></i>
                    <p>{{ __('Categorías') }}</p>
                </a>
            </li>

            <!-- Nueva sección: Bodegas -->
            <li @if ($pageSlug == 'bodegas') class="active " @endif>
                <a href="{{ route('bodegas.index') }}">
                    <i class="tim-icons icon-bank"></i>
                    <p>{{ __('Bodegas') }}</p>
                </a>
            </li>
            <li @if ($pageSlug == 'listas-precios') class="active " @endif>
                <a href="{{ route('listasPrecios.index') }}">
                <i class="tim-icons icon-tag"></i>
                <p>{{ __('Listas de Precios') }}</p>
                </a>
            </li>


            <!-- Nueva sección: Productos -->
            <li>
                <a data-toggle="collapse" href="#Productos" aria-expanded="false">
                    <i class="tim-icons icon-bag-16"></i>
                    <span class="nav-link-text">{{ __('Productos') }}</span>
                    <b class="caret mt-1"></b>
                </a>
                <div class="collapse" id="Productos">
                    <ul class="nav pl-4">
                        <li @if ($pageSlug == 'productos-publicados') class="active " @endif>
                            <a href="{{ route('productos.publicados') }}">
                                <i class="tim-icons icon-atom"></i>
                                <p>{{ __('Productos Publicados') }}</p>
                            </a>
                        </li>
                        <li @if ($pageSlug == 'cargar_productos') class="active " @endif>
                            <a href="{{ route('productos.cargar') }}">
                                <i class="tim-icons icon-upload"></i>
                                <p>{{ __('Cargar Productos') }}</p>
                            </a>
                        </li>
                        <li @if ($pageSlug == 'validar_productos') class="active " @endif>
                            <a href="{{ route('productos.validar') }}">
                                <i class="tim-icons icon-check-2"></i>
                                <p>{{ __('Validar Productos') }}</p>
                            </a>
                        </li>
                        <li @if ($pageSlug == 'asignar_productos') class="active " @endif>
                            <a href="{{ route('productos.asignar') }}">
                                <i class="tim-icons icon-send"></i>
                                <p>{{ __('Asignar a Bodegas') }}</p>
                            </a>
                        </li>
                        <li @if ($pageSlug == 'asignar_productos') class="active " @endif>
                            <a href="{{ route('productos.lista-precios') }}">
                                <i class="tim-icons icon-send"></i>
                                <p>{{ __('Lista Precios') }}</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </li> 
        </ul>
    </div>
</div>
