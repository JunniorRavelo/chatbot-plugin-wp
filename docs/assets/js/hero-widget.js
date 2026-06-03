(function () {
  "use strict";

  var ROBOT_SVG =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
    '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
    "</svg>";

  var LAUNCHER_INNER =
    '<span class="maicb-launcher-icon-wrap" aria-hidden="true">' +
    '<span class="maicb-launcher-pulse"></span>' +
    '<span class="maicb-launcher-icon">' +
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>' +
    '<path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/>' +
    "</svg></span></span>";

  function buildHeaderHtml() {
    return (
      '<header class="maicb-header">' +
      '<div class="maicb-header-brand">' +
      '<span class="maicb-header-avatar" aria-hidden="true">' +
      ROBOT_SVG +
      "</span>" +
      '<div class="maicb-header-info">' +
      '<h3 class="maicb-header-title">Agente IA</h3>' +
      '<p class="maicb-header-sub">' +
      '<span class="maicb-header-status" aria-hidden="true"></span>' +
      '<span class="maicb-header-sub-text">Sistema en línea</span>' +
      "</p></div></div>" +
      '<div class="maicb-header-actions">' +
      '<button type="button" class="maicb-icon-btn maicb-minimize" title="Minimizar" aria-label="Minimizar chat">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M5 12h14"/></svg></button>' +
      '<button type="button" class="maicb-icon-btn maicb-reset" title="Reiniciar" aria-label="Reiniciar conversación (vista previa)">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>' +
      '<button type="button" class="maicb-icon-btn maicb-close" title="Cerrar" aria-label="Cerrar chat">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>' +
      "</div></header>"
    );
  }

  function buildMessageRow(role, text, meta) {
    var avatar =
      role === "assistant"
        ? '<span class="maicb-msg-avatar" aria-hidden="true">' + ROBOT_SVG + "</span>"
        : "";
    var metaHtml = meta ? '<span class="maicb-msg-meta">' + meta + "</span>" : "";
    return (
      '<div class="maicb-msg-row maicb-msg-row-' +
      role +
      '">' +
      avatar +
      '<div class="maicb-msg maicb-msg-' +
      role +
      '">' +
      text +
      metaHtml +
      "</div></div>"
    );
  }

  function buildLauncherHtml() {
    return (
      '<button type="button" class="maicb-launcher maicb-launcher-bottom-right" ' +
      'aria-label="Abrir chat de Agente IA" title="Abrir chat" hidden>' +
      LAUNCHER_INNER +
      '<span class="maicb-launcher-text">Agente IA</span>' +
      "</button>"
    );
  }

  function buildPanelHtml() {
    return (
      '<section class="maicb-panel maicb-position-bottom-center" aria-label="Agente IA">' +
      buildHeaderHtml() +
      '<div class="maicb-messages" role="log">' +
      buildMessageRow(
        "assistant",
        "Hola. Soy un agente de IA. Puedo cometer errores; verifique la información importante antes de tomar decisiones. ¿En qué puedo ayudarle?",
        "Mensaje de bienvenida"
      ) +
      buildMessageRow("user", "¿Cuál es su horario de atención?", "") +
      buildMessageRow(
        "assistant",
        "Estamos abiertos de lunes a viernes, de 9:00 a 18:00.",
        "gemini-3.5-flash (la API usó este; respaldo configurado: gemini-3.1-flash-lite)"
      ) +
      "</div>" +
      '<form class="maicb-composer">' +
      '<div class="maicb-composer-inner">' +
      '<textarea class="maicb-input" rows="1" placeholder="Escribe tu mensaje…" readonly tabindex="-1" aria-label="Escribe tu mensaje…"></textarea>' +
      '<button type="button" class="maicb-send" tabindex="-1" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></button>' +
      "</div></form></section>"
    );
  }

  function wirePreview(wrap) {
    var launcher = wrap.querySelector(".maicb-launcher");
    var panel = wrap.querySelector(".maicb-panel");
    if (!launcher || !panel) return;

    var isOpen = true;

    function setOpen(open) {
      isOpen = open;
      panel.hidden = !open;
      launcher.hidden = open;
      wrap.setAttribute("data-panel-open", open ? "true" : "false");
      wrap.classList.toggle("maicb-hero-preview-open", open);
      wrap.setAttribute(
        "aria-label",
        open
          ? "Vista previa del chat MultiAI ChatBot abierto"
          : "Vista previa del chat MultiAI ChatBot cerrado"
      );
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

    panel.querySelector(".maicb-reset").addEventListener("click", function () {
      /* Vista previa: sin historial real */
    });

    panel.querySelector(".maicb-composer").addEventListener("submit", function (e) {
      e.preventDefault();
    });

    setOpen(true);
  }

  function mountPreview(host) {
    host.innerHTML =
      '<div class="hero-site-mock" aria-hidden="true">' +
      '<div class="hero-site-mock__chrome">' +
      '<span class="hero-site-mock__dot"></span>' +
      '<span class="hero-site-mock__dot"></span>' +
      '<span class="hero-site-mock__dot"></span>' +
      "</div>" +
      '<div class="hero-site-mock__hero"></div>' +
      '<div class="hero-site-mock__line hero-site-mock__line--wide"></div>' +
      '<div class="hero-site-mock__line"></div>' +
      '<div class="hero-site-mock__line"></div>' +
      '<div class="hero-site-mock__line hero-site-mock__line--short"></div>' +
      "</div>" +
      '<div class="maicb-widget maicb-wrap maicb-preset-default maicb-hero-preview" id="multch-style-preview" data-panel-open="true" role="region" aria-label="Vista previa del chat MultiAI ChatBot abierto">' +
      buildLauncherHtml() +
      buildPanelHtml() +
      "</div>";

    wirePreview(host.querySelector("#multch-style-preview"));
  }

  function boot() {
    var host = document.getElementById("hero-widget-host");
    if (host) mountPreview(host);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
