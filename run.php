<?php

$priorVersions = [];
$existingVersions = [];
$oldMajors = [];
$release = [];
$lines = [];

$j = json_decode(file_get_contents('.cow.pat.json'));
$v = $j->{'silverstripe/recipe-kitchen-sink'}->Version;
$b = preg_match('#-(beta1|beta2|beta3|rc1|rc2|rc3)$#', $v, $m);
$tagSuffix = $b ? '-' . $m[1] : '';

if (file_exists('release.txt')) {
    foreach (explode("\n", file_get_contents('release.txt')) as $line) {
        if (strpos($line, ',') === false) {
            continue;
        }
        list($name, $doRelease) = explode(',', $line);
        $release[$name] = $doRelease;
    }
}

function createReleaseSample() {
    $json = json_decode(file_get_contents('output.json'));
    global $lines;
    foreach((array) $json as $name => $data) {
        outputRelease($name, $data);
    }
    sort($lines);
    file_put_contents('release.sample.txt', implode("\n", $lines));
}

function updateExistingVersions($name, $data) {
    global $existingVersions;
    $existingVersions[$name] = $data->Version;
    foreach ((array) $data->Items ?? [] as $name => $data) {
        updateExistingVersions($name, $data);
    }
}

function getPriorVersions($name, $data) {
    global $priorVersions;
    $priorVersions[$name] = $data->Version;
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
    $data->Version = getNextMinorTag($name, $data->Version, $upgradeOnly, $tagSuffix);
    foreach ((array) $data->Items ?? [] as $name => &$_data) {
        updateMinorTags($name, $_data, $tagSuffix);
    }
}

function outputRelease($name, &$data) {
    global $lines;
    $alwaysRelease = [
        // LOCKSTEP RECIPES
        'silverstripe/recipe-authoring-tools',
        'silverstripe/recipe-blog',
        'silverstripe/recipe-ccl',
        'silverstripe/recipe-cms',
        'silverstripe/recipe-collaboration',
        'silverstripe/recipe-content-blocks',
        'silverstripe/recipe-core',
        'silverstripe/recipe-form-building',
        'silverstripe/recipe-kitchen-sink',
        'silverstripe/recipe-reporting-tools',
        'silverstripe/recipe-services',
        'silverstripe/recipe-solr-search',
        'silverstripe/installer',
        // LOCKSTEP CORE MODULES
        'silverstripe/framework',
        'silverstripe/assets',
        'silverstripe/versioned',
        'silverstripe/admin',
        'silverstripe/asset-admin',
        'silverstripe/versioned-admin',
        'silverstripe/campaign-admin',
        'silverstripe/cms',
        'silverstripe/reports',
        'silverstripe/errorpoage',
        'silverstripe/siteconfig',
        // ALSO ALWAYS RELEASE
        'silverstripe/developer-docs',
    ];
    if ($data->UpgradeOnly ?? false) {
        $lines[] = "$name,U";
    } else {
        $doRelease = in_array($name, $alwaysRelease) ? '1' : '0';
        $lines[] = "$name,$doRelease";
    }
    foreach ((array) $data->Items ?? [] as $name => &$_data) {
        outputRelease($name, $_data);
    }
}

