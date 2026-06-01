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

    document.querySelectorAll(".multch-field-wordpress-ai").forEach(function (el) {
      el.style.display = v === "wordpress_ai" ? "" : "none";
    });
    document.querySelectorAll(".multch-field-ollama").forEach(function (el) {
      el.style.display = v === "ollama" ? "" : "none";
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
