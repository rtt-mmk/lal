<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />

    <title>Validate App</title>
</head>
<?php


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    @ini_set('zlib.output_compression',0);
    @ini_set('implicit_flush',1);
    @ob_end_clean();
    set_time_limit(120);
    ob_implicit_flush(1);
    $user = $_SERVER['PHP_AUTH_USER'];
    echo 'Get list.json content... '.str_pad('', 4096); flush();
    sleep(2);
    $json = file_get_contents(dirname(__FILE__).'/list.json');
    $app_array = json_decode($json, true);
    echo "OK<br />".str_pad('', 4096); flush();
    $dir = '/tmp/lal'.rand(10, 1000);
    echo 'Cloning git repo to fetch manifest... '.str_pad('', 4096); flush();
    exec('git clone '. $_POST["git-url"] .' -b '. $_POST["git-branch"] .' '. $dir, $result, $result_code);
    if ($result_code) {
        echo '<strong>Error:</strong> is the URL/Branch right ?'; die;
    }
    exec('cd '. $dir .' && git reset --hard '. $_POST["git-rev"], $result, $result_code);
    if ($result_code) {
        echo '<strong>Error:</strong> wrong revision'; die;
    }
    $manifest_json = file_get_contents($dir.'/manifest.json');
    $manifest_array = json_decode($manifest_json, true);
    system('/bin/rm -rf ' . escapeshellarg($dir));
    echo "OK<br />".str_pad('', 4096); flush();

    $app_id = $manifest_array['id'];

    if (array_key_exists($app_id, $app_array)) {
        if ($app_array[$app_id]['git']['revision'] == $_POST["git-rev"]) {
            echo '<strong>Error:</strong> App already up-to-date on the list (revision unchanged)'; die;
        }
        $commit_msg = 'Update ';
        unset($app_array[$app_id]);
    } else {
        $commit_msg  = 'Add ';
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

    echo 'Write list.json changes... '.str_pad('', 4096); flush();
    $file = fopen(dirname(__FILE__).'/list.json', 'w');
    fwrite($file, json_encode($app_array));
    fclose($file);
    echo "OK<br />".str_pad('', 4096); flush();

    echo 'Commit changes... '.str_pad('', 4096); flush();
    exec('cd '. dirname(__FILE__) .' && hg commit -m"'. $commit_msg.$app_id .'" --config ui.username='. $user, $result, $result_code);
    if ($result_code) {
        echo 'Error on committing list changes'; die;
    }
    echo "OK<br />".str_pad('', 4096); flush();
    echo 'Sending confirmation mail... '.str_pad('', 4096); flush();
    $instance_url = 'http://'.$_SERVER['HTTP_HOST'].substr($_SERVER['REQUEST_URI'], 0, -12);

    $to      = 'app-request@yunohost.org, '.$_POST["mail"];
    $subject = '[YNH '. $commit_msg .' App] '. $app_id .' validated by '. $user;
    $message = 'The following app has been validated by '. $user ." :\r\n". $instance_url .'?app='. $app_id;
    $headers = 'From: validators@yunohost.org' . "\r\n" .
               'Reply-To: app-request@yunohost.org' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    if (!mail($to, $subject, $message, $headers)) {
        echo '<strong>Error:</strong> Mail sending fail !'; die;
    }

    echo 'OK<br /><br /><strong>Success !</strong> =)<br /><br /><br /><a href="'. $instance_url .'?app='. $app_id .'">&larr; Back to list</a>'; die;
}

?>
<body>
    <h1>Validate an App</h1>
    <form action="validate.php" method="post" accept-charset="utf-8">
        <div style="text-align: right; float: left; width: 100px; line-height: 20px; padding-right: 10px;">
            <label for="git-url">Repo URL: </label><br /><br />
            <label for="git-branch">Branch: </label><br /><br />
            <label for="git-rev">Revision: </label><br /><br />
            <label for="mail">Author's Mail: </label><br /><br />
        </div>
        <div style="float: left; width: 400px;">
            <input type="text" name="git-url" id="git-url" placeholder="https://github.com/repo.git" required /><br /><br />
            <input type="text" name="git-branch" id="git-branch" placeholder="master" required /><br /><br />
            <input type="text" name="git-rev" id="git-rev" placeholder="04ff1b3a2281932a937e73064163017b9ec082db" required /><br /><br />
            <input type="text" name="mail" id="mail" placeholder="john@doe.org" required /><br /><br />
        </div>
        <div style="clear: both;"></div>
        <input style="margin-left: 100px;" type="submit" value="Validate" />
    </form>
</body>
