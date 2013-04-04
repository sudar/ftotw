<?php
require_once '../../../wp-blog-header.php';
require_once 'ftotw.php';
$ftotw = new Ftotw();

$lines = explode(PHP_EOL, file_get_contents('retweets2.csv'));

foreach ($lines as $line) {
    $fields = explode('","', $line);
    $tweet_text = trim($fields[2], '"');
    if (stripos($tweet_text, '~') !== FALSE && (
        stripos($tweet_text, 'insightful') !== FALSE ||
        stripos($tweet_text, 'brilliant') !== FALSE ||
        stripos($tweet_text, 'amazing') !== FALSE ||
        stripos($tweet_text, 'interesting') !== FALSE ||
        stripos($tweet_text, 'cool') !== FALSE
    )) {

        $tweeter = $ftotw->get_tweeter($tweet_text);

        if ($tweeter != '') {
            $wing = $ftotw->get_wing($tweet_text);

            if ($wing > 0) {
                $tweet_date = strtotime(trim($fields[1], '"'));

                echo "Inserting tweet: $fields[0] <br>";
                // update the db
                $ftotw->insert_tweet($tweeter, trim($fields[0], '"'), $tweet_text, $wing, $tweet_date);
                //$ftotw->update_wing($tweeter, $wing, $tweet_date);
            }
        }
    }
}
?>
