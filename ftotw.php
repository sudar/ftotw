<?php
/**
Plugin Name: FTOTW
Plugin URI: http://sudarmuthu.com/wordpress/
Description: Automatically created posts from Twitter feed
Author: Sudar
Version: 0.3
Author URI: http://sudarmuthu.com/
Text Domain: ftotw

=== RELEASE NOTES ===
2011-05-14 - v0.1 - Initial Release
2011-11-14 - v0.2 - Second Release
2013-01-24 - v0.3 - Change the page templates
*/

global $wpdb;
global $ftotw_table_name;
$ftotw_table_name = $wpdb->prefix . "ftotw";

global $ftotw_tweet_table_name;
$ftotw_tweet_table_name = $wpdb->prefix . "ftotw_tweets";

// TODO - Should find some way to get away with these global variables.
global $ftotw_db_version;
$ftotw_db_version = "0.2";

/**
 * FTOTW Plugin Class
 */
class Ftotw {

    /**
     * Initalize the plugin by registering the hooks
     */
    function __construct() {

        // Load localization domain
        load_plugin_textdomain( 'ftotw', false, dirname(plugin_basename(__FILE__)) . '/languages' );

        // Register hooks
        add_action('do_ftotw', array(&$this, 'do_ftotw'));
        add_action('do_ftotw_fetch_tweets', array(&$this, 'do_ftotw_fetch_tweets'));
        
        // Settings hooks
        add_action( 'admin_menu', array(&$this, 'register_settings_page') );
        add_action( 'admin_init', array(&$this, 'add_settings') );

        // add filters
        add_filter( 'cron_schedules', array( &$this, 'filter_cron_schedules' ) );

        // Manual run
        if (isset ($_POST['action']) && $_POST['action'] == 'manual-run') {
//            $this->do_ftotw_fetch_tweets();
            $this->do_ftotw();
        }
    }

    /**
     * Create post by fetching content
     */
    function do_ftotw() {

        global $wpdb;
        global $ftotw_tweet_table_name;

        $last_tweet_id = get_option('ftotw-last-tweet-id', 0);
        
        $tweets = $wpdb->get_results("SELECT * FROM $ftotw_tweet_table_name WHERE id > $last_tweet_id ORDER BY wings DESC");
        $max_id = $wpdb->get_var($wpdb->prepare("SELECT MAX(id) FROM $ftotw_tweet_table_name"));
        
        if ($tweets) {
            $options = get_option('ftotw-options');
            // create post
            $post = $this->create_post_content($tweets, $options);

            // schedule post
            $ftotw_no = get_option('ftotw_no', 1);
            $postarr = array(
                'post_content' => $post,
                'post_status' => 'publish',
                'post_category' => array($options['category']),
                'post_title' => 'FTOTW' . $ftotw_no . ' - best links of the week ending ' . $this->getDate(),
                'post_author' => 1
            );
            $post_id = wp_insert_post($postarr);

            //insert tags
            wp_set_post_terms($post_id, $options['tags']);
            // update running number for post title
            update_option('ftotw_no', $ftotw_no + 1);
            //update the last tweet id
            update_option('ftotw-last-tweet-id', $max_id);
        }
    }

    /**
     * Fetch the tweets and process them. Should run everyday
     */
    function do_ftotw_fetch_tweets() {

        $options = get_option('ftotw-options');

        if ($options['twitter-feed'] != '') {

            // fetch and parse Yahoo pipes
            include_once(ABSPATH . WPINC . '/feed.php');

            $feed_url = $options['twitter-feed'];
            $since_id = get_option('ftotw-since-id', 1);
//            $feed_url .= '?count=10';
            $feed_url .= '&count=100&since_id=' . $since_id;

            $feed = fetch_feed($feed_url);

            if (!is_wp_error( $feed ) ) { // Checks that the object is created correctly
                if ($feed->get_item_quantity() > 0) {

                    $tweets = $feed->get_items();

                    foreach ($tweets as $tweet) {
                        $tweet_text = $tweet->get_description();
                        if (stripos($tweet_text, '~') !== FALSE && (
                                stripos($tweet_text, 'insightful') !== FALSE ||
                                stripos($tweet_text, 'brilliant') !== FALSE ||
                                stripos($tweet_text, 'amazing') !== FALSE ||
                                stripos($tweet_text, 'interesting') !== FALSE ||
                                stripos($tweet_text, 'cool') !== FALSE
                            )) {

                            $tweeter = $this->get_tweeter($tweet_text);

                            if ($tweeter != '') {
                                $wing = $this->get_wing($tweet_text);

                                if ($wing > 0) {
                                    $tweet_date = $tweet->get_date("U");
                                    
                                    // update the db
                                    $this->insert_tweet($tweeter, $this->get_guid_from_permalink($tweet->get_permalink()), $tweet_text, $wing, $tweet_date);
                                    $this->update_wing($tweeter, $wing, $tweet_date);
                                }
                            }
                        }
                    }

                    // update since id
                    $this->update_since_id($tweets);
                } else {
                    $this->send_email("Zero tweets found. Either there are no tweets available or Twitter could be down. The Plugin will try to retrieve the tweets again. You don't have to do anything.");
                    error_log("Zero entries found");
                }
            } else {
                $this->send_email("Error parsing the feed. Possible reason is that Twitter could be down. The Plugin will try to retrieve the tweets again. You don't have to do anything. The following is the log information for debugging. " . var_export($feed, TRUE));
                error_log("Error parsing the feed" . var_export($feed, TRUE));
            }
        }
    }

