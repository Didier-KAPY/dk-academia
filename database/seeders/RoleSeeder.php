<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionDefinitions = [
            'users.view' => ['Consulter les utilisateurs', 'Voir les comptes utilisateurs autorisés.'],
            'users.manage' => ['Gérer les utilisateurs', 'Créer et mettre à jour les comptes utilisateurs.'],
            'roles.manage' => ['Gérer les rôles', 'Attribuer les rôles et permissions.'],
            'settings.manage' => ['Gérer les paramètres généraux', 'Modifier l’identité et les coordonnées de l’organisation.'],
            'backups.manage' => ['Gérer les sauvegardes', 'Créer et télécharger les sauvegardes vérifiées.'],
            'trainings.manage' => ['Gérer les formations', 'Créer, modifier et publier les formations.'],
            'pedagogy.manage' => ['Gérer la pédagogie', 'Organiser les sessions, présences et évaluations.'],
            'finance.view' => ['Consulter les finances', 'Consulter les informations financières autorisées.'],
            'cash.manage' => ['Gérer la caisse', 'Saisir les opérations de caisse autorisées.'],
            'services.manage' => ['Gérer les prestations', 'Suivre les clients, devis et projets informatiques.'],
            'learning.access' => ['Accéder aux formations', 'Consulter les formations et ressources attribuées.'],
        ];

        $permissions = [];

        foreach ($permissionDefinitions as $slug => [$name, $description]) {
            $permissions[$slug] = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description],
            );
        }

        $roleDefinitions = [
            'administrateur' => ['Administrateur général', 'Vue globale et administration de la plateforme.', array_keys($permissionDefinitions)],
            'gestionnaire-administratif' => ['Gestionnaire administratif', 'Gestion des dossiers et du suivi administratif.', ['users.view', 'trainings.manage', 'pedagogy.manage']],
            'comptable' => ['Comptable', 'Suivi des opérations et rapports financiers autorisés.', ['finance.view', 'cash.manage']],
            'responsable-caisse' => ['Responsable de caisse', 'Gestion des opérations de la caisse attribuée.', ['cash.manage']],
            'responsable-pedagogique' => ['Responsable pédagogique', 'Supervision des formations, sessions et évaluations.', ['trainings.manage', 'pedagogy.manage']],
            'formateur' => ['Formateur', 'Accès aux sessions, cours et apprenants attribués.', ['pedagogy.manage']],
            'apprenant' => ['Apprenant', 'Accès personnel aux formations et ressources pédagogiques.', ['learning.access']],
            'responsable-informatique' => ['Responsable informatique', 'Suivi des prestations et projets informatiques.', ['services.manage']],
            'commercial' => ['Commercial', 'Suivi des prospects, clients et demandes de service.', ['services.manage']],
        ];

        foreach ($roleDefinitions as $slug => [$name, $description, $rolePermissions]) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description],
            );

            $role->permissions()->syncWithoutDetaching(
                array_map(
                    fn (string $permissionSlug): int => $permissions[$permissionSlug]->getKey(),
                    $rolePermissions,
                ),
            );
        }
    }
}
