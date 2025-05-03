<?php
/**
 * CPT Admin Quick Search Feature
 */

if (!defined('WPINC')) {
    die;
}

class CPT_Admin_Quick_Search
{
    private static $instance = null;
    private $searchable_items = []; // Renamed from menu_items

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_footer', array($this, 'add_modal_html'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_trigger'), 999);
    }

    public function enqueue_scripts()
    {
        // Only enqueue in the admin area
        if (!is_admin()) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'cpt-admin-quick-search-style',
            CPT_PLUGIN_URL . 'assets/css/admin-quick-search.css',
            array(),
            CPT_VERSION
        );

        // Enqueue Fuse.js from CDN (ensure it loads before our script)
        wp_enqueue_script(
            'fuse-js',
            'https://cdn.jsdelivr.net/npm/fuse.js@6.6.2/dist/fuse.min.js', // Use a specific version
            array(), // No WP dependencies
            '6.6.2',
            true // Load in footer
        );

        // Enqueue our JS (now depends on fuse-js)
        wp_enqueue_script(
            'cpt-admin-quick-search-script',
            CPT_PLUGIN_URL . 'assets/js/admin-quick-search.js',
            array('fuse-js'), // Add fuse-js as a dependency
            CPT_VERSION,
            true // Load in footer
        );

        // Prepare and Localize Searchable Items
        $this->prepare_searchable_items();
        wp_localize_script(
            'cpt-admin-quick-search-script',
            'cptAdminSearchItems', // Renamed localized variable
            $this->searchable_items
        );
    }

    private function prepare_searchable_items()
    {
        global $menu, $submenu;
        $this->searchable_items = [];
        $current_user = wp_get_current_user();

        // Function to clean up menu titles (remove spans like update counts)
        $clean_title = function ($title) {
            return trim(preg_replace('/<span.*<\/span>/is', '', $title));
        };

        // --- 1. Add Admin Menu Items ---
        foreach ($menu as $menu_item) {
            // Skip separators and items without titles or slugs
            if ($menu_item[4] === 'wp-menu-separator' || empty($menu_item[0]) || empty($menu_item[2])) {
                continue;
            }
            $slug = $menu_item[2];
            $title = $clean_title($menu_item[0]);
            $cap = $menu_item[1];
            $icon = $menu_item[6] ?? 'dashicons-admin-generic'; // Get icon or default

            // Check capability
            if (!current_user_can($cap)) {
                continue;
            }

            $url = menu_page_url($slug, false);
            if (!empty($title) && !empty($url)) {
                $this->searchable_items[] = [
                    'id' => 'menu-' . sanitize_key($slug),
                    'title' => $title,
                    'url' => esc_url($url),
                    'parent' => '',
                    'type' => 'menu',
                    'icon' => strpos($icon, 'dashicons-') === 0 ? $icon : 'dashicons-admin-generic' // Ensure it's a dashicon
                ];
            }

            // Process submenu items
            if (isset($submenu[$slug])) {
                foreach ($submenu[$slug] as $sub_item) {
                    // Skip items without titles or slugs
                    if (empty($sub_item[0]) || empty($sub_item[2])) {
                        continue;
                    }
                    $sub_title = $clean_title($sub_item[0]);
                    $sub_slug = $sub_item[2];
                    $sub_cap = $sub_item[1];

                    // Check capability
                    if (!current_user_can($sub_cap)) {
                        continue;
                    }

                    $sub_url = menu_page_url($sub_slug, false); // Try direct function first

                    // Fallback URL generation if menu_page_url fails
                    if (!$sub_url || $sub_url === admin_url()) {
                        if (strpos($sub_slug, 'http') === 0) { // It's already a full URL
                            $sub_url = $sub_slug;
                        } elseif (strpos($slug, '.php') !== false) { // Parent is a .php file
                            $sub_url = admin_url(add_query_arg('page', $sub_slug, $slug));
                        } else { // Parent is likely a plugin page slug
                            $sub_url = admin_url(add_query_arg('page', $sub_slug, 'admin.php'));
                        }
                    }

                    // Special handling for Customizer links
                    if (strpos($sub_url, 'customize.php') !== false) {
                        $current_url = admin_url(); // Or get current admin page URL if possible
                        $sub_url = add_query_arg('return', urlencode($current_url), $sub_url);
                    }

                    if (!empty($sub_title) && !empty($sub_url)) {
                        $this->searchable_items[] = [
                            'id' => 'menu-' . sanitize_key($slug) . '-' . sanitize_key($sub_slug),
                            'title' => $sub_title,
                            'url' => esc_url($sub_url),
                            'parent' => $title,
                            'type' => 'menu',
                            'icon' => $icon // Inherit parent icon for submenus
                        ];
                    }
                }
            }
        }

        // --- 2. Add Quick Actions ---
        if (current_user_can('edit_posts')) {
            $this->searchable_items[] = [
                'id' => 'action-add-post',
                'title' => 'Add New Post',
                'url' => admin_url('post-new.php'),
                'parent' => 'Quick Action',
                'type' => 'action',
                'icon' => 'dashicons-plus-alt'
            ];
        }
        if (current_user_can('edit_pages')) {
            $this->searchable_items[] = [
                'id' => 'action-add-page',
                'title' => 'Add New Page',
                'url' => admin_url('post-new.php?post_type=page'),
                'parent' => 'Quick Action',
                'type' => 'action',
                'icon' => 'dashicons-plus-alt'
            ];
        }
        $this->searchable_items[] = [
            'id' => 'action-view-site',
            'title' => 'View Site',
            'url' => home_url('/'),
            'parent' => 'Quick Action',
            'type' => 'action',
            'icon' => 'dashicons-admin-site-alt3',
            'target' => '_blank' // Add target blank for view site
        ];
        $this->searchable_items[] = [
            'id' => 'action-logout',
            'title' => 'Log Out',
            'url' => wp_logout_url(),
            'parent' => 'Quick Action',
            'type' => 'action',
            'icon' => 'dashicons-exit'
        ];

        // --- 3. Add Recent Posts (Limit for performance) ---
        if (current_user_can('edit_posts')) {
            $recent_posts = wp_get_recent_posts(array(
                'numberposts' => 15, // Adjust number as needed
                'post_status' => 'publish,draft,pending,private,future'
            ), OBJECT);
            foreach ($recent_posts as $post) {
                $this->searchable_items[] = [
                    'id' => 'post-' . $post->ID,
                    'title' => $post->post_title ? $post->post_title : ' (no title)',
                    'url' => get_edit_post_link($post->ID),
                    'parent' => 'Post',
                    'type' => 'post',
                    'icon' => 'dashicons-admin-post'
                ];
            }
        }

        // --- 4. Add Recent Pages (Limit for performance) ---
        if (current_user_can('edit_pages')) {
            $recent_pages = get_posts(array(
                'numberposts' => 15, // Adjust number
                'post_type' => 'page',
                'post_status' => 'publish,draft,pending,private,future'
            ));
            foreach ($recent_pages as $page) {
                $this->searchable_items[] = [
                    'id' => 'page-' . $page->ID,
                    'title' => $page->post_title ? $page->post_title : ' (no title)',
                    'url' => get_edit_post_link($page->ID),
                    'parent' => 'Page',
                    'type' => 'page',
                    'icon' => 'dashicons-admin-page'
                ];
            }
        }

        // --- 5. Add Users (Limit for performance/security) ---
        if (current_user_can('list_users')) {
            $users = get_users(array(
                'number' => 20, // Limit number of users
                'orderby' => 'display_name',
                // 'role__in' => [ 'administrator', 'editor' ], // Optionally limit roles
            ));
            foreach ($users as $user) {
                if (current_user_can('edit_user', $user->ID)) { // Check edit capability for this specific user
                    $this->searchable_items[] = [
                        'id' => 'user-' . $user->ID,
                        'title' => $user->display_name,
                        'url' => get_edit_user_link($user->ID),
                        'parent' => 'User (' . $user->user_login . ')',
                        'type' => 'user',
                        'icon' => 'dashicons-admin-users'
                    ];
                }
            }
        }

        // Sort alphabetically by combined title (Parent > Child or just Child)
        // usort($this->searchable_items, function ($a, $b) {
        //     $a_full_title = $a['parent'] ? $a['parent'] . ' > ' . $a['title'] : $a['title'];
        //     $b_full_title = $b['parent'] ? $b['parent'] . ' > ' . $b['title'] : $b['title'];
        //     return strcasecmp($a_full_title, $b_full_title); // Use case-insensitive compare
        // });
        // Sorting might not be ideal when mixing types, let JS handle filtering/sorting
    }

    public function add_admin_bar_trigger(&$wp_admin_bar)
    {
        $wp_admin_bar->add_node(array(
            'id' => 'cpt-admin-quick-search-trigger',
            'title' => '<span class="ab-icon"></span><span class="ab-label">Search / Actions</span>', // Updated Label
            'href' => '#',
            'meta' => array(
                'title' => 'Quick Search & Actions (Cmd/Ctrl+K)', // Updated Title
            )
        ));
    }

    public function add_modal_html()
    {
        ?>
        <div class="cpt-admin-search-overlay" style="display: none;"></div>
        <div id="cpt-admin-search-modal" style="display: none;">
            <div class="cpt-admin-search-header">
                <input type="text" id="cpt-admin-search-input" placeholder="Search menus, content, users, or actions...">
                <?php // Updated Placeholder ?>
                <button class="cpt-admin-search-close" title="Close (Esc)">&times;</button>
            </div>
            <ul id="cpt-admin-search-results">
                <!-- Results will be populated by JS -->
            </ul>
        </div>
        <?php
    }
}