    /**
     * Format date
     * 
     * @return <type> 
     */
    private function getDate() {
        $date = getdate();
        return $date['mday'] . '-' . $date['month'] . '-' . $date['year'];
    }

    /**
     * Update Since id for tweets
     * 
     * @param <type> $tweets
     */
    function update_since_id($tweets) {
        $last_guid = $tweets[0]->get_permalink();
        $since_id = $this->get_guid_from_permalink($last_guid);
        if ($since_id > 0) {
            update_option('ftotw-since-id', $since_id);
        }
    }

    /**
     * Get Guid from permalink
     *
     * @param <type> $permalink
     * @return <type>
     */
    function get_guid_from_permalink($permalink) {
        return substr($permalink, strrpos($permalink, '/') + 1);
    }
    /**
     * Create Post Content
     *
     * @param <type> $tweets
     * @param <type> $options
     */
    function create_post_content($tweets, $options) {
        $post = '<h3>Prolog</h3>
        <p>Here are the best links shared on my tweet stream this week. </p>
        <h3>Best Links </h3>';
        $post .= '<ol>';
        foreach ($tweets as $tweet) {
            $tweet_text = $tweet->tweet;

            $helper = new TweetHelper($tweet);
            $tweet_content = $helper->get_processed_content();

//                $tweet_title = $this->get_tweet_title($tweet->get_title());
//            $tweet_content = $this->process_url($tweet_text);
//            $tweet_content = $this->expand_twitter_name($tweet_content);
//            $tweet_content = $this->add_permalink($tweet_content, 'http://twitter.com/rsukumar/statuses/' . $tweet->tweet_id);

            $post .= <<<EOD
                <li>
                    <p>{$tweet_content}</p>
                </li>
EOD;
        }
        $post .= '</ol>';

        $post .= $options['footer-text'];
        return $post;
    }

    /**
     * Get twitter title
     *
     * @param <type> $tweet
     * @return <type>
     */
    function get_tweet_title($tweet) {
        preg_match('#^rsukumar:.{1,}:\s(.{1,})(http?:[A-Za-z0-9/\.]*).*#ix', $tweet, $matches);
        return trim($matches[1]);
    }

    /**
     * Get the twitter username
     * 
     * @param <type> $content
     * @return <type>
     */
    function get_tweeter($content) {
        preg_match("/@(\w+)/", $content, $matches);
        return $matches[1];
    }

    /**
     * Calculate the wing for a particular tweet
     *
     * @param <type> $content
     * @return <type>
     */
    function get_wing($content) {
        if (stripos($content, '****ing brilliant') !== FALSE) {
            return 4;
        }
        
        if (stripos($content, 'brilliant') !== FALSE ||
            stripos($content, 'amazing') !== FALSE) {
            
            return 3;
        }

        if (stripos($content, 'vv cool') !== FALSE ||
            stripos($content, 'vv interesting') !== FALSE ||
            stripos($content, 'vv insightful') !== FALSE) {
            
            return 2;
        }

        if (stripos($content, 'cool') !== FALSE ||
            stripos($content, 'interesting') !== FALSE ||
            stripos($content, 'insightful') !== FALSE) {

            return 1;
        }

        return 0;
    }

