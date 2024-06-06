<?php

function determine_version_bump($commit_message)
{
    if (preg_match('/^(feat|fix|docs|style|refactor|perf|test|chore|build|ci|revert|merge)/', $commit_message)) {
        if (preg_match('/^(feat)(\(.*\))?:/', $commit_message)) {
            return "minor";
        } elseif (preg_match('/^(fix)(\(.*\))?:/', $commit_message)) {
            return "patch";
        } elseif (preg_match('/^(BREAKING CHANGE:|BREAKING-CHANGE:|BREAKING CHANGE\()(.*\)):/', $commit_message)) {
            return "major";
        } else {
            return "none";
        }
    } else {
        return "none";
    }
}

function increment_version($current_version, $bump_type)
{
    $version_parts = explode('.', $current_version);
    $major = (int)$version_parts[0];
    $minor = (int)$version_parts[1];
    $patch = (int)$version_parts[2];

    switch ($bump_type) {
        case 'major':
            $major++;
            $minor = 0;
            $patch = 0;
            break;
        case 'minor':
            $minor++;
            $patch = 0;
            break;
        case 'patch':
        case 'none':
            $patch++;
            break;
    }

    return "$major.$minor.$patch";
}

$current_date = date('Y-m-d');
$major = false;
$minor = false;
$patch = false;

exec("git log --since=\"$current_date 00:00:00\" --until=\"$current_date 23:59:59\" --pretty=format:\"%s\"", $commit_messages);

foreach ($commit_messages as $commit_message) {
    $version_bump = determine_version_bump($commit_message);

    switch ($version_bump) {
        case 'major':
            $major = true;
            break;
        case 'minor':
            $minor = true;
            break;
        case 'patch':
            $patch = true;
            break;
    }
}

if ($major) {
    $final_bump = "major";
} elseif ($minor) {
    $final_bump = "minor";
} elseif ($patch) {
    $final_bump = "patch";
} else {
    $final_bump = "none";
}

$composers = [
    __DIR__ . '/../composer.json',
    __DIR__ . '/../turso-doctrine-dbal/composer.json',
    __DIR__ . '/../turso-driver-laravel/composer.json'
];

foreach ($composers as $file) {
    $current_version = json_decode(file_get_contents($file))->version;
    $new_version = increment_version($current_version, $final_bump);

    $composer_json = json_decode(file_get_contents($file), true);
    $composer_json['version'] = $new_version;
    file_put_contents($file, json_encode($composer_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

exec("git add .");
exec("git commit -m \"Bump version to $new_version based on $final_bump changes.\"");
exec("git tag \"$new_version\"");
exec("git push origin main");
exec("git push origin \"$new_version\"");

echo "Version bumped to $new_version based on $final_bump changes.\n";
