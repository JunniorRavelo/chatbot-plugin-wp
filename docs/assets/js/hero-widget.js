(function () {
  "use strict";

  var BOT_AVATAR_SVG =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
    '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
    "</svg>";

  var DEMO_MESSAGES = [
    {
      role: "assistant",
      text: "¡Hola! Soy el asistente de tu sitio. ¿En qué puedo ayudarte hoy?",
      meta: "Mensaje de bienvenida",
    },
    {
      role: "user",
      text: "¿Qué plugins de IA recomiendas para WordPress?",
    },
    {
      role: "assistant",
      text:
        "**MultiAI ChatBot** integra WordPress Connectors, Google Gemini y Ollama con un panel de administración completo, temas visuales y telemetría opcional.",
      meta: "gemini-2.5-flash",
    },
  ];

  var DEMO_REPLIES = [
    "Puedes instalar el plugin desde el directorio oficial de WordPress.org y configurar tu proveedor de IA en **MultiAI ChatBot → AI Model**.",
    "El widget admite modo flotante o embebido con el shortcode `[multch_widget]`.",
    "Las claves API nunca se envían al navegador: todas las peticiones pasan por el backend de WordPress.",
  ];

  function injectThinkingStyles() {
    if (document.getElementById("maicb-thinking-keyframes")) return;
    var style = document.createElement("style");
    style.id = "maicb-thinking-keyframes";
    style.textContent =
      "@keyframes maicb-typing-pulse{0%,70%,100%{opacity:.28;transform:scale(.82)}35%{opacity:1;transform:scale(1)}}" +
      ".maicb-widget .maicb-thinking-dots{display:inline-flex;align-items:center;gap:.3rem;height:1.1rem}" +
      ".maicb-widget .maicb-thinking-dot{display:block;width:.4rem;height:.4rem;border-radius:50%;background:currentColor;" +
      "animation:maicb-typing-pulse 1.35s ease-in-out infinite}" +
      ".maicb-widget .maicb-thinking-dot:nth-child(2){animation-delay:.2s}" +
      ".maicb-widget .maicb-thinking-dot:nth-child(3){animation-delay:.4s}";
    document.head.appendChild(style);
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatInline(text) {
    return escapeHtml(text).replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
  }

  function buildHeaderHtml() {
    return (
      '<div class="maicb-header-brand">' +
      '<span class="maicb-header-avatar" aria-hidden="true">' +
      BOT_AVATAR_SVG +
      "</span>" +
      '<div class="maicb-header-info">' +
      '<h3 class="maicb-header-title">MultiAI ChatBot</h3>' +
      '<p class="maicb-header-sub"><span class="maicb-header-status" aria-hidden="true"></span>' +
      '<span class="maicb-header-sub-text">En línea</span></p>' +
      "</div></div>" +
      '<div class="maicb-header-actions">' +
      '<button type="button" class="maicb-icon-btn maicb-minimize" title="Minimizar" aria-label="Minimizar" hidden>' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>' +
      "</button>" +
      '<button type="button" class="maicb-icon-btn maicb-reset" data-demo-reset title="Reiniciar chat" aria-label="Reiniciar chat">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>' +
      "</svg></button>" +
      '<button type="button" class="maicb-icon-btn maicb-close" title="Cerrar" aria-label="Cerrar" hidden>' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button></div>'
    );
  }

  function createMessage(role, text, meta, pending) {
    var row = document.createElement("div");
    row.className = "maicb-msg-row maicb-msg-row-" + role;

    if (role === "assistant") {
      var avatar = document.createElement("span");
      avatar.className = "maicb-msg-avatar";
      avatar.setAttribute("aria-hidden", "true");
      avatar.innerHTML = BOT_AVATAR_SVG;
      row.appendChild(avatar);
    }

    var bubble = document.createElement("div");
    bubble.className = "maicb-msg maicb-msg-" + role;

    if (pending) {
      bubble.classList.add("maicb-msg-pending");
      var thinking = document.createElement("div");
      thinking.className = "maicb-thinking";
      thinking.setAttribute("role", "status");
      var dotsWrap = document.createElement("span");
      dotsWrap.className = "maicb-thinking-dots";
      dotsWrap.setAttribute("aria-hidden", "true");
      for (var i = 0; i < 3; i += 1) {
        var dot = document.createElement("span");
        dot.className = "maicb-thinking-dot";
        dotsWrap.appendChild(dot);
      }
      thinking.appendChild(dotsWrap);
      bubble.appendChild(thinking);
    } else if (role === "assistant") {
      bubble.classList.add("maicb-msg-rich");
      var body = document.createElement("div");
      body.className = "maicb-msg-body";
      body.innerHTML = formatInline(text);
      bubble.appendChild(body);
      if (meta) {
        var metaEl = document.createElement("span");
        metaEl.className = "maicb-msg-meta";
        metaEl.textContent = meta;
        bubble.appendChild(metaEl);
      }
    } else {
      bubble.textContent = text;
    }

    row.appendChild(bubble);
    return row;
  }

  function mountWidget(host) {
    injectThinkingStyles();

    var wrap = document.createElement("div");
    wrap.className = "maicb-widget maicb-wrap maicb-inline-wrap maicb-preset-default";
    wrap.id = "multch-style-preview";

    var panel = document.createElement("section");
    panel.className = "maicb-panel maicb-position-bottom-right";
    panel.setAttribute("aria-label", "MultiAI ChatBot");

    var header = document.createElement("header");
    header.className = "maicb-header";
    header.innerHTML = buildHeaderHtml();

    var messagesEl = document.createElement("div");
    messagesEl.className = "maicb-messages";
    messagesEl.setAttribute("role", "log");
    messagesEl.setAttribute("aria-live", "polite");

    var composer = document.createElement("form");
    composer.className = "maicb-composer";
    composer.innerHTML =
      '<div class="maicb-composer-inner">' +
      '<textarea class="maicb-input" rows="1" placeholder="Escribe tu mensaje…" maxlength="700" aria-label="Escribe tu mensaje…"></textarea>' +
      '<button type="submit" class="maicb-send" aria-label="Enviar">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>' +
      "</svg></button></div>";

    panel.appendChild(header);
    panel.appendChild(messagesEl);
    panel.appendChild(composer);
    wrap.appendChild(panel);
    host.appendChild(wrap);

    var input = composer.querySelector(".maicb-input");
    var sendBtn = composer.querySelector(".maicb-send");
    var resetBtn = header.querySelector("[data-demo-reset]");
    var replyIndex = 0;
    var isSending = false;

    function scrollBottom() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function renderDemoMessages() {
      messagesEl.innerHTML = "";
      DEMO_MESSAGES.forEach(function (msg) {
        messagesEl.appendChild(createMessage(msg.role, msg.text, msg.meta, false));
      });
      scrollBottom();
    }

    function appendUserMessage(text) {
      messagesEl.appendChild(createMessage("user", text, "", false));
      scrollBottom();
    }

    function appendThinking() {
      var row = createMessage("assistant", "", "", true);
      messagesEl.appendChild(row);
      scrollBottom();
      return row;
    }

    function replaceThinking(row, text) {
      var bubble = row.querySelector(".maicb-msg");
      bubble.classList.remove("maicb-msg-pending");
      bubble.classList.add("maicb-msg-rich");
      bubble.innerHTML = "";
      var body = document.createElement("div");
      body.className = "maicb-msg-body";
      body.innerHTML = formatInline(text);
      bubble.appendChild(body);
      var meta = document.createElement("span");
      meta.className = "maicb-msg-meta";
      meta.textContent = "gemini-2.5-flash";
      bubble.appendChild(meta);
      scrollBottom();
    }

    function resizeInput() {
      input.style.height = "auto";
      input.style.height = Math.min(input.scrollHeight, 96) + "px";
    }

    function sendDemo(text) {
      if (isSending || !text.trim()) return;
      isSending = true;
      sendBtn.disabled = true;
      appendUserMessage(text.trim());
      var thinkingRow = appendThinking();
      var reply = DEMO_REPLIES[replyIndex % DEMO_REPLIES.length];
      replyIndex += 1;
      window.setTimeout(function () {
        replaceThinking(thinkingRow, reply);
        isSending = false;
        sendBtn.disabled = false;
        input.focus();
      }, 900);
    }

    composer.addEventListener("submit", function (e) {
      e.preventDefault();
      var value = input.value.trim();
      if (!value) return;
      input.value = "";
      resizeInput();
      sendDemo(value);
    });

    input.addEventListener("input", resizeInput);
    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        composer.requestSubmit();
      }
    });

    resetBtn.addEventListener("click", function () {
      replyIndex = 0;
      isSending = false;
      sendBtn.disabled = false;
      input.value = "";
      resizeInput();
      renderDemoMessages();
    });

    renderDemoMessages();
  }

  function boot() {
    var host = document.getElementById("hero-widget-host");
    if (host) mountWidget(host);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
