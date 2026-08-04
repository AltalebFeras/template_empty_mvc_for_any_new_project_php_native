<?php

namespace App\Repositories;

use App\Abstracts\AbstractRepository;

/**
 * UserRepository — inherits getAll, getById, create, updateById,
 * deleteById, count, getLastInsertId, isOwnedBy, and transaction helpers
 * from AbstractRepository.
 *
 * The base class auto-maps this repository to the `users` table and
 * the `App\Entities\User` entity via naming convention.
 *
 * Add custom query methods below as your project requires.
 */
class UserRepository extends AbstractRepository
{
    /**
     * Finds a user by email address.
     *
     * @param string $email The email to search for.
     * @return \App\Entities\User|null The user entity, or null if not found.
     */
    public function findByEmail(string $email): ?\App\Entities\User
    {
        $stmt = $this->DB->prepare(
            "SELECT * FROM `users` WHERE `email` = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $result = $stmt->fetchObject(\App\Entities\User::class);
        return $result ?: null;
    }
}
