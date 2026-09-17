<?php
// Shared hosting connection settings. Environment DB_* settings take precedence
// in the application and backup runner. This file performs no database queries.
function agila_database_config(): array
{
    $agila_host = 'localhost';
    $agila_user = 'justinebo4ek_user';
    $agila_pass = 'oWDtO(BCPrI6pM&%';
    $agila_db   = 'justinebo4ek_db';
    return ['host' => $agila_host, 'port' => 3306, 'user' => $agila_user,
        'password' => $agila_pass, 'database' => $agila_db];
}