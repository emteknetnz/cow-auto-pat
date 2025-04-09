<?php

// Run this script to check for new patch releases in the JSON files.
// It compares the old JSON file (.cow.pat.json) with the new JSON file (output.json)
// This is intended to be run during the stable release when the rc changelog
// is copied to the stable changelog and the -rc versions is removed.

function readJsonFile($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("File not found: $filePath");
    }
    $content = file_get_contents($filePath);
    return json_decode($content, true);
}

function findPatchReleases($oldData, $newData, $path = '') {
    $patchReleases = [];

    foreach ($oldData as $key => $value) {
        $currentPath = $path ? "$path/$key" : $key;

        if (is_array($value) && isset($newData[$key]) && is_array($newData[$key])) {
            $patchReleases = array_merge($patchReleases, findPatchReleases($value, $newData[$key], $currentPath));
        } elseif ($key === 'Version' && isset($newData['Version']) && isset($oldData['Version'])) {
            $oldVersion = $oldData['Version'];
            $newVersion = $newData['Version'];

            if (isPatchRelease($oldVersion, $newVersion)) {
                $patchReleases[] = [
                    'path' => $path,
                    'oldVersion' => $oldVersion,
                    'newVersion' => $newVersion
                ];
            }
        }
    }

    return $patchReleases;
}

function isPatchRelease($oldVersion, $newVersion) {
    $oldParts = explode('.', $oldVersion);
    $newParts = explode('.', $newVersion);

    if (count($oldParts) !== 3 || count($newParts) !== 3) {
        return false;
    }

    return $oldParts[0] === $newParts[0] && $oldParts[1] === $newParts[1] && $oldParts[2] < $newParts[2];
}

try {
    $oldFilePath = __DIR__ . '/.cow.pat.json';
    $newFilePath = __DIR__ . '/output.json';

    $oldData = readJsonFile($oldFilePath);
    $newData = readJsonFile($newFilePath);

    $patchReleases = findPatchReleases($oldData, $newData);

    if (empty($patchReleases)) {
        echo "No new patch releases found.\n";
    } else {
        echo "New patch releases found:\n";
        foreach ($patchReleases as $release) {
            echo "Path: {$release['path']}\n";
            echo "Old Version: {$release['oldVersion']}\n";
            echo "New Version: {$release['newVersion']}\n\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

