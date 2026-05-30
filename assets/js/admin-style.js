(function () {
  "use strict";

  const cfg = window.chatbotStylePreview || {};
  const optionKey = cfg.optionKey || "chatbot_plugin_settings";
  const PRESETS = Array.isArray(cfg.presets) && cfg.presets.length
    ? cfg.presets
    : ["default", "dark-glass", "minimal", "ocean", "sunset", "forest", "lavender", "plum"];
  const POSITIONS = [
    "bottom-right",
    "center-right",
    "bottom-left",
    "center-left",
    "bottom-center",
  ];

  function previewI18n(key, fallback) {
    return cfg.i18n && cfg.i18n[key] ? cfg.i18n[key] : fallback;
  }

  function launcherMarkup(showLabel, labelText) {
    return (
      '<span class="maicb-launcher-icon-wrap" aria-hidden="true">' +
      '<span class="maicb-launcher-pulse"></span>' +
      '<span class="maicb-launcher-icon">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>' +
      '<path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/>' +
      "</svg></span></span>" +
      (showLabel ? '<span class="maicb-launcher-text">' + labelText + "</span>" : "")
    );
  }

  function buildComposerHtml() {
    const placeholder = previewI18n("placeholder", "Type your message…");
    const sendLabel = previewI18n("send", "Send");
    return (
      '<div class="maicb-composer-inner">' +
      '<textarea class="maicb-input" rows="1" placeholder="' +
      placeholder +
      '" maxlength="700" readonly aria-label="' +
      placeholder +
      '"></textarea>' +
      '<button type="submit" class="maicb-send" aria-label="' +
      sendLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>' +
      "</svg></button></div>"
    );
  }

  function buildHeaderHtml() {
    return (
      '<div class="maicb-header-brand">' +
      '<span class="maicb-header-avatar" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
      '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
      "</svg></span>" +
      '<div class="maicb-header-info">' +
      '<h3 class="maicb-header-title"></h3>' +
      '<p class="maicb-header-sub"><span class="maicb-header-status" aria-hidden="true"></span>' +
      '<span class="maicb-header-sub-text"></span></p>' +
      "</div></div>" +
      '<div class="maicb-header-actions">' +
      '<button type="button" class="maicb-icon-btn maicb-minimize" title="Minimize" aria-label="Minimize">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M5 12h14"/>' +
      "</svg></button>" +
      '<button type="button" class="maicb-icon-btn maicb-reset" title="Reset" aria-label="Reset">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>' +
      "</svg></button>" +
      '<button type="button" class="maicb-icon-btn maicb-close" title="Close" aria-label="Close">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>' +
      "</svg></button></div>"
    );
  }

  function field(name) {
    return document.querySelector('[name="' + optionKey + "[" + name + ']"]');
  }

  function checkboxField(name) {
    return document.querySelector(
      '[name="' + optionKey + "[" + name + ']"][type="checkbox"]'
    );
  }

  function readSettings() {
    const presetEl = field("style_preset");
    const positionEl = field("style_position");
    const primaryEl = field("style_primary");
    const accentEl = field("style_accent");
    const radiusEl = field("style_radius");
    const offsetEl = field("style_offset");
    const widthEl = field("style_panel_width");
    const launcherLabelEl = checkboxField("style_launcher_label");

    return {
      preset: presetEl ? presetEl.value : "default",
      position: positionEl ? positionEl.value : "bottom-right",
      primary: primaryEl ? primaryEl.value.trim() : "",
      accent: accentEl ? accentEl.value.trim() : "",
      radius: radiusEl ? radiusEl.value.trim() : "",
      offset: offsetEl ? offsetEl.value.trim() : "1rem",
      panelWidth: widthEl ? widthEl.value.trim() : "",
      launcherLabel: launcherLabelEl ? launcherLabelEl.checked : true,
      title: cfg.widgetTitle || "AI Agent",
      subtitle: cfg.widgetSubtitle || "System online",
      welcome: cfg.welcomeMessage || "Hello! How can I help you?",
    };
  }

  function launcherSide(position) {
    if (position.indexOf("left") !== -1) return "left";
    if (position === "bottom-center") return "center";
    return "right";
  }

  function applyStyleVars(wrap, settings) {
    wrap.style.removeProperty("--maicb-primary");
    wrap.style.removeProperty("--maicb-accent");
    wrap.style.removeProperty("--maicb-radius");
    wrap.style.removeProperty("--maicb-offset");
    wrap.style.removeProperty("--maicb-panel-width");

    if (settings.primary) wrap.style.setProperty("--maicb-primary", settings.primary);
    if (settings.accent) wrap.style.setProperty("--maicb-accent", settings.accent);
    if (settings.radius) wrap.style.setProperty("--maicb-radius", settings.radius);
    if (settings.offset) wrap.style.setProperty("--maicb-offset", settings.offset);
    if (settings.panelWidth) wrap.style.setProperty("--maicb-panel-width", settings.panelWidth);
  }

  function updatePositionButtons(position) {
    document.querySelectorAll(".chatbot-position-btn").forEach(function (btn) {
      btn.classList.toggle("is-active", btn.dataset.position === position);
    });
    const label = document.getElementById("chatbot-position-label");
    if (label && cfg.positionLabels && cfg.positionLabels[position]) {
      label.textContent = cfg.positionLabels[position];
    }
  }

  function buildPreviewDOM(viewport) {
    const widgetHost = viewport.querySelector(".maicb-preview-widget-host");
    const mount = widgetHost || viewport;
    if (widgetHost) {
      widgetHost.innerHTML = "";
    } else {
      viewport.innerHTML = "";
    }

    const wrap = document.createElement("div");
    wrap.className = "maicb-widget maicb-wrap maicb-preview-widget";
    wrap.id = "chatbot-style-preview";

    const settings = readSettings();
    wrap.classList.add("maicb-preset-" + settings.preset);

    const launcher = document.createElement("button");
    launcher.type = "button";
    launcher.className =
      "maicb-launcher maicb-launcher-" +
      launcherSide(settings.position) +
      (settings.launcherLabel ? "" : " maicb-launcher--icon-only");
    launcher.setAttribute("aria-label", "Open chat");
    launcher.innerHTML = launcherMarkup(settings.launcherLabel, settings.title);

    const panel = document.createElement("section");
    panel.className = "maicb-panel maicb-position-" + settings.position;
    panel.setAttribute("aria-label", settings.title || "MultiAI ChatBot");

    panel.innerHTML =
      '<header class="maicb-header">' +
      buildHeaderHtml() +
      "</header>" +
      '<div class="maicb-messages" role="log"></div>' +
      '<div class="maicb-error" hidden></div>' +
      '<form class="maicb-composer">' +
      buildComposerHtml() +
      "</form>";

    wrap.appendChild(launcher);
    wrap.appendChild(panel);
    mount.appendChild(wrap);

    let isOpen = false;

    function setOpen(open) {
      isOpen = open;
      panel.hidden = !open;
      launcher.hidden = open;
    }

    launcher.addEventListener("click", function () {
      setOpen(true);
    });
    panel.querySelector(".maicb-minimize").addEventListener("click", function () {
      setOpen(false);
    });
    panel.querySelector(".maicb-close").addEventListener("click", function () {
      setOpen(false);
    });
    panel.querySelector(".maicb-composer").addEventListener("submit", function (e) {
      e.preventDefault();
    });

    const toggleBtn = document.getElementById("chatbot-preview-toggle");
    if (toggleBtn) {
      toggleBtn.addEventListener("click", function () {
        setOpen(!isOpen);
        toggleBtn.setAttribute("aria-pressed", isOpen ? "true" : "false");
        toggleBtn.textContent = isOpen ? previewI18n("closePanel", "Close panel") : previewI18n("openPanel", "Open panel");
      });
    }

    panel.querySelector(".maicb-header-title").textContent = settings.title;
    panel.querySelector(".maicb-header-sub-text").textContent = settings.subtitle;
    applyStyleVars(wrap, settings);
    renderMessages(panel.querySelector(".maicb-messages"), settings);

    setOpen(true);
    if (toggleBtn) {
      toggleBtn.setAttribute("aria-pressed", "true");
      toggleBtn.textContent = previewI18n("closePanel", "Close panel");
    }

    return { wrap: wrap, launcher: launcher, panel: panel, setOpen: setOpen };
  }

  function createPreviewMessage(role, text) {
    const row = document.createElement("div");
    row.className = "maicb-msg-row maicb-msg-row-" + role;

    if (role === "assistant") {
      const avatar = document.createElement("span");
      avatar.className = "maicb-msg-avatar";
      avatar.setAttribute("aria-hidden", "true");
      avatar.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
        '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
        "</svg>";
      row.appendChild(avatar);
    }

    const bubble = document.createElement("div");
    bubble.className = "maicb-msg maicb-msg-" + role;
    bubble.textContent = text;
    row.appendChild(bubble);
    return row;
  }

  function renderMessages(messagesEl, settings) {
    messagesEl.innerHTML = "";
    messagesEl.appendChild(createPreviewMessage("assistant", settings.welcome));
    messagesEl.appendChild(createPreviewMessage("user", "What are your opening hours?"));
    messagesEl.appendChild(
      createPreviewMessage("assistant", "We are open Monday through Friday, 9:00 AM to 6:00 PM.")
    );
  }

  function applyPreview(settings, refs) {
    const { wrap, launcher, panel } = refs;
    const side = launcherSide(settings.position);

    PRESETS.forEach(function (p) {
      wrap.classList.remove("maicb-preset-" + p);
    });
    wrap.classList.add("maicb-preset-" + settings.preset);
    wrap.dataset.preset = settings.preset;

    POSITIONS.forEach(function (p) {
      panel.classList.remove("maicb-position-" + p);
    });
    panel.classList.add("maicb-position-" + settings.position);

    ["left", "right", "center"].forEach(function (s) {
      launcher.classList.remove("maicb-launcher-" + s);
    });
    launcher.classList.add("maicb-launcher-" + side);
    launcher.classList.toggle("maicb-launcher--icon-only", !settings.launcherLabel);
    launcher.innerHTML = launcherMarkup(settings.launcherLabel, settings.title);

    panel.querySelector(".maicb-header-title").textContent = settings.title;
    panel.querySelector(".maicb-header-sub-text").textContent = settings.subtitle;

    applyStyleVars(wrap, settings);
    renderMessages(panel.querySelector(".maicb-messages"), settings);

    updatePositionButtons(settings.position);
  }

  function syncPositionInput(position) {
    const el = field("style_position");
    if (el) el.value = position;
  }

  function bindEvents(refs) {
    const inputs = document.querySelectorAll(
      '[name^="' + optionKey + '[style_"]'
    );

    inputs.forEach(function (input) {
      if (
        input.type === "hidden" &&
        document.querySelector('[name="' + input.name + '"][type="checkbox"]')
      ) {
        return;
      }
      const evt = input.type === "checkbox" || input.tagName === "SELECT" ? "change" : "input";
      input.addEventListener(evt, function () {
        applyPreview(readSettings(), refs);
      });
    });

    document.querySelectorAll(".chatbot-position-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const position = btn.dataset.position;
        syncPositionInput(position);
        applyPreview(readSettings(), refs);
      });
    });
  }

  function initColorPickers(onChange) {
    if (typeof jQuery === "undefined" || !jQuery.fn.wpColorPicker) return;

    jQuery(".chatbot-color-picker").each(function () {
      const $input = jQuery(this);
      if ($input.hasClass("wp-color-picker")) return;

      $input.wpColorPicker({
        change: function () {
          setTimeout(onChange, 10);
        },
        clear: function () {
          setTimeout(onChange, 10);
        },
      });
    });
  }

  function boot() {
    const viewport = document.getElementById("chatbot-preview-viewport");
    if (!viewport) return;

    const refs = buildPreviewDOM(viewport);
    applyPreview(readSettings(), refs);
    bindEvents(refs);

    initColorPickers(function () {
      applyPreview(readSettings(), refs);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
