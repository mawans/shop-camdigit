/**
 * CamDigit .CM redirect — intercepts WHMCS cart searches for .cm domains.
 * Upload to: <whmcs_root>/cm-redirect.js
 */
(function () {
  "use strict";

  var REG = "https://shop-camdigit.cm/register-cm.php";

  function tryRedirect(val) {
    var bare = String(val || "")
      .trim()
      .toLowerCase()
      .replace(/\.cm$/i, "");
    if (!bare || !/^[a-z0-9][a-z0-9\-]{0,61}[a-z0-9]?$/.test(bare))
      return false;
    window.location.href = REG + "?sld=" + encodeURIComponent(bare);
    return true;
  }

  function attach() {
    // Cast a wide net for the search form and input across WHMCS themes
    var form = document.querySelector(
      '#frmDomainsSearch, form[action*="cart.php"], .domain-search-form',
    );
    var input = document.querySelector(
      'input[name="query"], #inputDomainName, .domain-search-input input, input[placeholder*="domain"]',
    );
    if (!form || !input) return;

    // capture: true fires before WHMCS's own listener so we can prevent AJAX check
    form.addEventListener(
      "submit",
      function (e) {
        if (tryRedirect(input.value)) {
          e.preventDefault();
          e.stopImmediatePropagation();
        }
      },
      true,
    );
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", attach);
  } else {
    attach();
  }
})();
