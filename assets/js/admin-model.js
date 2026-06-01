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

  function toggle() {
    var v = sel.value;

    document.querySelectorAll(".multch-field-api-key").forEach(function (el) {
      el.style.display = v === "ollama" ? "none" : "";
    });
    document.querySelectorAll(".multch-field-gemini").forEach(function (el) {
      el.style.display = v === "gemini" ? "" : "none";
    });
    document.querySelectorAll(".multch-field-deepseek").forEach(function (el) {
      el.style.display = v === "deepseek" ? "" : "none";
    });
    document.querySelectorAll(".multch-field-ollama").forEach(function (el) {
      el.style.display = v === "ollama" ? "" : "none";
    });
    document.querySelectorAll(".multch-field-openai").forEach(function (el) {
      el.style.display = v === "openai_compatible" ? "" : "none";
    });
    document.querySelectorAll(".multch-field-deepseek-url").forEach(function (el) {
      el.style.display = v === "deepseek" ? "" : "none";
    });

    if (modelDesc && descriptions[v]) {
      modelDesc.textContent = descriptions[v].model || "";
    }
    if (candidatesDesc && descriptions[v]) {
      candidatesDesc.textContent = descriptions[v].candidates || "";
    }
  }

  sel.addEventListener("change", toggle);
  toggle();
})();
