<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\HotelRoom;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'request' => $request->except(['password', 'password_confirmation']),
            ]);

            return sendError('Erreur lors de la création de l\'entreprise', 500, []);
        }

    }

    public function register(Request $request)
    {
        $authUser = $request->user();
        if (! $authUser || ! $authUser->company_id) {
            return sendError('Action non autorisée : aucune entreprise associée.', 403, []);
        }

        if (! $authUser->hasRole(['admin', 'Admin', 'super_admin'])) {
            return sendError('Seuls les administrateurs peuvent créer des utilisateurs.', 403, []);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return sendError('Erreurs de validation', 422, $validator->errors());
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'company_id' => $authUser->company_id,
        ]);

        return sendResponse(
            $user->load('company', 'roles'),
            'Utilisateur créé avec succès',
            201
        );
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

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['roles', 'company']);

        $this->autoOpenHotelCaisses($user);

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
        $this->autoCloseHotelCaisses($request->user());

        $request->user()->currentAccessToken()->delete();

        return sendResponse(null, 'Déconnexion réussie', 200);
    }

    /**
     * Auto-open hotel cash registers for all sections when a hotel user logs in.
     */
    private function autoOpenHotelCaisses(User $user): void
    {
        $companyId = $user->company_id;

        if (HotelRoom::where('company_id', $companyId)->doesntExist()) {
            return;
        }

        $sections = ['restaurant', 'bar', 'rooms', 'conference', 'reception'];

        foreach ($sections as $section) {
            $alreadyOpen = CashRegister::where('company_id', $companyId)
                ->where('status', 'open')
                ->where('hotel_section', $section)
                ->exists();

            if ($alreadyOpen) {
                continue;
            }

            $lastClosed = CashRegister::where('company_id', $companyId)
                ->where('hotel_section', $section)
                ->where('status', 'closed')
                ->latest('closed_at')
                ->first();

            CashRegister::create([
                'company_id' => $companyId,
                'hotel_section' => $section,
                'opened_by' => $user->id,
                'opening_balance' => $lastClosed?->closing_balance ?? 0,
                'opened_at' => now(),
                'status' => 'open',
                'opening_note' => 'Ouverture automatique à la connexion',
            ]);
        }
    }

    /**
     * Auto-close all open hotel cash registers when the user logs out.
     */
    private function autoCloseHotelCaisses(User $user): void
    {
        $registers = CashRegister::where('company_id', $user->company_id)
            ->where('status', 'open')
            ->where('opened_by', $user->id)
            ->whereNotNull('hotel_section')
            ->get();

        foreach ($registers as $register) {
            $expectedBalance = $register->calculateExpectedBalance();

            $register->update([
                'closed_by' => $user->id,
                'closing_balance' => $expectedBalance,
                'expected_balance' => $expectedBalance,
                'difference' => 0,
                'closed_at' => now(),
                'status' => 'closed',
                'closing_note' => 'Fermeture automatique à la déconnexion',
            ]);
        }
    }
}
