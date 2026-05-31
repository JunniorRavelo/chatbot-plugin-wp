#!/usr/bin/env bash
# Publish GitHub release v1.0.0 (requires: gh auth login OR GITHUB_TOKEN/GH_TOKEN).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPO="JunniorRavelo/multiai-chatbot-for-wordpress"
TAG="v1.0.0"
ZIP="${ROOT}/multiai-chatbot.zip"
NOTES="${ROOT}/.release-notes-v1.0.0.md"
ASSET_NAME="multiai-chatbot.zip"

cd "${ROOT}"

# Prefer user-local gh if the shell PATH does not include it yet.
if ! command -v gh >/dev/null 2>&1 && [[ -x "${HOME}/.local/bin/gh" ]]; then
	export PATH="${HOME}/.local/bin:${PATH}"
fi

write_release_notes() {
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
}

ensure_zip() {
	echo "Building ${ASSET_NAME}..."
	./scripts/package-plugin.sh
	if [[ ! -f "${ZIP}" ]]; then
		echo "Error: ${ZIP} was not created." >&2
		exit 1
	fi
}

ensure_git_tag() {
	if git rev-parse "${TAG}" >/dev/null 2>&1; then
		echo "Tag ${TAG} already exists locally."
	else
		echo "Creating tag ${TAG}..."
		git tag -a "${TAG}" -m "v1.0.0 — First public release"
	fi

	echo "Pushing branch and tag to origin..."
	git push origin main
	git push origin "${TAG}"
}

publish_with_gh() {
	write_release_notes
	ensure_zip
	ensure_git_tag

	if gh release view "${TAG}" --repo "${REPO}" >/dev/null 2>&1; then
		echo "Release ${TAG} exists; uploading asset..."
		gh release upload "${TAG}" "${ZIP}" --repo "${REPO}" --clobber
	else
		gh release create "${TAG}" \
			--repo "${REPO}" \
			--title "v1.0.0 — First public release" \
			--notes-file "${NOTES}" \
			"${ZIP}"
	fi

	rm -f "${NOTES}"
	echo "Release published: https://github.com/${REPO}/releases/tag/${TAG}"
}

api_token() {
	if [[ -n "${GH_TOKEN:-}" ]]; then
		echo "${GH_TOKEN}"
	elif [[ -n "${GITHUB_TOKEN:-}" ]]; then
		echo "${GITHUB_TOKEN}"
	fi
}

api_request() {
	local method="$1"
	local url="$2"
	shift 2
	curl -sS -L -X "${method}" \
		-H "Accept: application/vnd.github+json" \
		-H "Authorization: Bearer $(api_token)" \
		-H "X-GitHub-Api-Version: 2022-11-28" \
		"$@" \
		"${url}"
}

publish_with_api() {
	local token release_id upload_url asset_id

	token="$(api_token)"
	if [[ -z "${token}" ]]; then
		return 1
	fi

	write_release_notes
	ensure_zip
	ensure_git_tag

	echo "Creating or locating GitHub release via API..."
	release_id="$(api_request GET "https://api.github.com/repos/${REPO}/releases/tags/${TAG}" | python3 -c "
import json, sys
data = json.load(sys.stdin)
if 'id' in data:
    print(data['id'])
")"

	if [[ -z "${release_id}" ]]; then
		release_id="$(api_request POST "https://api.github.com/repos/${REPO}/releases" \
			-d "$(python3 - <<PY
import json, pathlib
notes = pathlib.Path('${NOTES}').read_text(encoding='utf-8')
print(json.dumps({
    'tag_name': '${TAG}',
    'name': 'v1.0.0 — First public release',
    'body': notes,
    'draft': False,
    'make_latest': True,
}))
PY
)" | python3 -c "
import json, sys
data = json.load(sys.stdin)
if 'id' not in data:
    print(data.get('message', data), file=sys.stderr)
    sys.exit(1)
print(data['id'])
")"
	fi

	upload_url="$(api_request GET "https://api.github.com/repos/${REPO}/releases/${release_id}" | python3 -c "
import json, sys
data = json.load(sys.stdin)
print(data.get('upload_url', '').split('{', 1)[0])
")"

	if [[ -z "${upload_url}" ]]; then
		echo "Error: could not get upload URL for release ${release_id}." >&2
		exit 1
	fi

	# Remove previous asset with the same name (re-upload).
	asset_id="$(api_request GET "https://api.github.com/repos/${REPO}/releases/${release_id}/assets" | python3 -c "
import json, sys
name = '${ASSET_NAME}'
for asset in json.load(sys.stdin):
    if asset.get('name') == name:
        print(asset['id'])
        break
")"

	if [[ -n "${asset_id}" ]]; then
		echo "Removing previous ${ASSET_NAME} asset..."
		api_request DELETE "https://api.github.com/repos/${REPO}/releases/assets/${asset_id}" >/dev/null
	fi

	echo "Uploading ${ASSET_NAME}..."
	api_request POST "${upload_url}?name=${ASSET_NAME}" \
		-H "Content-Type: application/zip" \
		--data-binary @"${ZIP}" \
		| python3 -c "
import json, sys
data = json.load(sys.stdin)
if 'browser_download_url' not in data:
    print(data.get('message', data), file=sys.stderr)
    sys.exit(1)
print('Asset URL:', data['browser_download_url'])
"

	rm -f "${NOTES}"
	echo "Release published: https://github.com/${REPO}/releases/tag/${TAG}"
}

main() {
	if command -v gh >/dev/null 2>&1; then
		if ! gh auth status >/dev/null 2>&1; then
			echo "GitHub CLI is installed but not authenticated. Run: gh auth login" >&2
			exit 1
		fi
		publish_with_gh
		return
	fi

	if [[ -n "$(api_token)" ]]; then
		publish_with_api
		return
	fi

	cat >&2 <<'EOF'
Cannot publish release: GitHub CLI (gh) is not installed and no API token was found.

Option A — install and use GitHub CLI:
  sudo apt install gh          # Debian/Ubuntu
  gh auth login
  ./scripts/publish-release-v1.0.0.sh

Option B — use a personal access token (repo scope):
  export GITHUB_TOKEN="ghp_..."
  ./scripts/publish-release-v1.0.0.sh

The ZIP is built with ./scripts/package-plugin.sh and attached to release v1.0.0.
EOF
	exit 1
}

main "$@"
