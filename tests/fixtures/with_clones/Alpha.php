<?php

declare(strict_types=1);

// This file intentionally duplicates the body of processUserRecords() from Beta.php.

function processUserRecords(array $records): array
{
    $output = [];

    foreach ($records as $id => $record) {
        if (!isset($record['name']) || $record['name'] === '') {
            continue;
        }

        $name  = trim((string) $record['name']);
        $email = isset($record['email']) ? strtolower(trim((string) $record['email'])) : '';
        $age   = isset($record['age']) ? (int) $record['age'] : 0;

        if ($age < 0 || $age > 150) {
            $age = 0;
        }

        $output[$id] = ['name' => $name, 'email' => $email, 'age' => $age];
    }

    return $output;
}