    /**
     * Insert new tweet into db
     *
     * @global <type> $wpdb
     * @global <type> $ftotw_tweet_table_name
     * @param <type> $tweeter
     * @param <type> $tweet_id
     * @param <type> $tweet_text
     * @param <type> $wing
     * @param <type> $tweet_date
     */
    function insert_tweet($tweeter, $tweet_id, $tweet_text, $wing, $tweet_date) {
        global $wpdb;
        global $ftotw_tweet_table_name;

        $wpdb->insert($ftotw_tweet_table_name, array(
            'tweet_id' => $tweet_id,
            'twitter_id' => $tweeter,
            'wings' => $wing,
            'tweet' => $tweet_text,
            'tweet_date' => date( 'Y-m-d H:i:s', $tweet_date)
        ));
    }

    /**
     * Update wings for a user
     *
     * @global <type> $wpdb
     * @global <type> $ftotw_table_name
     * @param <type> $tweeter
     * @param <type> $wing
     * @param <type> $tweet_date
     */
    function update_wing($tweeter, $wing, $tweet_date) {
        global $wpdb;
        global $ftotw_table_name;

        $user_wings = $wpdb->get_var($wpdb->prepare("SELECT wings from $ftotw_table_name WHERE twitter_id = '$tweeter'"));
        if ($user_wings > 0) {
            // user present, so update
            $wpdb->update($ftotw_table_name, array(
                'wings' => $user_wings + $wing
            ), array(
                'twitter_id' => $tweeter
            ));
        } else {
            // user not present, so insert
            $wpdb->insert($ftotw_table_name, array(
                'twitter_id' => $tweeter,
                'wings' => $wing,
                'added_on' => date( 'Y-m-d H:i:s', $tweet_date)
            ));
        }
    }

    /**
     * Register the settings page
     */
    function register_settings_page() {
        add_options_page( __('FTOTW', 'ftotw'), __('FTOTW', 'ftotw'), 8, 'ftotw', array(&$this, 'settings_page') );
    }

    /**
     * add options
     */
    function add_settings() {
        // Register options
        register_setting( 'ftotw-options', 'ftotw-options', array(&$this, 'validate_settings'));

        //Global Options section
        add_settings_section('ft_global_section', '', array(&$this, 'print_ft_global_section_text'), __FILE__);

        add_settings_field('twitter-feed', __('Twitter Feed', 'ftotw'), array(&$this, 'ft_pipes_url_callback'), __FILE__, 'ft_global_section');
        add_settings_field('time', __('First Time', 'ftotw'), array(&$this, 'ft_time_callback'), __FILE__, 'ft_global_section');
        add_settings_field('footer-text', __('Footer Text', 'ftotw'), array(&$this, 'ft_footer_text_callback'), __FILE__, 'ft_global_section');
        add_settings_field('category', __('Category', 'ftotw'), array(&$this, 'ft_category_callback'), __FILE__, 'ft_global_section');
        add_settings_field('tags', __('Tags', 'ftotw'), array(&$this, 'ft_tags_callback'), __FILE__, 'ft_global_section');
    }

    /**
     * Dipslay the Settings page
     */
    function settings_page() {
?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php _e( 'FTOTW Settings', 'ftotw' ); ?></h2>

            <form id="smer_form" method="post" action="options.php">
                <?php settings_fields('ftotw-options'); ?>
        		<?php do_settings_sections(__FILE__); ?>

                <p class="submit">
                    <input type="submit" name="ftotw-submit" class="button-primary" value="<?php _e('Save Changes', 'ftotw') ?>" />
                </p>
            </form>

<?php
            $this->add_manual_run_section();
?>
        </div>
<?php
    }

    /**
     * add custom time to cron
     *
     * @param <type> $param
     * @return <type>
     */
    function filter_cron_schedules( $param ) {
        return array( 'weekly' => array(
        'interval' => 604800, // seconds
        'display' => __( 'Weekly' )
        ) );
    }

    // ---------------------------Callback functions ----------------------------------------------------------

    /**
     * Validate the options entered by the user
     *
     * @param <type> $input
     * @return <type>
     */
    function validate_settings($input) {
        $input['twitter-feed'] = esc_url($input['twitter-feed'], array('http', 'https'));
        //TODO add validations for other fields

        if ($input['time'] != '') {
            if ( wp_next_scheduled( 'do_ftotw' ) ) {
                wp_clear_scheduled_hook('do_ftotw');
            }

            wp_schedule_event( time(), 'weekly', 'do_ftotw' );
        }

        // schedule the daily event as well
    	if ( !wp_next_scheduled( 'do_ftotw_fetch_tweets' ) ) {
        	wp_schedule_event(time(), 'daily', 'do_ftotw_fetch_tweets');
        }
        return $input;
    }

