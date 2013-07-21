<?php

if (isset($_POST['submit'])) {
    require_once '../../../wp-blog-header.php';
    require_once 'ftotw.php';
    $ftotw = new Ftotw();

    $options = get_option('ftotw-options');
    $current_password = $options['password'];
    $password = $_POST['password'];

    if ($current_password == '' || $password != $current_password) {
        echo "Cheating Heh?";
        exit;
    } else {
        $tweet_text = $_POST['tweet'];

        $tweeter = $ftotw->get_tweeter($tweet_text);

        if ($tweeter != '') {
            $wing = $ftotw->get_wing($tweet_text);

            if ($wing > 0) {
                $tweet_date = strtotime(trim($fields[1], '"'));

                echo "Inserting tweet: $fields[0] <br>";
                // update the db
                $ftotw->insert_tweet($tweeter, trim($fields[0], '"'), $tweet_text, $wing, $tweet_date);
                $ftotw->update_wing($tweeter, $wing, $tweet_date);
            }
        }

        wp_redirect('https://twitter.com/intent/tweet?text=' . $tweet_text);
        exit;
    }
} else {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Tweet</title>
</head>
<body>
    <form action="" method = "post">
        <label for="tweet">Tweet</label><br>
        <textarea name="tweet" cols="50" rows="4"></textarea><br>
        <label for="password">Password</label><br>
        <input type="password" name = "password"><br>

        <input type = "submit" name = "submit" value = "Tweet">
    </form>
</body>
</html>

<?php
}
?>
