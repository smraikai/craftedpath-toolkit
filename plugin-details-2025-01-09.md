# CraftedPath Toolkit Plugin Details - 2025-01-09

## Plugin Overview

**CraftedPath Toolkit** is a comprehensive WordPress plugin that enhances the admin experience with AI-powered tools and modern UI improvements. The plugin follows a modular architecture with feature-based organization.

**Version**: 1.0.0  
**Text Domain**: craftedpath-toolkit  
**Main File**: craftedpath-toolkit.php  
**License**: GPL v2 or later  

## Core Architecture

### Main Plugin Class
- **File**: `craftedpath-toolkit.php`
- **Class**: `CraftedPath_Toolkit` (singleton pattern)
- **Constants**: 
  - `CPT_PLUGIN_DIR` - Plugin directory path
  - `CPT_PLUGIN_URL` - Plugin URL
  - `CPT_VERSION` - Current version (1.0.0)

### Settings Management
- **Class**: `CPT_Settings_Manager` (singleton)
- **File**: `includes/admin/class-settings-manager.php`
- **Option Name**: `craftedpath_toolkit_settings`
- **Features registered in**: `register_features()` method

## Feature Categories

### AI Tools (Section: "AI Tools")
1. **Page Structure Generator** (`ai_page_generator`)
   - Class: `CPT_AI_Page_Generator`
   - File: `includes/features/ai-page-generator/class-cpt-ai-page-generator.php`
   - Default: Enabled
   - Generates hierarchical page structures using AI

2. **Menu Generator** (`ai_menu_generator`)
   - Class: `CPT_AI_Menu_Generator`
   - File: `includes/features/ai-menu-generator/class-cpt-ai-menu-generator.php`
   - Default: Enabled
   - Creates navigation menus based on page structure

3. **Auto Categorize Posts** (`ai_auto_categorize`)
   - Class: `CPT_AI_Auto_Categorize`
   - File: `includes/features/ai-auto-categorize/class-cpt-ai-auto-categorize.php`
   - Default: Enabled
   - Automatically categorizes posts using AI

4. **Auto Tag Posts** (`ai_auto_tag`)
   - Class: `CPT_AI_Auto_Tag`
   - File: `includes/features/ai-auto-tag/class-cpt-ai-auto-tag.php`
   - Default: Enabled
   - Generates relevant tags for posts

5. **Alt Text Generation** (`ai_alt_text`)
   - Class: `CPT_AI_Alt_Text`
   - File: `includes/features/ai-alt-text/class-cpt-ai-alt-text.php`
   - Default: Disabled
   - Creates accessibility-friendly image descriptions

### UI Enhancements (Section: "UI Enhancements")
1. **Admin Menu Order** (`admin_menu_order`)
   - Class: `CPT_Admin_Menu_Order`
   - File: `includes/features/admin-menu-order/class-cpt-admin-menu-order.php`
   - Default: Enabled
   - Drag-and-drop menu reordering with AI sorting

2. **Admin Quick Search** (`admin_quick_search`)
   - Class: `CPT_Admin_Quick_Search`
   - File: `includes/features/admin-quick-search/class-cpt-admin-quick-search.php`
   - Default: Enabled
   - Command palette (Cmd/Ctrl+K) for admin navigation

3. **Admin Refresh UI** (`admin_refresh_ui`)
   - Class: `CPT_Admin_Refresh_UI`
   - File: `includes/features/admin-refresh-ui/class-admin-refresh-ui.php`
   - Default: Disabled
   - Experimental WordPress admin styling refresh

4. **Disable Comments** (`disable_comments`)
   - Class: `CPT_Disable_Comments`
   - File: `includes/features/disable-comments/class-cpt-disable-comments.php`
   - Default: Disabled
   - Completely disables comment functionality

### Developer Tools (Section: "Developer Tools")
1. **Wireframe Mode** (`wireframe_mode`)
   - No specific class (CSS-only)
   - File: `assets/css/wireframe-mode.css`
   - Default: Disabled
   - Strips visual styling for structural view

2. **Bricks Colors** (`bricks_colors`)
   - Class: `CPT_Bricks_Colors`
   - File: `includes/features/bricks-colors/class-cpt-bricks-colors.php`
   - Default: Disabled
   - Visual editor for Bricks theme color variables

