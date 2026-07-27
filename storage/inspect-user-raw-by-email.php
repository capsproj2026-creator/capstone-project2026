<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? '';
if ($email === '') {
    fwrite(STDERR, "Usage: php storage/inspect-user-raw-by-email.php <email>\n");
    exit(1);
}

$users = Illuminate\Support\Facades\DB::connection('mongodb')->getCollection('users');
$doc = $users->findOne(['email' => $email]);
if (! $doc) {
    echo "not found\n";
    exit(0);
}

$id = $doc['id'] ?? null;
$oid = $doc['_id'] ?? null;

echo "email={$email}\n";
echo "doc['_id'] type=".gettype($oid)." value=".(is_object($oid) ? get_class($oid) : (string) $oid)."\n";
if (is_object($oid) && method_exists($oid, 'jsonSerialize')) {
    echo "doc['_id'] json=".json_encode($oid)."\n";
}
echo "doc['id'] type=".gettype($id)." value=".(is_object($id) ? get_class($id) : (string) $id)."\n";

