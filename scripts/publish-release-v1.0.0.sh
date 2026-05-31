#!/usr/bin/env bash
# Publish GitHub release v1.0.0 (requires: gh auth login, git push access).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPO="JunniorRavelo/multiai-chatbot-for-wordpress"
TAG="v1.0.0"
ZIP="${ROOT}/multiai-chatbot.zip"
NOTES="${ROOT}/.release-notes-v1.0.0.md"

cd "${ROOT}"

if ! command -v gh >/dev/null 2>&1; then
	echo "Install GitHub CLI: https://cli.github.com/"
	exit 1
fi

if [[ ! -f "${ZIP}" ]]; then
	./scripts/package-plugin.sh
fi

cat > "${NOTES}" <<'EOF'
## MultiAI ChatBot v1.0.0

First public release of the AI chat widget plugin for WordPress.

### Highlights

- **Multiple AI providers:** Google Gemini, DeepSeek, Ollama (local), and OpenAI-compatible APIs
- **Flexible widget:** floating button or embedded panel via `[chatbot_widget]` shortcode
- **8 visual themes:** Sapphire, Midnight, Monochrome, Aqua, Ember, Emerald, Amethyst, and Plum
- **Admin panel:** General, AI Model, Security, Style, Statistics, and History tabs
- **Streaming responses** (optional) for a more natural chat experience
- **Conversation history** with public IDs (`CB-YYYY-MM-DD-HH-MM-SS`)
- **Telemetry and CSV export** for latency, errors, and model usage
- **Security:** IP rate limiting, server-side API keys, and `wp-config.php` constant support
- **Translations:** Spanish (Spain and Colombia)

### Requirements

- WordPress 6.2+
- PHP 8.0+
- API key (not required for Ollama)

### Installation

1. Download `multiai-chatbot.zip` from this release
2. Go to **Plugins → Add New → Upload Plugin**
3. Activate the plugin and configure your AI provider under **MultiAI ChatBot**

### Shortcodes

```
[chatbot_widget]
[chatbot_widget mode="inline"]
```

### REST API

- `POST /wp-json/chatbot-plugin/v1/chat` — JSON response
- `POST /chatbot-plugin/v1/chat/stream` — streaming (text/plain)

See the [README](https://github.com/JunniorRavelo/multiai-chatbot-for-wordpress#readme) for full documentation.
EOF

git push origin main
git push origin "${TAG}" 2>/dev/null || git tag -a "${TAG}" -m "v1.0.0 — First public release" && git push origin "${TAG}"

gh release create "${TAG}" \
	--repo "${REPO}" \
	--title "v1.0.0 — First public release" \
	--notes-file "${NOTES}" \
	"${ZIP}"

echo "Release published: https://github.com/${REPO}/releases/tag/${TAG}"
rm -f "${NOTES}"
