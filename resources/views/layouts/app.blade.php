<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'GESPRO RIVERA'))</title>
        <!-- Favicon -->
        <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('black') }}/img/apple-icon.png">
        <link rel="icon" type="image/png" href="{{ asset('black') }}/img/favicon.png">
        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,600,700,800" rel="stylesheet" />
        <link href="https://use.fontawesome.com/releases/v5.0.6/css/all.css" rel="stylesheet">
        <!-- Material Icons -->
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <!-- Icons -->
        <link href="{{ asset('black') }}/css/nucleo-icons.css" rel="stylesheet" />
        <!-- CSS -->
        <link href="{{ asset('black') }}/css/black-dashboard.css?v=1.0.0" rel="stylesheet" />
        <link href="{{ asset('black') }}/css/theme.css" rel="stylesheet" />
        <link href="{{ asset('css/pagination.css') }}" rel="stylesheet" />
        <link href="{{ asset('css/custom-badges.css') }}" rel="stylesheet" />
        <link href="{{ asset('css/fix-tables.css') }}" rel="stylesheet" />
        <style>
            /* Ocultar el fixed-plugin en todo el sitio */
            .fixed-plugin {
                display: none !important;
            }

            /* Estilos para el menú dropdown del perfil - texto negro */
            .dropdown-menu.dropdown-navbar .nav-item.dropdown-item,
            .dropdown-menu.dropdown-navbar .nav-link .nav-item.dropdown-item {
                color: #000 !important;
            }
            
            .dropdown-menu.dropdown-navbar .nav-item.dropdown-item:hover,
            .dropdown-menu.dropdown-navbar .nav-link .nav-item.dropdown-item:hover {
                color: #000 !important;
                background-color: #f5f5f5 !important;
            }
            
            .dropdown-menu.dropdown-navbar {
                color: #000 !important;
            }

            /* Estilos para selects con tema oscuro - fondo oscuro y texto visible */
            select.form-control,
            select.form-control:focus {
                background-color: #2b3553 !important;
                background-image: url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3E%3Cpath fill='%23ffffff' d='M2 0L0 2h4zm0 5L0 3h4z'/%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right 0.75rem center !important;
                background-size: 8px 10px !important;
                color: rgba(255, 255, 255, 0.8) !important;
                border: 1px solid #cad1d7 !important;
                appearance: none !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
            }

            /* Estilos para las opciones dentro del select */
            select.form-control option {
                background-color: #2b3553 !important;
                color: rgba(255, 255, 255, 0.8) !important;
                padding: 8px 12px !important;
            }

            /* Opción seleccionada */
            select.form-control option:checked,
            select.form-control option:hover {
                background-color: #1e88e5 !important;
                color: #ffffff !important;
            }

            /* Estilos adicionales para selects múltiples */
            select.form-control[multiple] {
                background-color: #2b3553 !important;
                background-image: none !important;
            }

            select.form-control[multiple] option {
                background-color: #2b3553 !important;
                color: rgba(255, 255, 255, 0.8) !important;
            }

            select.form-control[multiple] option:checked {
                background-color: #1e88e5 !important;
                color: #ffffff !important;
            }

            /* Estilos globales para botones - evitar cortes de texto */
            .btn {
                white-space: nowrap !important;
                overflow: visible !important;
                text-overflow: clip !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                word-wrap: normal !important;
            }

            .btn .material-icons {
                flex-shrink: 0;
                margin-right: 4px;
                font-size: 18px;
                line-height: 1;
            }

            .btn .material-icons:only-child {
                margin-right: 0;
            }

            /* Botones con texto después del ícono */
            .btn .material-icons + *:not(.material-icons) {
                margin-left: 0;
            }

            /* Asegurar que los botones no se corten en columnas pequeñas */
            .col-md-auto .btn,
            .col-sm-auto .btn,
            .col-auto .btn {
                width: auto !important;
                min-width: fit-content;
            }

            /* Estilos para select cuando tiene focus */
            select.form-control:focus {
                border-color: rgba(50, 151, 211, 0.5) !important;
                box-shadow: 0 0 0 0.2rem rgba(50, 151, 211, 0.25) !important;
                outline: 0 !important;
            }

            /* Compatibilidad con navegadores que no soportan option:hover */
            select.form-control option {
                background: #2b3553 !important;
            }

            /* Forzar color de texto en el select cuando está abierto (Chrome/Safari) */
            select.form-control:active,
            select.form-control:focus {
                color: rgba(255, 255, 255, 0.8) !important;
            }
        </style>
    </head>
    <body class="{{ $class ?? '' }}">

        @auth()
            <div class="wrapper">
                    @include('layouts.navbars.sidebar')
                <div class="main-panel">
                    @include('layouts.navbars.navbar')

                    <!-- Notificación de Sincronización -->
                    @auth
                        @if(auth()->user()->hasRole('Vendedor'))
                            <div id="sincronizacion-notification" class="alert alert-info alert-dismissible fade show" style="display: none; margin: 10px 15px;">
                                <i class="fas fa-sync-alt fa-spin"></i>
                                <strong>Sincronizando clientes...</strong> Los datos se están actualizando automáticamente.
                                <button type="button" class="close" data-dismiss="alert">
                                    <span>&times;</span>
                                </button>
                            </div>
                        @endif
                    @endauth

                    <div class="content">
                        @yield('content')
                    </div>

                    @include('layouts.footer')
                </div>
            </div>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        @else
            @include('layouts.navbars.navbar')
            <div class="wrapper wrapper-full-page">
                <div class="full-page {{ $contentClass ?? '' }}">
                    <div class="content m-0 p-0 ">
        
                            @yield('content')
    
                    </div>
                    @include('layouts.footer')
                </div>
            </div>
        @endauth
        <div class="fixed-plugin">
            <div class="dropdown show-dropdown">
                <a href="#" data-toggle="dropdown">
                <i class="fa fa-cog fa-2x"> </i>
                </a>
                <ul class="dropdown-menu">
                <li class="header-title"> Sidebar Background</li>
                <li class="adjustments-line">
                    <a href="javascript:void(0)" class="switch-trigger background-color">
                    <div class="badge-colors text-center">
                        <span class="badge filter badge-primary active" data-color="primary"></span>
                        <span class="badge filter badge-info" data-color="blue"></span>
                        <span class="badge filter badge-success" data-color="green"></span>
                    </div>
                    <div class="clearfix"></div>
                    </a>
                </li>
                </ul>
            </div>
        </div>
        <script src="{{ asset('black') }}/js/core/jquery.min.js"></script>
        <script src="{{ asset('black') }}/js/core/popper.min.js"></script>
        <script src="{{ asset('black') }}/js/core/bootstrap.min.js"></script>
        <script src="{{ asset('black') }}/js/plugins/perfect-scrollbar.jquery.min.js"></script>
        
        <!--  Google Maps Plugin    -->
        <!-- Place this tag in your head or just before your close body tag. -->
        {{-- <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_KEY_HERE"></script> --}}
        <!-- Chart JS -->
        {{-- <script src="{{ asset('black') }}/js/plugins/chartjs.min.js"></script> --}}
        <!--  Notifications Plugin    -->
        <script src="{{ asset('black') }}/js/plugins/bootstrap-notify.js"></script>

        <script src="{{ asset('black') }}/js/black-dashboard.min.js?v=1.0.0"></script>
        <script src="{{ asset('black') }}/js/theme.js"></script>




        <script>
            $(document).ready(function() {
                $().ready(function() {
                    $sidebar = $('.sidebar');
                    $navbar = $('.navbar');
                    $main_panel = $('.main-panel');

                    // Mostrar notificación de sincronización si es la primera visita del día
                    @auth
                        @if(auth()->user()->hasRole('Vendedor'))
                            // Verificar si es la primera visita del día
                            const ultimaSincronizacion = localStorage.getItem('ultima_sincronizacion_{{ auth()->id() }}');
                            const hoy = new Date().toDateString();
                            
                            if (ultimaSincronizacion !== hoy) {
                                // Mostrar notificación de sincronización
                                $('#sincronizacion-notification').fadeIn();
                                
                                // Ocultar después de 5 segundos
                                setTimeout(function() {
                                    $('#sincronizacion-notification').fadeOut();
                                }, 5000);
                                
                                // Marcar como sincronizado hoy
                                localStorage.setItem('ultima_sincronizacion_{{ auth()->id() }}', hoy);
                            }
                        @endif
                    @endauth

                    $full_page = $('.full-page');

                    $sidebar_responsive = $('body > .navbar-collapse');
                    sidebar_mini_active = true;
                    white_color = false;

                    // Ocultar sidebar por defecto en el dashboard
                    if (window.location.pathname === '/dashboard' || window.location.pathname === '/') {
                        // El sidebar ya está oculto por defecto, solo marcamos el botón como toggled
                        $('.navbar-toggle').addClass('toggled');
                        blackDashboard.misc.navbar_menu_visible = 1;
                    }

                    window_width = $(window).width();

                    fixed_plugin_open = $('.sidebar .sidebar-wrapper .nav li.active a p').html();

                    $('.fixed-plugin a').click(function(event) {
                        if ($(this).hasClass('switch-trigger')) {
                            if (event.stopPropagation) {
                                event.stopPropagation();
                            } else if (window.event) {
                                window.event.cancelBubble = true;
                            }
                        }
                    });

                    $('.fixed-plugin .background-color span').click(function() {
                        $(this).siblings().removeClass('active');
                        $(this).addClass('active');

                        var new_color = $(this).data('color');

                        if ($sidebar.length != 0) {
                            $sidebar.attr('data', new_color);
                        }

                        if ($main_panel.length != 0) {
                            $main_panel.attr('data', new_color);
                        }

                        if ($full_page.length != 0) {
                            $full_page.attr('filter-color', new_color);
                        }

                        if ($sidebar_responsive.length != 0) {
                            $sidebar_responsive.attr('data', new_color);
                        }
                    });

                    $('.switch-sidebar-mini input').on("switchChange.bootstrapSwitch", function() {
                        var $btn = $(this);

                        if (sidebar_mini_active == true) {
                            $('body').removeClass('sidebar-mini');
                            sidebar_mini_active = false;
                            blackDashboard.showSidebarMessage('Sidebar mini deactivated...');
                        } else {
                            $('body').addClass('sidebar-mini');
                            sidebar_mini_active = true;
                            blackDashboard.showSidebarMessage('Sidebar mini activated...');
                        }

                        // we simulate the window Resize so the charts will get updated in realtime.
                        var simulateWindowResize = setInterval(function() {
                            window.dispatchEvent(new Event('resize'));
                        }, 180);

                        // we stop the simulation of Window Resize after the animations are completed
                        setTimeout(function() {
                            clearInterval(simulateWindowResize);
                        }, 1000);
                    });

                    $('.switch-change-color input').on("switchChange.bootstrapSwitch", function() {
                            var $btn = $(this);

                            if (white_color == true) {
                                $('body').addClass('change-background');
                                setTimeout(function() {
                                    $('body').removeClass('change-background');
                                    $('body').removeClass('white-content');
                                }, 900);
                                white_color = false;
                            } else {
                                $('body').addClass('change-background');
                                setTimeout(function() {
                                    $('body').removeClass('change-background');
                                    $('body').addClass('white-content');
                                }, 900);

                                white_color = true;
                            }
                    });
                });
            });
        </script>
        @stack('js')
    </body>
</html>
