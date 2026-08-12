<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Toutes les permissions disponibles dans l'application.
     * Correspond aux clés `permission` / `permissions` des navItems du frontend.
     *
     * Hôtel :
     *   hotel_rooms → Chambres, Réservations, Salles de conférence, Factures hôtel
     *   hotel_bar   → Restaurant-Bar, Cuisine
     */
    private const ALL_PERMISSIONS = [
        'dashboard', 'sales', 'clients', 'stock',
        'pharmaceutical', 'bakery', 'restaurant',
        'hotel_rooms', 'hotel_bar', 'hotel_bar_order',
        'journal', 'reports', 'expenses', 'company', 'users',
    ];

    private const BASE_PERMISSIONS = ['dashboard'];

    /**
     * Logique de domaine :
     *   domain = null   → rôle universel, visible dans TOUS les domaines
     *   domain = 'xyz'  → visible uniquement pour les entreprises du domaine 'xyz'
     */
    public function run(): void
    {
        $roles = [

            // ─── Rôles universels (domain = null) ─────────────────────────

            [
                'name' => 'admin',
                'label' => 'Administrateur',
                'description' => 'Accès complet à toutes les fonctionnalités',
                'domain' => null,
                'permissions' => self::ALL_PERMISSIONS,
            ],
            [
                'name' => 'super_admin',
                'label' => 'Super Administrateur',
                'description' => 'Accès total multi-entreprises',
                'domain' => null,
                'permissions' => self::ALL_PERMISSIONS,
            ],
            [
                'name' => 'manager',
                'label' => 'Manager',
                'description' => 'Supervision et gestion des opérations',
                'domain' => null,
                'permissions' => [
                    'dashboard', 'sales', 'clients', 'stock',
                    'pharmaceutical', 'bakery', 'restaurant',
                    'hotel_rooms', 'hotel_bar',
                    'journal', 'reports', 'expenses',
                ],
            ],
            [
                'name' => 'accountant',
                'label' => 'Comptable',
                'description' => 'Gestion financière, journal et comptabilité',
                'domain' => null,
                'permissions' => ['dashboard', 'journal', 'expenses', 'reports'],
            ],
            [
                'name' => 'cashier',
                'label' => 'Caissier',
                'description' => 'Encaissements et transactions de vente',
                'domain' => null,
                'permissions' => ['dashboard', 'sales'],
            ],
            [
                'name' => 'sales',
                'label' => 'Commercial',
                'description' => 'Gestion des clients et des ventes',
                'domain' => null,
                'permissions' => ['dashboard', 'sales', 'clients'],
            ],
            [
                'name' => 'stock_manager',
                'label' => 'Gestionnaire de Stock',
                'description' => 'Gestion des inventaires et entrepôts',
                'domain' => null,
                'permissions' => ['dashboard', 'stock'],
            ],
            [
                'name' => 'user',
                'label' => 'Utilisateur',
                'description' => 'Accès minimal au tableau de bord',
                'domain' => null,
                'permissions' => self::BASE_PERMISSIONS,
            ],

            // ─── Domaine : Commerce général ────────────────────────────────

            [
                'name' => 'store_clerk',
                'label' => 'Vendeur',
                'description' => 'Vente directe en boutique',
                'domain' => 'general',
                'permissions' => ['dashboard', 'sales', 'clients'],
            ],

            // ─── Domaine : Pharmacie ───────────────────────────────────────

            [
                'name' => 'pharmacist',
                'label' => 'Pharmacien',
                'description' => 'Gestion des médicaments et prescriptions',
                'domain' => 'pharmaceutical',
                'permissions' => ['dashboard', 'pharmaceutical', 'clients', 'stock'],
            ],
            [
                'name' => 'pharmacy_assistant',
                'label' => 'Assistant Pharmacien',
                'description' => 'Vente et gestion des produits pharmaceutiques',
                'domain' => 'pharmaceutical',
                'permissions' => ['dashboard', 'pharmaceutical'],
            ],

            // ─── Domaine : Boulangerie ─────────────────────────────────────

            [
                'name' => 'baker',
                'label' => 'Boulanger',
                'description' => 'Production et gestion des recettes',
                'domain' => 'bakery',
                'permissions' => ['dashboard', 'bakery'],
            ],
            // [
            //     'name' => 'bakery_manager',
            //     'label' => 'Gérant Boulangerie',
            //     'description' => 'Gestion complète de la boulangerie',
            //     'domain' => 'bakery',
            //     'permissions' => ['dashboard', 'bakery', 'stock', 'sales', 'reports'],
            // ],

            // ─── Domaine : Restaurant ──────────────────────────────────────

            [
                'name' => 'restaurant',
                'label' => 'Restaurant',
                'description' => 'Gestion des tables, commandes et facturation',
                'domain' => 'restaurant',
                'permissions' => ['dashboard', 'restaurant'],
            ],
            [
                'name' => 'restaurant_manager',
                'label' => 'Gérant Restaurant',
                'description' => 'Direction complète du restaurant',
                'domain' => 'restaurant',
                'permissions' => ['dashboard', 'restaurant', 'stock', 'reports', 'expenses'],
            ],
            [
                'name' => 'waiter',
                'label' => 'Serveur',
                'description' => 'Prise de commandes et service en salle',
                'domain' => 'restaurant',
                'permissions' => ['dashboard', 'restaurant'],
            ],

            // ─── Domaine : Hôtel ───────────────────────────────────────────
            //
            // hotel_rooms     → Chambres, Réservations, Salles de conf., Factures hôtel
            // hotel_bar       → Restaurant-Bar + Cuisine : gestion complète (tables, menu, plats, stock, factures)
            // hotel_bar_order → Restaurant-Bar + Cuisine : passer des commandes uniquement

            [
                'name' => 'hotel',
                'label' => 'Hôtel',
                'description' => 'Accès complet aux fonctionnalités hôtelières (chambres + bar)',
                'domain' => 'hotel',
                'permissions' => ['dashboard', 'hotel_rooms', 'hotel_bar', 'clients', 'reports', 'journal'],
            ],
            [
                'name' => 'hotel_manager',
                'label' => 'Gestionnaire Hôtel',
                'description' => 'Réservations, chambres, salles de conférence, commandes bar/cuisine pour clients en chambre, toutes les factures et rapports',
                'domain' => 'hotel',
                'permissions' => [
                    'dashboard',
                    'hotel_rooms',       // chambres, réservations, salles de conf., factures hôtel
                    'hotel_bar_order',   // commander au restaurant-bar/cuisine pour ses clients
                    'clients',
                    'reports',
                    'journal',
                ],
            ],
            [
                'name' => 'hotel_bar_manager',
                'label' => 'Gestionnaire Restaurant-Bar',
                'description' => 'Gestion complète restaurant-bar et cuisine : tables, menu, plats, stock, commandes, factures et rapports',
                'domain' => 'hotel',
                'permissions' => [
                    'dashboard',
                    'hotel_bar',   // restaurant-bar + cuisine : gestion complète
                    'clients',
                    'reports',
                    'journal',
                ],
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                [
                    'name' => $role['name'],
                    'label' => $role['label'],
                    'description' => $role['description'],
                    'domain' => $role['domain'],
                    'permissions' => json_encode($role['permissions']),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
