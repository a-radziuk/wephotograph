(function () {
  var form = document.getElementById("contact-form");
  if (!form) return;

  var statusEl = document.getElementById("contact-form-status");
  var submitBtn = form.querySelector('button[type="submit"]');
  var defaultBtnText = submitBtn ? submitBtn.textContent : "";

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

  function stripQueryParams() {
    if (!window.history.replaceState) return;
    var url = new URL(window.location.href);
    if (!url.searchParams.has("sent") && !url.searchParams.has("error")) return;
    url.search = "";
    window.history.replaceState({}, "", url.pathname + url.hash);
  }

  function handleQueryRedirectMessages() {
    var params = new URLSearchParams(window.location.search);
    if (params.get("sent") === "1") {
      showStatus("success", "Message sent. We will get back to you soon.");
      stripQueryParams();
      return;
    }
    if (params.get("error") === "1") {
      showStatus("error", "Could not send your message. Please try again or email us directly.");
      stripQueryParams();
    }
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    hideStatus();

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Sending…";
    }

    var fd = new FormData(form);

    fetch(form.action, {
      method: "POST",
      body: fd,
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var data;
          try {
            data = text ? JSON.parse(text) : {};
          } catch (err) {
            data = { ok: false, message: "Invalid response from server." };
          }
          return { res: res, data: data };
        });
      })
      .then(function (_ref) {
        var res = _ref.res;
        var data = _ref.data;
        var ok = res.ok && data && data.ok === true;
        if (ok) {
          form.reset();
          showStatus("success", data.message || "Message sent. We will get back to you soon.");
        } else {
          var msg =
            (data && data.message) ||
            (res.status === 405 ? "This form must be submitted from the website." : null) ||
            (!res.ok ? "Something went wrong. Please try again." : "Could not send your message.");
          showStatus("error", msg);
        }
      })
      .catch(function () {
        showStatus("error", "Network error. Check your connection and try again.");
      })
      .then(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = defaultBtnText;
        }
      });
  });

  handleQueryRedirectMessages();
})();
