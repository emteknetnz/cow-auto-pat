<?php

$json = json_decode(file_get_contents('.cow.pat.json'));
$prior = json_decode(file_get_contents('.prior.cow.pat.json'));

$priorVersions = [];

// recursive function
function getPriorVersions($name, $data) {
    global $priorVersions;
    $priorVersions[$name] = $data->PriorVersion;
    foreach ((array) $data->Items as $name => $data) {
        getPriorVersions($name, $data);
    }
}

function updatePriorVersions($name, &$data) {
    global $priorVersions;
    $data->PriorVersion = $priorVersions[$name];
    foreach ((array) $data->Items as $name => &$_data) {
        updatePriorVersions($name, $_data);
    }
}

// TODO
function getAccountRepo($name) {
    // silverstripe/admin => ['silverstripe', 'silverstripe-admin']
    // dnadesign/silverstripe-elemetanal => ['silverstripe', 'silverstripe-elemental']
    // should have this function defined in silverstripe-data-fetcher or similar
    // could possibly just use silverstripe-data-fetcher for all of this
}

foreach((array) $prior as $name => $data) {
    getPriorVersions($name, $data);
}
foreach((array) $json as $name => &$data) {
    updatePriorVersions($name, $data);
    list($account, $repo) = getAccountRepo($name);
    // TODO: get current version from GitHub API, always assume minor release
}

file_put_contents('output.json', str_replace('\/', '/', json_encode($json, JSON_PRETTY_PRINT)));