    /**
     * Print global section text
     */
    function  print_ft_global_section_text() {
        // Nothing... dummy callback
    }

    /**
     * Callback for pipes url
     */
    function ft_pipes_url_callback() {
        $options = get_option('ftotw-options');
        echo "<input id='twitter-feed' name='ftotw-options[twitter-feed]' size='50' type='text' value='{$options['twitter-feed']}' />";
    }

    /**
     * Callback for printing time
     */
    function ft_time_callback() {
        $options = get_option('ftotw-options');
        echo "<input id='time' name='ftotw-options[time]' size='50' type='text' value='{$options['time']}' />";
    }

    /**
     * Callback for Footer Text Setting
     */
    function ft_footer_text_callback() {
        $options = get_option('ftotw-options');
        echo "<textarea id='footer-text' name='ftotw-options[footer-text]' cols = '40'>{$options['footer-text']}</textarea>";
    }

    /**
     * Callback for category
     *
     */
    function ft_category_callback() {
        $options = get_option('ftotw-options');
        $categories = get_categories(array('hide_empty' => 0));
        echo "<select id='category' name='ftotw-options[category]'>";
        foreach($categories as $cat) {
            print_r($cat);
            echo "<option value='" . $cat->cat_ID . "' " . selected($cat->cat_ID, $options['category'], false) . " >$cat->name</option>";
        }
        echo "</select>";

    }

    /**
     * Callback for tags
     *
     */
    function ft_tags_callback() {
        $options = get_option('ftotw-options');
        echo "<input id='tags' name='ftotw-options[tags]' size='50' type='text' value='{$options['tags']}' />";
    }

    function add_manual_run_section() {
?>
        <h2>Manual Run</h2>

        <form method="POST" action="<?php echo get_bloginfo("wpurl"); ?>/wp-admin/options-general.php?page=ftotw">
            <input type="hidden" name="action" value="manual-run" >
            <input type="submit" value="Run Now">
        </form>
<?php
    }
 
    /**
     * Send error email
     * 
     * @param <type> $body
     * @param <type> $subject 
     */
    function send_email($message, $subject = 'FTOTW Failed', $to = '') {
        if ($to == '') {
             $to = get_option('admin_email');
        }
        wp_mail($to, $subject, $message);
    }

    // PHP4 compatibility
    function Ftotw() {
        $this->__construct();
    }
}

// Start this plugin once all other plugins are fully loaded
add_action( 'init', 'Ftotw' ); function Ftotw() { global $Ftotw; $Ftotw = new Ftotw(); }

// On install functions
function ftotw_on_install() {

   global $wpdb;
   global $ftotw_table_name;
   global $ftotw_tweet_table_name;
   global $ftotw_db_version;

   if($wpdb->get_var("show tables like '{$ftotw_table_name}'") != $ftotw_table_name) {

      $sql = "CREATE TABLE " . $ftotw_table_name . " (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          twitter_id VARCHAR(100) NOT NULL,
          wings mediumint(9) NOT NULL DEFAULT 0,
          added_on timestamp(14) NOT NULL,
          PRIMARY KEY  (id)
        );";

      require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
      dbDelta($sql);

      add_option("ftotw_db_version", $ftotw_db_version);
   }

   if($wpdb->get_var("show tables like '{$ftotw_tweet_table_name}'") != $ftotw_tweet_table_name) {

      $sql = "CREATE TABLE " . $ftotw_tweet_table_name . " (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          tweet_id bigint NOT NULL UNIQUE,
          twitter_id VARCHAR(100) NOT NULL,
          wings mediumint(9) NOT NULL DEFAULT 0,
          tweet TEXT NOT NULL,
          tweet_date timestamp(14) NOT NULL,
          PRIMARY KEY  (id)
        );";

      require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
      dbDelta($sql);
   }

    // schedule the daily event as well
    if ( !wp_next_scheduled( 'do_ftotw_fetch_tweets' ) ) {
        wp_schedule_event(time(), 'daily', 'do_ftotw_fetch_tweets');
    }
}

// When installed
register_activation_hook(__FILE__, 'ftotw_on_install');

// Helper classes

/**
 * Tweet Helper class
 */
