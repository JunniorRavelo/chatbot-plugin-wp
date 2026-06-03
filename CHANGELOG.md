# Changelog

## Unreleased

### Added

- **Google IA** provider (`google_ia`): use your own Google Gemini API key with **primary** and **fallback** models. Model IDs are taken from the WordPress Connectors catalog (same Gemini names as **Settings → Connectors**); requests call the Google Generative Language API directly.
- Admin **AI Model** tab: API key field, primary/fallback model pickers, and `MULTCH_GEMINI_API_KEY` / `MULTCH_PROVIDER` wp-config overrides for Google IA.

### Documentation

- README, `readme.txt`, `docs/env.example`, and new `docs/AI-PROVIDERS.md`: choose **WordPress AI (Connectors)** vs **Google IA (direct Gemini)**, when data is sent, and privacy for WordPress.org.
- `readme.txt`: FAQ and External services clarify both Gemini paths and credential storage.
- Suggested privacy policy text under **Settings → Privacy** explains the two Gemini options.

## 1.0.3

### Changed

- Cloud AI requests use the WordPress 7.0 AI Client (`wp_ai_client_prompt`) and site-wide **Settings → Connectors** credentials.
- Removed direct HTTP integrations with Gemini, DeepSeek, and OpenAI-compatible APIs from the plugin package.
- Admin **AI Model** tab: **WordPress AI (Connectors)** or **Ollama** only; no per-plugin API key fields.
- Legacy provider values (`gemini`, `deepseek`, `openai_compatible`) migrate automatically to `wordpress_ai`.

### Breaking (upgrade notes)

- Configure cloud providers under **Settings → Connectors** (WordPress 7.0+).
- API keys previously stored in `multch_plugin_settings` are cleared on migration and are not used.

## 1.0.2

### Changed

- Unique plugin prefix `multch` / `MULTCH_` for PHP classes, hooks, options, transients, REST routes (`multch/v1`), shortcode (`[multch_widget]`), and script handles (WordPress.org naming guidelines).
- Automatic migration from legacy `chatbot_*` options, database tables, and cron events on upgrade.
- `CHATBOT_*` constants in `wp-config.php` remain supported as fallbacks for `MULTCH_*`.

### Breaking (upgrade notes)

- Shortcode is now `[multch_widget]` (replace `[chatbot_widget]` in content).
- REST base path is `/wp-json/multch/v1/` (was `chatbot-plugin/v1`).
- Admin screen slug: `admin.php?page=multch-plugin`.

## 1.0.1

### Changed

- Version bump to 1.0.1 for WordPress.org release.
- `readme.txt`: document optional third-party AI services (Gemini, DeepSeek, OpenAI-compatible, Ollama) with what data is sent, when it is sent, and links to each provider's terms and privacy policies.

## 1.0.0

### Changed

- Widget CSS classes migrated from `cb-*` to `maicb-*` (MultiAI ChatBot) to reduce collisions with themes and other plugins.
- CSS custom properties renamed from `--cb-*` to `--maicb-*` on the public widget.
- Widget styles are scoped under `#multch-plugin-root` and `#multch-style-preview`.
- Widget roots expose `data-maicb-root`; critical controls use `data-maicb` hooks for JavaScript.
- Multiple widget instances receive unique root IDs (`multch-plugin-root-2`, etc.).

### Added

- [docs/NAMING.md](docs/NAMING.md) naming conventions.
- `scripts/check-namespace` audit script.
- WordPress filters: `multch_plugin_root_id`, `multch_widget_class_prefix`.

### Removed

- Deprecated `cb-*` widget class names (no backward-compatible aliases in 1.1.0). Custom CSS must use `maicb-*` selectors.

## 1.0.0

- Initial release.
