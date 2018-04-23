<?php
/*
Plugin Name: Algolia Multisite Enabler
Description: Mu-plugin to enable Algolia plugin to mix-content from multiple WordPress sites in one index.
Version: 0.1.0
Author: Sebastian Thulin
Author URI: http://sebastianthulin.se

WARNING: You should see this plugin as a Proof-of-concept prototype. Do not deploy in
production if you aren't aware of the consequences. This plugin will break your existing
index. It is strongly recommended to create a new clean index before enabling this plugin.
Some functionality is not tested thorougly.

Only posts has been tested in this version of the plugin. Taxonomies/terms, users and metadata
may not be stored as intended.

*/

class AlgoliaMultisiteEnabler
{

    protected $prefix = null;

    function __construct()
    {
        add_action('init', array($this, 'init'), 2);
    }

    /**
     * Init plugin
     * @return [void]
     */
    public function init() {
        //Create a unique prefix
        global $wpdb;
        $this->prefix = preg_replace("/_/", "", DB_NAME . "-" . $wpdb->prefix);

        //Run hooks
        add_filter('algolia_clear_index_if_existing', array($this, 'disableClearIndexOnReindex')); //Disables clear index on re-index
        add_filter('algolia_get_post_object_id', array($this, 'filterPostObjectId'), 10, 3); //Translate post id's to a multisite-unique id
        add_filter('algolia_searchable_post_records', array($this, 'filterSearchablePostRecords'));
    }

    /**
     * Filters the post record object & post id to be ms unique
     * @param  [array] $records Site uniqiue collection of post objects.
     * @return [array]          Multisite unique collection of post objects to be updated.
     */
    public function filterSearchablePostRecords($records) : array {
        if (is_array($records) && !empty($records)) {
            foreach ($records as &$record) {
                if (isset($record['objectID'])) {

                    if(!preg_match("/".$this->prefix."/i", $record['post_id'])) {
                        if (is_multisite() && $cBlogId = get_current_blog_id()) {
                            $record['post_id']  = implode("-", array($this->prefix, $cBlogId, $record['post_id']));
                        } else {
                            $record['post_id']  = implode("-", array($this->prefix, $record['post_id']));
                        }
                    }

                    if(!preg_match("/".$this->prefix."/i", $record['objectID'])) {
                        if (is_multisite() && $cBlogId = get_current_blog_id()) {
                            $record['objectID'] = implode("-", array($this->prefix, $cBlogId, $record['objectID']));
                        } else {
                            $record['objectID'] = implode("-", array($this->prefix, $record['objectID']));
                        }
                    }
                }
            }
        }
        return $records;
    }

    /**
     * Filter to make post obejct uniqie.
     * @param  [string] $input        Original identifier (uid)
     * @param  [int]    $post_id      WordPress post id.
     * @param  [int]    $record_index Index of the record (used when splitting a item into many)
     * @return [string]               Input string as a uuid
     */
    public function filterPostObjectId($input, $post_id, $record_index) : string {
        if(preg_match("/".$this->prefix."/i", $input)) {
            return $input;
        }
        if (is_multisite() && $cBlogId = get_current_blog_id()) {
            return implode("-", array($this->prefix, $cBlogId, $input));
        }
        return $this->prefix . "-" . $input;
    }

    /**
     * Disable clear index on re-index action (maintains exising data in index, no removal of old data)
     * @param  [bool] $state Default state (true)
     * @return [bool]        State false, disables clear.
     */
    public function disableClearIndexOnReindex($state) : bool {
        return false;
    }
}

new AlgoliaMultisiteEnabler();
