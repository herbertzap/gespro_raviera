<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        // Asegurar que siempre redirija al dashboard después del login
        return redirect()->intended($this->redirectPath());
    }

    /**
     * Get the post login redirect path.
     *
     * @return string
     */
    public function redirectPath()
    {
        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/dashboard';
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'login'; // Campo personalizado para email o RUT
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        $login = trim($request->get('login'));
        
        // Si contiene @, buscar por email (aunque no sea válido según filter_var)
        if (strpos($login, '@') !== false) {
            return ['email' => $login, 'password' => $request->get('password')];
        }
        
        // Si no, buscar por RUT
        return ['rut' => $login, 'password' => $request->get('password')];
    }
    
    /**
     * Attempt to log the user into the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        $login = trim($request->get('login'));
        $password = $request->get('password');
        
        // Si contiene @, buscar por email o email_alternativo
        if (strpos($login, '@') !== false) {
            $user = \App\Models\User::where(function($query) use ($login) {
                $query->where('email', $login)
                      ->orWhere('email_alternativo', $login);
            })->first();
            
            if ($user && \Hash::check($password, $user->password)) {
                \Auth::login($user, $request->filled('remember'));
                return true;
            }
            
            return false;
        }
        
        // Si no, usar el método estándar que busca por RUT
        return $this->guard()->attempt(
            ['rut' => $login, 'password' => $password],
            $request->filled('remember')
        );
    }

    /**
     * Get the failed login response instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            $this->username() => [__('Las credenciales proporcionadas no coinciden con nuestros registros.')],
        ]);
    }
}
