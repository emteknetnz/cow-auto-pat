<?php

$priorVersions = [];

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

// TODO
function getAccountRepo($name) {
    // silverstripe/admin => ['silverstripe', 'silverstripe-admin']
    // dnadesign/silverstripe-elemetanal => ['silverstripe', 'silverstripe-elemental']
    // should have this function defined in silverstripe-data-fetcher or similar
    // could possibly just use silverstripe-data-fetcher for all of this
    return ['account', 'repo'];
}

function fetch($path) {

}

function run($request)
{
    $json = json_decode('.cow.pat.json');
    $prior = json_decode('.prior.cow.pat.json');

    // get prior versions
    foreach((array) $prior as $name => $data) {
        getPriorVersions($name, $data);
    }
    // update prior versions
    foreach((array) $json as $name => &$data) {
        updatePriorVersions($name, $data);
    }
    // fetch next versions
    // foreach((array) $json as $name => &$data) {
    //     list($account, $repo) = $this->getAccountRepo($name);
    //     // TODO: get current version from GitHub API, always assume minor release
    // }
    $account = 'silverstripe';
    $repo = 'silverstripe-campaign-admin';
    fetch("/repos/$account/$repo/branches", '', $account, $repo, true);

    file_put_contents('output.json', str_replace('\/', '/', json_encode($json, JSON_PRETTY_PRINT)));
}