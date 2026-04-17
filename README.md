# SmartTube - YouTube Playlist Gallery for WordPress

A powerful WordPress plugin that transforms your site into a YouTube video hub. Display channel playlists in beautiful grids, tabbed layouts, and category-based video pages — with lightbox on desktop and Picture-in-Picture on mobile.

Built for [Yemen TV](https://yementv.tv) and the Jannah theme, but works with any WordPress theme.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php)
![License](https://img.shields.io/badge/License-GPL--2.0-green)
![Version](https://img.shields.io/badge/Version-1.3.1-orange)

---

## Features

### Video Display
- **Playlist Grid** — Display any YouTube playlist as a responsive video grid
- **Tabbed Programs Layout** — Channel programs page with switchable tabs (Yemen TV style)
- **Category Override** — Replace WordPress post loops with YouTube videos on category pages
- **Responsive Design** — 1-6 column grids, mobile-friendly

### Playback
- **Lightbox** — Click-to-play popup on desktop with dark overlay
- **PiP (Picture-in-Picture)** — Draggable floating video player on mobile
- **Inline** — Replace thumbnail with embedded player
- **New Tab** — Open on YouTube

### Admin Dashboard
- **Playlist Manager** — Fetch all playlists from your YouTube channel
- **Category Linker** — Link playlists to WordPress categories with per-playlist video limits
- **Tabs Builder** — Visual shortcode generator for tabbed layouts
- **Bulk Actions** — Enable/disable all, set limits in bulk, create categories automatically
- **Exclude System** — Mark categories that should always show posts, never videos

### Post Cleanup Tool
- **Multi-Category Selection** — Select multiple categories at once
- **Preview Posts** — See all posts with thumbnails before deleting
- **Batch Deletion** — Deletes posts + featured images in safe batches of 5 (avoids server WAF blocks)
- **Progress Bar** — Real-time progress with stop button
- **Auto-retry** — Retries failed batches automatically

### Customization
- **CSS Variables** — `--stube-bg`, `--stube-header`, `--stube-accent` for easy theming
- **Color Picker** — Set background, header, and accent colors from admin
- **RTL Support** — Full right-to-left layout for Arabic, Persian, etc.
- **Multi-language** — Works with Arabic, French, Persian, English sites

---

## Shortcodes

### Basic Playlist Grid
```
[smarttube playlist="PLxxxxxxx" limit="12" columns="3" title="Latest Videos"]
```

### Latest Channel Videos
```
[smarttube latest="10" title="Latest Videos"]
```

### Tabbed Programs Layout
```
[smarttube_tabs auto="true" limit="15" header="Channel Programs"]
```

### Category Auto-Detect
```
[smarttube_category]
```
Place in category description or widget — auto-detects the current category and shows linked playlist videos.

### Programs Grid
```
[smarttube_programs auto="true" limit="12" columns="3"]
```

---

## Installation

1. Upload the `smarttube` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress
3. Go to **SmartTube** in the admin menu
4. Enter your **YouTube API Key** and **Channel ID**
5. Click **Fetch Playlists**
6. Link playlists to categories and configure display settings

---

## Requirements

- WordPress 5.0+
- PHP 7.4+
- YouTube Data API v3 key ([Get one here](https://console.cloud.google.com/apis/library/youtube.googleapis.com))

---

## File Structure

```
smarttube/
  smarttube.php                    # Main plugin file
  includes/
    class-youtube-api.php          # YouTube Data API wrapper with caching
    class-shortcode.php            # All shortcodes rendering
    class-category-override.php    # Hooks into theme to replace post loops
    class-admin.php                # Admin settings, playlist manager, category linker
    class-cleanup.php              # Post cleanup tool (separate admin page)
    class-widget.php               # Sidebar widget
  assets/
    css/
      admin.css                    # Admin styles
      frontend.css                 # Frontend styles with CSS variables
    js/
      admin.js                     # Admin interactions (save, fetch, bulk actions)
      frontend.js                  # Lightbox, PiP, tab switching
      cleanup.js                   # Batch deletion with progress bar
  languages/                       # Translation-ready
```

---

## Screenshots

### Category Page Override
Videos replace posts on linked category pages with featured video hero + grid layout.

### Admin - Playlist Manager
Fetch playlists, link to categories, set limits, enable/disable per-playlist.

### Admin - Post Cleanup
Multi-category selection with batch deletion and progress bar.

### Mobile PiP Player
Draggable floating video player on mobile devices.

---

## Theme Compatibility

Tested with:
- **Jannah** by TieLabs (primary) — hooks into `TieLabs/after_archive_title`
- Works with any theme using standard WordPress category templates

---

## Changelog

### 1.3.1
- Added PiP (Picture-in-Picture) popup for mobile video playback
- Added Post Cleanup tool with multi-category selection and batch deletion
- Fixed video limit not respected (cache key now includes limit)
- Fixed lightbox not working on category override pages
- Added "Include posts without images" option to cleanup

### 1.3.0
- Category override system for Jannah theme
- Excluded categories feature
- Bulk enable/disable and limit actions
- Auto-create categories for unlinked playlists

### 1.2.0
- Tabbed programs layout
- Visual tabs builder with shortcode generator
- Category linking with per-playlist limits

### 1.1.0
- Playlist fetcher from YouTube channel
- Standard grid shortcode
- Lightbox and inline play modes

### 1.0.0
- Initial release

---

## License

GPL-2.0-or-later

## Author

**Yemen TV Media** — [yementv.tv](https://yementv.tv)
