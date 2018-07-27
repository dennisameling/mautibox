<?php

require_once __DIR__.'/../vendor/autoload.php';

$key        = 'mautic_pulls';
$ttl        = 300;
$pool       = new Cache\Adapter\Apcu\ApcuCachePool();
$simplified = $pool->get($key);
if (!$simplified) {
    $client = new Github\Client();
    $pager  = new Github\ResultPager($client);
    $client->addCache($pool, ['default_ttl' => $ttl]);
    $client->authenticate(getenv('GH_TOKEN'), null, Github\Client::AUTH_HTTP_TOKEN);

    // Get all open PRs sorted by popularity.
    $params     = [
        'state'     => 'open',
        'base'      => 'staging',
        'sort'      => 'popularity',
        'direction' => 'desc',
        'per_page'  => 100,
    ];
    $repoApi    = $client->api('pullRequest');
    $pulls      = $pager->fetch($repoApi, 'all', ['mautic', 'mautic', $params]);
    $simplified = [];
    while (!empty($pulls)) {
        foreach ($pulls as $pull) {
            $prNumber              = $pull['number'];
            $simplified[$prNumber] = [
                'title' => $pull['title'],
                'user'  => !empty($pull['user']['login']) ? $pull['user']['login'] : '',
            ];
        }
        $pulls = [];
        if ($pager->hasNext()) {
            $pulls = $pager->fetchNext();
        }
    }
    $pool->set($key, $simplified, $ttl);
}
header('Content-Type: application/json');
echo json_encode($simplified);