### SEO Tools (Section: "SEO Tools")
1. **SEO Title & Meta** (`seo_tools`)
   - Namespace: `\CraftedPath\Toolkit\SEO\`
   - File: `includes/features/seo/seo.php`
   - Default: Enabled
   - Meta descriptions and social media integration

### Custom Post Types (Section: "Custom Post Types")
**Requires Meta Box Plugin** (`function_exists('rwmb_meta')`)

1. **Testimonials** (`cpt_testimonials`)
   - Class: `CPT_Testimonials`
   - File: `includes/features/custom-post-types/class-cpt-testimonials.php`

2. **FAQs** (`cpt_faqs`)
   - Class: `CPT_FAQs`
   - File: `includes/features/custom-post-types/class-cpt-faqs.php`

3. **Staff** (`cpt_staff`)
   - Class: `CPT_Staff`
   - File: `includes/features/custom-post-types/class-cpt-staff.php`

4. **Events** (`cpt_events`)
   - Class: `CPT_Events`
   - File: `includes/features/custom-post-types/class-cpt-events.php`

## Admin Interface

### Menu Structure
- **Main Menu**: "CraftedPath" (toplevel_page_craftedpath-toolkit)
- **Submenus**:
  - Features (default page)
  - Menu Order (if enabled)
  - SEO (if enabled)
  - Page Generator (if class exists)
  - Menu Generator (if class exists)
  - Bricks Colors (if enabled)
  - Settings (always last)

### Content Menu (if CPTs enabled)
- **Menu**: "Content" (cptk-content-menu)
- **Position**: 26 (after Comments)
- **Icon**: dashicons-archive
- **Capability**: edit_posts

## Asset Management

### CSS Files
- `assets/css/variables.css` - Global CSS variables
- `assets/css/toast.css` - Toast notification styles
- `assets/css/wireframe-mode.css` - Wireframe mode styles
- `assets/css/admin-quick-search.css` - Quick search styles
- `includes/admin/css/settings.css` - Admin settings styles

### JavaScript Files
- `assets/js/toast.js` - Toast notification system
- `assets/js/wireframe-mode.js` - Wireframe mode functionality
- `assets/js/admin-quick-search.js` - Quick search functionality
- `includes/admin/js/settings.js` - Admin settings JavaScript
- `includes/admin/js/tooltips.js` - Tooltip system

### External Dependencies
- **Iconoir CSS**: https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css
- **Floating UI**: https://unpkg.com/@floating-ui/core@1/ and @floating-ui/dom@1/
- **Fuse.js**: https://cdn.jsdelivr.net/npm/fuse.js@6.6.2/dist/fuse.min.js
- **Sortable.js**: Local vendor file at `assets/js/vendor/Sortable.min.js`

## Database Structure

### Options Table Entries
- `craftedpath_toolkit_settings` - Main plugin settings
- `cptk_options` - General settings (API keys, etc.)
- `craftedpath_seo_settings` - SEO feature settings
- `cpt_admin_menu_order` - Admin menu order settings
- `cptk_disable_comments_processed` - Comment disable status

### WordPress Integration
- **Hooks**: Extensive use of WordPress actions and filters
- **Capabilities**: Proper permission checking throughout
- **AJAX**: WordPress AJAX system with nonce verification
- **REST API**: Custom endpoints for AI functionality

## Build System

### NPM Configuration
- **Package**: `package.json` with @wordpress/scripts
- **Scripts**: `build`, `start` (WordPress Scripts)
- **Build Output**: `build/` directory
- **Source**: `src/` directory for React components

### React/Gutenberg Integration
- **Main File**: `src/index.js`
- **Components**: SEO panel, AI tools sidebar
- **Dependencies**: WordPress core packages (@wordpress/*)

## Security Considerations

### Current Implementation
- ✅ Nonce verification for AJAX requests
- ✅ Capability checks for admin functions
- ✅ Input sanitization with WordPress functions
- ✅ Direct access protection on all PHP files

### Known Issues (Requires Attention)
- ⚠️ SQL injection risk in disable comments feature
- ⚠️ API keys stored in plaintext
- ⚠️ Missing rate limiting on AI features
- ⚠️ Insufficient output escaping in some areas

## API Integration

### OpenAI API
- **Endpoint**: https://api.openai.com/v1/chat/completions
- **Default Model**: gpt-4o
- **Supported Models**: gpt-4o, gpt-4o-mini, gpt-3.5-turbo
- **Authentication**: Bearer token
- **Usage**: All AI-powered features

### WordPress REST API
- **Namespace**: craftedpath-toolkit/v1
- **Endpoints**: Various for AI functionality
- **Authentication**: WordPress nonce system

## Development Notes

### Adding New Features
1. Create feature class in `includes/features/[feature-name]/`
2. Register feature in `CPT_Settings_Manager::register_features()`
3. Add conditional loading in `CraftedPath_Toolkit::load_features()`
4. Follow singleton pattern for main feature classes

### Modifying Existing Features
- Features are conditionally loaded based on settings
- Each feature is self-contained in its directory
- UI components are reusable via `includes/admin/views/ui-components.php`

### Testing Considerations
- Features can be individually enabled/disabled
- Meta Box dependency for CPT features
- OpenAI API key required for AI features
- Admin capabilities required for most functionality

## Recent Changes (Based on Git History)
- Added Disable Comments feature for complete comment disabling
- Removed unnecessary feature initialization for performance
- Cleaned up debugging information from Admin Menu Order
- Refactored AI auto-sorting with improved AJAX handling
- Enhanced admin menu organization with intelligent spacer placement

## Performance Notes
- Conditional asset loading based on admin pages
- Singleton pattern prevents multiple instantiations
- CDN usage for external libraries
- Potential optimization needed for repeated database queries

## Documentation Maintenance

**IMPORTANT**: When making changes to this plugin, create a new documentation file with the updated date (e.g., `plugin-details-2025-01-10.md`) to maintain an accurate record of the plugin's current state. This ensures developers always have up-to-date context for editing or adding features.

This documentation serves as a comprehensive reference for understanding, modifying, and extending the CraftedPath Toolkit plugin.