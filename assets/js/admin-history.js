(function () {
  "use strict";

  const cfg = window.chatbotHistoryAdmin || {};
  const list = document.getElementById("chatbot-history-list");

  if (!list) {
    return;
  }

  function updateUrl(conversationId) {
    if (!window.history || !window.history.replaceState) {
      return;
    }

    const url = new URL(window.location.href);

    if (conversationId) {
      url.searchParams.set("conversation", String(conversationId));
    } else {
      url.searchParams.delete("conversation");
    }

    window.history.replaceState({}, "", url.toString());
  }

  function closeCard(card) {
    const toggle = card.querySelector(".chatbot-admin-history-card__toggle");
    const panel = card.querySelector(".chatbot-admin-history-card__panel");

    card.classList.remove("is-open");
    if (toggle) {
      toggle.setAttribute("aria-expanded", "false");
    }
    if (panel) {
      panel.hidden = true;
    }
  }

  function openCard(card, options) {
    const opts = options || {};
    const toggle = card.querySelector(".chatbot-admin-history-card__toggle");
    const panel = card.querySelector(".chatbot-admin-history-card__panel");
    const conversationId = card.getAttribute("data-conversation-id");

    if (!toggle || !panel || !conversationId) {
      return;
    }

    list.querySelectorAll(".chatbot-admin-history-card.is-open").forEach(function (other) {
      if (other !== card) {
        closeCard(other);
      }
    });

    card.classList.add("is-open");
    toggle.setAttribute("aria-expanded", "true");
    panel.hidden = false;

    if (!opts.skipUrl) {
      updateUrl(conversationId);
    }

    if (card.getAttribute("data-loaded") === "1") {
      if (opts.scroll) {
        card.scrollIntoView({ behavior: "smooth", block: "nearest" });
      }
      return;
    }

    panel.innerHTML =
      '<div class="chatbot-admin-history-card__loading">' +
      (cfg.i18n && cfg.i18n.loading ? cfg.i18n.loading : "Cargando…") +
      "</div>";

    const requestUrl = new URL(cfg.ajaxUrl || "/wp-admin/admin-ajax.php", window.location.origin);
    requestUrl.searchParams.set("action", "chatbot_history_detail");
    requestUrl.searchParams.set("nonce", cfg.nonce || "");
    requestUrl.searchParams.set("id", conversationId);

    fetch(requestUrl.toString(), {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
      },
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data || !payload.data.html) {
          throw new Error("invalid_response");
        }

        panel.innerHTML = payload.data.html;
        card.setAttribute("data-loaded", "1");

        if (opts.scroll) {
          card.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
      })
      .catch(function () {
        panel.innerHTML =
          '<div class="chatbot-admin-history-card__error">' +
          (cfg.i18n && cfg.i18n.error ? cfg.i18n.error : "Error al cargar.") +
          "</div>";
      });
  }

  list.addEventListener("click", function (event) {
    const toggle = event.target.closest(".chatbot-admin-history-card__toggle");
    if (!toggle || !list.contains(toggle)) {
      return;
    }

    const card = toggle.closest(".chatbot-admin-history-card");
    if (!card) {
      return;
    }

    if (card.classList.contains("is-open")) {
      closeCard(card);
      updateUrl(null);
      return;
    }

    openCard(card, { scroll: true });
  });

  const initial = list.querySelector(".chatbot-admin-history-card.is-open");
  if (initial) {
    const conversationId = initial.getAttribute("data-conversation-id");
    if (conversationId && window.location.search.indexOf("conversation=" + conversationId) !== -1) {
      initial.scrollIntoView({ behavior: "auto", block: "start" });
    }
  }
})();
