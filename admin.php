<?php


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    set_time_limit(120);
    //$user= $_SERVER['PHP_AUTH_USER'];
    $user= 'kload';
    $json = file_get_contents(dirname(__FILE__).'/list.json');
    $app_array = json_decode($json, true);
    $dir = '/tmp/lal'.rand(10,1000);
    exec('git clone '. $_POST["git-url"] .' -b '. $_POST["git-branch"] .' '. $dir, $result, $result_code);
    if ($result_code) {
        echo 'Error on fetching git repository (is the URL/Branch right ?)'; die;
    }
    exec('cd '. $dir .' && git reset --hard '. $_POST["git-rev"], $result, $result_code);
    if ($result_code) {
        echo 'Error on finding revision'; die;
    }
    $manifest_json = file_get_contents($dir.'/manifest.webapp');
    $manifest_array = json_decode($manifest_json, true);
    system('/bin/rm -rf ' . escapeshellarg($dir));

    $app_id = $manifest_array['yunohost']['uid'];

    if (array_key_exists($app_id, $app_array)) {
        if ($app_array[$app_id]['git']['revision'] == $_POST["git-rev"]) {
            echo 'Error: the revision is the same'; die;
        }
        $commit_msg = 'Update '.$app_id;
        unset($app_array[$app_id]);
    } else {
        $commit_msg  = 'Add '.$app_id;
    }
    $app_array[$app_id] = Array(
        'lastUpdate' => time(),
        'manifest'   => $manifest_array,
        'git'        => Array(
            'url'       => $_POST["git-url"],
            'branch'    => $_POST["git-branch"],
            'revision'  => $_POST["git-rev"]
        )
    );
    $file = fopen(dirname(__FILE__).'/list.json', 'w');
    fwrite($file, json_encode($app_array));
    fclose($file);
    exec('cd '. dirname(__FILE__) .' && hg commit -m"'. $commit_msg .'" --config ui.username='. $user, $result, $result_code);
    if ($result_code) {
        echo 'Error on committing list changes'; die;
    }

    echo 'Success ! :)'; die;
}

?>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />

    <title>Admin</title>
</head>
<body>
    <h1>Validate an App</h1>
    <form action="admin.php" method="post" accept-charset="utf-8">
        <div style="text-align: right; float: left; width: 80px; line-height: 19px; padding-right: 15px;">
            <label for="git-url">Repo URL: </label><br /><br />
            <label for="git-branch">Branch: </label><br /><br />
            <label for="git-rev">Revision: </label><br /><br />
        </div>
        <div style="float: left; width: 400px;">
            <input type="text" name="git-url" id="git-url" placeholder="https://github.com/repo.git" required /><br /><br />
            <input type="text" name="git-branch" id="git-branch" placeholder="master" required /><br /><br />
            <input type="text" name="git-rev" id="git-rev" placeholder="04ff1b3a2281932a937e73064163017b9ec082db" required /><br /><br />
        </div>
        <div style="clear: both;"></div>
        <input style="margin-left: 100px;" type="submit" value="Validate" />
    </form>
</body>
