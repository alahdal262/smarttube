# SmartTube - YouTube Playlist Gallery for WordPress

<p align="center">
  <img src="assets/banner.svg" alt="SmartTube Banner" width="100%"/>
</p>

<p align="center">
  <strong>Transform your WordPress site into a YouTube video hub.</strong><br>
  Display channel playlists in grids, tabbed layouts, and category-based video pages — with lightbox on desktop and Picture-in-Picture on mobile.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress" alt="WordPress"/>
  <img src="https://img.shields.io/badge/PHP-7.4%2B-purple?logo=php" alt="PHP"/>
  <img src="https://img.shields.io/badge/License-GPL--2.0-green" alt="License"/>
  <img src="https://img.shields.io/badge/Version-1.3.1-orange" alt="Version"/>
  <img src="https://img.shields.io/badge/RTL-Supported-teal" alt="RTL"/>
</p>

<p align="center">
  Built for <a href="https://yementv.tv">Yemen TV</a> and the Jannah theme, but works with any WordPress theme.
</p>

---

## Screenshots

### Category Page Override
YouTube videos replace WordPress posts on linked category pages — featured hero video + responsive grid.

<p align="center">
  <img src="screenshots/feature-category.svg" alt="Category Page Override" width="90%"/>
</p>

### Video Playback — Desktop Lightbox + Mobile PiP
Auto-detects device: lightbox popup on desktop, draggable floating PiP player on mobile.

<p align="center">
  <img src="screenshots/feature-playback.svg" alt="Playback Modes" width="90%"/>
</p>

### Admin — Playlist Manager & Category Linker
Fetch playlists from your YouTube channel, link to WordPress categories, set limits, bulk enable/disable.

<p align="center">
  <img src="screenshots/feature-admin.svg" alt="Admin Dashboard" width="90%"/>
</p>

### Post Cleanup Tool
Multi-category selection with batch deletion, progress bar, and stop button.

<p align="center">
  <img src="screenshots/feature-cleanup.svg" alt="Post Cleanup" width="90%"/>
</p>

---

## Features

### Video Display
- **Playlist Grid** — Display any YouTube playlist as a responsive 1-6 column grid
- **Tabbed Programs Layout** — Channel programs page with switchable tabs
- **Category Override** — Replace WordPress post loops with YouTube videos on category pages
- **Widget** — Sidebar widget for latest videos

### Playback Modes
- **Lightbox** — Click-to-play popup on desktop with dark overlay and ESC to close
- **PiP (Picture-in-Picture)** — Draggable floating video player on mobile
- **Inline** — Replace thumbnail with embedded player in-place
- **New Tab** — Open video on YouTube

### Admin Dashboard
- **Playlist Manager** — Fetch all playlists from your YouTube channel with one click
- **Category Linker** — Link playlists to WordPress categories with per-playlist video limits
- **Tabs Builder** — Visual shortcode generator for tabbed layouts
- **Bulk Actions** — Enable/disable all, set limits in bulk, create categories automatically
- **Exclude System** — Mark categories that should always show posts, never videos
- **Filter Tabs** — View all/linked/unlinked playlists with search

### Post Cleanup Tool
- **Multi-Category Selection** — Select multiple categories with search and select all/none
- **Preview Posts** — See all posts with thumbnails before deleting
- **Batch Deletion** — Deletes posts + featured images in safe batches of 5 (avoids server WAF 403 blocks)
- **Progress Bar** — Real-time progress with percentage and stop button
- **Auto-retry** — Retries failed batches automatically with cooldown

### Customization
- **CSS Variables** — `--stube-bg`, `--stube-header`, `--stube-accent`
- **Color Picker** — Set background, header, and accent colors from admin
- **RTL Support** — Full right-to-left layout for Arabic, Persian, etc.
- **Multi-language** — Works with Arabic, French, Persian, English sites

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[smarttube playlist="PLxxx" limit="12" columns="3"]` | Display a specific playlist grid |
| `[smarttube latest="10" title="Latest"]` | Show latest channel videos |
| `[smarttube_tabs auto="true" header="Programs"]` | Tabbed programs layout with all playlists |
| `[smarttube_tabs playlists="ID1:Name,ID2:Name"]` | Tabbed layout with specific playlists |
| `[smarttube_category]` | Auto-detect current category and show linked playlist |
| `[smarttube_programs auto="true" columns="3"]` | Programs grid with thumbnails |

---

## Installation

1. Upload the `smarttube` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress
3. Go to **SmartTube** in the admin sidebar
4. Enter your **YouTube API Key** and **Channel ID**
5. Click **Fetch Playlists**
6. Link playlists to categories and set video limits
7. Enable categories to show videos instead of posts

### Requirements

- WordPress 5.0+
- PHP 7.4+
- YouTube Data API v3 key ([Get one here](https://console.cloud.google.com/apis/library/youtube.googleapis.com))

---

## File Structure

```
smarttube/
  smarttube.php                    # Main plugin file (v1.3.1)
  includes/
    class-youtube-api.php          # YouTube Data API wrapper with transient caching
    class-shortcode.php            # All shortcodes: grid, tabs, programs, category
    class-category-override.php    # Hooks into Jannah theme to replace post loops
    class-admin.php                # Admin: settings, playlist manager, category linker
    class-cleanup.php              # Post cleanup tool (separate admin page)
    class-widget.php               # Sidebar widget for latest videos
  assets/
    css/
      admin.css                    # Admin dashboard styles
      frontend.css                 # Frontend styles with CSS variables + PiP
    js/
      admin.js                     # Admin: save, fetch, bulk actions, tabs builder
      frontend.js                  # Lightbox, PiP player, tab switching
      cleanup.js                   # Batch deletion with progress bar
  screenshots/                     # README images
  languages/                       # Translation-ready
```

---

## How It Works

### Category Override Flow
1. Plugin hooks into `template_redirect` on category pages
2. Checks if the category has a linked + enabled playlist
3. Empties the main WP query (no posts shown)
4. Hooks into `TieLabs/after_archive_title` (Jannah theme)
5. Renders featured hero video + grid from YouTube API
6. Caches API responses as WordPress transients

### Playback Detection
```
User clicks video thumbnail
       |
  Is mobile device?
  /           \
YES            NO
 |              |
Open PiP      Open Lightbox
(draggable,   (fullscreen overlay,
 floating)     ESC to close)
```

---

## Theme Compatibility

| Theme | Support | Notes |
|-------|---------|-------|
| **Jannah** (TieLabs) | Full | Hooks into `TieLabs/after_archive_title` |
| **GeneratePress** | Grid + Tabs | Category override needs manual shortcode |
| **Astra** | Grid + Tabs | Category override needs manual shortcode |
| Any theme | Grid + Tabs | Use shortcodes in pages/widgets |

---

## Changelog

### 1.3.1
- Added PiP (Picture-in-Picture) draggable popup for mobile playback
- Added Post Cleanup tool with multi-category selection and batch deletion
- Fixed video limit not respected (cache key now includes limit)
- Fixed lightbox not working on category override pages
- Added progress bar with stop button for cleanup
- Added auto-retry for failed deletion batches

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
