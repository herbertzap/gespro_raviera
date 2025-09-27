<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso al Sistema - Comercial Higuera</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2196F3;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        .credentials {
            background-color: #fff;
            border: 2px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Bienvenido al Sistema</h1>
        <h2>Comercial Higuera</h2>
    </div>
    
    <div class="content">
        <p>Hola <strong>{{ $user->name }}</strong>,</p>
        
        <p>Se ha creado tu cuenta de usuario en el sistema de Comercial Higuera. A continuación encontrarás tus datos de acceso:</p>
        
        <div class="credentials">
            <h3>Datos de Acceso</h3>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            @if($user->email_alternativo)
            <p><strong>Email Alternativo:</strong> {{ $user->email_alternativo }}</p>
            @endif
            @if($user->rut)
            <p><strong>RUT:</strong> {{ $user->rut }}</p>
            @endif
            <p><strong>Contraseña Temporal:</strong> {{ $password }}</p>
        </div>
        
        <div class="warning">
            <h4>⚠️ Importante</h4>
            <p>Por seguridad, deberás cambiar tu contraseña en el primer inicio de sesión.</p>
        </div>
        
        <p>Puedes acceder al sistema haciendo clic en el siguiente enlace:</p>
        
        <p style="text-align: center;">
            <a href="{{ $loginUrl }}" class="button">Acceder al Sistema</a>
        </p>
        
        <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactar al administrador del sistema.</p>
        
        <p>Saludos cordiales,<br>
        <strong>Equipo de Comercial Higuera</strong></p>
    </div>
    
    <div class="footer">
        <p>Este es un mensaje automático, por favor no responder a este correo.</p>
        <p>© {{ date('Y') }} Comercial Higuera. Todos los derechos reservados.</p>
    </div>
</body>
</html>

