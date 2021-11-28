<?php

namespace emteknetnz\CowAutoPat;

use emteknetnz\DataFetcher\Apis\GitHubApiConfig;
use emteknetnz\DataFetcher\Requesters\RestRequester;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Dev\BuildTask;

class UpdateTask extends BuildTask
{
    use Configurable;

    private static $cowPatJsonFilename = '.cow.pat.json';
    
    private static $priorCowPathJsonFilename = '.prior.cow.pat.json';
    
    private static $outputJsonFilename = 'output.json';

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

    public function run($request)
    {
        $json = json_decode(file_get_contents($this->config()->get('cowPatJsonFilename')));
        $prior = json_decode(file_get_contents($this->config()->get('priorCowPathJsonFilename')));
    
        // get prior versions
        foreach((array) $prior as $name => $data) {
            $this->getPriorVersions($name, $data);
        }
        // update prior versions
        foreach((array) $json as $name => &$data) {
            $this->updatePriorVersions($name, $data);
        }
        // fetch next versions
        // foreach((array) $json as $name => &$data) {
        //     list($account, $repo) = $this->getAccountRepo($name);
        //     // TODO: get current version from GitHub API, always assume minor release
        // }
        $account = 'silverstripe';
        $repo = 'silverstripe-campaign-admin';
        $requester = new RestRequester(new GitHubApiConfig());
        $requester->fetch("/repos/$account/$repo/branches", '', $account, $repo, true);
    
        file_put_contents(
            $this->config()->get('outputJsonFilename'),
            str_replace('\/', '/', json_encode($json, JSON_PRETTY_PRINT))
        );
    }
}
