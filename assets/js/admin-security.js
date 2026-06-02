(function () {
  "use strict";

  var cfg = window.multchSecurityAdmin || {};
  var i18n = cfg.i18n || {};

  function t(key, fallback) {
    return i18n[key] || fallback;
  }

  function formatDuration(seconds) {
    var value = parseInt(String(seconds), 10);
    if (!value || value <= 0) {
      return t("cacheOff", "Disabled");
    }
    if (value < 3600) {
      return t("cacheMinutes", "%d min").replace("%d", String(Math.round(value / 60)));
    }
    if (value < 86400) {
      var hours = Math.floor(value / 3600);
      var minutes = Math.floor((value % 3600) / 60);
      if (minutes > 0) {
        return t("suspendHours", "%1$d h %2$d min")
          .replace("%1$d", String(hours))
          .replace("%2$d", String(minutes));
      }
      return t("cacheHours", "%d h").replace("%d", String(hours));
    }
    var days = Math.floor(value / 86400);
    if (days === 1) {
      return t("cacheDay", "1 day");
    }
    return t("cacheDays", "%d days").replace("%d", String(days));
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) {
      el.textContent = text;
    }
  }

  function updateKpis() {
    var cacheInput = document.getElementById("multch-cache-ttl");
    var ipMin = document.getElementById("multch-rate-limit-per-minute");
    var ipDay = document.getElementById("multch-rate-limit-per-day");
    var modelMin = document.getElementById("multch-rate-limit-model-per-minute");
    var modelDay = document.getElementById("multch-rate-limit-model-per-day");
    var soft = document.getElementById("multch-rate-limit-soft-threshold");
    var suspendAfter = document.getElementById("multch-ip-suspend-after");
    var suspendSeconds = document.getElementById("multch-ip-suspend-seconds");

    if (cacheInput) {
      var cacheVal = parseInt(cacheInput.value || "0", 10);
      var cacheLabel = formatDuration(cacheVal);
      setText("multch-kpi-cache", cacheLabel);
      setText("multch-security-summary-cache", cacheLabel);
      setText("multch-cache-ttl-hint", cacheLabel);

      document.querySelectorAll(".multch-admin-pills--cache .multch-admin-pills__btn").forEach(function (btn) {
        var seconds = btn.getAttribute("data-cache-seconds");
        btn.classList.toggle("is-active", String(cacheVal) === String(seconds));
      });
    }

    if (ipMin) setText("multch-kpi-ip-min", ipMin.value || "0");
    if (ipDay) setText("multch-kpi-ip-day", ipDay.value || "0");
    if (modelMin) setText("multch-kpi-model-min", modelMin.value || "0");
    if (modelDay) setText("multch-kpi-model-day", modelDay.value || "0");

    if (soft) {
      var pct = Math.round(parseFloat(soft.value || "0") * 100);
      setText("multch-kpi-soft-threshold", pct + "%");
    }

    if (suspendAfter && suspendSeconds) {
      var after = parseInt(suspendAfter.value || "0", 10);
      var secs = parseInt(suspendSeconds.value || "0", 10);
      var suspendLabel = formatDuration(secs);
      setText("multch-ip-suspend-hint", suspendLabel);
      setText(
        "multch-security-summary-suspend",
        t("suspendSummary", "After %1$d violations · %2$s")
          .replace("%1$d", String(after))
          .replace("%2$s", suspendLabel)
      );
    }
  }

  function parseOrigins(value) {
    return String(value || "")
      .split(",")
      .map(function (part) {
        return part.trim();
      })
      .filter(Boolean);
  }

  function updateOriginChips() {
    var textarea = document.getElementById("multch-allowed-origins");
    var container = document.getElementById("multch-security-origin-chips");
    if (!textarea || !container) {
      return;
    }

    var origins = parseOrigins(textarea.value);
    container.innerHTML = "";

    if (!origins.length) {
      var chip = document.createElement("span");
      chip.className = "multch-admin-origin-chip multch-admin-origin-chip--default";
      chip.textContent = cfg.siteOrigin || "";
      container.appendChild(chip);

      var hint = document.createElement("p");
      hint.className = "description multch-admin-security-origins-preview__hint";
      hint.textContent = t(
        "originsDefaultHint",
        "Default: only this WordPress site can use the chat API."
      );
      container.appendChild(hint);
      return;
    }

    origins.forEach(function (origin) {
      var item = document.createElement("span");
      item.className = "multch-admin-origin-chip";
      item.textContent = origin;
      container.appendChild(item);
    });
  }

  function copyText(text, onSuccess) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
        window.alert(t("copyFailed", "Could not copy."));
      });
      return;
    }

    try {
      var temp = document.createElement("textarea");
      temp.value = text;
      temp.setAttribute("readonly", "");
      temp.style.position = "absolute";
      temp.style.left = "-9999px";
      document.body.appendChild(temp);
      temp.select();
      var ok = document.execCommand("copy");
      document.body.removeChild(temp);
      if (ok) {
        onSuccess();
      } else {
        window.alert(t("copyFailed", "Could not copy."));
      }
    } catch (e) {
      window.alert(t("copyFailed", "Could not copy."));
    }
  }

  function bindEvents() {
    [
      "multch-cache-ttl",
      "multch-rate-limit-per-minute",
      "multch-rate-limit-per-day",
      "multch-rate-limit-model-per-minute",
      "multch-rate-limit-model-per-day",
      "multch-rate-limit-soft-threshold",
      "multch-ip-suspend-after",
      "multch-ip-suspend-seconds",
    ].forEach(function (id) {
      var input = document.getElementById(id);
      if (!input) return;
      input.addEventListener("input", updateKpis);
      input.addEventListener("change", updateKpis);
    });

    var origins = document.getElementById("multch-allowed-origins");
    if (origins) {
      origins.addEventListener("input", updateOriginChips);
    }

    document.querySelectorAll(".multch-admin-pills--cache .multch-admin-pills__btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var cacheInput = document.getElementById("multch-cache-ttl");
        if (!cacheInput) return;
        cacheInput.value = btn.getAttribute("data-cache-seconds") || "0";
        cacheInput.dispatchEvent(new Event("input", { bubbles: true }));
        updateKpis();
      });
    });

    var copyBtn = document.getElementById("multch-copy-site-origin");
    if (copyBtn) {
      copyBtn.addEventListener("click", function () {
        var origin = copyBtn.getAttribute("data-origin") || cfg.siteOrigin || "";
        copyText(origin, function () {
          var original = copyBtn.textContent;
          copyBtn.textContent = t("copied", "Copied");
          window.setTimeout(function () {
            copyBtn.textContent = original;
          }, 2000);
        });
      });
    }
  }

  function boot() {
    updateKpis();
    updateOriginChips();
    bindEvents();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
