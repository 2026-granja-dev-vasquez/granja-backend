<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Genera un token de reset y simula el envío por email.
     * En producción conectar con Mail::send() o un servicio SMTP.
     */
    public function sendLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Eliminar tokens anteriores para este email
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        // TODO: Enviar email con el enlace que incluye el token
        // Mail::to($request->email)->send(new PasswordResetMail($token));

        return response()->json([
            'message' => 'Se ha enviado un enlace de recuperación a tu correo.',
            // En desarrollo retornamos el token para testing
            'dev_token' => config('app.env') === 'local' ? $token : null,
        ]);
    }

    /**
     * Valida el token y actualiza la contraseña.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email|exists:users,email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'El token de recuperación es inválido o ha expirado.',
            ], 422);
        }

        if (! Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'El token de recuperación es inválido o ha expirado.',
            ], 422);
        }

        // Token válido por 60 minutos
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'message' => 'El enlace de recuperación ha expirado. Solicita uno nuevo.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => Hash::make($request->password)]);

        // Revocar todos los tokens activos del usuario
        $user->tokens()->delete();

        // Limpiar el token de reset
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
