<?php

namespace src\Repositories;

use src\Abstracts\AbstractRepository;

/**
 * UserRepository — inherits getAll, getById, create, updateById,
 * deleteById, count, and getLastInsertId from AbstractRepository.
 *
 * The base class auto-maps this repository to the `users` table and
 * the `src\Entities\User` entity via naming convention.
 *
 * Add custom query methods below as your project requires.
 */
class UserRepository extends AbstractRepository
{
    // Example — find a user by email address:
    // public function findByEmail(string $email): ?\src\Entities\User
    // {
    //     $stmt = $this->DB->prepare(
    //         "SELECT * FROM users WHERE email = :email LIMIT 1"
    //     );
    //     $stmt->execute([':email' => $email]);
    //     $result = $stmt->fetchObject(\src\Entities\User::class);
    //     return $result ?: null;
    // }
}

