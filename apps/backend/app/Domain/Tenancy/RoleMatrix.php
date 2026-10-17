<?php

namespace App\Domain\Tenancy;

final class RoleMatrix
{
    public const ROLES = ['owner', 'admin', 'supervisor', 'seller', 'collector', 'logistics', 'viewer'];

    public const CAPABILITIES = [
        'tenant.view', 'tenant.update',
        'users.view', 'users.create', 'users.update', 'users.disable', 'users.assign_roles',
        'authorization.view', 'authorization.manage',
        'customers.view', 'customers.create', 'customers.update', 'customers.disable', 'customers.assign_collector', 'customers.unassign_collector', 'customers.view_assignments',
        'products.view', 'products.create', 'products.update', 'products.manage_prices', 'products.view_costs', 'products.manage_costs',
        'quotations.view', 'quotations.view_all', 'quotations.create', 'quotations.update', 'quotations.submit', 'quotations.approve', 'quotations.reject', 'quotations.send', 'quotations.cancel', 'quotations.export', 'quotations.manage_activities',
        'sales.view', 'sales.view_all', 'sales.create', 'sales.create_direct', 'sales.approve', 'sales.confirm', 'sales.cancel', 'sales.documents.manage', 'sales.documents.verify', 'sales.policies.manage',
        'collections.view', 'collections.register', 'collections.reassign', 'collections.portfolio.view', 'collections.portfolio.view_all', 'collections.portfolio.manage',
        'collection_zones.view', 'collection_zones.manage',
        'collection_teams.view', 'collection_teams.manage', 'collection_teams.manage_members',
        'payments.view', 'payments.create', 'payments.verify', 'payments.verify_transfer', 'payments.allocate', 'payments.reverse',
        'routes.view', 'routes.manage', 'routes.publish',
        'settlements.view', 'settlements.create', 'settlements.submit', 'settlements.review', 'settlements.approve',
        'reports.view', 'integrations.manage',
    ];

    private const PERMISSIONS = [
        'admin' => self::CAPABILITIES,
        'supervisor' => ['tenant.view', 'users.view', 'customers.view', 'customers.update', 'products.view', 'quotations.view', 'quotations.view_all', 'quotations.approve', 'quotations.reject', 'quotations.export', 'quotations.manage_activities', 'sales.view', 'sales.view_all', 'sales.approve', 'sales.confirm', 'sales.documents.manage', 'sales.documents.verify', 'collections.view', 'collections.reassign', 'collections.portfolio.view', 'payments.view', 'payments.create', 'payments.verify', 'payments.verify_transfer', 'payments.allocate', 'settlements.view', 'settlements.create', 'settlements.review', 'settlements.approve', 'routes.view', 'routes.manage', 'routes.publish', 'reports.view'],
        'seller' => ['tenant.view', 'customers.view', 'customers.create', 'customers.update', 'products.view', 'quotations.view', 'quotations.create', 'quotations.update', 'quotations.submit', 'quotations.send', 'quotations.reject', 'quotations.cancel', 'quotations.export', 'quotations.manage_activities', 'sales.view', 'sales.create', 'sales.documents.manage', 'routes.view'],
        'collector' => ['tenant.view', 'customers.view', 'collections.view', 'collections.register', 'collections.portfolio.view', 'payments.view', 'payments.create', 'routes.view', 'settlements.view', 'settlements.create', 'settlements.submit'],
        'logistics' => ['tenant.view', 'customers.view', 'routes.view'],
        'viewer' => ['tenant.view', 'customers.view', 'products.view', 'quotations.view', 'quotations.view_all', 'sales.view', 'sales.view_all', 'collections.view', 'routes.view', 'settlements.view', 'reports.view'],
    ];

    private const RANK = ['owner' => 7, 'admin' => 6, 'supervisor' => 5, 'seller' => 4, 'collector' => 3, 'logistics' => 2, 'viewer' => 1];

    public static function label(string $role): string
    {
        return match ($role) {
            'owner' => 'Propietario',
            'admin' => 'Administrador',
            'supervisor' => 'Supervisor',
            'seller' => 'Vendedor',
            'collector' => 'Cobrador',
            'logistics' => 'Logística',
            'viewer' => 'Consulta',
            default => $role,
        };
    }

    public static function permissions(string $role): array
    {
        return $role === 'owner' ? self::CAPABILITIES : (self::PERMISSIONS[$role] ?? []);
    }

    public static function canAssign(string $actorRole, string $targetRole): bool
    {
        if (! in_array($targetRole, self::ROLES, true) || $targetRole === 'owner') {
            return false;
        }

        return (self::RANK[$actorRole] ?? 0) >= (self::RANK[$targetRole] ?? 99)
            && in_array($actorRole, ['owner', 'admin'], true);
    }

    public static function canManage(string $actorRole, string $targetRole): bool
    {
        return $actorRole === 'owner' || ($actorRole === 'admin' && $targetRole !== 'owner');
    }
}
