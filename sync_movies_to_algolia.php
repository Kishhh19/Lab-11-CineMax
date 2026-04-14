<?php

require __DIR__ . '/vendor/autoload.php';

use Algolia\AlgoliaSearch\SearchClient;

// MySQL connection settings for XAMPP / phpMyAdmin
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'mls_lab11';

// Algolia settings: replace with your actual Algolia App ID and Admin API Key
$algoliaAppId = '0XGJH71QMS';
$algoliaAdminApiKey = '9fada231904887306e848f8e47f5b35b';
$algoliaIndexName = 'movies';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "MySQL connection failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n");
    exit(1);
}

$movieTable = findMovieTable($mysqli, $dbName);
if ($movieTable === null) {
    fwrite(STDERR, "No movie table found in database '{$dbName}'. Please check the table name and update the script.\n");
    exit(1);
}

$client = SearchClient::create($algoliaAppId, $algoliaAdminApiKey);
$index = $client->initIndex($algoliaIndexName);

$query = "SELECT * FROM `{$movieTable}`";
$result = $mysqli->query($query);
if ($result === false) {
    fwrite(STDERR, "Failed to query movie records: {$mysqli->error}\n");
    exit(1);
}

$records = [];
$fieldInfo = $result->fetch_fields();
$primaryKey = findPrimaryKey($mysqli, $movieTable);
$objectIdField = $primaryKey ?? getDefaultPrimaryField($fieldInfo);

if ($objectIdField === null) {
    fwrite(STDERR, "Unable to determine a primary key field for objectID. Please ensure your movie table has an 'id' or primary key column.\n");
    exit(1);
}

while ($row = $result->fetch_assoc()) {
    if (!isset($row[$objectIdField])) {
        fwrite(STDERR, "Record missing objectID field '{$objectIdField}'\n");
        continue;
    }
    $row['objectID'] = $row[$objectIdField];
    $records[] = $row;
}

if (empty($records)) {
    fwrite(STDOUT, "No movie records found to sync.\n");
    exit(0);
}

$batchSize = 1000;
$total = count($records);
$sent = 0;

while ($sent < $total) {
    $slice = array_slice($records, $sent, $batchSize);
    $index->saveObjects($slice);
    $sent += count($slice);
    fwrite(STDOUT, "Synced {$sent}/{$total} records to Algolia.\n");
}

fwrite(STDOUT, "Movie sync complete. Indexed {$total} records into '{$algoliaIndexName}'.\n");

$result->free();
$mysqli->close();

function findMovieTable(mysqli $mysqli, string $database): ?string
{
    $candidates = ['moviedb', 'movies', 'movie', 'tbl_movies', 'tbl_movie'];
    foreach ($candidates as $name) {
        $query = "SHOW TABLES LIKE '{$name}'";
        $res = $mysqli->query($query);
        if ($res && $res->num_rows > 0) {
            return $name;
        }
    }

    $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$database}' " .
             "AND (TABLE_NAME LIKE '%moviedb%' OR TABLE_NAME LIKE '%movie%' OR TABLE_NAME LIKE '%film%') LIMIT 1";
    $res = $mysqli->query($query);
    if ($res && $row = $res->fetch_array()) {
        return $row[0];
    }

    return null;
}

function findPrimaryKey(mysqli $mysqli, string $table): ?string
{
    $query = "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'";
    $res = $mysqli->query($query);
    if ($res && $row = $res->fetch_assoc()) {
        return $row['Column_name'] ?? null;
    }
    return null;
}

function getDefaultPrimaryField(array $fields): ?string
{
    foreach ($fields as $field) {
        if ($field->name === 'id') {
            return 'id';
        }
    }
    return $fields[0]->name ?? null;
}
