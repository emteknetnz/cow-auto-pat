<?php

namespace emteknetnz\CowAutoPat;

class Updater
{
    private $priorVersions = [];

    private function getPriorVersions($name, $data)
    {
        $this->priorVersions[$name] = $data->PriorVersion;
        foreach ((array) $data->Items ?? [] as $name => $data) {
            $this->getPriorVersions($name, $data);
        }
    }

    private function updatePriorVersions($name, &$data)
    {
        $data->PriorVersion = $this->priorVersions[$name] ?? null;
        foreach ((array) $data->Items ?? [] as $name => &$_data) {
            $this->updatePriorVersions($name, $_data);
        }
    }

    // TODO
    private function getAccountRepo($name)
    {
        // silverstripe/admin => ['silverstripe', 'silverstripe-admin']
        // dnadesign/silverstripe-elemetanal => ['silverstripe', 'silverstripe-elemental']
        // should have this function defined in silverstripe-data-fetcher or similar
        // could possibly just use silverstripe-data-fetcher for all of this
        return ['account', 'repo'];
    }

    public function update($cowPatJsonFilename, $priorCowPathJsonFilename, $outputJsonFilename)
    {
        $json = json_decode(file_get_contents($cowPatJsonFilename));
        $prior = json_decode(file_get_contents($priorCowPathJsonFilename));
    
        foreach((array) $prior as $name => $data) {
            $this->getPriorVersions($name, $data);
        }
        foreach((array) $json as $name => &$data) {
            $this->updatePriorVersions($name, $data);
            list($account, $repo) = $this->getAccountRepo($name);
            // TODO: get current version from GitHub API, always assume minor release
        }
    
        file_put_contents($outputJsonFilename, str_replace('\/', '/', json_encode($json, JSON_PRETTY_PRINT)));
    }
}
