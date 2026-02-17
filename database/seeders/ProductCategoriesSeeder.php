<?php

namespace Database\Seeders;

use App\Models\CategoryProduct;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer la première company et user pour les catégories
        $company = Company::first();
        $user = User::first();

        if (! $company || ! $user) {
            $this->command->warn('Aucune company ou user trouvé. Veuillez créer une company et un user d\'abord.');

            return;
        }

        $categories = [
            [
                'name' => 'Boulangerie',
                'description' => 'Produits de boulangerie et pâtisserie',
                'company_id' => $company->id,
                'user_id' => $user->id,
            ],
            [
                'name' => 'Pharmaceutique',
                'description' => 'Produits pharmaceutiques et médicaments',
                'company_id' => $company->id,
                'user_id' => $user->id,
            ],
        ];

        foreach ($categories as $category) {
            CategoryProduct::firstOrCreate(
                ['name' => $category['name'], 'company_id' => $category['company_id']],
                $category
            );
        }

        $this->command->info('Catégories de produits créées avec succès.');
    }
}