class TweetHelper {
    public $tweet;

    function __construct ($new_tweet) {
        $this->tweet = $new_tweet;
    }

    function get_processed_content() {
        
        $tweet_content = $this->process_url($this->tweet->tweet);        
        $tweet_content = $this->expand_twitter_name($tweet_content);
        $tweet_content = $this->add_permalink($tweet_content, 'http://twitter.com/rsukumar/statuses/' . $this->tweet->tweet_id);

        return $tweet_content;
    }

    /**
     * Expand short urls and make them clickable
     *
     * @param <type> $content
     * @return <type>
     */
    function process_url($content) {
        $content = ' ' . $content;
        $content = preg_replace_callback('#(?<!=[\'"])(?<=[*\')+.,;:!&$\s>])(\()?([\w]+?://(?:[\w\\x80-\\xff\#%~/?@\[\]-]|[\'*(+.,;:!=&$](?![\b\)]|(\))?([\s]|$))|(?(1)\)(?![\s<.,;:]|$)|\)))+)#is', array(&$this, '_expand_url_and_make_url_clickable_cb'), $content);
        return trim($content);
    }

    /**
     * Callback to convert URI match to HTML A element.
     *
     * @access private
     *
     * @param array $matches Single Regex Match.
     * @return string HTML A element with URI address.
     */
    function _expand_url_and_make_url_clickable_cb($matches) {
        $url = $matches[2];
        $suffix = '';

        /** Include parentheses in the URL only if paired **/
        while ( substr_count( $url, '(' ) < substr_count( $url, ')' ) ) {
            $suffix = strrchr( $url, ')' ) . $suffix;
            $url = substr( $url, 0, strrpos( $url, ')' ) );
        }

//        $url = $this->expand_url(esc_url($url));
        if ( empty($url) )
            return $matches[0];

//        $ftotw_link_no = get_option('ftotw_link_no', 1);
//        update_option('ftotw_link_no', $ftotw_link_no + 1);
//        return $matches[1] . "<a href=\"$url\" >FTOTW Link $ftotw_link_no</a>" . $suffix;
          return $matches[1] . "<a href=\"$url\" >$url</a>" . $suffix;

    }

    /**
     * Get response location of a given URL
     *
     * @param <type> $url
     * @return <type>
     */
    function expand_url($url){
        //Get response headers
        $response = get_headers($url, 1);
        //Get the location property of the response header. If failure, show error
        $location = $response["Location"];
        if (isset($location)) {
            if (is_array($location)) {
                return $location[count($location) - 1];
            }
        }
        return $url;
    }

    /**
     * Expand Twitter usernames
     *
     * @param <type> $content
     * @return <type>
     */
    function expand_twitter_name($content) {
        $content = preg_replace("/@(\w+)/", "<a href=\"http://www.twitter.com/\\1\" target=\"_blank\">@\\1</a>", $content);
        return trim($content);
    }

    /**
     * Add permalink to Tweet
     *
     * @param <type> $content
     * @param <type> $permalink
     * @return <type>
     *
     */
    function add_permalink($content, $permalink) {
        $content = preg_replace("/(rsukumar: )/", " ", $content);
        $content .= " - <a href=\"$permalink\" target=\"_blank\">Original Tweet</a>";
        return trim($content);
    }
}

// Template Functions

/**
 * Template function to display the list of tweets
 *
 * @global <type> $wpdb
 * @global <type> $ftotw_tweet_table_name
 * @param <type> $wing
 */
function ftotw_get_timeline($wing = 0) {
    global $wpdb;
    global $ftotw_tweet_table_name;

    $timeline = '';

    if ($wing > 0 && $wing <= 4) {
        $tweets = $wpdb->get_results("SELECT * FROM $ftotw_tweet_table_name WHERE wings = $wing ORDER BY tweet_date DESC");
    } else {
        $tweets = $wpdb->get_results("SELECT * FROM $ftotw_tweet_table_name ORDER BY wings DESC, tweet_date DESC");
    }

    if ($tweets) {
        $timeline .= <<<EOD
        <table border = "1">
            <tr>
                <th>Author</th>
                <th>Tweet</th>
EOD;

        if ($wing > 0 && $wing <= 4) {
            // nothing
        } else {
            $timeline .= '<th>Wing</th>';
        }

        $timeline .= <<<EOD
                <th>Date</th>
            </tr>
EOD;
        
        foreach ($tweets as $tweet) {

            $timeline .= '<tr>';
            $timeline .= '<td>' . $tweet->twitter_id . '</td>';

            $helper = new TweetHelper($tweet);
            $tweet_content = $helper->get_processed_content();
            
            $timeline .= '<td>' . $tweet_content . '</td>';

            if ($wing > 0 && $wing <= 4) {
                // nothing
            } else {
                $timeline .= '<td>' . $tweet->wings . '</td>';
            }

            $timeline .= '<td>' . date('d-M-Y', strtotime($tweet->tweet_date)) . '</td>';
            $timeline .= '</tr>';
        }
        
        $timeline .= '</table>';
    }

    return $timeline;
}

