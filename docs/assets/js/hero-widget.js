(function () {
  "use strict";

  var COPY = {
    welcome:
      "Hola. Soy un agente de IA. Puedo cometer errores; verifique la información importante antes de tomar decisiones. ¿En qué puedo ayudarle?",
    welcomeMeta: "Mensaje de bienvenida",
    userQuestion: "¿Cuál es su horario de atención?",
    assistantReply: "Estamos abiertos de lunes a viernes, de 9:00 a 18:00.",
    assistantMeta:
      "gemini-3.5-flash (la API usó este; respaldo configurado: gemini-3.1-flash-lite)",
    placeholder: "Escribe tu mensaje…",
  };

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
      '<button type="button" class="maicb-icon-btn maicb-reset" title="Reiniciar" aria-label="Reiniciar conversación">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>' +
      '<button type="button" class="maicb-icon-btn maicb-close" title="Cerrar" aria-label="Cerrar chat">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>' +
      "</div></header>"
    );
  }

  function createMessageRow(role, text, meta) {
    var row = document.createElement("div");
    row.className = "maicb-msg-row maicb-msg-row-" + role;

    if (role === "assistant") {
      var avatar = document.createElement("span");
      avatar.className = "maicb-msg-avatar";
      avatar.setAttribute("aria-hidden", "true");
      avatar.innerHTML = ROBOT_SVG;
      row.appendChild(avatar);
    }

    var bubble = document.createElement("div");
    bubble.className = "maicb-msg maicb-msg-" + role;
    bubble.textContent = text;

    if (meta) {
      var metaEl = document.createElement("span");
      metaEl.className = "maicb-msg-meta";
      metaEl.textContent = meta;
      bubble.appendChild(metaEl);
    }

    row.appendChild(bubble);
    return row;
  }

  function createThinkingRow() {
    var row = document.createElement("div");
    row.className = "maicb-msg-row maicb-msg-row-assistant maicb-hero-thinking-row";

    var avatar = document.createElement("span");
    avatar.className = "maicb-msg-avatar";
    avatar.setAttribute("aria-hidden", "true");
    avatar.innerHTML = ROBOT_SVG;
    row.appendChild(avatar);

    var bubble = document.createElement("div");
    bubble.className = "maicb-msg maicb-msg-assistant maicb-thinking";
    bubble.setAttribute("role", "status");
    bubble.setAttribute("aria-live", "polite");
    bubble.setAttribute("aria-label", "El agente está escribiendo");

    var dotsWrap = document.createElement("span");
    dotsWrap.className = "maicb-thinking-dots";
    dotsWrap.setAttribute("aria-hidden", "true");
    for (var i = 0; i < 3; i += 1) {
      var dot = document.createElement("span");
      dot.className = "maicb-thinking-dot";
      dotsWrap.appendChild(dot);
    }
    bubble.appendChild(dotsWrap);
    row.appendChild(bubble);
    return row;
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
      '<div class="maicb-messages" role="log" aria-live="polite"></div>' +
      '<form class="maicb-composer">' +
      '<div class="maicb-composer-inner">' +
      '<textarea class="maicb-input" rows="1" readonly placeholder="' +
      COPY.placeholder +
      '" aria-label="' +
      COPY.placeholder +
      '"></textarea>' +
      '<button type="button" class="maicb-send" tabindex="-1" aria-hidden="true" disabled>' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></button>' +
      "</div></form></section>"
    );
  }

  function createDemoRunner(panel, wrap) {
    var messagesEl = panel.querySelector(".maicb-messages");
    var input = panel.querySelector(".maicb-input");
    var composerInner = panel.querySelector(".maicb-composer-inner");
    var resetBtn = panel.querySelector(".maicb-reset");
    var generation = 0;
    var busy = false;

    function scrollMessages() {
      if (!messagesEl) return;
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function clearComposer() {
      if (!input) return;
      input.value = "";
      input.placeholder = COPY.placeholder;
      input.classList.remove("maicb-hero-input-typing");
      if (composerInner) {
        composerInner.classList.remove("maicb-hero-composer-active");
      }
    }

    function setBusy(on) {
      busy = on;
      wrap.classList.toggle("maicb-hero-preview--demo-busy", on);
      if (resetBtn) {
        resetBtn.disabled = on;
        resetBtn.setAttribute("aria-busy", on ? "true" : "false");
      }
    }

    function isCancelled(gen) {
      return gen !== generation;
    }

    function wait(ms, gen) {
      return new Promise(function (resolve) {
        if (isCancelled(gen)) {
          resolve(false);
          return;
        }
        window.setTimeout(function () {
          resolve(!isCancelled(gen));
        }, ms);
      });
    }

    function typeIntoInput(text, gen) {
      return new Promise(function (resolve) {
        if (!input || isCancelled(gen)) {
          resolve(false);
          return;
        }

        input.placeholder = "";
        input.classList.add("maicb-hero-input-typing");
        if (composerInner) {
          composerInner.classList.add("maicb-hero-composer-active");
        }
        var index = 0;

        function step() {
          if (isCancelled(gen)) {
            resolve(false);
            return;
          }
          if (index <= text.length) {
            input.value = text.slice(0, index);
            index += 1;
            scrollMessages();
            window.setTimeout(step, index === 1 ? 280 : 42);
            return;
          }
          input.classList.remove("maicb-hero-input-typing");
          if (composerInner) {
            composerInner.classList.remove("maicb-hero-composer-active");
          }
          resolve(true);
        }

        step();
      });
    }

    async function runSequence() {
      if (!messagesEl || !input) return;

      generation += 1;
      var gen = generation;
      setBusy(true);

      messagesEl.innerHTML = "";
      clearComposer();

      messagesEl.appendChild(
        createMessageRow("assistant", COPY.welcome, COPY.welcomeMeta)
      );
      scrollMessages();

      if (!(await wait(900, gen))) return;

      if (!(await typeIntoInput(COPY.userQuestion, gen))) return;
      if (!(await wait(450, gen))) return;

      clearComposer();
      messagesEl.appendChild(createMessageRow("user", COPY.userQuestion, ""));
      scrollMessages();

      if (!(await wait(500, gen))) return;

      var thinkingRow = createThinkingRow();
      messagesEl.appendChild(thinkingRow);
      scrollMessages();

      if (!(await wait(1100, gen))) return;

      if (thinkingRow.parentNode) {
        thinkingRow.parentNode.removeChild(thinkingRow);
      }

      messagesEl.appendChild(
        createMessageRow("assistant", COPY.assistantReply, COPY.assistantMeta)
      );
      scrollMessages();

      if (!isCancelled(gen)) {
        setBusy(false);
      }
    }

    function cancel() {
      generation += 1;
      setBusy(false);
      clearComposer();
    }

    function restart() {
      cancel();
      runSequence();
    }

    return { restart: restart, runSequence: runSequence, cancel: cancel };
  }

  function wirePreview(wrap) {
    var launcher = wrap.querySelector(".maicb-launcher");
    var panel = wrap.querySelector(".maicb-panel");
    if (!launcher || !panel) return;

    var demo = createDemoRunner(panel, wrap);
    var input = panel.querySelector(".maicb-input");

    if (input) {
      input.addEventListener("keydown", function (e) {
        e.preventDefault();
      });
      input.addEventListener("paste", function (e) {
        e.preventDefault();
      });
    }

    function setOpen(open) {
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
      if (open) {
        demo.runSequence();
      }
    }

    launcher.addEventListener("click", function () {
      setOpen(true);
    });

    panel.querySelector(".maicb-minimize").addEventListener("click", function () {
      demo.cancel();
      setOpen(false);
    });

    panel.querySelector(".maicb-close").addEventListener("click", function () {
      demo.cancel();
      setOpen(false);
    });

    panel.querySelector(".maicb-reset").addEventListener("click", function () {
      demo.restart();
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
