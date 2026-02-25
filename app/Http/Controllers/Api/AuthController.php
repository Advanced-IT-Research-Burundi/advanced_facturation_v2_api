<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use function sendError;
use function sendResponse;

class AuthController extends Controller
{
    public function registerCompany(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'tp_TIN' => 'required|string|max:255|unique:companies',
            'domain' => 'nullable|string|in:general,hotel,pharmaceutical,restaurant,bakery',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return sendError('Erreurs de validation', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // 1. Create Company with required taxpayer fields
            $company = Company::create([
                'name' => $request->company_name,
                'tp_name' => $request->company_name,
                'tp_TIN' => $request->tp_TIN,
                'domain' => $request->input('domain', 'general'),
                'vat_taxpayer' => 'NO',
                'ct_taxpayer' => 'NO',
                'tl_taxpayer' => 'NO',
                'tp_type' => '1',
            ]);

            // 2. Create Admin User
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'company_id' => $company->id,
            ]);

            // 3. Link Company to User (Owner)
            $company->update(['user_id' => $user->id]);

            // 4. Assign Admin Role
            $adminRole = Role::where('name', 'admin')->first();
            if ($adminRole) {
                $user->assignRole($adminRole);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            $data = [
                'user' => $user->load('company', 'roles'),
                'token' => $token,
                'token_type' => 'Bearer',
            ];

            return sendResponse($data, 'Entreprise et utilisateur créés avec succès', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return sendError('Erreur lors de la création de l\'entreprise', 500, ['error' => $e->getMessage()]);
        }

    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'company_id' => 'required|exists:companies,id',
        ]);

        if ($validator->fails()) {
            return sendError('Erreurs de validation', 422, $validator->errors());
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'company_id' => $request->company_id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $data = [
            'user' => $user->load('company', 'roles'),
            'token' => $token,
            'token_type' => 'Bearer',
        ];

        return sendResponse($data, 'Utilisateur créé avec succès', 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return sendError('Erreurs de validation', 422, $validator->errors());
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return sendError('Identifiants invalides', 401, [
                'email' => ['Les identifiants fournis ne correspondent pas à nos enregistrements.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Charger les rôles avec leurs permissions et l'entreprise
        $user->load(['roles', 'company']);

        $data = [
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => now()->addHours(8)->toDateTimeString(),
        ];

        return sendResponse($data, 'Connexion réussie', 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return sendResponse(null, 'Déconnexion réussie', 200);
    }
}
