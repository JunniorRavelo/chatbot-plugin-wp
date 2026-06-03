(function () {
  "use strict";

  var cfg = window.multchModelAdmin || {};
  var descriptions = cfg.descriptions || {};
  var sel = document.getElementById("multch-provider");

  if (!sel) {
    return;
  }

  var modelDesc = document.getElementById("multch-model-desc");
  var candidatesDesc = document.getElementById("multch-model-candidates-desc");
  var modelDescGoogle = document.getElementById("multch-model-desc-google");
  var candidatesDescGoogle = document.getElementById("multch-model-candidates-desc-google");
  var wpModel = document.getElementById("multch-model");
  var wpFallbackModel = document.getElementById("multch-model-fallback");
  var googlePrimaryModel = document.getElementById("multch-model-google-primary");
  var googleFallbackModel = document.getElementById("multch-model-google-fallback");
  var googleApiKey = document.getElementById("multch-google-api-key");
  var ollamaModel = document.getElementById("multch-model-ollama");

  function setFieldEnabled(el, enabled) {
    if (!el) {
      return;
    }
    el.disabled = !enabled;
  }

  function toggle() {
    var v = sel.value;
    var isWp = v === "wordpress_ai";
    var isGoogle = v === "google_ia";
    var isOllama = v === "ollama";

    document.querySelectorAll(".multch-field-wordpress-ai").forEach(function (el) {
      el.style.display = isWp ? "" : "none";
    });
    document.querySelectorAll(".multch-field-google-ia").forEach(function (el) {
      el.style.display = isGoogle ? "" : "none";
    });
    document.querySelectorAll(".multch-field-ollama").forEach(function (el) {
      el.style.display = isOllama ? "" : "none";
    });

    setFieldEnabled(wpModel, isWp);
    setFieldEnabled(wpFallbackModel, isWp);
    setFieldEnabled(googlePrimaryModel, isGoogle);
    setFieldEnabled(googleFallbackModel, isGoogle);
    setFieldEnabled(googleApiKey, isGoogle);
    setFieldEnabled(ollamaModel, isOllama);

    var desc = descriptions[v] || {};
    if (modelDesc) {
      modelDesc.textContent = isWp ? desc.model || "" : "";
    }
    if (candidatesDesc) {
      candidatesDesc.textContent = isWp ? desc.candidates || "" : "";
    }
    if (modelDescGoogle) {
      modelDescGoogle.textContent = isGoogle ? desc.model || "" : "";
    }
    if (candidatesDescGoogle) {
      candidatesDescGoogle.textContent = isGoogle ? desc.candidates || "" : "";
    }
  }

  sel.addEventListener("change", toggle);
  toggle();
})();
