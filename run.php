<?php

$priorVersions = [];
$oldMajors = [];

function getPriorVersions($name, $data) {
    global $priorVersions;
    $priorVersions[$name] = $data->PriorVersion;
    foreach ((array) $data->Items ?? [] as $name => $data) {
        getPriorVersions($name, $data);
    }
}

function updatePriorVersions($name, &$data) {
    global $priorVersions;
    $data->PriorVersion = $priorVersions[$name] ?? null;
    foreach ((array) $data->Items ?? [] as $name => &$_data) {
        updatePriorVersions($name, $_data);
    }
}

function updateMinorTags($name, &$data, $tagSuffix) {
    $upgradeOnly = $data->UpgradeOnly ?? false;
    $data->Version = getNextMinorTag($name, $upgradeOnly, $tagSuffix);
    foreach ((array) $data->Items ?? [] as $name => &$_data) {
        updateMinorTags($name, $_data, $tagSuffix);
    }
}

function getAccountRepo($name) {
    list($account, $repo) = explode('/', $name);
    if ($account == 'silverstripe') {
        // recipe- prefix
        if (strpos($repo, 'recipe') === 0) {
            return [$account, $repo];
        }
        // module not prefixed with 'silverstripe-'
        if (in_array($repo, ['comment-notifications'])) {
            return [$account, $repo];
        }
        return ['silverstripe', 'silverstripe-' . $repo];
    }
    // cwp account
    if ($account == 'cwp') {
        if ($repo == 'agency-extensions') {
            return ['silverstripe', 'cwp-agencyextensions'];
        }
        if (strpos($repo, 'cwp') === 0) {
            return ['silverstripe', $repo];
        } else {
            return ['silverstripe', 'cwp-' . $repo];
        }
    }
    // dnadesign
    if ($account == 'dnadesign' && $repo == 'silverstripe-elemental') {
        return ['silverstripe', $repo];
    }
    // tractorcow
    if ($account == 'tractorcow' && $repo == 'silverstripe-fluent') {
        return ['tractorcow-farm', $repo];
    }
    // everything else
    return [$account, $repo];
}

// returns username:token
// https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token
// tick ZERO of the permission checkboxes since accessing public repos
// .credentials
// user=my_github_username
// token=abcdef123456
function getCredentials() {
    $data = [];
    $s = file_get_contents('.credentials');
    $lines = preg_split('/[\r\n]/', $s);
    foreach ($lines as $line) {
        $kv = preg_split('/=/', $line);
        if (count($kv) != 2) break;
        $key = $kv[0];
        $value = $kv[1];
        $data[$key] = $value;
    }
    return $data['user'] . ':' . $data['token'];
}

$lastRequestTS = 0;
function waitUntilCanFetch() {
    // https://developer.github.com/v3/#rate-limiting
    // - authentacted users can make 5,000 requests per hour
    // - wait 1 second between requests (max of 3,600 per hour)
    global $lastRequestTS;
    $ts = time();
    if ($ts == $lastRequestTS) {
        sleep(1);
    }
    $lastRequestTS = $ts;
}

function fetch($path) {
    $domain = "https://api.github.com";
    $path = ltrim($path, '/');
    if (preg_match('#/[0-9]+$#', $path) || preg_match('@/[0-9]+/files$@', $path)) {
        // requesting details
        $url = "${$domain}/${$path}";
    } else {
        // requesting a list
        $op = strpos($path, '?') ? '&' : '?';
        $url = "${domain}/${path}${op}per_page=100";
    }
    $label = str_replace($domain, '', $url);
    echo "Fetching from ${label}\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, getCredentials());
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    waitUntilCanFetch();
    $s = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($s);
    return $json;
}

function getNextMinorTag($name, $upgradeOnly, $tagSuffix) {
    global $priorVersions;
    $oldMajor = substr($priorVersions[$name], 0, 1);
    list($account, $repo) = getAccountRepo($name);
    $json = fetch("/repos/$account/$repo/tags");
    $vals = [];
    foreach ($json as $tag) {
        $name = $tag->name;
        $name = preg_replace('#[^0-9\.]#', '', $name);
        if (!preg_match('#^([1-9])\.([0-9]+)\.([0-9]+)$#', $name, $m)) {
            continue;
        }
        if ($m[1] != $oldMajor) {
            continue;
        }
        $vals[] = $m[1] * 10000 + $m[2] * 100 + $m[3];
    }
    sort($vals);
    $vals = array_reverse($vals);
    $val = $vals[0];
    $major = floor($val / 10000);
    $minor = floor(($val % 10000) / 100);
    $patch = ($val % 10000) % 100;
    if ($upgradeOnly) {
        return $major . '.' . $minor . '.' . $patch;
    } else {
        return $major . '.' . ($minor + 1) . '.0' . $tagSuffix;
    }
}

// RUN
$tagSuffix = count($argv) > 1 ? '-' . $argv[1] : '';

$json = json_decode(file_get_contents('.cow.pat.json'));
$prior = json_decode(file_get_contents('.prior.cow.pat.json'));

// get prior versions
foreach((array) $prior as $name => $data) {
    getPriorVersions($name, $data);
}
// update prior versions
foreach((array) $json as $name => &$data) {
    updatePriorVersions($name, $data);
}
// fetch next versions
foreach((array) $json as $name => &$data) {
    updateMinorTags($name, $data, $tagSuffix);
}

file_put_contents('output.json', str_replace('\/', '/', json_encode($json, JSON_PRETTY_PRINT)));
