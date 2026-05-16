/**
 * CamDigit .CM Domain Order Flow — client-side JS
 * Loaded by cm-domain-order.tpl
 *
 * Requires: jQuery (loaded by WHMCS), cm-order-proxy.php in the WHMCS root.
 */
(function ($) {
  "use strict";

  // ─── Config ──────────────────────────────────────────────────────────────
  var PROXY_URL = "/cm-order-proxy.php"; // relative to WHMCS root

  // ─── State ───────────────────────────────────────────────────────────────
  var state = {
    csrfToken: "",
    currentStep: 1,
    sld: "",
    domain: "",
  };

  // ─── DOM refs ────────────────────────────────────────────────────────────
  var $steps = $("[data-step]");
  var $step1 = $("#cmStep1");
  var $step2 = $("#cmStep2");
  var $step3 = $("#cmStep3");
  var $alert = $("#cmAlert");
  var $searchForm = $("#cmSearchForm");
  var $detailsForm = $("#cmDetailsForm");

  // ─── Init ─────────────────────────────────────────────────────────────────
  $(function () {
    fetchCsrf();
    bindEvents();
  });

  // ─── CSRF fetch ──────────────────────────────────────────────────────────
  function fetchCsrf() {
    $.ajax({
      url: PROXY_URL,
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({ step: "get_csrf", csrf_token: "" }),
      success: function (res) {
        if (res && res.csrf_token) {
          state.csrfToken = res.csrf_token;
        }
      },
      error: function () {
        showAlert(
          "Could not initialise session. Please refresh the page.",
          "error",
        );
      },
    });
  }

  // ─── Bind events ─────────────────────────────────────────────────────────
  function bindEvents() {
    // Step 1 — domain search
    $searchForm.on("submit", function (e) {
      e.preventDefault();
      doSearch();
    });

    // Step 1 — proceed button (domain is available)
    $(document).on("click", "#cmProceedBtn", function () {
      goToStep(2);
    });

    // Step 2 — back button
    $("#cmBackBtn").on("click", function () {
      goToStep(1);
    });

    // Step 2 — change domain
    $("#cmChangeDomain").on("click", function () {
      goToStep(1);
    });

    // Step 2 — order submit
    $detailsForm.on("submit", function (e) {
      e.preventDefault();
      doSubmitOrder();
    });

    // Password toggle
    $("#cmTogglePw").on("click", function () {
      var $pw = $("#cmPassword");
      var type = $pw.attr("type") === "password" ? "text" : "password";
      $pw.attr("type", type);
      $(this).find("i").toggleClass("fa-eye fa-eye-slash");
    });

    // Password strength
    $("#cmPassword").on("input", function () {
      updatePwStrength($(this).val());
    });

    // Domain name: strip .cm if pasted with it
    $("#cmSld").on("input", function () {
      var val = $(this).val().replace(/\.cm$/i, "").replace(/\s/g, "");
      $(this).val(val);
    });
  }

  // ─── Step navigation ─────────────────────────────────────────────────────
  function goToStep(step) {
    hideAlert();
    state.currentStep = step;

    // Panels
    $step1.addClass("hidden");
    $step2.addClass("hidden");
    $step3.addClass("hidden");

    if (step === 1) $step1.removeClass("hidden");
    if (step === 2) {
      $step2.removeClass("hidden");
      $("#cmOrderDomainLabel").text(state.domain);
    }
    if (step === 3) $step3.removeClass("hidden");

    // Step indicator
    $steps.each(function () {
      var s = parseInt($(this).data("step"), 10);
      $(this).removeClass("active done");
      if (s === step) $(this).addClass("active");
      if (s < step) $(this).addClass("done");
    });

    // Scroll to top of page section
    $("html, body").animate(
      { scrollTop: $(".cm-order-page").offset().top - 100 },
      200,
    );
  }

  // ─── Step 1: domain availability check ───────────────────────────────────
  function doSearch() {
    var sld = $.trim($("#cmSld").val()).toLowerCase().replace(/\.cm$/i, "");

    if (!sld || !/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/.test(sld)) {
      showAlert(
        "Please enter a valid domain name (letters, numbers and hyphens only, no leading/trailing hyphens).",
        "error",
      );
      return;
    }

    hideAlert();
    setBtnLoading("#cmSearchBtn", true);

    $.ajax({
      url: PROXY_URL,
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        step: "check_domain",
        csrf_token: state.csrfToken,
        sld: sld,
      }),
      success: function (res) {
        setBtnLoading("#cmSearchBtn", false);
        if (res.success) {
          renderDomainResult(res);
        } else {
          showAlert(res.error || "Domain lookup failed.", "error");
          hideDomainResult();
        }
      },
      error: function (xhr) {
        setBtnLoading("#cmSearchBtn", false);
        var msg =
          (xhr.responseJSON && xhr.responseJSON.error) ||
          "Could not check domain. Please try again.";
        showAlert(msg, "error");
        hideDomainResult();
      },
    });
  }

  function renderDomainResult(res) {
    var $result = $("#cmDomainResult");
    var $badge = $("#cmDomainBadge");
    var $name = $("#cmDomainName");
    var $status = $("#cmDomainStatus");
    var $proceed = $("#cmProceedBtn");

    state.sld = res.domain.replace(/\.cm$/i, "");
    state.domain = res.domain;

    $name.text(res.domain);
    $result.removeClass("hidden available taken");

    if (res.available) {
      $result.addClass("available");
      $badge.html('<i class="fas fa-check"></i>');
      $status.text("Available — register now!");
      $proceed.removeClass("hidden");
    } else {
      $result.addClass("taken");
      $badge.html('<i class="fas fa-times"></i>');
      $status.text("Already registered — try a different name.");
      $proceed.addClass("hidden");
    }
  }

  function hideDomainResult() {
    $("#cmDomainResult").addClass("hidden").removeClass("available taken");
  }

  // ─── Step 2: order submission ─────────────────────────────────────────────
  function doSubmitOrder() {
    hideAlert();

    // Client-side validation
    var errors = validateDetailsForm();
    if (errors.length) {
      showAlert(errors.join("<br>"), "error");
      return;
    }

    setBtnLoading("#cmSubmitBtn", true);

    var payload = {
      step: "submit_order",
      csrf_token: state.csrfToken,
      sld: state.sld,
      firstname: $.trim($("#cmFirstname").val()),
      lastname: $.trim($("#cmLastname").val()),
      email: $.trim($("#cmEmail").val()),
      phone: $.trim($("#cmPhone").val()),
      address1: $.trim($("#cmAddress").val()),
      city: $.trim($("#cmCity").val()),
      postcode: $.trim($("#cmPostcode").val()),
      country: $("#cmCountry").val(),
      password: $("#cmPassword").val(),
    };

    $.ajax({
      url: PROXY_URL,
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify(payload),
      success: function (res) {
        setBtnLoading("#cmSubmitBtn", false);
        if (res.success) {
          renderSuccess(res);
          goToStep(3);
        } else {
          showAlert(res.error || "Order failed. Please try again.", "error");
        }
      },
      error: function (xhr) {
        setBtnLoading("#cmSubmitBtn", false);
        var msg =
          (xhr.responseJSON && xhr.responseJSON.error) ||
          "Order could not be submitted. Please contact support.";
        showAlert(msg, "error");
      },
    });
  }

  function validateDetailsForm() {
    var errors = [];

    if (!$.trim($("#cmFirstname").val()))
      errors.push("First name is required.");
    if (!$.trim($("#cmLastname").val())) errors.push("Last name is required.");

    var email = $.trim($("#cmEmail").val());
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.push("A valid email address is required.");
    }

    if (!$.trim($("#cmAddress").val())) errors.push("Address is required.");
    if (!$.trim($("#cmCity").val())) errors.push("City is required.");
    if (!$("#cmCountry").val()) errors.push("Country is required.");

    var pw = $("#cmPassword").val();
    var pw2 = $("#cmPassword2").val();
    if (!pw || pw.length < 8)
      errors.push("Password must be at least 8 characters.");
    if (pw !== pw2) errors.push("Passwords do not match.");

    if (!$("#cmTerms").is(":checked"))
      errors.push("You must accept the Terms & Conditions.");

    return errors;
  }

  function renderSuccess(res) {
    $("#cmSuccessMsg").text(res.message || "Your order has been placed!");
    var html = "";
    html +=
      "<p><strong>Domain:</strong> " +
      escHtml(res.domain || state.domain) +
      "</p>";
    if (res.order_id)
      html +=
        "<p><strong>Order #:</strong> " +
        escHtml(String(res.order_id)) +
        "</p>";
    if (res.invoice_id)
      html +=
        "<p><strong>Invoice #:</strong> " +
        escHtml(String(res.invoice_id)) +
        "</p>";
    html +=
      "<p>Please check your email for login details and payment instructions.</p>";
    $("#cmSuccessDetails").html(html);
  }

  // ─── Password strength ────────────────────────────────────────────────────
  function updatePwStrength(pw) {
    var score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    var $bar = $("#cmPwBar");
    var $label = $("#cmPwLabel");

    $bar.removeClass("weak fair strong");
    if (!pw) {
      $label.text("");
      return;
    }

    if (score <= 1) {
      $bar.addClass("weak");
      $label.text("Weak");
    } else if (score <= 2) {
      $bar.addClass("fair");
      $label.text("Fair");
    } else {
      $bar.addClass("strong");
      $label.text("Strong");
    }
  }

  // ─── UI helpers ──────────────────────────────────────────────────────────
  function showAlert(msg, type) {
    $alert
      .removeClass("hidden error success")
      .addClass(type || "error")
      .html(
        '<i class="fas fa-' +
          (type === "success" ? "check-circle" : "exclamation-circle") +
          '"></i> ' +
          msg,
      );
  }

  function hideAlert() {
    $alert.addClass("hidden").removeClass("error success");
  }

  function setBtnLoading($sel, loading) {
    var $btn = $($sel);
    var $txt = $btn.find(".cm-btn-text");
    var $spin = $btn.find(".cm-spinner");
    $btn.prop("disabled", loading);
    if (loading) {
      $txt.css("opacity", 0.5);
      $spin.removeClass("hidden");
    } else {
      $txt.css("opacity", 1);
      $spin.addClass("hidden");
    }
  }

  function escHtml(str) {
    return $("<span>").text(str).html();
  }
})(jQuery);
