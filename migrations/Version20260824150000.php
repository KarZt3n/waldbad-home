<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Converts global editorial roles and module lists to global administrator roles and per-module roles.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->connection->fetchAllAssociative('SELECT id, roles, modules FROM cms_user') as $row) {
            if (!is_string($row['id'] ?? null)) {
                continue;
            }

            $oldRoles = $this->stringList($row['roles'] ?? null);
            $modules = $this->stringList($row['modules'] ?? null);
            $globalRoles = array_values(array_intersect($oldRoles, ['admin', 'super_admin']));
            if (in_array('super_admin', $globalRoles, true)) {
                $globalRoles = ['super_admin'];
            } elseif ($globalRoles !== []) {
                $globalRoles = ['admin'];
            }

            $moduleAccess = [];
            foreach ($modules as $module) {
                $moduleAccess[$module] = $this->moduleRole($module, $oldRoles);
            }

            $this->addSql(
                'UPDATE cms_user SET roles = :roles, modules = :modules WHERE id = :id',
                [
                    'roles' => json_encode($globalRoles, JSON_THROW_ON_ERROR),
                    'modules' => json_encode($moduleAccess, JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach ($this->connection->fetchAllAssociative('SELECT id, roles, modules FROM cms_user') as $row) {
            if (!is_string($row['id'] ?? null)) {
                continue;
            }

            $globalRoles = $this->stringList($row['roles'] ?? null);
            $moduleAccess = $this->stringMap($row['modules'] ?? null);
            $oldRoles = $globalRoles;
            foreach ($moduleAccess as $module => $role) {
                if ($module === 'pages' && in_array($role, ['editor', 'publisher'], true)) {
                    $oldRoles[] = $role;
                } elseif ($module === 'pages' && $role === 'moderator') {
                    $oldRoles[] = 'moderator';
                } elseif ($module !== 'pages' && $role === 'editor') {
                    $oldRoles[] = in_array($module, ['guestbook', 'contact_requests', 'event_helpers'], true) ? 'moderator' : 'editor';
                } else {
                    $oldRoles[] = 'viewer';
                }
            }

            $this->addSql(
                'UPDATE cms_user SET roles = :roles, modules = :modules WHERE id = :id',
                [
                    'roles' => json_encode(array_values(array_unique($oldRoles)), JSON_THROW_ON_ERROR),
                    'modules' => json_encode(array_keys($moduleAccess), JSON_THROW_ON_ERROR),
                    'id' => $row['id'],
                ],
            );
        }
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, is_string(...)));
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $module => $role) {
            if (is_string($module) && is_string($role)) {
                $result[$module] = $role;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $roles
     */
    private function moduleRole(string $module, array $roles): string
    {
        if ($module === 'pages') {
            if (in_array('publisher', $roles, true)) {
                return 'publisher';
            }
            if (in_array('moderator', $roles, true) && !in_array('editor', $roles, true)) {
                return 'moderator';
            }

            return in_array('editor', $roles, true) ? 'editor' : 'viewer';
        }

        $editable = match ($module) {
            'guestbook', 'contact_requests', 'event_helpers' => in_array('moderator', $roles, true),
            'membership_applications', 'user_management' => in_array('admin', $roles, true) || in_array('super_admin', $roles, true),
            default => in_array('editor', $roles, true) || in_array('publisher', $roles, true),
        };

        return $editable ? 'editor' : 'viewer';
    }
}
