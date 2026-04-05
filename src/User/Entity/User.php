<?php

declare(strict_types=1);

namespace App\User\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * In-memory user backed by environment variables (ADMIN_EMAIL / ADMIN_PASSWORD_HASH).
 * No database table — the memory provider in security.php handles instantiation.
 */
final readonly class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string $email
     * @param list<string> $roles
     */
    public function __construct(
        private string $email,
        private string $password,
        private array $roles = ['ROLE_ADMIN'],
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function eraseCredentials(): void
    {
        // No sensitive data to erase for in-memory users
    }
}