// $name = composer name e.g. silverstripe/admin
function getAccountRepo($name) {
    list($account, $repo) = explode('/', $name);
    if ($account == 'silverstripe-themes') {
        $account = 'silverstripe';
    }
    if ($account == 'silverstripe') {
        // recipe- prefix
        if (strpos($repo, 'recipe') === 0) {
            return [$account, $repo];
        }
        // module not prefixed with 'silverstripe-'
        if (in_array($repo, [
            'comment-notifications',
            'vendor-plugin',
            // silverstripe/silverstripe-fluent fork
            'silverstripe-fluent',
            'developer-docs',
        ])) {
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
    // colymba
    if ($account == 'colymba' && $repo == 'gridfield-bulk-editing-tools') {
        return ['silverstripe', 'silverstripe-gridfield-bulk-editing-tools'];
    }
    // everything else
    return [$account, $repo];
}

// returns token
// https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token
// tick ZERO of the permission checkboxes since accessing public repos
function getCredentials() {
    // read GH_TOKEN from environment variable
    $token = getenv('GH_TOKEN');
    if (!$token) {
        throw new Exception('Missing GH_TOKEN env variable');
    }
    var_dump($token);die;
    return $token;
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
    $path = str_replace(
        'colymba/gridfield-bulk-editing-tools',
        'colymba/GridfieldBulkEditingTools',
        $path
    );
    $path = ltrim($path, '/');
    if (preg_match('#/[0-9]+$#', $path) || preg_match('@/[0-9]+/files$@', $path)) {
        // requesting details
        $url = "{$domain}/{$path}";
    } else {
        // requesting a list
        $op = strpos($path, '?') ? '&' : '?';
        $url = "{$domain}/{$path}{$op}per_page=100";
    }
    $label = str_replace($domain, '', $url);
    echo "Fetching from {$label}\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BEARER);
    curl_setopt($ch,CURLOPT_XOAUTH2_BEARER, getCredentials());
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    waitUntilCanFetch();
    $s = curl_exec($ch);
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($responseCode != 200) {
        throw new RuntimeException("Couldn't fetch - got response code $responseCode");
    }
    $json = json_decode($s);
    $p = str_replace('/', '-', $path);
    if (is_object($json)) {
        file_put_contents("debug/$p.json", json_encode($json, 448));
    } else {
        file_put_contents("debug/$p.json", $s);
    }
    return $json;
}

function getNextMinorTag($name, $cowpatVersion, $upgradeOnly, $tagSuffix) {
    global $priorVersions, $release;
    if (!isset($priorVersions[$name])) {
        $parts = explode('.', $cowpatVersion);
        $oldMajor = array_shift($parts);
    } else {
        $oldMajor = substr($priorVersions[$name], 0, 1);
    }
    list($account, $repo) = getAccountRepo($name);
    $json = fetch("/repos/$account/$repo/tags");
    $vals = [];
    foreach ($json as $tag) {
        $tagName = $tag->name;
        if (!preg_match('#^([1-9])\.([0-9]+)\.([0-9]+)#', $tagName, $m)) {
            continue;
        }
        if ($m[1] != $oldMajor) {
            continue;
        }
        $vals[] = $m[1] * 10000 + $m[2] * 100 + $m[3];
    }
    $vals = array_unique($vals);
    sort($vals);
    $vals = array_reverse($vals);
    if (!isset($vals[0])) {
        if ($name == 'silverstripe/linkfield') {
            return '4.0.0';
        }
        echo "! Couldn't find any tags for $name";
        die;
    }
    $val = $vals[0];
    $major = floor($val / 10000);
    $minor = floor(($val % 10000) / 100);
    $patch = ($val % 10000) % 100;
    $doRelease = $release[$name] ?? '0';
    if ($upgradeOnly || $doRelease != '1') {
        return $major . '.' . $minor . '.' . $patch;
    } else {
        return $major . '.' . ($minor + 1) . '.0' . $tagSuffix;
    }
}

function findAllCowpatModules($name, $data, &$output) {
    $output[$name] = $data->Version;
    foreach ((array) $data->Items ?? [] as $name => $data) {
        findAllCowpatModules($name, $data, $output);
    }
}

// RUN
if (file_exists('debug')) {
    shell_exec('rm -rf debug');
}
mkdir('debug');

$json = json_decode(file_get_contents('.cow.pat.json'));
$prior = json_decode(file_get_contents('.prior.cow.pat.json'));

// uncomment this to regenerate release.sample.txt
// createReleaseSample(); die;

// get existing versions
foreach((array) $json as $name => $data) {
    updateExistingVersions($name, $data);
}

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
// get the names and versions of all modules in the cowpat
$cowpatModules = [];
foreach((array) $json as $name => $data) {
    findAllCowpatModules($name, $data, $cowpatModules);
}

// look for modules missing from release.txt or from cowpat
$missingFromCowpat = array_diff(array_keys($release), array_keys($cowpatModules));
$missingFromRelease = array_diff(array_keys($cowpatModules), array_keys($release));
if (!empty($missingFromCowpat)) {
    echo "WARNING!!! The following modules are missing from .cow.pat.json:\n";
    foreach ($missingFromCowpat as $missingModule) {
        echo "- $missingModule\n";
    }
}
if (!empty($missingFromRelease)) {
    echo "WARNING!!! The following modules are missing from release.txt:\n";
    foreach ($missingFromRelease as $missingModule) {
        echo "- $missingModule\n";
    }
}

file_put_contents('output.json', str_replace('\/', '/', json_encode($json, JSON_PRETTY_PRINT)));
echo "Wrote to output.json\n";

// changelog-table-next.txt
$markup = [
    '<details>',
    '<summary>Included module versions</summary>',
    '',
    '| Module | Version |',
    '| ------ | ------- |',
];
ksort($cowpatModules);
foreach ($cowpatModules as $name => $version) {
    if ($name === 'silverstripe/developer-docs') {
        continue;
    }
    $markup[] = "| $name | $version |";
}
$markup[] = '';
$markup[] = '</details>';
$markup[] = '';

$filename = 'changelog-table-next.txt';
file_put_contents($filename, implode("\n", $markup));
echo "Wrote to $filename\n";

// changelog-table-existing.txt
$markup = [
    '<details>',
    '<summary>Included module versions</summary>',
    '',
    '| Module | Version |',
    '| ------ | ------- |',
];
foreach (array_keys($cowpatModules) as $name) {
    $version = $existingVersions[$name];
    if ($name === 'silverstripe/developer-docs') {
        continue;
    }
    $markup[] = "| $name | $version |";
}
$markup[] = '';
$markup[] = '</details>';
$markup[] = '';

$filename = 'changelog-table-existing.txt';
file_put_contents($filename, implode("\n", $markup));
echo "Wrote to $filename\n";
