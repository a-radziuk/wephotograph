(function () {
  var form = document.getElementById("contact-form");
  if (!form) return;

  var statusEl = document.getElementById("contact-form-status");
  var submitBtn = form.querySelector('button[type="submit"]');
  var defaultBtnText = submitBtn ? submitBtn.textContent : "";

  var flashMessages = {
    success: "Message sent. We will get back to you soon.",
    error: {
      validation: "Please fill in all required fields.",
      invalid_submission: "Something went wrong. Please try again.",
      config: "Contact form is temporarily unavailable.",
      notificator: "Your message could not be sent. Please try again later.",
      method: "Invalid request.",
      default: "Could not send your message. Please try again.",
    },
  };

  function hideStatus() {
    if (!statusEl) return;
    statusEl.classList.remove("is-visible", "form-status--success", "form-status--error");
    statusEl.textContent = "";
  }

  function showStatus(kind, message) {
    if (!statusEl) return;
    hideStatus();
    statusEl.textContent = message;
    statusEl.classList.add("is-visible", kind === "success" ? "form-status--success" : "form-status--error");
    statusEl.focus({ preventScroll: true });
  }

  function showFlash(status, reason) {
    var isSuccess = status === "success";
    var text = isSuccess
      ? flashMessages.success
      : flashMessages.error[reason] || flashMessages.error.default;
    showStatus(isSuccess ? "success" : "error", text);
  }

  form.addEventListener("submit", function () {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Sending…";
    }
  });

  fetch("flash.php")
    .then(function (res) {
      return res.json();
    })
    .then(function (flash) {
      if (flash && flash.status) {
        showFlash(flash.status, flash.reason || "");
      }
    })
    .catch(function () {});
})();