/*
 * Get the ftotw river
 */
function ftotw_get_river($wing = 0, $month = 0, $year = 0) {

    global $wpdb;
    global $ftotw_tweet_table_name;

    $river = '';
    $date_condition = '';

    if ($wing == 0 && $month == 0 && $year == 0) {
        $month = date('m');
        $year  = date('Y');
    }    

    if ($month > 0 && $year > 0) {
        $date_condition = " MONTH(tweet_date) = $month AND YEAR(tweet_date) = $year ";
    }

    if ($wing > 0 && $wing <= 4) {
        $query = "SELECT * FROM $ftotw_tweet_table_name WHERE wings = $wing ";
        if ($date_condition != '') {
            $query .= ' AND ' . $date_condition;
        }
        $query .= " ORDER BY tweet_date DESC";
    } else {
        $query = "SELECT * FROM $ftotw_tweet_table_name " ; 
        if ($date_condition != '') {
            $query .= ' WHERE '. $date_condition;
        }
        $query .= " ORDER BY tweet_date DESC, wings DESC";
    }

    $tweets = $wpdb->get_results($query);
    if ($tweets) {
        $current_date = '';
        
        foreach ($tweets as $tweet) {
            $helper = new TweetHelper($tweet);
            $tweet_content = $helper->get_processed_content();

            if ($current_date != date('F-d, Y', strtotime($tweet->tweet_date))) {
                $current_date = date('F-d, Y', strtotime($tweet->tweet_date));
                $river .= '<h2>' . date('F-d, Y', strtotime($tweet->tweet_date)) . '</h2>';
            }

            $river .=  '<p>';

            $river .= date('G:i:s', strtotime($tweet->tweet_date));
            $river .= ' <a href = "http://twitter.com/' . $tweet->twitter_id . '">' . $tweet->twitter_id . '</a> ';
            $river .= ' ' . $tweet_content . ' ';
            
            $river .=  '</p>';
        }
    }

    return $river;

}
/**
 * Template function to display the leaderboard
 *
 * @global <type> $wpdb
 * @global <type> $ftotw_tweet_table_name
 * @param <type> $wing
 */
function ftotw_get_leaderboard() {
    global $wpdb;
    global $ftotw_table_name;

    $leaderboard = '';

    $tweeters = $wpdb->get_results("SELECT * FROM $ftotw_table_name ORDER BY wings DESC");

    if ($tweeters) {
        $leaderboard .= <<<EOD
        <table border = "1">
            <tr>
                <th>Author</th>
                <th>Wings</th>
            </tr>
EOD;

        foreach ($tweeters as $tweeter) {

            $leaderboard .= '<tr>';
            $leaderboard .= '<td><a href = "http://twitter.com/' . $tweeter->twitter_id . '">' . $tweeter->twitter_id . '</a></td>';
            $leaderboard .= '<td>' . $tweeter->wings . '</td>';
            $leaderboard .= '</tr>';
        }

        $leaderboard .= '</table>';
    }

    return $leaderboard;
}

