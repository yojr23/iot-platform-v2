<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    private const ALLOWED_EMAIL_DOMAINS = [
        'gmail.com',
        'hotmail.com',
        'outlook.com',
        'unab.edu.co',
    ];

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->hasAllowedEmailDomain((string) $value)) {
                        $fail('Solo se permiten correos con dominio @gmail.com, @hotmail.com, @outlook.com o @unab.edu.co.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    private function hasAllowedEmailDomain(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return in_array($domain, self::ALLOWED_EMAIL_DOMAINS, true);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    /**
     * Handle post-registration actions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function registered(Request $request, $user)
    {
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return null;
    }
}
