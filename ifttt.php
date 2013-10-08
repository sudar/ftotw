<?php
if (isset($_GET['password'])) {

    require_once '../../../wp-blog-header.php';
    require_once 'ftotw.php';
    $ftotw = new Ftotw();

    $options = get_option('ftotw-options');
    $email = $options['email'];
    $current_password = $options['password'];
    $password = $_GET['password'];

    if ($current_password == '' || $password != $current_password) {
        echo "Cheating Heh?";
        exit;
    } else {
        $tweet_text = $_GET['title'];
        $tweet_id = $ftotw->get_guid_from_permalink($_GET['description']);

        $tweeter = $ftotw->get_tweeter($tweet_text);

        if ($tweeter != '') {
            $wing = $ftotw->get_wing($tweet_text);

            if ($wing > 0) {
                $tweet_date = time();
                // update the db
                $ftotw->insert_tweet($tweeter, $tweet_id, $tweet_text, $wing, $tweet_date);
                $ftotw->update_wing($tweeter, $wing, $tweet_date);

                wp_mail( $email, 'FTOTW Tweet', stripcslashes ($tweet_text ));
            }
        }
    }
} else {

header ('Location: http://sastwingees.org');
exit;
}
?>