function ftotw_get_table() {
		global $wpdb, $ftotw_tweet_table_name;

		$m = isset( $_GET['m'] ) ? (int) $_GET['m'] : date('Y') . date('m');
		$level = isset( $_GET['level'] ) ? (int) $_GET['level'] : 0;
		$twitter_id = isset( $_GET['contributor'] ) ? $_GET['contributor'] : "0";
?>
    <form action="" method="get" accept-charset="utf-8">
<?php            
            ftotw_print_date_options();
?>
        <select name="level" id="">
            <option value = "0" <?php echo selected($level, "0", false); ?> >All Level</option>
            <option value = "1" <?php echo selected($level, "1", false); ?> >Level 1</option>
            <option value = "2" <?php echo selected($level, "2", false); ?> >Level 2</option>
            <option value = "3" <?php echo selected($level, "3", false); ?> >Level 3</option>
            <option value = "4" <?php echo selected($level, "4", false); ?> >Level 4</option>
        </select>

        <select name="contributor">
            <option value = "0" <?php selected($twitter_id, "0"); ?>>All</option>
<?php
            ftotw_print_contributor_options($twitter_id);
?>
        </select>

        <button type="submit">Filter</button>
    </form>
<?php    
        $query_cond = "";

        if ($level > 0 ) {
            $query_cond = " AND wings = $level ";
        }

        if ($m != 0) {
            $year = substr($m, 0, 4);
            $month = substr($m, 4);
            $query_cond .= " AND YEAR (tweet_date) = $year AND MONTH(tweet_date) = $month ";
        }

        if ($twitter_id != "0") {
            $query_cond .= " AND twitter_id = '$twitter_id' ";
        }
        
        $tweets = $wpdb->get_results("SELECT * from $ftotw_tweet_table_name WHERE 1=1 $query_cond ORDER BY tweet_date DESC");

        $content = "[table ai='1' tablesorter='1']\n Tweet|Wings|Contributor|Date\n";
        foreach ($tweets as $tweet) {
            $helper = new TweetHelper($tweet);
            $tweet_content = $helper->get_processed_content();
            $tweet_content = str_replace("|", "[PIPE]", $tweet_content);
            $content .= trim(preg_replace('/\s+/', ' ', $tweet_content)) . '|' . $tweet->wings . '|' . $tweet->twitter_id . '|' . $tweet->tweet_date . "\n";
        }

        $content .= '[/table]';

        echo str_replace("[PIPE]", "|", do_shortcode($content));
}

function ftotw_print_date_options() {
		global $wpdb, $wp_locale, $ftotw_tweet_table_name;

		$months = $wpdb->get_results( "SELECT DISTINCT YEAR( tweet_date ) AS year, MONTH( tweet_date ) AS month
			FROM $ftotw_tweet_table_name
			ORDER BY tweet_date DESC" );

		$month_count = count( $months );

		if ( !$month_count || ( 1 == $month_count && 0 == $months[0]->month ) )
			return;

		$m = isset( $_GET['m'] ) ? (int) $_GET['m'] : date('Y') . date('m');
?>
		<select name='m'>
			<option<?php selected( $m, 0 ); ?> value='0'><?php _e( 'Show all dates' ); ?></option>
<?php
		foreach ( $months as $arc_row ) {
			if ( 0 == $arc_row->year )
				continue;

			$month = zeroise( $arc_row->month, 2 );
			$year = $arc_row->year;

			printf( "<option %s value='%s'>%s</option>\n",
				selected( $m, $year . $month, false ),
				esc_attr( $arc_row->year . $month ),
				$wp_locale->get_month( $month ) . " $year"
			);
		}
?>
		</select>
<?php
}

function ftotw_print_contributor_options($twitter_id) {
    global $wpdb, $ftotw_tweet_table_name;

    $contributors = $wpdb->get_results("SELECT DISTINCT(twitter_id) AS twitter_name FROM $ftotw_tweet_table_name ORDER BY twitter_id DESC ");
    foreach ($contributors as $contributor) {
        echo '<option value = "' . $contributor->twitter_name . '" ' . selected($twitter_id, $contributor->twitter_name, false) . '>' . $contributor->twitter_name . '</option>' ;
    }
}

/**
 * Get the last updated date for ftotw data
 */
function ftotw_get_last_updated_date() {
    global $wpdb;
    global $ftotw_tweet_table_name;

    $last_updated_on = $wpdb->get_var($wpdb->prepare("SELECT MAX(tweet_date) FROM $ftotw_tweet_table_name"));

    return date('d-M-Y G:i:s', strtotime($last_updated_on));
}

// get month from title
function ftotw_get_month_from_title($title) {
    $parts = explode(' ', $title);
    if (count($parts) != 3) {
        return 0;
    }

    return intval($parts[0]);

    //$month = $parts[count($parts) - 3];

    //return date('m', strtotime($month . '-2012')); // small hack which I got from http://stackoverflow.com/a/9941819/24949
}

// get year from title
function ftotw_get_year_from_title($title) {
    $parts = explode(' ', $title);
    if (count($parts) != 3) {
        return 0;
    }

    return intval($parts[2]);
}
?>
