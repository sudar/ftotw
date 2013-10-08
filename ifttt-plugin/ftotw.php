<?php
/**
 * FTOTW Plugin
 */
class FtotwPlugin extends Plugin {
    
    public function execute($object) {

        __log("Processed in ftotw.php");
        __log("Object:" . print_r($object, true));
        
        $password = $object->password;
        $title = $object->title;
        $description = $object->description;

        __log("Content: " . file_get_contents("http://www.sastwingees.org/wordpress/wp-content/plugins/ftofw/ifttt.php?title=" . urlencode($title) . "&description=" . urlencode($description) . "&password=" . $password));
        
        return $object;
    }
}
